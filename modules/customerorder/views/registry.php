<?php
/**
 * Ожидает:
 * $result = [
 *   'ok'    => bool,
 *   'rows'  => array,
 *   'count' => int,
 *   'page'  => int,
 *   'limit' => int,
 *   'error' => string
 * ]
 *
 * Пример:
 * require_once __DIR__ . '/../customerorder_bootstrap.php';
 * $repository = new CustomerOrderRepository();
 * $service = new CustomerOrderService($repository);
 * $controller = new CustomerOrderController($service);
 * $result = $controller->index($_GET);
 */

if (!isset($result)) {
    $result = array(
        'ok' => false,
        'error' => 'Переменная $result не передана в registry.php',
        'rows' => array(),
        'count' => 0,
        'page' => 1,
        'limit' => 50,
    );
}

$filters = array(
    'id' => isset($_GET['id']) ? $_GET['id'] : '',
    'number' => isset($_GET['number']) ? $_GET['number'] : '',
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'payment_status' => isset($_GET['payment_status']) ? $_GET['payment_status'] : '',
    'shipment_status' => isset($_GET['shipment_status']) ? $_GET['shipment_status'] : '',
    'manager_employee_id' => isset($_GET['manager_employee_id']) ? $_GET['manager_employee_id'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'sort_field' => isset($_GET['sort_field']) ? $_GET['sort_field'] : 'id',
    'sort_dir' => isset($_GET['sort_dir']) ? $_GET['sort_dir'] : 'DESC',
    'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
    'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50,
);

$rows = !empty($result['rows']) ? $result['rows'] : array();
$total = !empty($result['count']) ? (int)$result['count'] : 0;
$page = !empty($result['page']) ? (int)$result['page'] : 1;
$limit = !empty($result['limit']) ? (int)$result['limit'] : 50;
$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function build_registry_url($params = array())
{
    $query = $_GET;

    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    return '?' . http_build_query($query);
}

function sort_link($field, $currentField, $currentDir)
{
    $newDir = 'ASC';

    if ($currentField === $field && strtoupper($currentDir) === 'ASC') {
        $newDir = 'DESC';
    }

    return build_registry_url(array(
        'sort_field' => $field,
        'sort_dir' => $newDir,
        'page' => 1,
    ));
}

function status_badge_class($status)
{
    $map = array(
        'draft' => 'bg-secondary',
        'new' => 'bg-primary',
        'confirmed' => 'bg-info',
        'in_progress' => 'bg-warning text-dark',
        'waiting_payment' => 'bg-warning text-dark',
        'paid' => 'bg-success',
        'partially_shipped' => 'bg-info',
        'shipped' => 'bg-success',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',

        'not_paid' => 'bg-secondary',
        'partially_paid' => 'bg-warning text-dark',
        'overdue' => 'bg-danger',
        'refund' => 'bg-dark',

        'not_shipped' => 'bg-secondary',
        'reserved' => 'bg-warning text-dark',
        'delivered' => 'bg-success',
        'returned' => 'bg-danger',
    );

    return isset($map[$status]) ? $map[$status] : 'bg-secondary';
}
?>
<?php
$title     = 'Замовлення';
$activeNav = 'sales';
$subNav    = 'orders';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin: 20px;
            color: #222;
            background: #f7f7f9;
        }

        .page-title {
            margin: 0 0 16px 0;
            font-size: 24px;
        }

        .card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        .field input,
        .field select {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #fff;
        }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .btn {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: #fff;
            color: #222;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }

        .btn-success {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }

        .summary {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .summary-item {
            font-size: 13px;
            color: #555;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table.registry {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        table.registry th,
        table.registry td {
            border-bottom: 1px solid #e5e5e5;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
            white-space: nowrap;
        }

        table.registry th a {
            color: #222;
            text-decoration: none;
        }

        table.registry tr:hover {
            background: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            line-height: 1;
            color: #fff;
        }

        .bg-primary { background: #0d6efd; }
        .bg-secondary { background: #6c757d; }
        .bg-success { background: #198754; }
        .bg-danger { background: #dc3545; }
        .bg-warning { background: #ffc107; color: #222; }
        .bg-info { background: #0dcaf0; color: #222; }
        .bg-dark { background: #212529; }

        .text-muted {
            color: #777;
        }

        .text-end {
            text-align: right;
        }

        .error-box {
            background: #fff3f3;
            border: 1px solid #f1b5b5;
            color: #8a1f1f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .empty-box {
            padding: 20px;
            text-align: center;
            color: #666;
        }

        .pagination {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            text-decoration: none;
            color: #222;
            background: #fff;
        }

        .pagination .active {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }

        .small {
            font-size: 12px;
        }
    </style>

<h1 class="page-title">Реестр заказов</h1>

<?php if (!$result['ok']): ?>
    <div class="error-box">
        <strong>Ошибка:</strong> <?= h(isset($result['error']) ? $result['error'] : 'Неизвестная ошибка') ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="get">
        <div class="filters-grid">
            <div class="field">
                <label for="id">ID</label>
                <input type="text" name="id" id="id" value="<?= h($filters['id']) ?>">
            </div>

            <div class="field">
                <label for="number">Номер</label>
                <input type="text" name="number" id="number" value="<?= h($filters['number']) ?>">
            </div>

            <div class="field">
                <label for="status">Статус</label>
                <select name="status" id="status">
                    <option value="">-- все --</option>
                    <?php
                    $statuses = array('draft','new','confirmed','in_progress','waiting_payment','paid','partially_shipped','shipped','completed','cancelled');
                    foreach ($statuses as $status):
                    ?>
                        <option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                            <?= h($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="payment_status">Оплата</label>
                <select name="payment_status" id="payment_status">
                    <option value="">-- все --</option>
                    <?php
                    $paymentStatuses = array('not_paid','partially_paid','paid','overdue','refund');
                    foreach ($paymentStatuses as $status):
                    ?>
                        <option value="<?= h($status) ?>" <?= $filters['payment_status'] === $status ? 'selected' : '' ?>>
                            <?= h($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="shipment_status">Отгрузка</label>
                <select name="shipment_status" id="shipment_status">
                    <option value="">-- все --</option>
                    <?php
                    $shipmentStatuses = array('not_shipped','reserved','partially_shipped','shipped','delivered','returned');
                    foreach ($shipmentStatuses as $status):
                    ?>
                        <option value="<?= h($status) ?>" <?= $filters['shipment_status'] === $status ? 'selected' : '' ?>>
                            <?= h($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="manager_employee_id">ID менеджера</label>
                <input type="text" name="manager_employee_id" id="manager_employee_id" value="<?= h($filters['manager_employee_id']) ?>">
            </div>

            <div class="field">
                <label for="date_from">Дата от</label>
                <input type="date" name="date_from" id="date_from" value="<?= h($filters['date_from']) ?>">
            </div>

            <div class="field">
                <label for="date_to">Дата до</label>
                <input type="date" name="date_to" id="date_to" value="<?= h($filters['date_to']) ?>">
            </div>

            <div class="field">
                <label for="limit">Лимит</label>
                <select name="limit" id="limit">
                    <?php foreach (array(20, 50, 100, 200) as $opt): ?>
                        <option value="<?= $opt ?>" <?= (int)$filters['limit'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="actions-row">
            <button type="submit" class="btn btn-primary">Применить фильтры</button>
            <a href="registry.php" class="btn">Сбросить</a>
            <a href="/customerorder/edit" class="btn btn-success">+ Новый заказ</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="summary">
        <div class="summary-item"><strong>Всего заказов:</strong> <?= (int)$total ?></div>
        <div class="summary-item"><strong>Страница:</strong> <?= (int)$page ?> / <?= max(1, (int)$totalPages) ?></div>
        <div class="summary-item"><strong>Лимит:</strong> <?= (int)$limit ?></div>
    </div>

    <div class="table-wrap">
        <table class="registry">
            <thead>
            <tr>
                <th><a href="<?= h(sort_link('id', $filters['sort_field'], $filters['sort_dir'])) ?>">ID</a></th>
                <th><a href="<?= h(sort_link('number', $filters['sort_field'], $filters['sort_dir'])) ?>">Номер</a></th>
                <th><a href="<?= h(sort_link('moment', $filters['sort_field'], $filters['sort_dir'])) ?>">Дата</a></th>
                <th>Статус</th>
                <th>Оплата</th>
                <th>Отгрузка</th>
                <th class="text-end"><a href="<?= h(sort_link('sum_total', $filters['sort_field'], $filters['sort_dir'])) ?>">Сумма</a></th>
                <th>Строк</th>
                <th>Организация</th>
                <th>Склад</th>
                <th>Менеджер</th>
                <th><a href="<?= h(sort_link('updated_at', $filters['sort_field'], $filters['sort_dir'])) ?>">Обновлён</a></th>
                <th>Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="13" class="empty-box">
                        Заказы пока не найдены.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td>
                            <strong><?= h($row['number']) ?></strong><br>
                            <span class="text-muted small"><?= h($row['external_code']) ?></span>
                        </td>
                        <td><?= h($row['moment']) ?></td>
                        <td>
                            <span class="badge <?= h(status_badge_class($row['status'])) ?>">
                                <?= h($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= h(status_badge_class($row['payment_status'])) ?>">
                                <?= h($row['payment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= h(status_badge_class($row['shipment_status'])) ?>">
                                <?= h($row['shipment_status']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?= number_format((float)$row['sum_total'], 2, '.', ' ') ?>
                            <div class="small text-muted"><?= h($row['currency_code']) ?></div>
                        </td>
                        <td><?= (int)$row['items_count'] ?></td>
                        <td><?= h($row['organization_name']) ?></td>
                        <td><?= h($row['store_name']) ?></td>
                        <td><?= h($row['manager_name']) ?></td>
                        <td><?= h($row['updated_at']) ?></td>
                        <td>
                           <a class="btn" href="/customerorder/edit?id=<?= (int)$row['id'] ?>">Открыть</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= h(build_registry_url(array('page' => $page - 1))) ?>">&laquo; Назад</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 3);
            $endPage = min($totalPages, $page + 3);

            for ($p = $startPage; $p <= $endPage; $p++):
            ?>
                <?php if ($p == $page): ?>
                    <span class="active"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= h(build_registry_url(array('page' => $p))) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(build_registry_url(array('page' => $page + 1))) ?>">Вперёд &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>