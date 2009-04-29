<?php

$time = microtime( 1 );//Calculate time in microseconds to calculate time taken to execute

define( 'COUNTERVERSION', '4.0.0.49' );
define( 'NOSERVER', 'false' );

//error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("memory_limit", '64M');

include( '/home/soxred93/public_html/common/header.php' );
include( '/home/soxred93/stats.php' );
require( '/home/soxred93/public_html/count/i18n.php' );
$tool = 'EditCounter';
$surl = "http://toolserver.org".$_SERVER['REQUEST_URI'];
if (isset($_GET['wiki']) && isset($_GET['lang']) && isset($_GET['name'])) {
	addStat( $tool, $surl, $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'] );//Stat checking
}
unset($tool, $surl);

//Output header
echo '<div id="content">
	<table class="cont_table" style="width:100%;">
	<tr>
	<td class="cont_td" style="width:75%;">
	<h2 class="table">'.wfMsg('title', COUNTERVERSION).'</h2>';


//Debugging stuff
function pre( $array ) {
	echo "<pre>";
	print_r( $array );
	echo "</pre>";
}
	
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


//Tell footer.php to output source
function outputSource() {
	echo "<li>
	<a href=\"https://svn.cluenet.org/viewvc/soxred93/trunk/bots/Tools/count/index.php?view=markup\">".wfMsg('viewsource')."</a>
	</li>";
}


//Get array of namespaces
function getNamespaces() {
	global $http, $name, $lang, $wiki, $oldlang, $oldwiki;

	$namespaces = getUrl( 'http://'.$oldlang.'.'.$oldwiki.'.org/w/api.php?action=query&meta=siteinfo&siprop=namespaces&format=php', false );
	$namespaces = unserialize( $namespaces );
	$namespaces = $namespaces['query']['namespaces'];
	if( !$namespaces[0] ) { toDie( wfMsg('nowiki', $oldlang.$oldwiki) ); };


	unset( $namespaces[-2] );
	unset( $namespaces[-1] );

	$namespaces[0]['*'] = wfMsg('mainspace');
	
	$namespaces1 = array();
	$namespaces2 = array();
	foreach ($namespaces as $value => $ns) {
		$namespaces[$ns['*']] = $value;
		$namespaces2[$value] = $ns['*'];
	}

	return array($namespaces, $namespaces2);
}

if( NOSERVER == 'true' ) {
	toDie( wfMsg( 'nosql' ) );
}


if( !isset( $_GET['name'] ) ) {
	$msg = wfMsg('welcome').'<br /><br />
		<form action="http://toolserver.org/~soxred93/count/index.php" method="get">
		'.wfMsg('username').': <input type="text" name="name" /><br />
		'.wfMsg('wiki').': <input type="text" value="';
		if (isset($_GET['uselang'])) {
			$msg .= $_GET['uselang'];
		}
		else {
			$msg .= 'en';
		}
		$msg .= '" name="lang" size="9" />.<input type="text" value="wikipedia" size="10" name="wiki" />.org<br />
		<input type="submit" value="'.wfMsg('submit').'" />
		</form><br /><hr />';
		
	$msg .= wfMsg('otherlang');
	$msg .= "<ul>";
	foreach( $messages as $lang => $msgs ) {
		$msg .= "<li><a href=\"http://toolserver.org/~soxred93/count/index.php?uselang=$lang\">".$canonical[$lang]." ";
		if( count( $messages[$lang] ) < count( $messages['en'] ) ) {
			$msg .= wfMsg('incomplete', count( $messages['en'] ) - count( $messages[$lang] ) );
		}
		$msg .= "</a></li>\n";
		//foreach( $messages['en'] as $code => $msg ) {
		//	if ( !isset($messages[$lang][$code]) ) {
		//		echo $code;
		//	}
		//}
	}
	$msg .= "</ul>";
	$msg .= "<br />".wfMsg('helptrans' );
	toDie( $msg );
}

$oldname = ucfirst( ltrim( rtrim( str_replace( array('&#39;','%20'), array('\'',' '), $_GET['name'] ) ) ) );
$oldname = urldecode($oldname);
$oldname = str_replace('_', ' ', $oldname);
$oldname = str_replace('/', '', $oldname);
$oldwiki = $_GET['wiki'];
$oldlang = $_GET['lang'];
$name = mysql_escape_string( $oldname );
$lang = mysql_escape_string( $oldlang );
$wiki = mysql_escape_string( $oldwiki );
$lang = str_replace('/', '', $lang);
$wiki = str_replace('/', '', $wiki);
$oldlang = str_replace('/', '', $oldlang);
$oldwiki = str_replace('/', '', $oldwiki);
$namespaces1 = getNamespaces();
$namespaces = $namespaces1[0];
$namespaces2 = $namespaces1[1];

//Check if the user is an IP address
if( preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $oldname ) ) {
	define( 'ISIPADDRESS', true );
}
else {
	define( 'ISIPADDRESS', false );
}

