/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

'use strict';

// §9.3 — fired by an S3 ObjectCreated event on the documents bucket
// (`documents/` prefix only — see the notification filter in
// infrastructure/localstack/init-aws.sh, which also keeps this Lambda from
// re-triggering itself when it writes a thumbnail back into the same
// bucket). Generates a thumbnail for image/PDF types, extracts basic
// metadata, runs the documented virus-scan stub, and reports the result to
// the API over the one HMAC-signed internal route.
//
// Deliberately pure-JS deps (jimp, pdf-lib) instead of sharp/canvas — this
// package is `npm install`ed once and the resulting node_modules works
// identically on the dev host and inside the Lambda's Linux container, with
// no native-binary cross-compilation step.

const crypto = require('crypto');
const http = require('http');
const https = require('https');
const { S3Client, GetObjectCommand, PutObjectCommand } = require('@aws-sdk/client-s3');
const Jimp = require('jimp');
const { PDFDocument } = require('pdf-lib');

const THUMBNAIL_PREFIX = 'thumbnails/';
const THUMBNAIL_MAX_SIZE = 300;
const IMAGE_MIME_TYPES = ['image/png', 'image/jpeg'];
const CALLBACK_RETRIES = 5;
const CALLBACK_RETRY_DELAY_MS = 1000;

function s3Client() {
  // LocalStack injects LOCALSTACK_HOSTNAME into every Lambda container it
  // runs, pointing back at itself on the same Docker network (see
  // LAMBDA_DOCKER_NETWORK in docker-compose.yml) — real AWS never sets this,
  // so falling through to the SDK's default (real S3) is correct there too.
  const endpoint = process.env.LOCALSTACK_HOSTNAME
    ? `http://${process.env.LOCALSTACK_HOSTNAME}:${process.env.EDGE_PORT || 4566}`
    : undefined;

  return new S3Client({
    region: process.env.AWS_REGION || 'us-east-1',
    ...(endpoint ? { endpoint, forcePathStyle: true } : {}),
  });
}

function mimeTypeFromKey(key) {
  const ext = key.split('.').pop().toLowerCase();
  return {
    pdf: 'application/pdf',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    png: 'image/png',
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    zip: 'application/zip',
  }[ext];
}

async function streamToBuffer(stream) {
  const chunks = [];
  for await (const chunk of stream) {
    chunks.push(chunk);
  }
  return Buffer.concat(chunks);
}

/** Real, working thumbnail: an actual resize of the uploaded image. */
async function thumbnailForImage(buffer) {
  const image = await Jimp.read(buffer);
  const metadata = { width: image.bitmap.width, height: image.bitmap.height };
  image.scaleToFit(THUMBNAIL_MAX_SIZE, THUMBNAIL_MAX_SIZE);
  const thumbnailBuffer = await image.getBufferAsync(Jimp.MIME_PNG);
  return { thumbnailBuffer, metadata };
}

/**
 * PDFs are not rasterized (that needs a native PDF renderer, which is
 * exactly the kind of heavy native dependency this worker avoids — see the
 * file header). Instead this generates a generic labeled placeholder icon
 * and extracts real metadata (page count) via pdf-lib — a documented
 * simplification, the same spirit as the plan's own virus-scan stub.
 */
async function thumbnailForPdf(buffer) {
  let pageCount = null;
  try {
    const pdf = await PDFDocument.load(buffer, { ignoreEncryption: true });
    pageCount = pdf.getPageCount();
  } catch {
    // Malformed/encrypted PDF — still produce a placeholder thumbnail below.
  }

  const label = pageCount !== null ? `PDF · ${pageCount}p` : 'PDF';
  const image = new Jimp(THUMBNAIL_MAX_SIZE, THUMBNAIL_MAX_SIZE, '#c0392b');
  const font = await Jimp.loadFont(Jimp.FONT_SANS_32_WHITE);
  image.print(font, 0, THUMBNAIL_MAX_SIZE / 2 - 16, { text: label, alignmentX: Jimp.HORIZONTAL_ALIGN_CENTER }, THUMBNAIL_MAX_SIZE);
  const thumbnailBuffer = await image.getBufferAsync(Jimp.MIME_PNG);

  return { thumbnailBuffer, metadata: pageCount !== null ? { pageCount } : {} };
}

