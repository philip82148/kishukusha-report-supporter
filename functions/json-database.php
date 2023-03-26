<?php

class JsonDatabase
{
    private mysqli $link;
    private string $tableName;

    public function __construct(string $tableName)
    {
        $this->link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
        mysqli_select_db($this->link, DB_NAME);
        mysqli_set_charset($this->link, DB_CHARSET);

        mysqli_query(
            $this->link,
            "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `key` varchar(200) NOT null,
                `object_json` varchar(21640) NOT null,
                `created_at` datetime NOT null DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT null DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY (`key`)
            ) DEFAULT CHARSET=" . DB_CHARSET . ";"
        );
        $this->tableName = $tableName;
    }

    public function restore(string $key)
    {
        $key = $this->escape($key);
        $result = mysqli_query($this->link, "SELECT `object_json` FROM `{$this->tableName}` WHERE `key`='{$key}'");
        $row = mysqli_fetch_assoc($result);
        if ($row)
            return json_decode($row['object_json'], true);
        return null;
    }

    public function store(string $key, mixed $object): void
    {
        $key = $this->escape($key);
        $objectJson = $this->escape(json_encode($object, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        mysqli_query($this->link, "INSERT INTO `{$this->tableName}` (`key`, `object_json`) VALUES ('{$key}', '{$objectJson}') ON DUPLICATE KEY UPDATE `object_json`=VALUES(`object_json`)");
    }

    public function getUpdatedTime(string $key): int
    {
        $key = $this->escape($key);
        $result = mysqli_query($this->link, "SELECT `updated_at` FROM `{$this->tableName}` WHERE `key`='{$key}'");
        $row = mysqli_fetch_assoc($result);
        if ($row) {
            $time = strtotime($row['updated_at']);
            if ($time !== false)
                return $time;
        }

        return 0;
    }

    public function delete(string $key)
    {
        $key = $this->escape($key);
        mysqli_query($this->link, "DELETE FROM `{$this->tableName}` WHERE `key`='{$key}'");
    }

    public function escape(string $string): string
    {
        return mysqli_real_escape_string($this->link, $string);
    }

    public function __destruct()
    {
        mysqli_close($this->link);
    }
}
