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
        $request = $db->prepare('SELECT * FROM cachingTable WHERE queryText = :queryText AND lastQueried > :lastQueried');
        $request->execute(array('queryText'=>$dbQuery, 'lastQueried'=>date("Y-m-d", strtotime('-1 month'))));

        $nbResults = 0;
        while($result = $request->fetch())
        {
            ++$nbResults;
            $tmpurls = unserialize($result['urls']);
            foreach($tmpurls as $url)
                $catImgUrls[] = $url;

            echo "from cache:\n";
            print_r($tmpurls);
        }

        $request->closeCursor();
        $queryInCache = $nbResults > 0;

        //TODO: update lastQueried value on cache query
        $request = $db->prepare('UPDATE cachingTable SET lastQueried = :lastQueried WHERE queryText = :queryText');
        $request->execute(array('queryText'=>$dbQuery, 'lastQueried'=>date("Y-m-d")));
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
        echo "populating the cache\n";
        $request = $db->prepare('INSERT OR REPLACE INTO cachingTable(queryText, page, lastQueried, urls) VALUES(:queryText, :page, :lastQueried, :urls)');
        $request->execute(array('queryText'=>$dbQuery, 'page'=>0, 'lastQueried'=>date("Y-m-d"), 'urls'=>serialize($catImgUrls) ));

        //delete cached values that are more than one month old
        $request = $db->prepare('DELETE FROM cachingTable WHERE lastQueried <= :lastQueried');
        $request->execute(array('lastQueried'=>date("Y-m-d", strtotime('-1 month'))));
    }
}

print_r($catImgUrls);

?>
