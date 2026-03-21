<?php

final class ViewHelper
{
    public static function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function buildUrl($basePath, array $params = array())
    {
        $query = http_build_query($params);
        return $query !== '' ? $basePath . '?' . $query : $basePath;
    }

    public static function redirect($basePath, array $params = array())
    {
        header('Location: ' . self::buildUrl($basePath, $params));
        exit;
    }
}