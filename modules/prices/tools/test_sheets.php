<?php

$vendorAutoload  = '/var/www/menufold/data/www/officetorg.com.ua/Google/vendor/autoload.php';
$credentialsFile = __DIR__ . '/../google_credentials.json';
$spreadsheetId   = isset($argv[1]) ? $argv[1] : '1uVAmdh58QsOMa24XZE97i07m625pfQnS';

require_once $vendorAutoload;

$client = new Google\Client();
$client->setAuthConfig($credentialsFile);
$client->setScopes(array(
    Google\Service\Sheets::SPREADSHEETS_READONLY,
    Google\Service\Drive::DRIVE_READONLY,
));

echo "Service account: " . json_decode(file_get_contents($credentialsFile))->client_email . "\n";
echo "Spreadsheet ID:  $spreadsheetId\n\n";

// 1. Проверка через Drive API
echo "=== Drive API: file info ===\n";
try {
    $drive = new Google\Service\Drive($client);
    $file  = $drive->files->get($spreadsheetId, array('fields' => 'id,name,mimeType,owners'));
    echo "Name:     " . $file->getName() . "\n";
    echo "MimeType: " . $file->getMimeType() . "\n";
    echo "Expected: application/vnd.google-apps.spreadsheet\n";
} catch (Exception $e) {
    echo "Drive error: " . $e->getMessage() . "\n";
}

// 2. Проверка через Sheets API
echo "\n=== Sheets API: spreadsheets.get ===\n";
try {
    $sheets = new Google\Service\Sheets($client);
    $meta   = $sheets->spreadsheets->get($spreadsheetId);
    echo "Title: " . $meta->getProperties()->getTitle() . "\n";
    foreach ($meta->getSheets() as $s) {
        echo "  Sheet: " . $s->getProperties()->getTitle() . " (gid=" . $s->getProperties()->getSheetId() . ")\n";
    }
} catch (Exception $e) {
    echo "Sheets error: " . $e->getMessage() . "\n";
}
