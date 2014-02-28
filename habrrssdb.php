<?php

class HabrRSSDb
{
    protected $hDatabase = null;

    public function __construct($dbFile)
    {
        fclose(fopen($dbFile, 'a')); // create file if not exists
        $this->hDatabase = fopen($dbFile, 'r+');
    }

    public function __destruct()
    {
        fclose($this->hDatabase);
    }

    public function isPublished($id)
    {
        fseek($this->hDatabase, $id);
        $status = fgetc($this->hDatabase);
        return $status === '1';
    }

    public function setPublished($id)
    {
        fseek($this->hDatabase, $id);
        fwrite($this->hDatabase, '1');
    }
}
