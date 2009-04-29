<?php

if( $_GET['format'] == 'xml' ) {
	header('Content-Type: text/xml');
}
elseif( $_GET['format'] == 'json' ) {
	header('Content-Type: applications/json');
}
elseif( $_GET['format'] == 'yaml' ) {
	header('Content-Type: application/yaml');
}
elseif( $_GET['format'] == 'php' ) {
	header('Content-Type: application/x-httpd-php');
}
elseif( $_GET['format'] == 'txt' ) {
	header('Content-Type: text/plain');
}

error_reporting(E_ERROR);
ini_set("display_errors", 1);

if( isset($_GET['action']) ) {
	$action = $_GET['action'];
}
else {
	$action = '';
}

$validactions = array(
	'replag',
	'status',
	'size',
);

$servers = array( 
	array( 
		'display' => 's1',
		'serv' => 'sql-s1',
		'db' => 'enwiki_p'
	),
	array( 
		'display' => 's1-c',
		'serv' => 'sql-s1',
		'db' => 'commonswiki_p'
	),
	array( 
		'display' => 's2',
		'serv' => 'sql-s2',
		'db' => 'dewiki_p'
	),
	array( 
		'display' => 's3',
		'serv' => 'sql-s3',
		'db' => 'frwiki_p'
	),
	array( 
		'display' => 's3-c',
		'serv' => 'sql-s3',
		'db' => 'commonswiki_p'
	)
);

if( is_null($action) || !in_array($action, $validactions) ) {
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<title>Toolserver API</title>
</head>
<body>
<pre>
<span style="color:blue;">&lt;?xml version=&quot;1.0&quot;?&gt;</span>
<span style="color:blue;">&lt;api&gt;</span>
  <span style="color:blue;">&lt;error code=&quot;help&quot; info=&quot;&quot;&gt;</span>
  Documentation at <a href="http://meta.wikimedia.org/wiki/User:Soxred93/API">http://meta.wikimedia.org/wiki/User:Soxred93/API</a>
<span style="color:blue;">&lt;/error&gt;</span>
<span style="color:blue;">&lt;/api&gt;</span>
</pre>
</body>
</html>';
}
elseif( $action == 'replag' ) {
	$f = array();
	require_once('/home/soxred93/database.inc');
	foreach( $servers as $serv ) {
		$display = $serv['display'];
		$domain = $serv['serv'];
		$db = $serv['db'];
		$mysql = mysql_connect($domain,$toolserver_username,$toolserver_password,true);
		mysql_select_db($db, $mysql);
		
		$query = "SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(rc_timestamp) as replag FROM recentchanges ORDER BY rc_timestamp DESC LIMIT 1";
    	$result = mysql_query( $query, $mysql );

    	$row = mysql_fetch_assoc( $result );
    	$replag = $row['replag'];
    	$seconds = floor($replag);
    	$f[$display] = $seconds;
	}
	
	if( isset($_GET['format']) ) {
		$format = $_GET['format'];
	}
	else {
		$format = '';
	}
	echo formatArray($f, $format,'serv', 'name');
}
elseif( $action == 'status' ) {
	$f = array();
	foreach (array("s1", "s2", "s3", "sql") as $srv)                                
	{                                                                               
	    $file = file("/var/www/status_".$srv);                                         
	    foreach ($file as $line)                                               
	    {           
	    	if (substr($line,0,1) != '#') {
	            $r = explode(';', $line);
	                            
	            $f[$srv] = $r[0];
	    	}                                      
	    }                                                                       
	}
	if( isset($_GET['format']) ) {
		$format = $_GET['format'];
	}
	else {
		$format = '';
	}
	echo formatArray($f, $format,'serv', 'name');
}
elseif( $action == 'size' ) {
	$s = array();
	foreach (array("hemlock:/home","/dev/mapper/rootvg-root") as $srv) {                                                                               
		$t = shell_exec('/home/soxred93/size.sh -t '.$srv);
		$f = shell_exec('/home/soxred93/size.sh -f '.$srv);
		$u = shell_exec('/home/soxred93/size.sh -u '.$srv);

		$s[$srv] = array(
			'free' => trim($f),
			'used' => trim($u),
			'total' => trim($t),
		);
	}
	
	if( isset($_GET['format']) ) {
		$format = $_GET['format'];
	}
	else {
		$format = '';
	}
	echo formatArray($s, $format,'size', 'type', 'size');
}

