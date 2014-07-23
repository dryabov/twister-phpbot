<?php

mb_internal_encoding('UTF-8');
setlocale(LC_CTYPE, 'en_US.UTF-8');

// Load RSS feed
require_once 'rssreader.php';
$feed_uri = 'http://habrahabr.ru/rss/hubs/new/';
$rss = getRssFeed($feed_uri);
if (!$rss) die('Cannot read rss or it is up to date');

// Initialise TwisterPost
require_once 'twisterpost.php';
$twister = new TwisterPost('habr_ru');

// Initialise RSS database
require_once 'rssdb.php';
$db = new RSSDb('habr_ru.dat');

foreach ($rss->channel->item as $item) {
    $link  = (string)$item->link;
    $title = (string)$item->title;
    // Note: habrahabr.ru does both special chars encoding and CDATA wrap
    $title = htmlspecialchars_decode($title);

    // get post id from link
    $id = (int)preg_replace('#[^\d]#', '', $link);

    if ($db->isPublished($id)) {
        continue;
    }

    // shorten URL [-6 chars do matter]
    $link = str_replace('habrahabr.ru', 'habr.ru', $link);
    $link = rtrim($link, '/');

    $msg = $twister->prettyPrint($title, $link, isset($item->category) ? $item->category : null);

    if ($twister->postMessage($msg)) {
        $db->setPublished($id);
    }
}