function PLURAL( $num, $str ) {
	if( $num == 1 ) {
		$str = str_replace('$1', '', $str);
	}
	else {
		$str = str_replace('$1', 's', $str);
	}
	return $str;
}


//Format numbers based on language
function numberformat( $num ) {
	global $lang;
	if ( $lang == 'www' || $lang == 'meta' || $lang == 'commons' || $lang == 'en' || $lang == 'incubator' ) {
		return number_format($num);
	}
	elseif ( $lang == 'fr' ) {
		return number_format($num, 0, ',', ' ');
	}
	else {
		return number_format($num);
	}
}

//Connect to database (kudos to SQL for the code)
require_once( '/home/soxred93/database.inc' );

if( $oldwiki.$oldlang != 'wikipediaen' ) {
	$wiki = str_replace( 'wikipedia', 'wiki', $wiki );
	//Support for non-standerd database names
	if ( $lang == 'www' && $wiki == 'mediawiki' ) {
		$lang = 'mediawiki';
		$wiki = 'wiki';
	}
	elseif ( $lang == 'meta' && $wiki == 'wikimedia' ) {
		$lang = 'meta';
		$wiki = 'wiki';
	}
	elseif ( $lang == 'commons' && $wiki == 'wikimedia' ) {
		$lang = 'commons';
		$wiki = 'wiki';
	}
	elseif ( $lang == 'www' && $wiki == 'wikimediafoundation' ) {
		$lang = 'foundation';
		$wiki = 'wiki';
	}
	elseif ( $lang == 'incubator' && $wiki == 'wikimedia' ) {
		$lang = 'incubator';
		$wiki = 'wiki';
	}
	else {
	}
	$dbh = $lang . $wiki . "-p";
	$dbni = $lang . $wiki . "_p";
	mysql_connect( 'sql',$toolserver_username,$toolserver_password );
	@mysql_select_db( 'toolserver' ) or toDie( wfMsg('mysqlerror', mysql_error() ) );
	$query = "select * from wiki where dbname = '$dbni';";
	$result = mysql_query( $query );
	$row = mysql_fetch_assoc( $result );
	$dbnm = $row['dbname'];
	$dbhs = $row['server'];
	$dbn = $dbnm;
	$dbh = "sql-s" . $dbhs;
	mysql_close();
	mysql_connect( "$dbh",$toolserver_username,$toolserver_password );
	@mysql_select_db( $dbn ) or print mysql_error();
	unset($dbh, $dbni, $toolserver_username, $toolserver_password, $query, $result, $row, $dbnm, $dbhs, $dbn, $dbh);
}
else {
	mysql_connect( "sql-s1",$toolserver_username,$toolserver_password );
	@mysql_select_db( "enwiki_p" ) or print mysql_error();
}
//Done

