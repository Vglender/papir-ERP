<?php

final class TableHelper
{
    public static function sortLink($label, $field, array $state, $basePath)
    {
        $newOrder = 'asc';

        if (isset($state['sort'], $state['order']) && $state['sort'] === $field && $state['order'] === 'asc') {
            $newOrder = 'desc';
        }

        $params = $state;
        $params['sort'] = $field;
        $params['order'] = $newOrder;
        $params['page'] = 1;

        $url = ViewHelper::buildUrl($basePath, $params);

        return '<a href="' . ViewHelper::h($url) . '" class="sort-link">' . ViewHelper::h($label) . '</a>';
    }

    public static function pageLink($pageNumber, array $state, $basePath)
    {
        $params = $state;
        $params['page'] = (int)$pageNumber;

        return ViewHelper::buildUrl($basePath, $params);
    }
}