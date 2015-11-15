<?php
$urlQuery = urlencode('"cat '.$_GET['q'].'"');
$dbQuery = htmlentities($urlQuery);

$databaseConnected = false;
$queryInCache = false;

$catImgUrls = array();
try
{
    $db = new PDO("sqlite:cache.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $databaseConnected = true;
}
catch(PDOException $e)
{
    $databaseConnected = false;
}

if($databaseConnected)
{
    try
    {
        echo "loading from cache\n";
        $request = $db->prepare('SELECT * FROM cachingTable WHERE queryText = ?');
        $request->execute(array($dbQuery));

        while($result = $request->fetch())
        {
            $tmpurls = unserialize($result['urls']);
            foreach($tmpurls as $url)
                $catImgUrls[] = $url;
        }

        $request->closeCursor();
        $queryInCache = true;

        //TODO: update lastQueried value on cache query
    }
    catch(PDOException $e)
    {
        $queryInCache = false;
    }
}

if(!$databaseConnected || !$queryInCache)
{
    //the database could not be accessed, or did not contain this query in cache, therefore we must load to populate the cache
    $catImgUrls = array();
    $url = 'https://lite.qwant.com/?q='.$urlQuery.'&t=images';

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
            $catImgUrls[] = $urlimg->item($j)->getAttribute('href');
    }

    if($databaseConnected)
    {
        //TODO: populate the cache
        echo "populating the cache\n";
        $request = $db->prepare('INSERT OR REPLACE INTO cachingTable(queryText, page, lastQueried, urls) VALUES(:queryText, :page, :lastQueried, :urls)');
        $request->execute(array('queryText'=>$dbQuery, 'page'=>0, 'lastQueried'=>date("Y-m-d"), 'urls'=>serialize($catImgUrls) ));
    }
}

print_r($catImgUrls);

?>
