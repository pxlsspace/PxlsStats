<?php
(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');

include(dirname(__FILE__).'/config.php');

date_default_timezone_set("Europe/Berlin");

$con = new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE);
if ($con->connect_error) {
    die('Connect Error (' . $con->connect_errno . ') ' . $con->connect_error);
}

$location = [];


$staff = $con->query("SELECT username,last_ip FROM users WHERE role IN('USER','MODERATOR','ADMIN') LIMIT 1000");

echo "we'll need ".$staff->num_rows." requests to ipinfo.io...\n";

while($row = $staff->fetch_object()) {
	$loc = json_decode(file_get_contents("http://ipinfo.io/".inet_ntop($row->last_ip)."/json"));
	echo ".";
	if(empty($loc->country)) {
		$loc = new stdClass();
		$loc->country = "UNKNOWN";
	}
	if(array_key_exists($loc->country, $location)) {
		$location[$loc->country]['count']++;
	} else {
		$location[$loc->country]['users'] = [];
		$location[$loc->country]['count'] = 1;
	}
	//$location[$loc->country]['users'][] = $row->username;
}

arsort($location);

foreach($location as $key=>$value) {
	echo $value['count'].' users are from '.$key."\n";
	//foreach($value['users'] as $user) {
	//	echo " - ".$user."\n";
	//}
	//echo "============================================\n";
}
?>
