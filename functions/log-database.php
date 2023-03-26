<?php

require_once __DIR__ . '/json-database.php';

class LogDatabase extends JsonDatabase
{
    private int $no;
    private string $key;
    private string $fullLog;

    public function __construct(string $tableName)
    {
        parent::__construct($tableName);
        $this->no = $this->restore('lastNo') ?? 9999;
        $this->no += 1;
        if ($this->no >= 10000)
            $this->no = 0;
        $this->key = sprintf('log%04d', $this->no);
        $this->fullLog = '';
    }

    public function log(string $string)
    {
        if ($this->fullLog === '') {
            $this->fullLog = "'{$string}'";
            $this->store($this->key, $this->fullLog);
            $this->store('lastNo', $this->no);
        } else {
            $this->fullLog .= "'{$string}'";
            $this->store($this->key, $this->fullLog);
        }
    }
}
