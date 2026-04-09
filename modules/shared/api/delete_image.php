<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../MffFtpSync.php';
require_once __DIR__ . '/../ProductImageService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$entityType = isset($_POST['entity_type']) ? trim($_POST['entity_type']) : '';
$imageId    = isset($_POST['image_id'])    ? (int)$_POST['image_id']    : 0;

$allowedTypes = array('category', 'product', 'manufacturer');
if (!in_array($entityType, $allowedTypes)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid entity_type'));
    exit;
}
if ($imageId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'image_id required'));
    exit;
}

if ($entityType === 'product') {
    $service = new ProductImageService();
    echo json_encode($service->delete($imageId));
    exit;
}

$imageBase = '/var/www/menufold/data/www/officetorg.com.ua/image/';

if ($entityType === 'category') {
    $r = Database::fetchRow('Papir',
        "SELECT image_id, image, category_id FROM category_images WHERE image_id = {$imageId}"
    );
    if (!$r['ok'] || empty($r['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Image not found'));
        exit;
    }
    $relImage  = (string)$r['row']['image'];
    $catId     = (int)$r['row']['category_id'];

    $delRes = Database::query('Papir',
        "DELETE FROM category_images WHERE image_id = {$imageId}"
    );
    if (!$delRes['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'DB delete failed'));
        exit;
    }

    // Delete file from disk
    if ($relImage !== '') {
        $filePath = $imageBase . ltrim($relImage, '/');
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    // Update main image: use next remaining image or empty string
    $nextRes  = Database::fetchRow('Papir',
        "SELECT image FROM category_images WHERE category_id = {$catId} ORDER BY sort_order, image_id LIMIT 1"
    );
    $newMain  = ($nextRes['ok'] && !empty($nextRes['row'])) ? (string)$nextRes['row']['image'] : '';
    Database::update('Papir', 'categoria',
        array('image' => $newMain),
        array('category_id' => $catId)
    );

    // Cascade to linked sites
    require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
    $sync = new SiteSyncService();
    $siteMappings = Database::fetchAll('Papir',
        "SELECT csm.site_id, csm.site_category_id FROM category_site_mapping csm
         JOIN sites s ON s.site_id = csm.site_id AND s.status = 1
         WHERE csm.category_id = {$catId}");
    if ($siteMappings['ok']) {
        foreach ($siteMappings['rows'] as $sm) {
            $sync->categoryUpdate((int)$sm['site_id'], (int)$sm['site_category_id'],
                array('image' => $newMain));
        }
    }
}

echo json_encode(array('ok' => true));
