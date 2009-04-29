<?php

$time = microtime( 1 );//Calculate time in microseconds to calculate time taken to execut

//error_reporting(E_ALL);
ini_set("display_errors", 1);

include( '/home/soxred93/public_html/common/header.php' );
include( '/home/soxred93/stats.php' );
include( '/home/soxred93/wikibot.classes.php' );
$tool = 'AfD';
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
	<h2 class="table">AfD Vote Calculator (clone of SQL\'s tool)</h2>';
	
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

if( !isset( $_GET['name'] ) ) {
	$msg = 'Welcome to the AFD !Vote Calculator!<br /><br />
		<form name="input" action="index.php" method="get">
		Username: <input type="text" name="name"><br />
		<input type="submit" value="Submit"></form><br />';
	toDie( $msg );
}

$wpq = new wikipediaquery;
$username = $_GET['name'];
$name = ucfirst( ltrim( rtrim( str_replace( array('&#39;','%20'), array('\'',' '), $_GET['name'] ) ) ) );
$name = urldecode($name);
$name = str_replace('_', ' ', $name);
$username_e = str_replace('/', '', $name);

//Check if the user is an IP address
if( preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $name ) ) {
	toDie('Cannot be an IP address.');
}

mysql_connect('enwiki-p.db.ts.wikimedia.org',$toolserver_username,$toolserver_password);
@mysql_select_db('enwiki_p') or print mysql_error();

$replag = getReplag();
if ($replag[0] > 120) {
	echo '<h2 class="replag">'.wfMsg('highreplag', $replag[1]).'</h2>';
}
unset( $replag );


$query = "SELECT user_editcount FROM user WHERE user_name = '$username_e';";
$result = mysql_query($query);
if(!$result) Die("ERROR: No result returned.");
$row = mysql_fetch_assoc($result);
$dedits = $row['user_editcount'];
if ($dedits == "") { 
    echo "<h2>Invalid user!</h2>\n"; 
} elseif ( $dedits > 100000 ) {
    echo "<h2>Unable to proceed!</h2>\nI'm sorry, but, in order to not consume more than my fair share of resources, I have limited this tool to users with less than 100,000 edits.\n";
} else {
    $query = "/* AFD */ /* Run against $username_e ( $dedits ) */ SELECT distinct page_title FROM page JOIN revision AS r on page_id = r.rev_page WHERE r.rev_user_text = '$username_e' AND page_is_redirect = 0 AND page_namespace = 4 AND page_title LIKE 'Articles_for_deletion/%' AND page_title NOT LIKE 'Articles_for_deletion/Log/%' AND (SELECT rev_user_text FROM revision where rev_page = page_id order by rev_id ASC limit 1) = '$username_e';  ";
    $result = mysql_query($query);
    if(!$result) Die("ERROR: No result returned.");
    echo "<h2>AFD's created by $username:</h2>\n";
    echo '<ul><li><span style="color:red">These statistics are based on the <strong>present</strong> status of the article title the program guessed at, and may be inaccurate.</span></li><li>The link to the AFD is there for a reason. Please see the AFD for context</li><li>Just because an article exists now, does not mean it was kept at AFD.</li></ul><br /><ol>';
    $ndel = 0;
    $nkept = 0;
    $nredir = 0;
    $nadel = 0;
    $nrdel = 0;
    echo "\n<!-- $query -->\n";
    while ($row = mysql_fetch_assoc($result)) {
        $pagename = $row['page_title'];
        $normPage = preg_replace('/_/', ' ', $pagename);
        $normPage = preg_replace('/Articles for deletion\//', '', $normPage);
        $probablePage = preg_replace('/ \(.* nomination\)/i', "", $normPage);
        $probablePage = ltrim(ltrim($probablePage));
        $ePP = preg_replace('/ /', '_', $probablePage);
        $ePP = mysql_real_escape_string($ePP);
        $q2 = "SELECT page_title, page_is_redirect FROM page WHERE page_namespace = 0 AND page_title = '$ePP';";
        $q3 = "SELECT log_id FROM logging WHERE log_namespace = 0 AND log_title = '$ePP' AND log_type = 'delete' AND log_comment LIKE '%Articles for deletion%';";
        $r2 = mysql_query($q2);
        $r3 = mysql_query($q3);
        if(!$r2) Die("ERROR: No result returned.");
        if(!$r2) Die("ERROR: No result returned.");
        $res = mysql_fetch_assoc($r2);
        $res2 = mysql_fetch_assoc($r3);
        $dafd = FALSE;
        if( isset( $res2['log_id'] ) ) {
            $dafd = TRUE;
        }
        if( !isset( $res['page_title'] ) && $dafd ) { 
            $deleted = '(<span style="color:red">Deleted (AFD)</span>)'; 
            $nadel++;
        } elseif( !isset( $res['page_title'] ) && !$dafd ) { 
            $deleted = '(<span style="color:red">Deleted</span>)'; 
            $ndel++;
        } elseif( isset( $res['page_title'] ) && $dafd ) { 
            $deleted = '(<span style="color:red">Deleted (AFD) - Recreated</span>)'; 
            $nrdel++;
        } elseif ($res['page_is_redirect'] == 1 ){
            $deleted = '(<span style="color:grey">Redirect</span>)'; 
            $nredir++;
        } else {
            $deleted = '(<span style="color:green">Kept</span>)'; 
            $nkept++;
        }
        echo "<li>$deleted <a href=\"http:/" . "/en.wikipedia.org/wiki/Wikipedia:$pagename\">$probablePage</a></li>\n";
    }
    echo "</ol>\n<br />\n";
    $total = $nadel + $nkept + $nredir + $ndel + $nrdel;
    $kp = $nkept / $total;
    $kp = round($kp * 100, 2);
    $dp = $nadel / $total;
    $dp = round($dp * 100, 2);
    $drp = $nrdel / $total;
    $drp = round($drp * 100, 2);
    $rp = $nredir / $total;
    $rp = round($rp * 100, 2);
    $out = "There appear to be $total AFD's started by $username. \n<ul><li>$nkept ($kp%) were probably kept</li>\n<li>$nadel ($dp%) were probably deleted</li>\n<li>$ndel were probably deleted via some other method</li>\n<li>$nrdel ($drp%) were deleted at an AFD at some point, and were re-created later.</li>\n<li>$nredir ($rp%) are now redirects</li>\n</ul><br />\n";
    echo $out;
}
mysql_close();


//Calculate time taken to execute
$exectime = number_format(microtime( 1 ) - $time, 2, '.', '');
echo "<br /><hr><span style=\"font-size:100%;\">Executed in $exectime seconds</span>";
echo "<br />Taken ". number_format((memory_get_usage() / (1024 * 1024)), 2, '.', '')." megabytes of memory to execute.";

//Output footer
include( '/home/soxred93/public_html/common/footer.php' );

?>