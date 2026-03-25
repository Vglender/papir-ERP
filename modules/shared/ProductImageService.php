<?php

class ProductImageService
{
    private $imageBase = '/var/www/menufold/data/www/officetorg.com.ua/image/';
    private $imageUrl  = 'https://officetorg.com.ua/image/';

    // site_id => db alias
    private $siteDb = array(1 => 'off', 2 => 'mff');

    public function getImages($productId)
    {
        $productId = (int)$productId;

        $r = Database::fetchAll('Papir',
            "SELECT image_id, path, sort_order
             FROM product_image
             WHERE product_id = {$productId}
             ORDER BY sort_order ASC, image_id ASC"
        );
        if (!$r['ok'] || empty($r['rows'])) return array();

        $images   = array();
        $imageIds = array();
        foreach ($r['rows'] as $row) {
            $id = (int)$row['image_id'];
            $images[$id] = array(
                'image_id'   => $id,
                'path'       => (string)$row['path'],
                'url'        => $this->imageUrl . ltrim((string)$row['path'], '/'),
                'sort_order' => (int)$row['sort_order'],
                'sites'      => array(),
            );
            $imageIds[] = $id;
        }

        // Load site assignments
        $inList = implode(',', $imageIds);
        $rs = Database::fetchAll('Papir',
            "SELECT image_id, site_id FROM product_image_site WHERE image_id IN ({$inList})"
        );
        if ($rs['ok']) {
            foreach ($rs['rows'] as $row) {
                $id = (int)$row['image_id'];
                if (isset($images[$id])) {
                    $images[$id]['sites'][] = (int)$row['site_id'];
                }
            }
        }

        return array_values($images);
    }

    public function upload($productId, $tmpPath, $imageMime, $fileSize)
    {
        $productId = (int)$productId;

        $err = $this->validateImage($tmpPath, $imageMime, $fileSize);
        if ($err) return array('ok' => false, 'error' => $err);

        $hex2      = sprintf('%02x', $productId & 0xff);
        $subdir    = 'catalog/product/' . $hex2 . '/' . $hex2 . '/';
        $uploadDir = $this->imageBase . $subdir;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            return array('ok' => false, 'error' => 'Upload directory not accessible');
        }

        $img = $this->processImage($tmpPath, $imageMime);
        if (!$img) return array('ok' => false, 'error' => 'Failed to process image');

        $filename = 'product_' . $productId . '_' . uniqid() . '.jpg';
        $destPath = $uploadDir . $filename;
        $relPath  = $subdir . $filename;

        $saved = imagejpeg($img, $destPath, 85);
        imagedestroy($img);
        if (!$saved) return array('ok' => false, 'error' => 'Failed to save image');

        // Mirror to mff server
        $ftp = new MffFtpSync();
        $ftp->upload($relPath);
        $ftp->disconnect();

        // Next sort_order
        $sortRes  = Database::fetchRow('Papir',
            "SELECT COALESCE(MAX(sort_order), -1) + 1 AS next FROM product_image WHERE product_id = {$productId}"
        );
        $nextSort = ($sortRes['ok'] && !empty($sortRes['row'])) ? (int)$sortRes['row']['next'] : 0;

        // Insert product_image
        $safePath = Database::escape('Papir', $relPath);
        $insRes   = Database::query('Papir',
            "INSERT INTO product_image (product_id, path, sort_order) VALUES ({$productId}, '{$safePath}', {$nextSort})"
        );
        if (!$insRes['ok']) {
            @unlink($destPath);
            return array('ok' => false, 'error' => 'DB insert failed');
        }

        $idRes   = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS image_id");
        $imageId = ($idRes['ok'] && !empty($idRes['row'])) ? (int)$idRes['row']['image_id'] : 0;

        // Auto-assign to sites where product currently has images (or defaults)
        $activeSites   = $this->getActiveSites($productId);
        $assignedSites = array();
        foreach ($activeSites as $siteId) {
            $r = Database::query('Papir',
                "INSERT IGNORE INTO product_image_site (image_id, site_id, sort_order) VALUES ({$imageId}, {$siteId}, {$nextSort})"
            );
            if ($r['ok']) $assignedSites[] = $siteId;
        }

        foreach ($assignedSites as $siteId) {
            $this->syncToSite($productId, $siteId);
        }

        if ($nextSort === 0) {
            $this->updateMainImageCache($productId, $relPath);
        }

