<?php

$time = microtime( 1 );//Calculate time in microseconds to calculate time taken to execut

//error_reporting(E_ALL);
ini_set("display_errors", 1);

include( '/home/soxred93/public_html/common/header.php' );
include( '/home/soxred93/stats.php' );
include( '/home/soxred93/wikibot.classes.php' );
$tool = 'AutoEdits';
$surl = "http://toolserver.org".$_SERVER['REQUEST_URI'];
if (isset($_GET['wiki']) && isset($_GET['lang']) && isset($_GET['name'])) {
	addStat( $tool, $surl, $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'] );//Stat checking
}
unset($tool, $surl);


//Debugging stuff
function pre( $array ) {
	echo "<pre>";
	print_r( $array );
	echo "</pre>";
}

//Output header
echo '<div id="content">
	<table class="cont_table" style="width:100%;">
	<tr>
	<td class="cont_td" style="width:75%;">
	<h2 class="table">Automated edits by user (clone of SQL\'s tool)</h2>';
	
//Access to the wiki
function getUrl($url) {
	$ch = curl_init();
    curl_setopt($ch,CURLOPT_MAXCONNECTS,100);
    curl_setopt($ch,CURLOPT_CLOSEPOLICY,CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_MAXREDIRS,10);
    curl_setopt($ch,CURLOPT_HEADER,0);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_TIMEOUT,30);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
    curl_setopt($ch,CURLOPT_HTTPGET,1);
    $data = curl_exec($ch);
    curl_close($ch);
    
    return $data;
}
	
//If there is a failure, do it pretty.
function toDie( $msg ) {
	echo $msg;
	include( '/home/soxred93/public_html/common/footer.php' );
	die();
}

if( !isset( $_GET['name'] ) ) {
	$msg = 'Welcome to X!\'s automated edits counter!<br /><br />
		<form action="http://toolserver.org/~soxred93/autoedits/index.php" method="get">
		Username: <input type="text" name="name" /><br />
		<input type="submit" />
		</form><br />';
	toDie( $msg );
}

$wpq = new wikipediaquery;
$oldname = $_GET['name'];
$name = ucfirst( ltrim( rtrim( str_replace( array('&#39;','%20'), array('\'',' '), $_GET['name'] ) ) ) );
$name = urldecode($name);
$name = str_replace('_', ' ', $name);
$name = str_replace('/', '', $name);

//Check if the user is an IP address
if( preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $name ) ) {
	toDie('Cannot be an IP address.');
}

$mysql = mysql_connect( 'enwiki-p.db.toolserver.org',$toolserver_username,$toolserver_password );
@mysql_select_db( 'enwiki_p', $mysql ) or toDie( "MySQL error, please report to X! using <a href=\"http://en.wikipedia.org/wiki/User:X!/Bugs\">the bug reporter.</a> Be sure to report the following SQL error when reporting:<br /><pre>".mysql_error()."</pre>" );


function getReplag() {
	$query = "SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(rc_timestamp) as replag FROM recentchanges ORDER BY rc_timestamp DESC LIMIT 1";
		$result = mysql_query( $query );
		if( !$result ) toDie( wfMsg('mysqlerror', mysql_error() ) );
		$row = mysql_fetch_assoc( $result );
		$replag = $row['replag'];
		
		$seconds = floor($replag);
		$text = formatReplag($seconds);
	    
	    return array($seconds,$text);
}

function formatReplag($secs) {
	$second = 1;
	$minute = $second * 60;
	$hour = $minute * 60;
	$day = $hour * 24;
	$week = $day * 7;
	
	$r = '';
	if ($secs > $week) {
		$r .= floor($secs/$week) . 'w';
		$secs %= $week;
	}
	if ($secs > $day) {
		$r .= floor($secs/$day) . 'd';
		$secs %= $day;
	}
	if ($secs > $hour) {
		$r .= floor($secs/$hour) . 'h';
		$secs %= $hour;
	}
	if ($secs > $minute) {
		$r .= floor($secs/$minute) . 'm';
		$secs %= $week;
	}
	if ($secs > $second) {
		$r .= floor(($secs/$second)/100) . 's';
	}
	
	return $r;
}

$replag = getReplag();
if ($replag[0] > 120) {
	echo '<h2 class="replag">'.wfMsg('highreplag', $replag[1]).'</h2>';
}
unset( $replag );

$edits = $wpq->contribcount($oldname);
$return = "<br />";
echo "<h2>Automated or script-assisted edits for $oldname</h2>";
if($edits >= 75001) {
    echo "<br />Script counts not available for users with more than 75K edits, sorry. <br />";
} else {
        $totalsc = 0;
        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE '%WP:TW%';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);
        
        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;
        
        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/WP:TW\">WP:TWINKLE</a>: $twedits$return";
        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment RLIKE '.*(AutoWikiBrowser|AWB).*';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);
        
        
        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;
        
        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/WP:AWB\">WP:AWB</a>: $twedits$return";
        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE '%WP:FRIENDLY%';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);
        
        
        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;
        
        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/WP:FRIENDLY\">WP:FRIENDLY</a>: $twedits$return";
        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE '%User:AWeenieMan/furme%';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);


        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;

        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/WP:FURME\">WP:FURME</a>: $twedits$return";
        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE '%Wikipedia:Tools/Navigation_popups%';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);


        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;

        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/Wikipedia:Tools/Navigation_popups\">Popups</a>: $twedits$return";
        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE '%User:MichaelBillington/MWT%';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);


        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;
        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/User:MichaelBillington/MWT\">MWT</a>: $twedits$return";

        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE '%[[WP:HG|HG]]%';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);


        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;
        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/WP:HG\">Huggle</a>: $twedits$return";


        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE '%WP:NPW%';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);


        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;

        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/WP:NPW\">NPWatcher</a>: $twedits$return";
        $query = "select COUNT(*) from revision where rev_user_text = '$name' AND rev_comment LIKE 'Reverted % edit% by % (%) to last revision by %';";
        $result = mysql_query($query);
        if(!$result) Die("ERROR: No result returned.");
        $row = mysql_fetch_assoc($result);


        $twedits = $row['COUNT(*)'];
        $totalsc = $totalsc +  $twedits;

        echo "Approximate edits using <a href=\"http://en.wikipedia.org/wiki/User:Gracenotes/amelvand.js\">amelvand</a>: $twedits$return";

        echo "<h4>Approximate total automated or assisted edits: $totalsc\n";
        echo "<h4>Total edits: $edits\n";
        echo "<h4>Auto/Total percentage: ".number_format((($edits ? $totalsc / $edits : 0 )*100), 2, '.', '')."%\n";
}

//Calculate time taken to execute
$exectime = number_format(microtime( 1 ) - $time, 2, '.', '');
echo "<br /><hr><span style=\"font-size:100%;\">Executed in $exectime seconds</span>";
echo "<br />Taken ". number_format((memory_get_usage() / (1024 * 1024)), 2, '.', '')." megabytes of memory to execute.";

//Output footer
include( '/home/soxred93/public_html/common/footer.php' );

?>
