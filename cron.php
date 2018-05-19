<?php
(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');

include(dirname(__FILE__).'/config.php');

date_default_timezone_set("Europe/Berlin");

echo "connecting to mysql server...\r\n";
$con = new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE);
if ($con->connect_error) {
    die('Connect Error (' . $con->connect_errno . ') ' . $con->connect_error);
}
echo "connection successful.\r\n";


$stats = Array();
echo "grabbing stats...\r\n";
$stats["total_users"]			= $con->query("SELECT COUNT(id) AS total FROM users;")->fetch_object()->total;
$stats["total_pixels_placed"]		= $con->query("SELECT COUNT(id) AS total FROM pixels WHERE mod_action = 0 AND rollback_action = 0 AND undo_action = 0;")->fetch_object()->total;
$stats["total_pixels_placed_mod"]	= $con->query("SELECT COUNT(id) AS total FROM pixels WHERE rollback_action = 0 AND undo_action = 0;")->fetch_object()->total;
$stats["most_pixels_placed"]		= $con->query("SELECT username, pixel_count AS pxlcount FROM users ORDER BY pixel_count DESC LIMIT 1;")->fetch_object();
$stats["most_pixel_placed_mod"]		= $con->query("SELECT COUNT(*) AS pxlcount, users.username AS user FROM pixels LEFT JOIN users ON pixels.who = users.id WHERE rollback_action = 0 AND undo_action = 0 GROUP BY WHO ORDER BY COUNT(*) DESC LIMIT 1;")->fetch_object();
$stats["last_updated"]		= '"'.date("Y/m/d - H:i:s").'"';
echo "done. grabbing top placed lists...\r\n";
$toplist = "<table style=\"width:100%;\"><tr><th>pxlcount</th><th>username</th></tr>";
$qToplist = $con->query("SELECT username, pixel_count FROM users ORDER BY pixel_count DESC LIMIT 50;");
while($row = $qToplist->fetch_object()) {
	$toplist .= "<tr><td>".$row->pixel_count."</td><td>".$row->username."</td></tr>";
}
$toplist .= "</table>";
$toplistall = "<table style=\"width:100%;\"><tr><th>pxlcount</th><th>username</th></tr>";
$qToplistall = $con->query("SELECT username, pixel_count_alltime FROM users ORDER BY pixel_count_alltime DESC LIMIT 50;");
while($row = $qToplistall->fetch_object()) {
	$toplistall .= "<tr><td>".$row->pixel_count_alltime."</td><td>".$row->username."</td></tr>";
}
$toplistall .= "</table>";
echo "done. writing to file...\r\n";

file_put_contents(dirname(__FILE__)."/basicstats.json",json_encode($stats));
file_put_contents(dirname(__FILE__)."/topstats.html",$toplist);
file_put_contents(dirname(__FILE__)."/topstatsall.html",$toplistall);

echo "done. closing mysql connection...\r\n";

$con->close();

echo "done.\r\n";

?>
