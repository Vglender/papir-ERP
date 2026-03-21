<?php

final class Request
{
    public static function getString($key, $default = '')
    {
        return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
    }

    public static function getInt($key, $default = 0)
    {
        return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
    }

    public static function postString($key, $default = '')
    {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }

    public static function postInt($key, $default = 0)
    {
        return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
    }

    public static function postNullableInt($key, $default = 0)
    {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            return $default;
        }

        return (int)$_POST[$key];
    }

    public static function isPost()
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}