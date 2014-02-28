<?php

mb_internal_encoding('UTF-8');

// Load RSS feed
$feed_uri = 'http://habrahabr.ru/rss/hubs/new/';
$rss = simplexml_load_file($feed_uri);
if (!$rss) die('Cannot read rss');

// Initialise TwisterPost
require_once 'twisterpost.php';
$twister = new TwisterPost('habr_ru');

// Set path to twisterd directory
$twister->twisterPath = '.' . DIRECTORY_SEPARATOR . 'twister-win32-bundle' . DIRECTORY_SEPARATOR;

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

    // short URL [-6 chars do matter]
    $link = str_replace('habrahabr.ru', 'habr.ru', $link);
    $link = rtrim($link, '/');
    $msg = $twister->prettyPrint($title, $link, isset($item->category) ? $item->category : null);

    if ($twister->postMessage($msg)) {
        $db->setPublished($id);
    }
}