$colorcodes = array(
	'admin' => '#FF5555',
	0 => '#FF5555',
	1 => '#55FF55',
	2 => '#FFFF55',
	3 => '#FF55FF',
	4 => '#5555FF',
	5 => '#55FFFF',
	6 => '#C00000',
	7 => '#0000C0',
	8 => '#080',
	9 => '#00C0C0',
	10 => '#FFAFAF',
	11 => '#808080',
	12 => '#00C000',
	13 => '#404040',
	14 => '#C0C000',
	15 => '#C000C0',
	100 => '#75A3D1',
	101 => '#A679D2',
	102 => '#080',
	103 => '#080',
	104 => '#080',
	105 => '#080'
);


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
		$r .= floor($secs/$week) . wfMsg( 'w' );
		$secs %= $week;
	}
	if ($secs > $day) {
		$r .= floor($secs/$day) . wfMsg( 'd' );
		$secs %= $day;
	}
	if ($secs > $hour) {
		$r .= floor($secs/$hour) . wfMsg( 'h' );
		$secs %= $hour;
	}
	if ($secs > $minute) {
		$r .= floor($secs/$minute) . wfMsg( 'm' );
		$secs %= $week;
	}
	if ($secs > $second) {
		$r .= floor(($secs/$second)/100) . wfMsg( 's' );
	}
	
	return $r;
}


function getColor($month_namespace_totals, $max_width, $total, $month) {
	//$nscount = $month_namespace_totals[$month];
	$pcts = array();
	foreach( $month_namespace_totals as $ns => $month2) {
		$pcts[$ns] = 0;
		foreach( $month2 as $m => $k ) {
			if( $m == $month ) {
				$width = ceil((500 * $k) / $max_width);
				$pcts[$ns] = $width;
			}
		}
	}
	return ($pcts);
}



function getEditCounts() {
	global $name, $namespaces, $http, $oldwiki, $oldlang, $oldname;

	//Get total edits
	if( ISIPADDRESS == false ) {//IP addresses don't have a field in the user table, so IPs must be done the old way.
		$query = "SELECT user_editcount, user_id FROM user WHERE user_name = '".$name."';";
		$result = mysql_query( $query );
		if( !$result ) toDie( wfMsg('mysqlerror', mysql_error() ) );
		$row = mysql_fetch_assoc( $result );
		$edit_count_total = $row['user_editcount'];
		$uid = $row['user_id'];
		if( $uid == 0 ) {
			toDie( wfMsg('nosuchuser', $name ) );
		}
	}
	unset( $row, $query, $result );
	

	if( ISIPADDRESS == false ) {//IPs don't have user groups!
		/*$groups = getUrl( 'http://'.$oldlang.'.'.$oldwiki.'.org/w/api.php?action=query&list=users&ususers='.urlencode( $oldname ).'&usprop=groups&format=php', false );
		$groups = unserialize( $groups );
		$groups = $groups['query']['users']['0']['groups'];*/
		
		$query = "select * from user_groups where ug_user = '".$uid."';";
		$result = mysql_query( $query );
		if( !$result ) toDie( wfMsg('mysqlerror', mysql_error() ) );
		$groups = array();
		while( $row = mysql_fetch_assoc( $result ) ) {
			$groups[] = $row['ug_group'];
		}
	}


	if( ISIPADDRESS == false ) {//IPs don't have a user ID
		$query = "SELECT /* SLOW_OK */ rev_timestamp,page_title,page_namespace,rev_comment FROM revision JOIN page ON page_id = rev_page WHERE rev_user = '".$uid."' ORDER BY rev_timestamp ASC;";
	}
	else {
		$query = "SELECT /* SLOW_OK */ rev_timestamp,page_title,page_namespace,rev_comment FROM revision JOIN page ON page_id = rev_page WHERE rev_user_text = '".$name."' ORDER BY rev_timestamp ASC;";
	}
	
	$result = mysql_query( $query );
	if( !$result ) toDie( wfMsg('mysqlerror', mysql_error() ) );
	unset($query);


	$edit_count_live = mysql_num_rows( $result );
	if( ISIPADDRESS == true ) { $edit_count_total = $edit_count_live; }//$edit_count_total was not set above if the user is an IP
	
	//Initialize some arrays
	$months = array();
	$namespace_totals = array();
	$namespace_unique_articles = array();
	$unique_articles = array();
	$namespace_ids = array();
	///$edit_summaries = array();
	//$month_namespace_totals = array();
	
	while ( $row = mysql_fetch_assoc( $result ) ) {
		//if( !isset($edit_summaries['yes'][substr( $row['rev_timestamp'], 0, 6 )])) {
			//$edit_summaries['yes'][substr( $row['rev_timestamp'], 0, 6 )] = 0;
			//$edit_summaries['no'][substr( $row['rev_timestamp'], 0, 6 )] = 0;
		//}
		
		$months[substr( $row['rev_timestamp'], 0, 6 )]++;
        
        //To be used later, for generating the months
        if( !isset( $first_edit ) ) {
        	//Extract segments from the timestamp (thanks, MetaWiki)
			$timestamp = $row['rev_timestamp'];
			$year = substr( $timestamp, 0, 4 );
     	   $month = substr( $timestamp, 4, 2 );
    	    $day = substr( $timestamp, 6, 2 );
    	    $hour = substr( $timestamp, 8, 2 );
    	    $minute = substr( $timestamp, 10, 2 );
    	    $second = substr( $timestamp, 12, 2 );
        	$first_edit = date( 'M d, Y H:i:s', mktime( $hour, $minute, $second, $month, $day, $year ) );
            $first_month = strtotime( "$year-$month-$day" );
		}
		
		$namespace_totals[$row['page_namespace']]++;
		//$namespace_ids[$nsname] = $row['page_namespace'];
		$month_namespace_totals[$row['page_namespace']][substr( $row['rev_timestamp'], 0, 6 )]++;
		
		
		//Disabled for users with over 45000 edits, PHP craps out with more than this.
		if( $edit_count_live < '45000' ) {
			$namespace_unique_articles[$row['page_namespace']][$row['page_title']]++;
		
			$unique_articles[$row['page_title']]++;
		}
	}//That was a long while statement.public_html

	$last_month = strtotime( date( 'Ymd' ) );
	
	$returnArray = array(
		$edit_count_total, //$temp[0];
		$edit_count_live, //$temp[1];
		$months, //$temp[2];
		$groups, //$temp[3];
		$uid, //$temp[4];
		$first_edit, //$temp[5];
		$first_month, //$temp[6];
		$last_month, //$temp[7];
		$namespace_totals, //$temp[9];
		$namespace_unique_articles, //$temp[9];
		$unique_articles, //$temp[10];
		$namespace_ids, //$temp[11];
		$month_namespace_totals, //$temp[12]
		//$edit_summaries //$temp[13]
	);
	
	unset($result, $row, $edit_count_total, $edit_count_live, $months, $groups, $uid, $first_edit, $first_month, $last_month, $namespace_totals, $namespace_unique_articles, $unique_articles, $namespace_ids, $month_namespace_totals);
		
	return $returnArray;
}


