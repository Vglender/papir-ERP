<?php
// Проверка наличия кода авторизации в URL
if (isset($_GET['code'])) {
    // Получение кода
    $authCode = $_GET['code'];
    echo "Authorization code: " . htmlspecialchars($authCode);
} else {
    echo "No authorization code received.";
}
?>