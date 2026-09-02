<?php
// One-off manual verification script for the §9.3 async pipeline — NOT part
// of the test suite. Uploads a real PNG through the live host PHP server
// (php spark serve --host 0.0.0.0 --port 8083) and polls until the
// LocalStack-deployed processing-worker Lambda's callback flips the
// document's processingStatus to COMPLETED with a thumbnail.
require __DIR__ . '/vendor/autoload.php';

$client = new \GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:8083/api/v1/', 'http_errors' => false]);

function jsonBody($response) { return json_decode((string) $response->getBody(), true); }

$login = jsonBody($client->post('auth/login', ['json' => ['email' => 'admin@meridian.test', 'password' => 'Passw0rd!']]));
if (!isset($login['data']['accessToken'])) { fwrite(STDERR, "login failed: " . json_encode($login) . "\n"); exit(1); }
$token = $login['data']['accessToken'];
$auth = ['Authorization' => "Bearer {$token}"];

$folder = jsonBody($client->post('folders', ['headers' => $auth, 'json' => ['name' => 'lambda-e2e-' . bin2hex(random_bytes(4)), 'parentFolderId' => null]]));
$folderId = $folder['data']['id'];
echo "folder: {$folderId}\n";

// A real, valid 1x1 red PNG (so Jimp can actually decode it).
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

$initiated = jsonBody($client->post('documents/uploads/initiate', ['headers' => $auth, 'json' => [
    'folderId' => $folderId, 'fileName' => 'e2e.png', 'mimeType' => 'image/png', 'sizeBytes' => strlen($png),
]]));
$uploadUrl = $initiated['data']['uploadUrl'];
$s3Key = $initiated['data']['s3Key'];
echo "s3Key: {$s3Key}\n";

$put = (new \GuzzleHttp\Client())->put($uploadUrl, ['body' => $png, 'headers' => ['Content-Type' => 'image/png']]);
echo "PUT status: {$put->getStatusCode()}\n";

$complete = jsonBody($client->post('documents/uploads/complete', ['headers' => $auth, 'json' => [
    'folderId' => $folderId, 's3Key' => $s3Key, 'name' => 'e2e.png', 'checksumSha256' => hash('sha256', $png),
]]));
$documentId = $complete['data']['id'];
echo "documentId: {$documentId}\n";

echo "Waiting for the Lambda's callback (up to 60s)...\n";
$deadline = time() + 60;
$status = null;
while (time() < $deadline) {
    $detail = jsonBody($client->get("documents/{$documentId}", ['headers' => $auth]));
    $status = $detail['data']['processingStatus'] ?? null;
    echo "  processingStatus: " . ($status ?? 'null') . "\n";
    if ($status === 'COMPLETED' || $status === 'FAILED') break;
    sleep(3);
}

if ($status !== 'COMPLETED') {
    fwrite(STDERR, "FAILED: processing never completed (last status: " . ($status ?? 'null') . ")\n");
    exit(1);
}

$thumb = $client->get("documents/{$documentId}/thumbnail", ['headers' => $auth]);
echo "thumbnail endpoint status: {$thumb->getStatusCode()}\n";
echo "thumbnail body: " . (string) $thumb->getBody() . "\n";

echo $thumb->getStatusCode() === 200 ? "SUCCESS: end-to-end Lambda pipeline verified.\n" : "FAILED: thumbnail not available.\n";
exit($thumb->getStatusCode() === 200 ? 0 : 1);
