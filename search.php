<?php
$databaseConnected = false;
$queryInCache = false;
try
{
    $pdo = new PDO("sqlite:cache.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $databaseConnected = true;
}
catch(PDOException $e)
{
    $databaseConnected = false;
}

if($databaseConnected)
{
    //TODO: load from database
}

if(!$databaseConnected || !$queryInCache)
{
    //the database could not be accessed, or did not contain this query in cache, therefore we must load to populate the cache
    $url = 'https://lite.qwant.com/?q='.urlencode('"cat '.$_GET['q'].'"').'&t=images';

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTMLfile($url);

    $a = new DOMXPath($dom);
    $results = $a->query('//*[@class="resultimgs"]');

    $catimgs = array();

    for($i = 0; $i < $results->length; ++$i)
    {
        $urlimg = $results->item($i)->getElementsByTagName('a');
        for($j = 0; $j < $urlimg->length; ++$j)
            $catimgs[] = $urlimg->item($j)->getAttribute('href');
    }

    if($databaseConnected)
    {
        //TODO: populate the cache
    }
}

print_r($catimgs);

?>
