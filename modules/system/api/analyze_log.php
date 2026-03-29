<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../modules/openai/openai_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$logContent = isset($_POST['log_content']) ? trim($_POST['log_content']) : '';
$logLabel   = isset($_POST['log_label'])   ? trim($_POST['log_label'])   : 'server log';

if ($logContent === '') {
    echo json_encode(array('ok' => false, 'error' => 'Log content is empty'));
    exit;
}

// Truncate to avoid exceeding token limits (~12000 chars ≈ 3000 tokens)
$maxChars = 12000;
$truncated = false;
if (mb_strlen($logContent) > $maxChars) {
    // Keep the tail — recent entries are more relevant
    $logContent = mb_substr($logContent, -$maxChars);
    $truncated  = true;
}

$prompt = 'Ти — Senior DevOps/SysAdmin. Проаналізуй серверний лог і дай стислий звіт.

Лог: ' . $logLabel . ($truncated ? ' (останні ~12000 символів)' : '') . '

```
' . $logContent . '
```

Відповідай структуровано:

## Проблеми
Перелічи знайдені помилки/попередження з коротким поясненням. Якщо проблем немає — напиши "Критичних проблем не знайдено."

## Рекомендації
Конкретні команди або дії для виправлення кожної проблеми (bash-команди в `code`).

## Висновок
1-2 речення: загальний стан + пріоритет дій.';

$client = openai_client();
$result = $client->chat($prompt, 'gpt-4o-mini', 1500, 0.3);

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => $result['error']));
    exit;
}

echo json_encode(array(
    'ok'        => true,
    'analysis'  => $result['text'],
    'truncated' => $truncated,
));
