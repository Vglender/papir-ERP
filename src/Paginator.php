<?php

final class Paginator
{
    /** @var int */
    public $page;

    /** @var int */
    public $perPage;

    /** @var int */
    public $totalRows;

    /** @var int */
    public $totalPages;

    /** @var int */
    public $offset;

    public function __construct($page, $perPage, $totalRows)
    {
        $this->page = max(1, (int)$page);
        $this->perPage = max(1, (int)$perPage);
        $this->totalRows = max(0, (int)$totalRows);
        $this->totalPages = $this->totalRows > 0 ? (int)ceil($this->totalRows / $this->perPage) : 1;

        if ($this->page > $this->totalPages) {
            $this->page = $this->totalPages;
        }

        $this->offset = ($this->page - 1) * $this->perPage;
    }
}