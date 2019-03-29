<?php (PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');
    echo "\nStats collection starting...\n";
    include(dirname(__FILE__).'/config.php');
    date_default_timezone_set("Europe/Berlin");

    echo "    Connecting to mysql server...\r\n";
    $con = new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE);
    if ($con->connect_error) {
        die('Connect Error (' . $con->connect_errno . ') ' . $con->connect_error);
    }
    echo "    Connection successful.\r\n";
    echo "    Grabbing general stats...\r\n";
    $boardInfo = [];
    try {
        $tempBoardInfo = json_decode(file_get_contents("https://pxls.space/info"));
        $boardInfo = [
            "width" => $tempBoardInfo->width,
            "height" => $tempBoardInfo->height,
            "palette" => $tempBoardInfo->palette
        ];
    } catch (Exception $e) { /* ignored */ }
    $stats = [
        "general" => [
            "total_users" => $con->query("SELECT COUNT(id) AS total FROM users;")->fetch_object()->total,
            "total_pixels_placed" => $con->query("SELECT COUNT(id) AS total FROM pixels WHERE mod_action = 0 AND rollback_action = 0 AND undo_action = 0 AND undone = 0;")->fetch_object()->total,
            "users_active_this_canvas" => $con->query("SELECT COUNT(id) AS total FROM users WHERE pixel_count>0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry));")->fetch_object()->total,
            "nth_list" => [
                [
                    "pretty" => "1st",
                    "intval" => 1,
                    "res" => grabNthPixel($con, 1)
                ],
                [
                    "pretty" => "100th",
                    "intval" => 100,
                    "res" => grabNthPixel($con, 100)
                ],
                [
                    "pretty" => "1,000th",
                    "intval" => 1000,
                    "res" => grabNthPixel($con, 1000)
                ],
                [
                    "pretty" => "500,000th",
                    "intval" => 500000,
                    "res" => grabNthPixel($con, 500000)
                ],
                [
                    "pretty" => "1,000,000th",
                    "intval" => 1000000,
                    "res" => grabNthPixel($con, 1000000)
                ],
                [
                    "pretty" => "2,000,000th",
                    "intval" => 2000000,
                    "res" => grabNthPixel($con, 2000000)
                ],
                [
                    "pretty" => "3,000,000th",
                    "intval" => 3000000,
                    "res" => grabNthPixel($con, 3000000)
                ],
                [
                    "pretty" => "4,000,000th",
                    "intval" => 4000000,
                    "res" => grabNthPixel($con, 4000000)
                ],
                [
                    "pretty" => "5,000,000th",
                    "intval" => 5000000,
                    "res" => grabNthPixel($con, 5000000)
                ]
            ]
        ],
        "breakdown" => [
            "last15m" => [],
            "lastHour" => [],
            "lastDay" => [],
            "lastWeek" => []
        ],
        "toplist" => [
            "alltime" => [],
            "canvas" => []
        ],
        "board_info" => $boardInfo
    ];


    echo "    Grabbing breakdown stats...\n";
    $stats["breakdown"]["last15m"] = handlePixelsBreakdown($con, 900);
    $stats["breakdown"]["lastHour"] = handlePixelsBreakdown($con, 3600);
    $stats["breakdown"]["lastDay"] = handlePixelsBreakdown($con, 86400);
    $stats["breakdown"]["lastWeek"] = handlePixelsBreakdown($con, 604800);

    echo "    Grabbing leaderboard stats...\n";
    $qToplistall = $con->query("SELECT username, pixel_count_alltime AS pixels, login FROM users WHERE pixel_count_alltime > 0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry)) ORDER BY pixel_count_alltime DESC LIMIT 1000;");
    $i = 1;
    while($row = $qToplistall->fetch_object()) {
        filterUsernameInRow($row);
        $row->place = $i++;
        $row->pixels = intval($row->pixels);
        $stats["toplist"]["alltime"][] = $row;
    }

    $qToplistCanvas = $con->query("SELECT username, pixel_count AS pixels, login FROM users WHERE pixel_count > 0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry)) ORDER BY pixel_count DESC;");
    $i = 1;
    while($row = $qToplistCanvas->fetch_object()) {
        filterUsernameInRow($row);
        $row->place = $i++;
        $row->pixels = intval($row->pixels);
        $stats["toplist"]["canvas"][] = $row;
    }

    $stats["generatedAt"] = date("Y/m/d - H:i:s (e)");

    echo "    Closing MySQL Connection...\n";
    $con->close();
    $outFile = dirname(__FILE__)."/stats.json";
    echo "    Flushing...\n";
    file_put_contents($outFile, json_encode($stats));
    echo "Job done.\n\n";

    function handlePixelsBreakdown($con, $q) {
        $query = $con->query("SELECT p.x,p.y,p.color,p.who AS 'uid', u.username AS 'username', u.login as 'login' FROM pixels p INNER JOIN users u ON p.who=u.id WHERE unix_timestamp()-unix_timestamp(p.time) <= ".intval($q)." AND NOT p.undone AND NOT p.undo_action AND NOT p.mod_action AND NOT p.rollback_action AND NOT (u.role='BANNED' OR u.role='SHADOWBANNED' OR (now() < ban_expiry));");
        $bdTemp = [
            "colors" => [],
            "users" => [],
            "temp" => [],
            "loginMap" => []
        ];
        while ($row = $query->fetch_object()) {
            if (!array_key_exists($row->username, $bdTemp["users"])) {
                $bdTemp["users"][$row->username] = 0;
                $bdTemp["loginMap"][$row->username] = $row->login;
            }
            if (!array_key_exists($row->color, $bdTemp["colors"])) {
                $bdTemp["colors"][$row->color] = 0;
            }
            ++$bdTemp["users"][$row->username];
            ++$bdTemp["colors"][$row->color];
        }
        arsort($bdTemp["users"]);
        arsort($bdTemp["colors"]);
        $users = [];
        $i = 1;
        foreach (array_slice($bdTemp["users"], 0, 5, true) as $key => $value) {
            if (substr($bdTemp["loginMap"][$key], 0, 2) == "ip") {
                $key = "-snip-";
            }
            $users[] = [
                "username" => $key,
                "pixels" => $value,
                "place" => $i++
            ];
        }

        $colors = [];
        $i = 1;
        foreach(array_slice($bdTemp["colors"], 0, 5, true) as $key => $value) {
            $colors[] = [
                "colorID" => $key,
                "count" => $value,
                "place" => $i++
            ];
        }
        return [
            "users" => $users,
            "colors" => $colors
        ];
    }

    function grabNthPixel($con, $Nth) {
        $query = $con->query("SELECT * FROM pixels WHERE mod_action=0 AND rollback_action=0 AND undo_action=0 ORDER BY id LIMIT $Nth,1;");
        $res = $query->fetch_object();
        if ($res == null) return false;
        return getUsernameFromID($con, $res->who);
    }

    function getUsernameFromID($con, $id) {
        $row = $con->query("SELECT username,login FROM users WHERE id=$id LIMIT 1;")->fetch_object();
        if (substr($row->login, 0, 2) == "ip") {
            $row->username = "-snip-";
        }
        return $row->username;
    }

    function filterUsernameInRow(&$row) {
        if (isset($row->login)) {
            if (substr($row->login, 0, 2) == "ip") {
                $row->username = "-snip-";
            }
            $row->login = null;
            unset($row->login);
        }
    }

//    class Stats {
//        public function __construct() {
//            //
//        }
//    }
?>