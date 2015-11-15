<?php
$urlQuery = urlencode('"cat '.$_GET['q'].'"');
$dbQuery = htmlentities($urlQuery);

$databaseConnected = false;
$queryInCache = false;

$useCache = true;
if(isset($_GET['cache']) && is_numeric($_GET['cache']) && $_GET['cache'] == 0)
    $useCache = false;

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

if($databaseConnected && $useCache)
{
    try
    {
        $request = $db->prepare('SELECT * FROM cachingTable WHERE queryText = :queryText AND lastQueried > :lastQueried');
        $request->execute(array('queryText'=>$dbQuery, 'lastQueried'=>date("Y-m-d", strtotime('-1 month'))));

        $nbResults = 0;
        while($result = $request->fetch())
        {
            ++$nbResults;
            $tmpurls = unserialize($result['urls']);
            foreach($tmpurls as $url)
                $catImgUrls[] = $url;
        }

        $request->closeCursor();
        $queryInCache = $nbResults > 0;

        //update lastQueried value
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
        $metadata = array();
        $img = $results->item($i)->getElementsByTagName('img');
        $metadata['src'] = $img->item(0)->getAttribute('src');

        $url = $results->item($i)->getElementsByTagName('a');
        $metadata['url'] = $url->item(0)->getAttribute('href');

        $catImgUrls[] = $metadata;
    }

    if($databaseConnected)
    {
        $request = $db->prepare('INSERT OR REPLACE INTO cachingTable(queryText, page, lastQueried, urls) VALUES(:queryText, :page, :lastQueried, :urls)');
        $request->execute(array('queryText'=>$dbQuery, 'page'=>0, 'lastQueried'=>date("Y-m-d"), 'urls'=>serialize($catImgUrls) ));

        //delete cached values that are more than one month old
        $request = $db->prepare('DELETE FROM cachingTable WHERE lastQueried <= :lastQueried');
        $request->execute(array('lastQueried'=>date("Y-m-d", strtotime('-1 month'))));
    }
}
?>
<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <title><?php echo $_GET['q']; ?> at CatCatGo</title>
    <link rel="stylesheet" href="./style.css">
</head>
<body>
    <?php
    foreach($catImgUrls as $catimg)
    {
        echo '<div class="resultimg">';
        echo '<a href="'.$catimg['url'].'"><img src="'.$catimg['src'].'" /></a>';
        echo '</div>';
    }
    ?>
</body>
