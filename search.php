<?php
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

print_r($catimgs);

?>