$temp = getEditCounts();
$replag = getReplag();
$total = $temp[0];
$live = $temp[1];
$months = $temp[2];
$groups = $temp[3];
$uid = $temp[4];
$first_edit = $temp[5];
$first_month = $temp[6];
$last_month = $temp[7];
$namespace_totals = $temp[8];
$namespace_unique_articles = $temp[9];
$unique_articles = $temp[10];
$namespace_ids = $temp[11];
$month_namespace_totals = $temp[12];
//$edit_summaries = $temp[13];
$deleted = $total - $live;

unset( $temp );//Just neatness

if ($replag[0] > 120) {
	echo '<h2 class="replag">'.wfMsg('highreplag', $replag[1]).'</h2>';
}
unset( $replag );

//Output general stats
echo '<h2>'.wfMsg('generalinfo').'</h2>';

echo wfMsg('username').": <a href=\"http://$oldlang.$oldwiki.org/wiki/User:$oldname\">$oldname</a><br />";

if( isset( $groups[0] ) ) {//Because IPs and other new users don't have groups
	echo wfMsg('usergroups').": ";
	echo implode( ', ', $groups );//Why is this in 3 lines?
	echo "<br />";
}
unset( $groups );

echo wfMsg('firstedit').": $first_edit<br />";
unset( $first_edit );

if( $live < '45000' ) {//Disabled for users with over 45000 edits, PHP craps out with more than this.
	echo wfMsg('unique').": ".numberformat(count( $unique_articles ))."<br />";
	echo wfMsg('avgedits').": ";
	echo sprintf( '%.2f', $total ? $total / count( $unique_articles ) : 0 );
	echo "<br />";
}

if( $deleted > 0 ) {//Users who have edited before 2006 (when the editcount field was added) have negative deleted edits
	echo wfMsg('total').": ".numberformat($total)."<br />";
	echo wfMsg('deleted').": ".numberformat($deleted)."<br />";
}

