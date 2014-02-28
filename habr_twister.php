<?php

set_time_limit(0);
mb_internal_encoding('UTF-8');
chdir(__DIR__);

function debugLog($msg)
{
    file_put_contents('./logrss.txt', "$msg\n", FILE_APPEND);
}

/**/debugLog("\n\n\n=== " . date("Y-m-d H:i:s") . "===");

// Load RSS feed
$feed_uri = 'http://habrahabr.ru/rss/hubs/new/';
$rss = simplexml_load_file($feed_uri);
if (!$rss) die('Cannot read rss');

// Initialise TwisterPost
require_once 'twisterpost.php';
$twister = new TwisterPost('habr_ru');

// Set path to twisterd directory
$twister->twisterPath = '.' . DIRECTORY_SEPARATOR . 'twister-win32-bundle' . DIRECTORY_SEPARATOR;
// Note: we use custom rpc port for twister
$twister->rpcport = 40001;

// Initialise RSS database
require_once 'habrrssdb.php';
$db = new HabrRSSDb('habr_db.dat');

foreach ($rss->channel->item as $item) {
    $link  = (string)$item->link;
    $title = (string)$item->title;

    // get post id from link
    $id = (int)preg_replace('#[^\d]#', '', $link);

    if ($db->isPublished($id)) {
        continue;
    }

    $msg = $twister->prettyPrint($title, $link, isset($item->category) ? $item->category : null);

/**/debugLog($msg);
    if ($twister->postMessage($msg)) {
        $db->setPublished($id);
    }
}

/**/debugLog('=== Done ===');