<?php

class RSSDb
{
    protected $hDatabase = null;
    protected $minId = 0;
    protected $bits = array(0=>1, 2, 4, 8, 16, 32, 64, 128);

    public function __construct($dbFile, $minId = 0)
    {
        fclose(fopen($dbFile, 'a')); // create file if not exists
        $this->hDatabase = fopen($dbFile, 'r+');
        $this->minId = $minId;
    }

    public function __destruct()
    {
        fclose($this->hDatabase);
    }

    public function isPublished($id)
    {
        $id -= $this->minId;
        if ($id < 0) return true;
        $pos = intval($id/8);
        fseek($this->hDatabase, $pos);
        $status = ord(fgetc($this->hDatabase));
        return (bool)($status & $this->bits[$id % 8]);
    }

    public function setPublished($id)
    {
        $id -= $this->minId;
        if ($id < 0) return;
        $pos = intval($id/8);
        fseek($this->hDatabase, $pos);
        $status = ord(fgetc($this->hDatabase));
        fseek($this->hDatabase, $pos);
        fwrite($this->hDatabase, chr($status | $this->bits[$id % 8]));
    }
}