echo "<b>".wfMsg('live').": ".numberformat($live)."</b><br />";//The end all answer!!!

if( $live == 0 ) {
	toDie( "<br /><br />User does not have any contribs.\n");
}


echo '<h2>'.wfMsg('nstotal').'</h2>';//Maybe this table could be prettier
echo "<table>";
ksort($namespace_totals);
$sercolors = array();
$sernames = array();
$serdata = array();
foreach ( $namespace_totals as $key => $value ) {
	if ($value == 0) {
		continue;
	}

	$key2 = $namespaces2[$key];
	//echo "$key => $key2 <br />";

	echo "<tr><td style=\"border: 1px solid #000;background:".$colorcodes[$key].";\" />$key2</td><td>$value</td><td>";
	$pct = sprintf( '%.2f', $value ? ( $value / $live ) * 100 : 0 );
	if( round($pct) > 0 ) {
		$serdata[] = $pct;
		$sernames[] = $key2;
		$sercolors[] = $colorcodes[$key];
	}
	echo "$pct%</td></tr>\n";
}
echo "</table>\n\n";
unset( $namespace_totals );

echo "<img src=\"";
echo "http://toolserver.org/~soxred93/count/graph.php?pcts=".urlencode(serialize($serdata))."&nsnames=".urlencode(serialize($sernames))."&colors=".urlencode(serialize($sercolors));
echo "\" alt=\"Graph\" />";

$months2 = array();
$last_monthkey = null;

for ( $date=$first_month; $date<=$last_month; $date+=10*24*60*60 ) {
	$monthkey = date( 'Ym', $date );
	if( $monthkey != $last_monthkey ) {
		array_push( $months2, $monthkey );
		$last_monthkey = $monthkey;
	}
}

$monthkey = date( 'Ym', $last_month );
if( $monthkey != $last_monthkey ) {
	array_push( $months2, $monthkey );
	$last_monthkey = $monthkey;
}
unset( $monthkey, $last_monthkey );

echo '<h2>'.wfMsg('monthcounts').'</h2>';
echo "<table class=months>\n";
$max_width = max( $months );
$widths = array();
$ms = array();
$nummon = 0;
foreach ( $months2 as $key ) {
	$nummon++;
	$total = $months[$key];
	if( !$months[$key] ) {
		$months[$key] = 0;
	}
	$ms[$key] = $months[$key];
	print "<tr><td class=date>".substr( $key, 0, 4 )."/".substr( $key, 4, 2 )."</td><td>".$months[$key]."</td>";
	$colorcount = getColor( $month_namespace_totals, $max_width, $total, $key );
	ksort($colorcount);
	//pre($colorcount);
	echo "<td>";
	if ($total != 0) {
		echo "<div class='outer_bar'>";
	}
	//print "<td><div class=green style='width:50px'></div>";
	//foreach( $col as $ns ) {
		//$widths[$key][$ns] = $colorcount[$ns];
	//}
	foreach($colorcount as $ns => $count) {
		$widths[$ns][] = $count;
		//$edit_summaries['no'][$key] = $count;
		//$edit_summaries['yes'][$key] = $count;
		$msg = '<div class=\'bar\' style=\'border-left:'.$count."px solid ".$colorcodes[$ns].'\'>';
		echo $msg;
	}
	echo str_repeat("</div>", count($colorcount));
	if ($total != 0) {
		echo "</div>";
	}
	echo "</td>\n";
	//print "<td><div class=green style='width:".bcdiv(bcmul(500, $total, 0), $max_width, 0)."px'></div>\n";
}
echo "</table>";
/*$ms2 = array();
foreach($ms as $m => $s ) {
	if( $s != 0 ) {
		$ms2[$m] = $s;
	}
	else {
		$ms2[$m] = '0';
	}
}*/
//echo serialize($ms);
//pre($edit_summaries);
//file_put_contents('./widths/'.urlencode($oldlang).'||'.urlencode($oldwiki).'||'.urlencode($oldname), serialize($widths));
//file_put_contents('./months/'.urlencode($oldlang).'||'.urlencode($oldwiki).'||'.urlencode($oldname), serialize($ms));
//file_put_contents('./summaries/'.urlencode($oldlang).'||'.urlencode($oldwiki).'||'.urlencode($oldname), serialize($edit_summaries));
//echo "<img src='http://toolserver.org/~soxred93/count/graph.php?user=$oldname&wiki=$oldwiki&lang=$oldlang&nummon=$nummon' />";
unset( $max_width, $months2, $months, $colorcount, $month_namespace_totals, $edit_summaries );


