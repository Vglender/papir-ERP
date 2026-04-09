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
            // Look up id_off from product_papir (action tables use id_off as key)
            $ppRow = Database::fetchRow('Papir', "SELECT id_off FROM product_papir WHERE product_id = " . $post_product_id . " LIMIT 1");
            $idOff = ($ppRow['ok'] && !empty($ppRow['row'])) ? (int)$ppRow['row']['id_off'] : 0;

            if ($idOff <= 0) {
                $errors[] = 'Товар не прив\'язаний до сайту (id_off = 0).';
            } elseif ($discount == 0 && $super_discont == 0) {
                // Both discounts zeroed — remove action completely
                $actionRepo->delete($idOff);
                $priceRepo->deleteByProductId($idOff);
                // Remove special price from OpenCart for this product
                require_once __DIR__ . '/../integrations/opencart2/SiteSyncService.php';
                $sync = new SiteSyncService();
                $transport = $sync->getTransport(1);
                if ($transport instanceof SiteSyncTransportDirectDb) {
                    Database::query('off',
                        "DELETE FROM oc_product_special
                         WHERE product_id = " . (int)$idOff . "
                           AND customer_group_id IN (1, 4)");
                } else {
                    // For HTTP agent: update product with empty specials via productUpdate
                    $transport->call('product.update', array(
                        'product_id' => (int)$idOff,
                        'fields' => array(),
                    ));
                }
                ViewHelper::redirect($basePath, $state);
            } else {
                $saveResult = $actionRepo->save($idOff, $discount, $super_discont);

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
    $ppDelRow = Database::fetchRow('Papir', "SELECT id_off FROM product_papir WHERE product_id = " . $product_id . " LIMIT 1");
    $idOffDel = ($ppDelRow['ok'] && !empty($ppDelRow['row'])) ? (int)$ppDelRow['row']['id_off'] : 0;
    if ($idOffDel > 0) {
        $actionRepo->delete($idOffDel);
    }
    ViewHelper::redirect($basePath, $state);
}

// Stats
$total_rows   = $dashboardRepo->getTotalRows($search, $filter);
$actionCount  = count($actionRepo->getAll());
$pendingCount = $priceRepo->getPendingPublishCount();

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

// Auto-select first row if no item is selected
if ($action !== 'edit' && $product_id === 0 && !empty($list)) {
    $product_id = (int)$list[0]['product_id'];
    $action     = 'edit';
}

// Edit row
$edit_row = $dashboardRepo->getDefaultEditRow();

if ($action === 'edit' && $product_id > 0) {
    $foundEditRow = $dashboardRepo->getEditRow($product_id);

    if ($foundEditRow !== null) {
        $edit_row = $foundEditRow;
    }
}

require __DIR__ . '/views/dashboard.php';
