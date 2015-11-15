<?php
if(isset($_GET['q']))
{
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
        $resultimg = $results->item($i);

        $metadata = array();
        $img = $resultimg->getElementsByTagName('img');
        $metadata['src'] = $img->item(0)->getAttribute('src');

        $url = $resultimg->getElementsByTagName('a');
        $metadata['url'] = $url->item(0)->getAttribute('href');

        $imgmeta = $resultimg->getElementsByTagName('div')->item(0);
        $metadata['title'] = $imgmeta->getElementsByTagName('h2')->item(0)->nodeValue;
        $metadata['domainname'] = $imgmeta->getElementsByTagName('p')->item(0)->nodeValue;
        $metadata['size'] = $imgmeta->getElementsByTagName('p')->item(1)->nodeValue;

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
    <header>
        <a href="./"><img id="logo" src="./CatCatGo.svg" /></a>
        <form method="get" action="./">
            <input type="search" name="q" id="q" class="textarea" value="<?php echo $_GET['q']; ?>" />
            <input type="submit" class="submit" value="" />
        </form>
        <aside>
            Results powered by <a href="https://www.qwant.com/">Qwant</a>
        </aside>
    </header>
    <section>
        <?php
        foreach($catImgUrls as $catimg)
        {
        ?>
        <article>
            <a href="<?php echo $catimg['url']; ?>"><img src="<?php echo $catimg['src']; ?>" /></a>
            <div class="metadata">
                <a href="<?php echo $catimg['url']; ?>"><h2><?php echo $catimg['title']; ?></h2></a>
                <?php echo $catimg['size']; ?> -- <a class="domainname" href="http://<?php echo parse_url($catimg['url'])['host']; ?>"><?php echo $catimg['domainname']; ?></a>
            </div>
        </article>
        <?php
        }
        ?>
    </section>
</body>
<?php
}
else
{
?>
<!DOCTYPE html>
<html lang="">
<head>
    <meta charset="utf-8">
    <title>CatCatGo</title>
    <link rel="stylesheet" href="./style.css">
</head>

<body>
    <div id="search-block">
        <div id="logo">
            <img src="./CatCatGo.svg" />
            <h1>CatCatGo</h1>
        </div>
        <form method="get" action="./">
            <input type="search" name="q" id="q" class="textarea" />
            <input type="submit" class="submit" value="" />
        </form>
    </div>
</body>
</html>

<?php
}
?>
