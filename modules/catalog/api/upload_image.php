<?php
// Delegate to shared image upload API
if (!isset($_POST['entity_type'])) { $_POST['entity_type'] = 'category'; }
if (!isset($_POST['entity_id']) && isset($_POST['category_id'])) {
    $_POST['entity_id'] = $_POST['category_id'];
}
require_once __DIR__ . '/../../shared/api/upload_image.php';
