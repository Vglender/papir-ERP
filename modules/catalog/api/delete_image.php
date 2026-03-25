<?php
// Delegate to shared image delete API
if (!isset($_POST['entity_type'])) { $_POST['entity_type'] = 'category'; }
require_once __DIR__ . '/../../shared/api/delete_image.php';
