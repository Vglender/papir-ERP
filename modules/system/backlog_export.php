<?php
require_once __DIR__ . '/../../modules/database/database.php';

function backlog_export_md() {
    $r = Database::fetchAll('Papir',
        "SELECT module, type, text, created_at
         FROM backlog
         WHERE resolved_at IS NULL
         ORDER BY module, type, created_at"
    );
    if (!$r['ok']) return;

    $grouped = array();
    foreach ($r['rows'] as $row) {
        $grouped[$row['module']][$row['type']][] = $row['text'];
    }

    $lines = array();
    $lines[] = '# Backlog';
    $lines[] = '';
    $lines[] = '> Автогенеровано. Не редагувати вручну. Керувати через /system/backlog або віджет у шапці.';
    $lines[] = '> Оновлено: ' . date('Y-m-d H:i:s');
    $lines[] = '';

    if (empty($grouped)) {
        $lines[] = '_Бэклог порожній._';
    } else {
        $typeLabels = array('bug' => '🐛 Баги', 'plan' => '📋 Плани', 'idea' => '💡 Ідеї');
        foreach ($grouped as $module => $types) {
            $lines[] = '## ' . $module;
            foreach ($types as $type => $items) {
                $label = isset($typeLabels[$type]) ? $typeLabels[$type] : $type;
                $lines[] = '### ' . $label;
                foreach ($items as $text) {
                    $lines[] = '- ' . str_replace("\n", ' ', $text);
                }
                $lines[] = '';
            }
        }
    }

    $path = __DIR__ . '/../../docs/backlog.md';
    file_put_contents($path, implode("\n", $lines) . "\n");
}
