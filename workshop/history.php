<?php

$time = microtime( 1 );//Calculate time in microseconds to calculate time taken to execute

include( '/home/soxred93/public_html/common/header.php' );
include( '/home/soxred93/wikibot.classes.php' );
include( '/home/soxred93/stats.php' );
$tool = 'PageHist';
$surl = "http://toolserver.org".$_SERVER['REQUEST_URI'];
if (isset($_GET['wiki']) && isset($_GET['lang']) && isset($_GET['name'])) {
	addStat( $tool, $surl, $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT'] );//Stat checking
}
unset($tool, $surl);

//Debugging stuff
error_reporting(E_ALL);
ini_set("display_errors", 1);
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
	<h2 class="table">Page History Stats</h2>';
	
	
//If there is a failure, do it pretty.
function toDie( $msg ) {
	echo $msg;
	include( '/home/soxred93/public_html/common/footer.php' );
	die();
}


//Tell footer.php to output source
/*function outputSource( $msg ) {
	echo "<li>
	<a href=\"https://svn.cluenet.org/viewvc/soxred93/trunk/bots/Tools/editsummary/index.php?view=markup\">View source</a>
	</li>";
}*/

if( !isset( $_GET['page'] ) ) {
	toDie( 'Welcome to the Page History Statistics tool!<br /><br />
		<form action="index.php" method="get">
		Page: <input type="text" name="page" /><br /><br />
		Namespace: <select name="namespace">
		<option value="0">Main</option>
		<option value="1">Talk</option>
		<option value="2">User</option>
		<option value="3">User talk</option>
		<option value="4">Wikipedia</option>
		<option value="5">Wikipedia talk</option>
		<option value="6">File</option>
		<option value="7">File talk</option>
		<option value="8">MediaWiki</option>
		<option value="9">MediaWiki talk</option>
		<option value="10">Template</option>
		<option value="11">Template talk</option>
		<option value="12">Help</option>
		<option value="13">Help talk</option>
		<option value="14">Category</option>
		<option value="15">Category talk</option>
		<option value="100">Portal</option>
		<option value="101">Portal talk</option>
		</select><br /><br />
		<input type="submit" />
		</form>' );
}

require_once( 'database.inc' );

mysql_connect( 'enwiki-p.db.toolserver.org',$toolserver_username,$toolserver_password );
@mysql_select_db( 'enwiki_p' ) or toDie( "MySQL error, please report to X! using <a href=\"http://en.wikipedia.org/wiki/User:X!/Bugs\">the bug reporter.</a> Be sure to report the following SQL error when reporting:<br /><pre>".mysql_error()."</pre>" );

$query = 'SELECT * from page where page_title=\''
		.ucfirst(mysql_escape_string($_GET['page'])).
		'\' and page_namespace = \''
		.mysql_escape_string($_GET['namespace']).
		'\';';
$result = mysql_query($query);
if( !$result ) toDie( "MySQL error, please report to X! using <a href=\"http://en.wikipedia.org/wiki/User:X!/Bugs\">the bug reporter.</a> Be sure to report the following SQL error when reporting:<br /><pre>".mysql_error()."</pre>" );
$row = mysql_fetch_assoc($result);
$pid = $row['page_id'];

if( !$pid ) {
	toDie("Page does not exist.");
}

$query = "SELECT * from revision where rev_page = '$pid';";
$result = mysql_query($query);
if( !$result ) toDie( "MySQL error, please report to X! using <a href=\"http://en.wikipedia.org/wiki/User:X!/Bugs\">the bug reporter.</a> Be sure to report the following SQL error when reporting:<br /><pre>".mysql_error()."</pre>" );

$revs = array();
$minorcount = 0;
$times = array();
$years = array();
$months = array();
$unique_editors = array();
$lastedits = array('day' => 0,'week' => 0,'month' => 0,'year' => 0);
$editors = array();
$i = 0;
while ($row = mysql_fetch_assoc($result)) {
	$revs[] = $row;
	
	if( $row['rev_minor_edit'] == 1 ) $minorcount++;
	
	$year = substr($row['rev_timestamp'], 0, 4);
    $month = substr($row['rev_timestamp'], 4, 2);
    $day = substr($row['rev_timestamp'], 6, 2);
    $hour = substr($row['rev_timestamp'], 8, 2);
    $minute = substr($row['rev_timestamp'], 10, 2);
    
    $secs = strtotime("$year-$month-$day"."T"."$hour:$minute:00");
    $times[] = $secs;
    
    if( !isset($firstedit)) {
		$firstedit = array( $secs, $row['rev_user_text']);
	}
	$lastedit = array( $secs, $row['rev_user_text']);
	
    if( !isset($years[$year]) ) $years[$year] = 0; 
    $years[$year]++; 
    
    if( !isset($months[$month.'/'.$year]) ) $months[$month.'/'.$year] = 0; 
    $months[$month.'/'.$year]++; 
    
    if( !isset($unique_editors[$row['rev_user_text']])) $unique_editors[$row['rev_user_text']] = $row['rev_user_text'];
    
    if( $secs > strtotime("-1 day") ) $lastedits['day']++;
    if( $secs > strtotime("-1 week") ) $lastedits['week']++;
    if( $secs > strtotime("-1 month") ) $lastedits['month']++;
    if( $secs > strtotime("-1 year") ) $lastedits['year']++;
    
    if( !isset($editors[$row['rev_user_text']])) {
    	$editors[$row['rev_user_text']] = array(
    		'count' => 1,
    		'minor' => $row['rev_minor_edit'],
    		'firstedit' => $secs,
    		'lastedit' => $secs,
    	);
    }
    else {
    	$editors[$row['rev_user_text']]['count']++;
    	if ($row['rev_minor_edit'] == 1) $editors[$row['rev_user_text']]['minor']++;
    	$editors[$row['rev_user_text']]['lastedit'] = $secs;
    }
    
    $i++;
}

$avgyears = array_sum($years)/count($years);
$avgmonths = array_sum($months)/count($months);

$pagedata = array(
	'totalcount' => count($revs),
	'minorcount' => $minorcount,
	'minorpct' => number_format( ( ( $minorcount/count($revs) )*100 ), 2),
	'firstedit' => $firstedit,
	'lastedit' => $lastedit,
	'avgperyear' => number_format($avgyears ,2),
	'avgpermonth' => number_format($avgmonths ,2),
	'unique_editors' => count($unique_editors),
	'editsperuser' => number_format( ( ( count($revs)/count($unique_editors) ) ), 2),
	'lastedits' => $lastedits,
	'editsperyear' => $years,
	'editspermonth' => $months,
	'editors' => $editors,
);

echo "<h2>Page history stats for ".$_GET['page']."</h2>";
echo "<b>Total edits:</b> ".$pagedata['totalcount']."<br />";
echo "<b>Minor edits:</b> ".$pagedata['minorcount']."<br />";
echo "<b>Minor edit percentage:</b> ".$pagedata['minorpct']."%<br />";
echo "<b>First edit:</b> ".date("F j, Y", $pagedata['firstedit'][0])." by ".$pagedata['firstedit'][1]."<br />";
echo "<b>Latest edit:</b> ".date("F j, Y", $pagedata['lastedit'][0])." by ".$pagedata['lastedit'][1]."<br />";
echo "<b>Total edits:</b> ".$pagedata['totalcount']."<br />";
echo "<b>Average edits per year:</b> ".$pagedata['avgperyear']."<br />";
echo "<b>Average edits per month:</b> ".$pagedata['avgpermonth']."<br />";
echo "<b>Unique editors:</b> ".$pagedata['unique_editors']."<br />";
echo "<b>Average edits per user:</b> ".$pagedata['editsperuser']."<br /><br />";
echo "<b>Edits in last day:</b> ".$pagedata['lastedits']['day']."<br />";
echo "<b>Edits in last week:</b> ".$pagedata['lastedits']['week']."<br />";
echo "<b>Edits in last month:</b> ".$pagedata['lastedits']['month']."<br />";
echo "<b>Edits in last year:</b> ".$pagedata['lastedits']['year']."<br />";

echo "<h2>Edits per year</h2>";
echo "<ul>";
foreach( $pagedata['editsperyear'] as $year => $edits ) {
	echo "<li>$year - $edits edits</li>";
}
echo "</ul>";

echo "<h2>Edits per month</h2>";
echo "<ul>";
foreach( $pagedata['editspermonth'] as $month => $edits ) {
	echo "<li>$month - $edits edits</li>";
}
echo "</ul>";

echo "<h2>User statistics</h2>";
echo "<table class=\"wikitable\" style=\"text-align:center;\">
		<tr><th>User</th>
		<th># edits</th>
		<th># minor</th>
		<th>first edit</th>
		<th>last edit</th></tr>";
ksort($pagedata['editors']);
foreach( $pagedata['editors'] as $name => $data ) {
	echo "<tr>";
	echo "<td>$name</td>";
	echo "<td>".$data['count']."</td>";
	echo "<td>".$data['minor']."</td>";
	echo "<td>".date("F j, Y; g:i \U\T\C", $data['firstedit'])."</td>";
	echo "<td>".date("F j, Y; g:i \U\T\C", $data['lastedit'])."</td>";
	echo "</tr>";
}
echo "</table>";

include("/home/soxred93/public_html/common/footer.php");

?>