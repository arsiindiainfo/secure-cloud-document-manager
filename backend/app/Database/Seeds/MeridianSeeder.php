<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Seeds;

use App\Models\DocumentModel;
use App\Models\FolderModel;
use Aws\S3\S3Client;
use CodeIgniter\Database\Seeder;
use Config\Aws as AwsConfig;
use ZipArchive;

/**
 * §27/§32 — the fictional "Meridian Consulting Group" portfolio dataset:
 * three top-level folders (Client Contracts / HR Records / Compliance)
 * with synthetic PDF/DOCX/PNG documents actually uploaded to the S3
 * (LocalStack) bucket, so download/preview work exactly like a real upload
 * would — not just DB rows pointing at nothing.
 *
 * Deliberately separate from DemoSeeder (which ApiTestCase reseeds before
 * every single test and must stay tiny/fast) — run this one explicitly for
 * the portfolio demo: `php spark db:seed MeridianSeeder` (requires
 * DemoSeeder's users to already exist — run that first, or just
 * `php spark migrate --all && php spark db:seed DemoSeeder && php spark db:seed MeridianSeeder`).
 *
 * Runs through FolderModel/DocumentModel directly (their stored
 * procedures, same as the real API) rather than the Service layer, since a
 * CLI seeder has no HTTP request / AuthContext to authorize against — a
 * seeder is inherently a privileged setup script, not a user-facing flow.
 */
class MeridianSeeder extends Seeder
{
    private const ADMIN_ID    = 1;
    private const MANAGER_ID  = 2;
    private const EMPLOYEE_ID = 3;

    public function run(): void
    {
        $folders   = new FolderModel();
        $documents = new DocumentModel();
        $s3        = $this->s3Client();
        $bucket    = (new AwsConfig())->documentsBucket;

        $clientContracts = $folders->create('Client Contracts', null, self::ADMIN_ID);
        $novaTrail        = $folders->create('NovaTrail Logistics', $clientContracts, self::ADMIN_ID);
        $hrRecords        = $folders->create('HR Records', null, self::ADMIN_ID);
        $compliance       = $folders->create('Compliance', null, self::ADMIN_ID);

        // Folder-level grants (§6.3 inheritance) — direct DB inserts since
        // there is no folder-sharing API endpoint in this app's scope; a
        // seeder is exactly the kind of privileged script allowed to do this.
        $this->db->table('document_permissions')->insertBatch([
            ['folder_id' => $clientContracts, 'user_id' => self::MANAGER_ID, 'permission' => 'EDITOR', 'granted_by' => self::ADMIN_ID],
            ['folder_id' => $hrRecords, 'user_id' => self::EMPLOYEE_ID, 'permission' => 'VIEWER', 'granted_by' => self::ADMIN_ID],
        ]);

        $this->uploadDocument($documents, $s3, $bucket, $novaTrail, 'MSA-2026-NovaTrail.pdf',
            $this->buildMinimalPdf('Master Service Agreement', 'NovaTrail Logistics -- 2026 Master Service Agreement. Confidential.'));
        $this->uploadDocument($documents, $s3, $bucket, $novaTrail, 'SOW-Q1-NovaTrail.docx',
            $this->buildMinimalDocx('Statement of Work -- NovaTrail Logistics, Q1 2026.'));

        $this->uploadDocument($documents, $s3, $bucket, $hrRecords, 'Employee-Handbook-2026.pdf',
            $this->buildMinimalPdf('Employee Handbook', 'Meridian Consulting Group -- Employee Handbook, 2026 Edition.'));
        $this->uploadDocument($documents, $s3, $bucket, $hrRecords, 'Org-Chart.png',
            $this->buildPlaceholderPng());

        $this->uploadDocument($documents, $s3, $bucket, $compliance, 'SOC2-Report-2026.pdf',
            $this->buildMinimalPdf('SOC 2 Report', 'Meridian Consulting Group -- SOC 2 Type II Report, 2026.'));
        $this->uploadDocument($documents, $s3, $bucket, $compliance, 'Data-Retention-Policy.docx',
            $this->buildMinimalDocx('Data Retention Policy -- effective 2026.'));

        echo "Meridian Consulting Group demo data seeded: 4 folders, 6 documents.\n";
    }

    private function uploadDocument(DocumentModel $documents, S3Client $s3, string $bucket, int $folderId, string $fileName, string $bytes): void
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeType  = match ($extension) {
            'pdf'   => 'application/pdf',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'png'   => 'image/png',
            default => 'application/octet-stream',
        };

        $s3Key = "documents/{$folderId}/" . bin2hex(random_bytes(8)) . "/{$fileName}";
        $s3->putObject(['Bucket' => $bucket, 'Key' => $s3Key, 'Body' => $bytes, 'ContentType' => $mimeType]);

        $documents->uploadCommit($folderId, $fileName, null, null, $bucket, $s3Key, $mimeType, strlen($bytes), hash('sha256', $bytes), self::ADMIN_ID);
    }

    private function s3Client(): S3Client
    {
        $config = new AwsConfig();
        $args   = [
            'version'     => 'latest',
            'region'      => $config->region,
            'credentials' => ['key' => $config->key, 'secret' => $config->secret],
        ];
        if ($config->endpoint !== '') {
            $args['endpoint']               = $config->endpoint;
            $args['use_path_style_endpoint'] = true;
        }

        return new S3Client($args);
    }

    /** A genuinely valid single-page PDF (accurate xref table), not just plausible-looking bytes. */
    private function buildMinimalPdf(string $title, string $bodyText): string
    {
        $title    = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $title);
        $bodyText = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $bodyText);
        $stream   = "BT /F1 16 Tf 72 700 Td ({$title}) Tj 0 -30 Td /F1 11 Tf ({$bodyText}) Tj ET";

        $objects = [
            1 => '<</Type/Catalog/Pages 2 0 R>>',
            2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            3 => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
            4 => "<</Length " . strlen($stream) . ">>\nstream\n{$stream}\nendstream",
            5 => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count      = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<</Size {$count}/Root 1 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /** A minimal but genuinely valid OOXML .docx — opens in Word/LibreOffice, not just a same-sized blob. */
    private function buildMinimalDocx(string $bodyText): string
    {
        $escaped = htmlspecialchars($bodyText, ENT_XML1);

        $contentTypes = <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
              <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
              <Default Extension="xml" ContentType="application/xml"/>
              <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
            </Types>
            XML;

        $rootRels = <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
              <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
            </Relationships>
            XML;

        $document = <<<XML
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
              <w:body>
                <w:p><w:r><w:t>{$escaped}</w:t></w:r></w:p>
              </w:body>
            </w:document>
            XML;

        $tmpPath = tempnam(sys_get_temp_dir(), 'scdm_docx_');
        $zip     = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        $bytes = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $bytes;
    }

    /** A small real PNG (GD if available; a static valid 1x1 PNG otherwise) — good enough for a demo thumbnail. */
    private function buildPlaceholderPng(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(400, 300);
            $bg    = imagecolorallocate($image, 240, 244, 248);
            $fg    = imagecolorallocate($image, 51, 65, 85);
            imagefill($image, 0, 0, $bg);
            imagestring($image, 5, 20, 20, 'Meridian Org Chart', $fg);
            ob_start();
            imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);

            return $bytes;
        }

        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }
}
