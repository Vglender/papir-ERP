<?php
/**
 * GET /counterparties/api/download_media?url=...&name=...
 * Proxies a remote file (AlphaSMS S3) and serves it with the correct filename.
 * Prevents SSRF by whitelisting the allowed S3 host.
 */
require_once __DIR__ . '/../counterparties_bootstrap.php';

$url  = isset($_GET['url'])  ? trim($_GET['url'])  : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';

if (!$url) {
    http_response_code(400); echo 'url required'; exit;
}

// Security: only proxy files from AlphaSMS S3
$parsed = parse_url($url);
$host   = isset($parsed['host']) ? strtolower($parsed['host']) : '';
$allowedHosts = array(
    'sms-pub.s3.eu-central-1.amazonaws.com',
    'sms-pub.s3.amazonaws.com',
);
if (!in_array($host, $allowedHosts)) {
    http_response_code(403); echo 'Forbidden'; exit;
}

// Sanitize filename
if ($name === '') {
    $name = basename($parsed['path']);
}
$name = preg_replace('/[^\w\s.\-\(\)\[\]а-яА-ЯіІїЇєЄґҐ]/u', '_', $name);
$name = trim($name, '. ');
if ($name === '') { $name = 'file'; }

// Fetch from S3
$ch = curl_init($url);
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HEADER         => true,
));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    http_response_code(502); echo 'Could not fetch file'; exit;
}

$body = substr($response, $headerSize);
$headers = substr($response, 0, $headerSize);

// Detect MIME from response Content-Type
$mime = 'application/octet-stream';
if (preg_match('/Content-Type:\s*([^\r\n;]+)/i', $headers, $m)) {
    $mime = trim($m[1]);
}

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$inline = in_array($ext, array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'));
$disposition = $inline ? 'inline' : 'attachment';

// Override generic MIME type based on extension
if ($mime === 'application/octet-stream' || $mime === 'binary/octet-stream') {
    $extMimes = array(
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt'  => 'text/plain; charset=utf-8',
        'mp3'  => 'audio/mpeg',
        'ogg'  => 'audio/ogg',
        'wav'  => 'audio/wav',
    );
    if (isset($extMimes[$ext])) {
        $mime = $extMimes[$ext];
    }
}

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . strlen($body));
header('Cache-Control: private, max-age=3600');
echo $body;
exit;