//Yet again, kudos to SQL for this snippit of code.
echo '<h2>'.wfMsg('logs').'</h2>';
$query = "SELECT log_action,COUNT(*) FROM user AS A JOIN logging AS B ON log_user = user_id WHERE user_name = '$name' GROUP BY log_action;";
$result = mysql_query( $query );
if( !$result ) toDie( wfMsg('mysqlerror', mysql_error() ) );

while ( $row = mysql_fetch_assoc( $result ) ) {
    switch ( $row['log_action'] ) {
        case "block":
              $blocks = $row['COUNT(*)'];
               echo wfMsg('ub').": $blocks<br />";
            break;
        case "rights":
               $blocks = $row['COUNT(*)'];
            echo wfMsg('urm').": $blocks<br />";
            break;
        case "create2":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('ac').": $blocks<br />";
            break;
        case "delete":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('pd').": $blocks<br />";
            break;
        case "patrol":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('ppa').": $blocks<br />";
            break;
        case "protect":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('ppr').": $blocks<br />";
            break;
        case "restore":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('pr').": $blocks<br />";
            break;
        case "unblock":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('uu').": $blocks<br />";
            break;
        case "unprotect":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('pu').": $blocks<br />";
            break;
        case "upload":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('fu').": $blocks<br />";
            break;
        case "renameuser":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('ur').": $blocks<br />";
            break;
        case "grant":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('rg').": $blocks<br />";
            break;
        case "revoke":
            $blocks = $row['COUNT(*)'];
            echo wfMsg('rr').": $blocks<br />";
            break;
        case "move":
            $moves = $row['COUNT(*)'];
            break;
        case "move_redir":
            $blocks = $row['COUNT(*)'] + $moves;
            echo wfMsg('pm').": $blocks<br />";
            break;
    }
}
unset( $query, $result, $row, $blocks, $moves );
    
    

//Disabled for users with over 45000 edits, PHP craps out with more than this. It also craps out when there are more than 30000 unique articles
if( $live < '45000' && $unique_articles > '30000' ) {
	$num_to_present = 10;//Output 10 entries

	echo '<h2>'.wfMsg('topedited').'</h2>';
	ksort($namespace_unique_articles);
	foreach ( $namespace_unique_articles as $ns => $arts ) {
		$ns = $namespaces2[$ns];
		print "<h3>$ns</h3>\n";
		echo "<ul>";
		
		asort( $arts );
		$arts = array_reverse( $arts );
		$arts2 = array_splice( $arts, $num_to_present );
		
		foreach ( $arts as $article => $count ) {
			if( $ns == wfMsg('mainspace') ) {
				$nscolon = '';
			}
			else {
				$nscolon = $ns.":";
			}
			$articleencoded = urlencode( $article );
			$articleencoded = str_replace( '%2F', '/', $articleencoded );
			$trimmed = substr($article, 0, 50).'...';
			print '<li>'.$count." - <a href='http://$oldlang.$oldwiki.org/wiki/".$nscolon.$articleencoded.'\'>';
			if(strlen(substr($article, 0, 50))<strlen($article)) {
				echo $trimmed;
			}
			else {
				echo $article;
			}
			echo "</a></li>\n";
		}
		print "</ul><br />";
	}
}


//Calculate time taken to execute
$exectime = number_format(microtime( 1 ) - $time, 2, '.', '');
echo "<br /><hr><span style=\"font-size:100%;\">".wfMsg('executed', $exectime)."</span>";
echo "<br />".wfMsg('memory', number_format((memory_get_usage() / (1024 * 1024)), 2, '.', ''));

//Output footer
include( '/home/soxred93/public_html/common/footer.php' );

?>
