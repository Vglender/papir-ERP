<?php

require_once __DIR__ . '/action_bootstrap.php';

$errors   = array();
$basePath = '/action';
$perPage  = 50;

$action     = Request::getString('action');
$product_id = Request::getInt('product_id', 0);

$search = Request::getString('search', '');
$filter = Request::getString('filter', 'all');

$dashboardRepo = new ActionDashboardRepository();
$actionRepo    = new ActionRepository();
$priceRepo     = new ActionPriceRepository();

$sort  = $dashboardRepo->normalizeSort(Request::getString('sort', 'product_id'));
$order = $dashboardRepo->normalizeOrder(Request::getString('order', 'asc'));
$page  = max(1, Request::getInt('page', 1));

$state = array(
    'search' => $search,
    'filter' => $filter,
    'sort'   => $sort,
    'order'  => $order,
    'page'   => $page,
);

// Handle POST: save discount
if (Request::isPost()) {
    $form_action = Request::postString('form_action');

    if ($form_action === 'save') {
        $post_product_id = Request::postInt('product_id', 0);
        $discount        = Request::postNullableInt('discount', 0);
        $super_discont   = Request::postNullableInt('super_discont', 0);

        if ($post_product_id <= 0) {
            $errors[] = 'Product ID должен быть больше нуля.';
        }

        if ($discount < 0 || $discount > 100) {
            $errors[] = 'Discount должен быть от 0 до 100.';
        }

        if ($super_discont < 0 || $super_discont > 100) {
            $errors[] = 'Super Discount должен быть от 0 до 100.';
        }

        if (empty($errors)) {
            // Both discounts zeroed — remove action completely
            if ($discount == 0 && $super_discont == 0) {
                $actionRepo->delete($post_product_id);
                $priceRepo->deleteByProductId($post_product_id);
                // Remove special price from OpenCart
                Database::query('off',
                    "DELETE FROM `oc_product_special`
                     WHERE `product_id` = " . $post_product_id . "
                       AND `customer_group_id` IN (1, 4)"
                );
                ViewHelper::redirect($basePath, $state);
            } else {
                $saveResult = $actionRepo->save($post_product_id, $discount, $super_discont);

                if (!$saveResult['ok']) {
                    $errors[] = 'Ошибка сохранения: ' . (isset($saveResult['error']) ? $saveResult['error'] : 'unknown');
                } else {
                    ViewHelper::redirect($basePath, $state);
                }
            }
        }
    }
}

// Handle GET: delete
if ($action === 'delete' && $product_id > 0) {
    $actionRepo->delete($product_id);
    ViewHelper::redirect($basePath, $state);
}

// Edit row
$edit_row = $dashboardRepo->getDefaultEditRow();

if ($action === 'edit' && $product_id > 0) {
    $foundEditRow = $dashboardRepo->getEditRow($product_id);

    if ($foundEditRow !== null) {
        $edit_row = $foundEditRow;
    }
}

// Stats
$updatedAt      = $dashboardRepo->getUpdatedAt();
$totalStockSum  = $dashboardRepo->getTotalStockSum();
$total_rows     = $dashboardRepo->getTotalRows($search, $filter);
$actionCount    = count($actionRepo->getAll());
$pendingCount   = $priceRepo->getPendingPublishCount();

$paginator   = new Paginator($page, $perPage, $total_rows);
$page        = $paginator->page;
$total_pages = $paginator->totalPages;
$offset      = $paginator->offset;

$state['page'] = $page;

$list = $dashboardRepo->getList(
    $search,
    $filter,
    $sort,
    $order,
    $offset,
    $perPage
);

require __DIR__ . '/views/dashboard.php';
