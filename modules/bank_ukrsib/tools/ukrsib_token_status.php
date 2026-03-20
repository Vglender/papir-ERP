<?php

$ukrsib = require __DIR__ . '/../ukrsib_config.php';

function ukrsib_uuid_v4()
{
    $data = openssl_random_pseudo_bytes(16);
    if ($data === false || strlen($data) < 16) {
        $data = md5(uniqid(mt_rand(), true), true);
    }

    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function ukrsib_token_read()
{
    $ukrsib = ukrsib_config();

    if (!file_exists($ukrsib['token_file'])) {
        return array();
    }

    $json = file_get_contents($ukrsib['token_file']);
    if ($json === false || trim($json) === '') {
        return array();
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : array();
}

function ukrsib_client_code_read()
{
    $ukrsib = ukrsib_config();

    if (!file_exists($ukrsib['client_code_file'])) {
        return array();
    }

    $json = file_get_contents($ukrsib['client_code_file']);
    if ($json === false || trim($json) === '') {
        return array();
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : array();
}

function ukrsib_client_code_write($data)
{
    $ukrsib = ukrsib_config();

    file_put_contents(
        $ukrsib['client_code_file'],
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function ukrsib_get_authorize_url_by_client_code($clientCode)
{
    $ukrsib = ukrsib_config();

    return rtrim($ukrsib['base_url'], '/')
        . '/authorize?client_id=' . urlencode(trim($ukrsib['client_id']))
        . '&client_code=' . urlencode(trim($clientCode))
        . '&response_type=code';
}

function formatDateTime($timestamp)
{
    if (empty($timestamp)) {
        return '—';
    }

    return date('d.m.Y H:i:s', (int)$timestamp);
}

function formatIntervalDays($expiresAt)
{
    if (empty($expiresAt)) {
        return '—';
    }

    $seconds = (int)$expiresAt - time();
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($seconds <= 0) {
        return 'Токен истёк';
    }

    return $days . ' дн. ' . $hours . ' ч. ' . $minutes . ' мин.';
}

function getTokenStatusClass($expiresAt)
{
    if (empty($expiresAt)) {
        return 'status-gray';
    }

    $seconds = (int)$expiresAt - time();

    if ($seconds <= 3 * 86400) {
        return 'status-red';
    }

    if ($seconds <= 7 * 86400) {
        return 'status-orange';
    }

    return 'status-green';
}

function getTokenStatusText($expiresAt)
{
    if (empty($expiresAt)) {
        return 'Токен отсутствует';
    }

    $seconds = (int)$expiresAt - time();

    if ($seconds <= 0) {
        return 'Токен истёк';
    }

    if ($seconds <= 3 * 86400) {
        return 'Срок действия скоро закончится';
    }

    if ($seconds <= 7 * 86400) {
        return 'Нужно обновить в ближайшие дни';
    }

    return 'Токен действителен';
}

if (isset($_GET['start']) && $_GET['start'] == '1') {
    $clientCode = ukrsib_uuid_v4();

    ukrsib_client_code_write(array(
        'client_code' => $clientCode,
        'created_at'  => time()
    ));

    header('Location: ' . ukrsib_get_authorize_url_by_client_code($clientCode));
    exit;
}
function ukrsib_config()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../ukrsib_config.php';
    }
    return $config;
}

$token = ukrsib_token_read();
$clientCodeData = ukrsib_client_code_read();

$expiresAt = isset($token['expires_at']) ? (int)$token['expires_at'] : 0;
$statusClass = getTokenStatusClass($expiresAt);
$statusText = getTokenStatusText($expiresAt);

$tokenExists = !empty($token['access_token']) && !empty($token['refresh_token']);
$clientCodeExists = !empty($clientCodeData['client_code']);

$accessPreview = $tokenExists ? substr($token['access_token'], 0, 40) . '...' : '—';
$refreshPreview = $tokenExists ? substr($token['refresh_token'], 0, 40) . '...' : '—';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>UKRSIB Token Status</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f3f5f7;
            color: #1f2937;
        }
        .wrap {
            max-width: 980px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            padding: 28px;
            margin-bottom: 24px;
        }
        .title {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .subtitle {
            color: #6b7280;
            margin: 0 0 24px;
            font-size: 14px;
        }
        .status-box {
            border-radius: 14px;
            padding: 18px 20px;
            color: #fff;
            margin-bottom: 24px;
        }
        .status-green { background: #16a34a; }
        .status-orange { background: #ea580c; }
        .status-red { background: #dc2626; }
        .status-gray { background: #6b7280; }

        .status-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .status-text {
            font-size: 14px;
            opacity: 0.95;
        }
        .grid {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px 18px;
            align-items: start;
        }
        .label {
            color: #6b7280;
            font-weight: 600;
            font-size: 14px;
        }
        .value {
            font-size: 14px;
            word-break: break-word;
        }
        .mono {
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 10px 12px;
            border-radius: 10px;
        }
        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }
        .btn {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: 0.2s ease;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #111827;
            color: #fff;
        }
        .btn-secondary:hover {
            background: #000;
        }
        .btn-light {
            background: #e5e7eb;
            color: #111827;
        }
        .btn-light:hover {
            background: #d1d5db;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 18px;
        }
        .hint {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 14px;
            line-height: 1.5;
        }
        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1 class="title">UKRSIB Token Status</h1>
        <p class="subtitle">Контроль access/refresh token для интеграции Papir с UKRSIB business API</p>

        <div class="status-box <?php echo $statusClass; ?>">
            <div class="status-title"><?php echo htmlspecialchars($statusText); ?></div>
            <div class="status-text">
                <?php if ($tokenExists): ?>
                    До истечения: <?php echo htmlspecialchars(formatIntervalDays($expiresAt)); ?>
                <?php else: ?>
                    В файле токенов нет действующей пары access_token / refresh_token.
                <?php endif; ?>
            </div>
        </div>

        <div class="grid">
            <div class="label">Base URL</div>
            <div class="value"><?php echo htmlspecialchars($ukrsib['base_url']); ?></div>

            <div class="label">Client ID</div>
            <div class="value mono"><?php echo htmlspecialchars($ukrsib['client_id']); ?></div>

            <div class="label">Файл токенов</div>
            <div class="value mono"><?php echo htmlspecialchars($ukrsib['token_file']); ?></div>

            <div class="label">Файл client_code</div>
            <div class="value mono"><?php echo htmlspecialchars($ukrsib['client_code_file']); ?></div>

            <div class="label">Истекает</div>
            <div class="value"><?php echo htmlspecialchars(formatDateTime($expiresAt)); ?></div>

            <div class="label">Осталось времени</div>
            <div class="value"><?php echo htmlspecialchars(formatIntervalDays($expiresAt)); ?></div>

            <div class="label">Access token</div>
            <div class="value mono"><?php echo htmlspecialchars($accessPreview); ?></div>

            <div class="label">Refresh token</div>
            <div class="value mono"><?php echo htmlspecialchars($refreshPreview); ?></div>
        </div>

        <div class="buttons">
            <a class="btn btn-primary" href="?start=1">Начать новую авторизацию</a>
            <a class="btn btn-secondary" href="ukrsib_token_exchange.php">Перейти к обмену токена</a>
            <a class="btn btn-light" href="ukrsib_token_status.php">Обновить страницу</a>
        </div>

        <div class="hint">
            После нажатия <strong>«Начать новую авторизацию»</strong> будет создан новый <strong>client_code</strong>,
            сохранён в файл и выполнен переход на страницу авторизации UKRSIB business.
            После успешного входа открой страницу обмена токена.
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Последний client_code</h2>

        <div class="grid">
            <div class="label">Client code</div>
            <div class="value mono">
                <?php echo $clientCodeExists ? htmlspecialchars($clientCodeData['client_code']) : '—'; ?>
            </div>

            <div class="label">Создан</div>
            <div class="value">
                <?php
                echo $clientCodeExists && !empty($clientCodeData['created_at'])
                    ? htmlspecialchars(formatDateTime($clientCodeData['created_at']))
                    : '—';
                ?>
            </div>

            <div class="label">Возраст</div>
            <div class="value">
                <?php
                if ($clientCodeExists && !empty($clientCodeData['created_at'])) {
                    $age = time() - (int)$clientCodeData['created_at'];
                    echo htmlspecialchars(floor($age / 60)) . ' мин. ' . htmlspecialchars($age % 60) . ' сек.';
                } else {
                    echo '—';
                }
                ?>
            </div>
        </div>

        <div class="hint">
            Для UKRSIB один и тот же <strong>client_code</strong> должен использоваться и в авторизации, и в обмене токена.
            Между этими шагами должно пройти не более 15 минут.
        </div>
    </div>

</div>
</body>
</html>