        return array(
            'ok'   => true,
            'data' => array(
                'image_id'   => $imageId,
                'path'       => $relPath,
                'url'        => $this->imageUrl . $relPath,
                'sort_order' => $nextSort,
                'sites'      => $assignedSites,
            ),
        );
    }

    public function delete($imageId)
    {
        $imageId = (int)$imageId;

        $r = Database::fetchRow('Papir',
            "SELECT product_id, path FROM product_image WHERE image_id = {$imageId}"
        );
        if (!$r['ok'] || empty($r['row'])) return array('ok' => false, 'error' => 'Image not found');

        $productId = (int)$r['row']['product_id'];
        $path      = (string)$r['row']['path'];

        $rs = Database::fetchAll('Papir',
            "SELECT site_id FROM product_image_site WHERE image_id = {$imageId}"
        );
        $siteIds = array();
        if ($rs['ok']) {
            foreach ($rs['rows'] as $row) $siteIds[] = (int)$row['site_id'];
        }

        Database::query('Papir', "DELETE FROM product_image_site WHERE image_id = {$imageId}");
        Database::query('Papir', "DELETE FROM product_image WHERE image_id = {$imageId}");

        if ($path !== '') {
            $fp = $this->imageBase . ltrim($path, '/');
            if (file_exists($fp)) @unlink($fp);

            // Remove from mff server
            $ftp = new MffFtpSync();
            $ftp->delete($path);
            $ftp->disconnect();
        }

        foreach ($siteIds as $siteId) {
            $this->syncToSite($productId, $siteId);
        }

        $this->refreshMainImageCache($productId);

        return array('ok' => true);
    }

    public function replace($imageId, $tmpPath, $imageMime, $fileSize)
    {
        $imageId = (int)$imageId;

        $err = $this->validateImage($tmpPath, $imageMime, $fileSize);
        if ($err) return array('ok' => false, 'error' => $err);

        $r = Database::fetchRow('Papir',
            "SELECT product_id, path FROM product_image WHERE image_id = {$imageId}"
        );
        if (!$r['ok'] || empty($r['row'])) return array('ok' => false, 'error' => 'Image not found');

        $productId = (int)$r['row']['product_id'];
        $oldPath   = (string)$r['row']['path'];

        $hex2      = sprintf('%02x', $productId & 0xff);
        $subdir    = 'catalog/product/' . $hex2 . '/' . $hex2 . '/';
        $uploadDir = $this->imageBase . $subdir;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            return array('ok' => false, 'error' => 'Upload directory not accessible');
        }

        $img = $this->processImage($tmpPath, $imageMime);
        if (!$img) return array('ok' => false, 'error' => 'Failed to process image');

        $filename = 'product_' . $productId . '_' . uniqid() . '.jpg';
        $destPath = $uploadDir . $filename;
        $relPath  = $subdir . $filename;

        $saved = imagejpeg($img, $destPath, 85);
        imagedestroy($img);
        if (!$saved) return array('ok' => false, 'error' => 'Failed to save image');

        $safePath = Database::escape('Papir', $relPath);
        $updRes   = Database::query('Papir',
            "UPDATE product_image SET path = '{$safePath}' WHERE image_id = {$imageId}"
        );
        if (!$updRes['ok']) {
            @unlink($destPath);
            return array('ok' => false, 'error' => 'DB update failed');
        }

        // Mirror new file to mff, remove old
        $ftp = new MffFtpSync();
        $ftp->upload($relPath);
        if ($oldPath !== '') $ftp->delete($oldPath);
        $ftp->disconnect();

        if ($oldPath !== '') {
            $fp = $this->imageBase . ltrim($oldPath, '/');
            if (file_exists($fp)) @unlink($fp);
        }

        $rs = Database::fetchAll('Papir',
            "SELECT site_id FROM product_image_site WHERE image_id = {$imageId}"
        );
        $siteIds = array();
        if ($rs['ok']) {
            foreach ($rs['rows'] as $row) $siteIds[] = (int)$row['site_id'];
        }

        foreach ($siteIds as $siteId) {
            $this->syncToSite($productId, $siteId);
        }

        $this->refreshMainImageCache($productId);

        return array(
            'ok'   => true,
            'data' => array(
                'image_id' => $imageId,
                'path'     => $relPath,
                'url'      => $this->imageUrl . $relPath,
            ),
        );
    }

    public function toggleSite($imageId, $siteId, $enabled)
    {
        $imageId = (int)$imageId;
        $siteId  = (int)$siteId;

        $r = Database::fetchRow('Papir',
            "SELECT product_id, sort_order FROM product_image WHERE image_id = {$imageId}"
        );
        if (!$r['ok'] || empty($r['row'])) return array('ok' => false, 'error' => 'Image not found');

        $productId = (int)$r['row']['product_id'];
        $sortOrder = (int)$r['row']['sort_order'];

        if ($enabled) {
            Database::query('Papir',
                "INSERT IGNORE INTO product_image_site (image_id, site_id, sort_order) VALUES ({$imageId}, {$siteId}, {$sortOrder})"
            );
        } else {
            Database::query('Papir',
                "DELETE FROM product_image_site WHERE image_id = {$imageId} AND site_id = {$siteId}"
            );
        }

        $this->syncToSite($productId, $siteId);

        return array('ok' => true);
    }

    public function syncToSite($productId, $siteId)
    {
        $productId = (int)$productId;
        $siteId    = (int)$siteId;

        if (!isset($this->siteDb[$siteId])) return;
        $db = $this->siteDb[$siteId];

        $siteProductId = $this->getSiteProductId($productId, $siteId);
        if (!$siteProductId) return;

        $r = Database::fetchAll('Papir',
            "SELECT pi.path, pis.sort_order
             FROM product_image pi
             JOIN product_image_site pis ON pis.image_id = pi.image_id
             WHERE pi.product_id = {$productId} AND pis.site_id = {$siteId}
             ORDER BY pis.sort_order ASC, pi.image_id ASC"
        );

        $images = ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();

        $mainImage = '';
        $extra     = array();
        foreach ($images as $i => $img) {
            if ($i === 0) {
                $mainImage = (string)$img['path'];
            } else {
                $extra[] = array('path' => (string)$img['path'], 'sort' => $i);
            }
        }

        Database::update($db, 'oc_product',
            array('image' => $mainImage),
            array('product_id' => $siteProductId)
        );

        Database::query($db, "DELETE FROM oc_product_image WHERE product_id = {$siteProductId}");
        foreach ($extra as $img) {
            $sp = Database::escape($db, $img['path']);
            if ($siteId === 1) {
                // off: oc_product_image has uuid/video/image_description (NOT NULL, no default)
                $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                Database::query($db,
                    "INSERT INTO oc_product_image (product_id, image, sort_order, uuid, video, image_description)
                     VALUES ({$siteProductId}, '{$sp}', {$img['sort']}, '{$uuid}', '', '')"
                );
            } else {
                // mff: simpler schema — only product_id, image, sort_order
                Database::query($db,
                    "INSERT INTO oc_product_image (product_id, image, sort_order)
                     VALUES ({$siteProductId}, '{$sp}', {$img['sort']})"
                );
            }
        }

        if ($siteId === 1 && $mainImage !== '') {
            $this->updateMainImageCache($productId, $mainImage);
        }
    }

    // ─── private ─────────────────────────────────────────────────────────────

    private function validateImage($tmpPath, $imageMime, $fileSize)
    {
        if ($fileSize > 5 * 1024 * 1024) return 'File too large (max 5MB)';
        $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
        if (!in_array($imageMime, $allowed)) return 'Unsupported image format';
        return null;
    }

    private function processImage($tmpPath, $imageMime)
    {
        $src = null;
        switch ($imageMime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($tmpPath); break;
            case 'image/png':  $src = @imagecreatefrompng($tmpPath);  break;
            case 'image/webp': $src = @imagecreatefromwebp($tmpPath); break;
            case 'image/gif':  $src = @imagecreatefromgif($tmpPath);  break;
        }
        if (!$src) return null;

        $origW  = imagesx($src);
        $origH  = imagesy($src);
        $maxDim = 1200;
        $newW   = $origW;
        $newH   = $origH;
        if ($origW > $maxDim || $origH > $maxDim) {
            if ($origW >= $origH) { $newW = $maxDim; $newH = (int)round($origH * $maxDim / $origW); }
            else                  { $newH = $maxDim; $newW = (int)round($origW * $maxDim / $origH); }
        }

        $dst = imagecreatetruecolor($newW, $newH);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);
        return $dst;
    }

    private function getActiveSites($productId)
    {
        $r = Database::fetchAll('Papir',
            "SELECT DISTINCT pis.site_id
             FROM product_image_site pis
             JOIN product_image pi ON pi.image_id = pis.image_id
             WHERE pi.product_id = {$productId}"
        );
        if ($r['ok'] && !empty($r['rows'])) {
            $sites = array();
            foreach ($r['rows'] as $row) $sites[] = (int)$row['site_id'];
            return $sites;
        }
        // No existing images — use product_site to find active sites
        $pr = Database::fetchAll('Papir',
            "SELECT site_id FROM product_site
             WHERE product_id = {$productId} AND site_product_id > 0"
        );
        $sites = array();
        if ($pr['ok']) {
            foreach ($pr['rows'] as $row) $sites[] = (int)$row['site_id'];
        }
        return $sites;
    }

    private function getSiteProductId($productId, $siteId)
    {
        $r = Database::fetchRow('Papir',
            "SELECT site_product_id FROM product_site
             WHERE product_id = {$productId} AND site_id = {$siteId} LIMIT 1"
        );
        if (!$r['ok'] || empty($r['row'])) return 0;
        return (int)$r['row']['site_product_id'];
    }

    private function updateMainImageCache($productId, $path)
    {
        $sp = Database::escape('Papir', $path);
        Database::query('Papir',
            "UPDATE product_papir SET image = '{$sp}' WHERE product_id = {$productId}"
        );
    }

    private function refreshMainImageCache($productId)
    {
        $r = Database::fetchRow('Papir',
            "SELECT path FROM product_image
             WHERE product_id = {$productId}
             ORDER BY sort_order ASC, image_id ASC LIMIT 1"
        );
        $path = ($r['ok'] && !empty($r['row'])) ? (string)$r['row']['path'] : '';
        $this->updateMainImageCache($productId, $path);
    }
}
