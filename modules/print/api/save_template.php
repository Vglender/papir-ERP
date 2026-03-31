<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : 'save';
$repo   = new PrintTemplateRepository();

if ($action === 'new_version') {
    $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    $result   = $repo->createVersion($parentId);
    echo json_encode($result);
    exit;
}

$data = array(
    'id'               => isset($_POST['id'])               ? (int)$_POST['id']      : 0,
    'type_id'          => isset($_POST['type_id'])          ? (int)$_POST['type_id'] : 0,
    'code'             => isset($_POST['code'])             ? $_POST['code']         : '',
    'name'             => isset($_POST['name'])             ? $_POST['name']         : '',
    'html_body'        => isset($_POST['html_body'])        ? $_POST['html_body']    : '',
    'status'           => isset($_POST['status'])           ? $_POST['status']       : 'draft',
    'variables_schema' => isset($_POST['variables_schema']) ? $_POST['variables_schema'] : null,
    'page_settings'    => isset($_POST['page_settings'])    ? $_POST['page_settings']    : null,
);

$result = $repo->save($data);
echo json_encode($result);