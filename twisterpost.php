<?php

class TwisterPost
{
    public $user = '';

    public $twisterPath = '';
    public $rpcuser = 'user';
    public $rpcpassword = 'pwd';
    public $rpcport = 28332;

    public $lastError = null;

    protected $maxId = -1;

    // see updateSeenHashtags in https://github.com/miguelfreitas/twister-core/blob/master/src/twister.cpp
    protected $hashBreakChars = " \n\t.,:/?!";


    public function __construct($user)
    {
        $this->user = $user;
    }

    protected function getRpcCommand($method, $params = '')
    {
        $twisterd_path = $this->twisterPath . "twisterd";
        $twisterd_path .= " -rpcuser={$this->rpcuser}";
        $twisterd_path .= " -rpcpassword={$this->rpcpassword}";
        $twisterd_path .= " -rpcport={$this->rpcport}";
        $twisterd_path .= " $method";
        if (!empty($params)) {
            $twisterd_path .= " $params";
        }

        return $twisterd_path;
    }

    public function runRpcCommand($method, $params = '')
    {
        $cmd = $this->getRpcCommand($method, $params);

        $result = null;
        if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
            $this->exec_win($cmd, $result);
        } else {
            exec($cmd, $result);
        }
        $result = json_decode(implode(' ', $result));

        return $result;
    }

    public function updateMaxId()
    {
        $this->lastError = null;
        $this->maxId = 0;

        $result = $this->runRpcCommand('getposts', "1 '[{\"username\":\"{$this->user}\"}]'");
        if (isset($result->code) && $result->code < 0) {
            $this->maxId = -1;
            $this->lastError = $result;
            return false;
        }
        foreach($result as $item) {
            if (isset($item->userpost->n) && $item->userpost->n === $this->user) {
                $this->maxId = $item->userpost->k;
            }
        }

        $result = $this->runRpcCommand('dhtget', "{$this->user} status s");
        if (isset($result->code) && $result->code < 0) {
            $this->maxId = -1;
            $this->lastError = $result;
            return false;
        }
        foreach($result as $item) {
            if (isset($item->sig_user) && isset($item->p) && $item->sig_user === $this->user) {
                if (isset($item->p->seq)) {
                    $this->maxId = max($this->maxId, $item->p->seq);
                }
                if (isset($item->p->v) && isset($item->p->v->userpost) && isset($item->p->v->userpost->k)) {
                    $this->maxId = max($this->maxId, $item->p->v->userpost->k);
                }
            }
        }

        return true;
    }

    public function postMessage($text)
    {
        $this->lastError = null;

        if ($this->maxId < 0) {
            if (!$this->updateMaxId()) {
                return false;
            }
        }

        $k = $this->maxId + 1;
        $text = escapeshellarg($text);
        $result = $this->runRpcCommand('newpostmsg', "{$this->user} $k $text");
        if (!isset($result->userpost) || !isset($result->userpost->k) || ($result->userpost->k != $k)) {
            $this->maxId = -1;
            $this->lastError = $result;
            return false;
        }
        $this->maxId = $k;

        return true;
    }

    public function prettyPrint($title, $url = '', $tags = null, $maxLen = 140)
    {
        $title_len = mb_strlen($title);
        $url_len  = mb_strlen($url);

        if ($url_len === 0) {
            if ($title_len > $maxLen) {
                $text = rtrim(mb_substr($title, 0, $maxLen - 1), ' ') . '…';
            } else {
                $text = $title;
            }
        } else if ($title_len + 1 + $url_len > $maxLen) {
            $text = rtrim(mb_substr($title, 0, $maxLen - 2 - $url_len), ' ') . '… ' . $url;
        } else {
            $text = $title . ' ' . $url;
        }

        if (isset($tags)) {
            foreach ($tags as $tag) {
                $tagText = (string)$tag;
                $tagText = strtr($tagText, $this->hashBreakChars, str_repeat('_', strlen($this->hashBreakChars)));
                $tagText = trim($tagText, '_');
                $tagText = preg_replace('#(?<=_)_+#', '', $tagText);
                if (!empty($tagText)) {
                    $text .= ' #' . $tagText;
                }
            }
            if(mb_strlen($text) > $maxLen) {
                $text = mb_substr($text, 0, $maxLen + 1);
                $pos  = mb_strrpos($text, ' ');
                $text = mb_substr($text, 0, $pos);
            }
        }

        return $text;
    }

    protected function exec_win($cmd, &$output = null, &$return_var = null)
    {
        $tempfile = uniqid().'_php_exec.bat';

        $bat = "@echo off\r\n"
            . "@chcp 65001 >nul\r\n"
            . "@cd \"" . getcwd() . "\"\r\n"
            . "$cmd\r\n";

        file_put_contents($tempfile, $bat);
        exec("start /b cmd /c $tempfile", $output, $return_var);
        unlink($tempfile);

        return count($output) ? $output[count($output) - 1] : '';
    }
}