async function signedPost(url, body) {
  const raw = JSON.stringify(body);
  const signature = crypto.createHmac('sha256', process.env.INTERNAL_HMAC_SECRET).update(raw).digest('hex');
  const { hostname, port, pathname, protocol } = new URL(url);

  return new Promise((resolve, reject) => {
    const req = (protocol === 'https:' ? https : http).request(
      {
        hostname,
        port,
        path: pathname,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(raw),
          'X-Signature': signature,
        },
      },
      (res) => {
        let responseBody = '';
        res.on('data', (chunk) => (responseBody += chunk));
        res.on('end', () => resolve({ statusCode: res.statusCode, body: responseBody }));
      },
    );
    req.on('error', reject);
    req.write(raw);
    req.end();
  });
}

/**
 * The S3 PUT (what triggers this Lambda) and the "complete" API call (what
 * creates the document_versions row this callback needs to find) are two
 * separate, client-orchestrated steps (§21.2) — the S3 event can genuinely
 * fire before "complete" has landed. A bounded retry absorbs that race
 * without the Lambda needing any DB access of its own.
 */
async function postCallbackWithRetry(callbackUrl, payload) {
  for (let attempt = 1; attempt <= CALLBACK_RETRIES; attempt++) {
    const response = await signedPost(callbackUrl, payload);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return;
    }

    const isRetryable = response.statusCode === 404 && response.body.includes('DOCUMENT_VERSION_NOT_FOUND');
    if (!isRetryable || attempt === CALLBACK_RETRIES) {
      throw new Error(`Callback failed (attempt ${attempt}/${CALLBACK_RETRIES}): ${response.statusCode} ${response.body}`);
    }

    console.log(`[processing-worker] version not committed yet, retrying (${attempt}/${CALLBACK_RETRIES})`);
    await new Promise((resolve) => setTimeout(resolve, CALLBACK_RETRY_DELAY_MS * attempt));
  }
}

async function processRecord(record, callbackUrl) {
  const bucket = record.s3.bucket.name;
  const key = decodeURIComponent(record.s3.object.key.replace(/\+/g, ' '));

  // Belt-and-suspenders alongside the notification filter (init-aws.sh) —
  // never process our own thumbnail output.
  if (key.startsWith(THUMBNAIL_PREFIX)) {
    return;
  }

  const mimeType = mimeTypeFromKey(key);
  const s3 = s3Client();

  try {
    const object = await s3.send(new GetObjectCommand({ Bucket: bucket, Key: key }));
    const buffer = await streamToBuffer(object.Body);

    let thumbnailBuffer = null;
    let metadata = {};

    if (IMAGE_MIME_TYPES.includes(mimeType)) {
      ({ thumbnailBuffer, metadata } = await thumbnailForImage(buffer));
    } else if (mimeType === 'application/pdf') {
      ({ thumbnailBuffer, metadata } = await thumbnailForPdf(buffer));
    }
    // DOCX/XLSX/ZIP: no thumbnail — the job still completes so the
    // frontend's "generating preview…" placeholder resolves to the
    // download-only fallback instead of hanging forever (§22.4).

    let thumbnailS3Key = null;
    if (thumbnailBuffer !== null) {
      thumbnailS3Key = `${THUMBNAIL_PREFIX}${crypto.createHash('sha1').update(key).digest('hex')}.png`;
      await s3.send(new PutObjectCommand({ Bucket: bucket, Key: thumbnailS3Key, Body: thumbnailBuffer, ContentType: 'image/png' }));
    }

    // Virus-scan stub (§2): logs a result, never integrates a real scanning
    // engine — documented the same way as the plan's own scan-stub bullet.
    console.log(`[processing-worker] scan-stub: ${key} => CLEAN`);

    await postCallbackWithRetry(callbackUrl, {
      s3Key: key,
      status: 'COMPLETED',
      scanResult: 'CLEAN',
      metadata,
      thumbnailS3Key,
    });
  } catch (error) {
    console.error(`[processing-worker] failed processing ${key}:`, error);
    await postCallbackWithRetry(callbackUrl, {
      s3Key: key,
      status: 'FAILED',
      errorMessage: String(error && error.message ? error.message : error).slice(0, 500),
    }).catch((callbackError) => console.error('[processing-worker] failed to report failure:', callbackError));
  }
}

exports.handler = async (event) => {
  const callbackUrl = process.env.API_CALLBACK_URL;
  if (!callbackUrl) {
    throw new Error('API_CALLBACK_URL is not set');
  }

  for (const record of event.Records || []) {
    await processRecord(record, callbackUrl);
  }
};

