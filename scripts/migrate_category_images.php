<?php
// Migration: create category_images table and migrate existing categoria.image values
require_once __DIR__ . '/../modules/database/database.php';

// Step 1: Create table
$createSql = "CREATE TABLE IF NOT EXISTS category_images (
  image_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  image VARCHAR(256) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_cat (category_id)
)";

$r = Database::query('Papir', $createSql);
if (!$r['ok']) {
    echo "ERROR creating table: " . print_r($r, true) . "\n";
    exit(1);
}
echo "Table category_images: OK\n";

// Step 2: Migrate existing categoria.image values
$catsRes = Database::fetchAll('Papir',
    "SELECT category_id, image FROM categoria WHERE image IS NOT NULL AND image <> ''"
);
if (!$catsRes['ok']) {
    echo "ERROR fetching categoria: " . print_r($catsRes, true) . "\n";
    exit(1);
}

$migrated = 0;
$skipped  = 0;
foreach ($catsRes['rows'] as $row) {
    $catId = (int)$row['category_id'];
    $image = (string)$row['image'];

    // Check if already in category_images for this category
    $existsRes = Database::fetchRow('Papir',
        "SELECT image_id FROM category_images WHERE category_id = {$catId} LIMIT 1"
    );
    if ($existsRes['ok'] && !empty($existsRes['row'])) {
        $skipped++;
        continue;
    }

    $safeImage = Database::escape('Papir', $image);
    $insRes = Database::query('Papir',
        "INSERT INTO category_images (category_id, image, sort_order) VALUES ({$catId}, '{$safeImage}', 1)"
    );
    if (!$insRes['ok']) {
        echo "ERROR inserting category_id={$catId}: " . print_r($insRes, true) . "\n";
    } else {
        $migrated++;
    }
}

echo "Migrated: {$migrated} rows\n";
echo "Skipped (already existed): {$skipped} rows\n";
echo "Done.\n";
