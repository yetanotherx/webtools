<?php

include('/home/soxred93/public_html/common/header.php');
include('/home/soxred93/wikibot.classes.php');

$http = new http;

//Tell footer.php to output source
function outputSource() {
	echo "<li>
	<a href=\"http://github.com/soxred93/webtools/blob?path%5B%5D=bash&path%5B%5D=index.php&raw=true\">View source</a>
	</li>";
}

?>

<div id="content">
<table class="cont_table" style="width:100%;">
<tr>
<td class="cont_td" style="width:75%;">

<?php

echo "<h2 class=\"table\">Random quote from <a href=\"http://meta.wikimedia.org/wiki/IRC/Quotes\">m:BASH</a></h2>";

$text = $http->get('http://meta.wikimedia.org/w/index.php?title=IRC/Quotes&action=raw&ctype=text/css', false);

$text = explode('<pre><nowiki>', $text);
$text = explode('</nowiki></pre>', $text[1]);
$text = explode('%%', $text[0]);
$text = substr($text[0], 2);
$text = htmlspecialchars($text);
$text = trim($text);
$text = preg_replace('/\n/', '<br />', $text);

$quotes = explode("%<br />", $text);
if ( isset($_GET['id']) ) {
    echo $quotes[$_GET['id']];
}
elseif ( isset($_GET['showall']) ) {
	foreach( $quotes as $id => $quote ) {
		echo "<h3>Quote # $id</h3>";
    	echo $quote;
	}
}
else {
    $rand = rand(0, count($quotes));
    echo "<h3>Quote # $rand</h3>";
    echo $quotes[$rand];
}

echo "<br /><br /><div style=\"align:center;font-size:200%;\"><a href='http://toolserver.org/~soxred93/bash/'>Show another quote!</a></div>";

include('/home/soxred93/public_html/common/footer.php');

?>