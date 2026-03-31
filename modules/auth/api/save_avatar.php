<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$me = \Papir\Crm\AuthService::getCurrentUser();
if (!$me) {
    echo json_encode(array('ok' => false, 'error' => 'Не авторизовано'));
    exit;
}

$userId = (int)$me['user_id'];

$allowed_colors = array('blue','green','orange','red','pink','indigo','teal','gray','amber','cyan');
$allowed_emojis = array(
    '🦊','🐱','🐶','🦁','🐻','🦝','🐼','🦄',
    '🦅','🐺','🐯','🦋','😎','🤖','🧙','🦸',
    '👾','🌟','⚡','🔥','💎','🚀','🌿','🎯',
);

// ── Варіант 1: вибір кольору (зберігає поточний режим — initials або emoji) ───
if (isset($_POST['color'])) {
    $color = trim($_POST['color']);
    if (!in_array($color, $allowed_colors)) {
        echo json_encode(array('ok' => false, 'error' => 'Невідомий колір'));
        exit;
    }
    // Read current avatar to preserve emoji if set
    $curSettings = \Papir\Crm\UserRepository::getSettings($userId);
    $curJson = isset($curSettings['settings_json']) ? $curSettings['settings_json'] : '';
    $curData = $curJson ? json_decode($curJson, true) : array();
    if (!is_array($curData)) { $curData = array(); }
    $curAvatar = isset($curData['avatar']) ? $curData['avatar'] : '';

    if (strpos($curAvatar, 'emoji:') === 0) {
        // Keep the emoji, change only background color
        $rest   = substr($curAvatar, 6);
        $grads  = array('blue','green','orange','red','pink','indigo','teal','gray','amber','cyan');
        $colPos = mb_strrpos($rest, ':', 0, 'UTF-8');
        $emoji  = $rest;
        if ($colPos !== false) {
            $maybeKey = mb_substr($rest, $colPos + 1, null, 'UTF-8');
            if (in_array($maybeKey, $grads)) { $emoji = mb_substr($rest, 0, $colPos, 'UTF-8'); }
        }
        $avatar = 'emoji:' . $emoji . ':' . $color;
    } else {
        $avatar = 'color:' . $color;
    }
    _saveAvatarField($userId, $avatar);
    echo json_encode(array('ok' => true, 'avatar' => $avatar));
    exit;
}

// ── Варіант 1б: вибір емодзі ─────────────────────────────────────────────────
if (isset($_POST['emoji'])) {
    $emoji = trim($_POST['emoji']);
    if (!in_array($emoji, $allowed_emojis)) {
        echo json_encode(array('ok' => false, 'error' => 'Невідома іконка'));
        exit;
    }
    $bgColor = isset($_POST['bg_color']) ? trim($_POST['bg_color']) : 'blue';
    if (!in_array($bgColor, $allowed_colors)) { $bgColor = 'blue'; }
    $avatar = 'emoji:' . $emoji . ':' . $bgColor;
    _saveAvatarField($userId, $avatar);
    echo json_encode(array('ok' => true, 'avatar' => $avatar));
    exit;
}

// ── Варіант 1в: повернутись до ініціалів ─────────────────────────────────────
if (isset($_POST['use_initials'])) {
    $bgColor = isset($_POST['bg_color']) ? trim($_POST['bg_color']) : 'blue';
    if (!in_array($bgColor, $allowed_colors)) { $bgColor = 'blue'; }
    $avatar = 'color:' . $bgColor;
    _saveAvatarField($userId, $avatar);
    echo json_encode(array('ok' => true, 'avatar' => $avatar));
    exit;
}

// ── Варіант 2: завантаження фото ──────────────────────────────────────────────
if (!empty($_FILES['avatar_file']['tmp_name'])) {
    $file    = $_FILES['avatar_file'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        echo json_encode(array('ok' => false, 'error' => 'Файл занадто великий (max 5MB)'));
        exit;
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, array('image/jpeg','image/png','image/gif','image/webp'))) {
        echo json_encode(array('ok' => false, 'error' => 'Тільки зображення'));
        exit;
    }

    $destDir = '/var/www/menufold/data/www/officetorg.com.ua/image/crm/avatars/';
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }

    $ext      = 'jpg';
    $filename = 'user_' . $userId . '_' . uniqid() . '.' . $ext;
    $destPath = $destDir . $filename;

    // Resize to 128×128 JPEG
    $src = null;
    if ($mime === 'image/jpeg') { $src = imagecreatefromjpeg($file['tmp_name']); }
    elseif ($mime === 'image/png') { $src = imagecreatefrompng($file['tmp_name']); }
    elseif ($mime === 'image/gif') { $src = imagecreatefromgif($file['tmp_name']); }
    elseif ($mime === 'image/webp') { $src = imagecreatefromwebp($file['tmp_name']); }

    if (!$src) {
        echo json_encode(array('ok' => false, 'error' => 'Не вдалося обробити зображення'));
        exit;
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $size = min($srcW, $srcH);
    // Crop square from center
    $cropX = (int)(($srcW - $size) / 2);
    $cropY = (int)(($srcH - $size) / 2);

    $out = imagecreatetruecolor(128, 128);
    // Fill white for transparent PNGs
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefill($out, 0, 0, $white);
    imagecopyresampled($out, $src, 0, 0, $cropX, $cropY, 128, 128, $size, $size);
    imagedestroy($src);

    if (!imagejpeg($out, $destPath, 85)) {
        imagedestroy($out);
        echo json_encode(array('ok' => false, 'error' => 'Не вдалося зберегти'));
        exit;
    }
    imagedestroy($out);

    // Delete old avatar photo if any
    $oldSettings = \Papir\Crm\UserRepository::getSettings($userId);
    $oldJson = isset($oldSettings['settings_json']) ? $oldSettings['settings_json'] : '';
    if ($oldJson) {
        $oldData = json_decode($oldJson, true);
        if (is_array($oldData) && !empty($oldData['avatar']) && strpos($oldData['avatar'], 'image/') === 0) {
            $oldFile = '/var/www/menufold/data/www/officetorg.com.ua/' . $oldData['avatar'];
            if (is_file($oldFile)) { @unlink($oldFile); }
        }
    }

    $avatar = 'image/crm/avatars/' . $filename;
    _saveAvatarField($userId, $avatar);
    echo json_encode(array('ok' => true, 'avatar' => $avatar, 'url' => '/' . $avatar));
    exit;
}

// ── Варіант 3: скинути аватар ──────────────────────────────────────────────────
if (isset($_POST['reset'])) {
    _saveAvatarField($userId, null);
    echo json_encode(array('ok' => true, 'avatar' => null));
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'Нічого не передано'));

function _saveAvatarField($userId, $avatar) {
    $r = \Database::fetchRow('Papir',
        "SELECT settings_json FROM auth_user_settings WHERE user_id = {$userId} LIMIT 1");
    $existing = ($r['ok'] && $r['row'] && !empty($r['row']['settings_json']))
        ? json_decode($r['row']['settings_json'], true)
        : array();
    if (!is_array($existing)) { $existing = array(); }
    if ($avatar === null) {
        unset($existing['avatar']);
    } else {
        $existing['avatar'] = $avatar;
    }
    $json = !empty($existing) ? json_encode($existing, JSON_UNESCAPED_UNICODE) : null;
    \Database::query('Papir',
        "UPDATE auth_user_settings SET settings_json = " .
        ($json !== null ? "'" . \Database::escape('Papir', $json) . "'" : "NULL") .
        " WHERE user_id = {$userId}");
}