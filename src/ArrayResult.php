<?php

final class ArrayResult
{
    /** @var array */
    private $rows;

    /** @var int */
    private $index = 0;

    /** @var int */
    public $num_rows = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc()
    {
        if (!isset($this->rows[$this->index])) {
            return null;
        }

        $row = $this->rows[$this->index];
        $this->index++;

        return $row;
    }
}