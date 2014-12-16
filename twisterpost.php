<?php

class TwisterPost
{
    public $user = '';

    public $rpcuser = 'user';
    public $rpcpassword = 'pwd';
    public $rpchost = '127.0.0.1';
    public $rpcport = 28332;

    public $timeout = 60; // timeout (in seconds)

    public $lastError = null;

    protected $maxId = false;

    // see updateSeenHashtags in https://github.com/miguelfreitas/twister-core/blob/master/src/twister.cpp
    protected $hashBreakChars = " \n\t.,:/?!";

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function runRpcCommand($method, $params = array())
    {
        $request = new stdClass;
        $request->jsonrpc = '2.0';
        $request->id = uniqid('', true);
        $request->method = $method;
        $request->params = $params;

        if (defined('JSON_UNESCAPED_UNICODE')) { // PHP 5.4+:
            $request_json = json_encode($request, JSON_UNESCAPED_UNICODE);
        } else { // PHP 5.3:
            $request_json = json_encode($request);
            $request_json = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function($matches){return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UTF-16');}, $request_json);
        }

        $ch = curl_init();
        $url = "http://{$this->rpchost}:{$this->rpcport}/";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Basic ' . base64_encode($this->rpcuser . ':' . $this->rpcpassword),
                'Content-Type: application/json; charset=utf-8'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_json);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);

        $response_json = curl_exec($ch);

        if (curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            curl_close($ch);
            return false;
        }

        curl_close( $ch );

        $response = json_decode($response_json);

        if (!is_object( $response ) ||
            (isset( $response->error ) && !is_null($response->error)) ||
            !isset( $response->result ) || !isset( $response->id ) || $response->id !== $request->id)
        {
            return false;
        }

        return $response->result;
    }

    public function updateMaxId()
    {
        $this->lastError = null;
        $this->maxId = -1;

        $result = $this->runRpcCommand('getposts', array(1, array(array('username' => $this->user))));
        if ($result === false || (isset($result->code) && $result->code < 0)) {
            $this->maxId = false;
            $this->lastError = $result;
            return false;
        }
        foreach($result as $item) {
            if (isset($item->userpost->n) && $item->userpost->n === $this->user) {
                $this->maxId = $item->userpost->k;
            }
        }

        $result = $this->runRpcCommand('dhtget', array($this->user, 'status', 's'));
        if ($result === false || (isset($result->code) && $result->code < 0)) {
            $this->maxId = false;
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

        if ($this->maxId === false) {
            if (!$this->updateMaxId()) {
                return false;
            }
        }

        $k = $this->maxId + 1;
        $result = $this->runRpcCommand('newpostmsg', array($this->user, $k, $text));
        if (!isset($result->userpost) || !isset($result->userpost->k) || ($result->userpost->k != $k)) {
            $this->maxId = false;
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
}
