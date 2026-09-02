<?php

/**
 * §25 — standalone worker for the sp_document_new_version concurrency test
 * (tests/api/DocumentVersionConcurrencyTest.php). Deliberately NOT bootstrapped
 * through CodeIgniter — this needs to be a genuinely separate OS process with
 * its own raw DB connection so two copies can race against the same row lock
 * at the same time, which a single-threaded PHPUnit process can't do on its own.
 *
 * argv: documentId uploadedBy
 * stdout: one JSON line {"versionId": int, "versionNo": int, "statusCode": string}
 */

[, $documentIdArg, $uploadedByArg] = $argv;
$documentId = (int) $documentIdArg;
$uploadedBy = (int) $uploadedByArg;

$mysqli = mysqli_init();
mysqli_real_connect($mysqli, '127.0.0.1', 'scdm', 'scdm', 'scdm_test', 3309);
if (mysqli_connect_errno()) {
    fwrite(STDERR, 'connect failed: ' . mysqli_connect_error());
    exit(1);
}

$s3Key = 'documents/concurrency-test/' . bin2hex(random_bytes(8)) . '.pdf';

$stmt = mysqli_prepare($mysqli, 'CALL sp_document_new_version(?, ?, ?, ?, ?, ?, ?, @v_id, @v_no, @status, @msg)');
mysqli_stmt_bind_param($stmt, 'isssisi', $documentId, $bucket, $s3Key, $mimeType, $size, $checksum, $uploadedBy);
$bucket   = 'scdm-documents-local';
$mimeType = 'application/pdf';
$size     = 100;
$checksum = str_repeat('a', 64);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$result = mysqli_query($mysqli, 'SELECT @v_id AS versionId, @v_no AS versionNo, @status AS statusCode');
$row    = mysqli_fetch_assoc($result);

echo json_encode([
    'versionId'  => $row['versionId'] !== null ? (int) $row['versionId'] : null,
    'versionNo'  => $row['versionNo'] !== null ? (int) $row['versionNo'] : null,
    'statusCode' => $row['statusCode'],
]) . "\n";

mysqli_close($mysqli);