function toDie($msg, $format) {
}

function formatArray($array, $format, $att = null, $attname = null, $method = null) {
	$formats = array(
		'php',
		'xml',
		'json',
		'txt',
		'yaml',
		'',
	);
	
	if( !in_array($format, $formats) ) {
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<title>Toolserver API</title>
</head>
<body>
<pre>
<span style="color:blue;">&lt;?xml version=&quot;1.0&quot;?&gt;</span>
<span style="color:blue;">&lt;api&gt;</span>
  <span style="color:blue;">&lt;error code=&quot;unknown_format&quot; info=&quot;Unrecognized value for parameter &amp;#039;format&amp;#039;: '.$format.'&quot;&gt;</span>
<span style="color:blue;">&lt;/error&gt;</span>
<span style="color:blue;">&lt;/api&gt;</span>
</pre>
</body>
</html>';
	}
	
	if( $format == 'php' ) {
		return serialize($array);
	}
	elseif( $format == 'xml' ) {
		return ArrayToXML::toXML($array, 'api', null, $att, $attname, $method);
	}
	elseif( $format == 'json' ) {
		return json_encode($array);
	}
	elseif( $format == 'txt' ) {
		return print_r($array,true);
	}
	elseif( $format == '' ) {
		return '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<title>Toolserver API Result</title>
</head>
<body>
<br/>
<small>
You are looking at the HTML representation of the TXT format.<br/>
HTML is good for debugging, but probably is not suitable for your application.<br/>
See <a href="http://meta.wikimedia.org/wiki/User:Soxred93/API">complete documentation</a>
</small>
<pre>' . print_r($array, true) .
'</pre>
</body>
</html>';
	}
	elseif( $format == 'yaml' ) {
		require('/home/soxred93/yaml.php');
		return Spyc :: YAMLDump($array);	
	}
}

class ArrayToXML
{
	/**
	 * The main function for converting to an XML document.
	 * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
	 *
	 * @param array $data
	 * @param string $rootNodeName - what you want the root node to be - defaultsto data.
	 * @param SimpleXMLElement $xml - should only be used recursively
	 * @return string XML
	 */
	public static function toXml($data, $rootNodeName = 'data', $xml=null, $att = null, $attname = null, $method = null)
	{
		
		if ($xml == null)
		{
			$xml = simplexml_load_string("<?xml version=\"1.0\" encoding=\"utf-8\"?><$rootNodeName />");
		}
		
		// loop through the data passed in.
		foreach($data as $key => $value)
		{
			// no numeric keys in our xml please!
			if (is_numeric($key))
			{
				// make string key...
				$key = "unknownNode_". (string) $key;
			}
			
			if( !is_null($att) ) {
				$attr = $attname;
				$val = $key;
				$key = $att;
			}
			else {
				// replace anything not alpha numeric
				$key = preg_replace('/[^a-z]/i', '', $key);
			}
			
			// if there is another array found recrusively call this function
			if (is_array($value))
			{
				if( $method == 'size' ) {
					$node = $xml->addChild('server');
					$node->addAttribute('name', $val);
				}
				else {
					 $node = $xml->addChild($key,$value);
				}
				// recrusive call.
				ArrayToXML::toXml($value, $rootNodeName, $node, $att, $attname);
			}
			else 
			{
				// add single node.
                $value = htmlentities($value);
				if( isset($attr) ) {
					$n = $xml->addChild($key,$value);
					$n->addAttribute($attr, $val);
				}
				else {
					 $xml->addChild($key,$value);
				}
			}
			
		}
		// pass back as string. or simple xml object if you want!
		return $xml->asXML();
	}
}

?>