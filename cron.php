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
            "users_active_this_canvas" => $con->query("SELECT COUNT(id) AS total FROM users WHERE pixel_count>0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry));")->fetch_object()->total
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
    $qToplistall = $con->query("SELECT username, pixel_count_alltime AS pixels FROM users WHERE pixel_count_alltime > 0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry)) ORDER BY pixel_count_alltime DESC LIMIT 1000;");
    $i = 1;
    while($row = $qToplistall->fetch_object()) {
        $row->place = $i++;
        $row->pixels = intval($row->pixels);
        $stats["toplist"]["alltime"][] = $row;
    }

    $qToplistCanvas = $con->query("SELECT username, pixel_count AS pixels FROM users WHERE pixel_count > 0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry)) ORDER BY pixel_count DESC;");
    $i = 1;
    while($row = $qToplistCanvas->fetch_object()) {
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
        $query = $con->query("SELECT p.x,p.y,p.color,p.who AS 'uid', u.username AS 'username' FROM pixels p INNER JOIN users u ON p.who=u.id WHERE unix_timestamp()-unix_timestamp(p.time) <= ".intval($q)." AND NOT p.undone AND NOT p.undo_action AND NOT p.mod_action AND NOT p.rollback_action AND NOT (u.role='BANNED' OR u.role='SHADOWBANNED' OR (now() < ban_expiry));");
        $bdTemp = [
            "colors" => [],
            "users" => [],
            "temp" => []
        ];
        $toRet = [
            "user" => "",
            "color" => ""
        ];
        while ($row = $query->fetch_object()) {
            if (!array_key_exists($row->username, $bdTemp["users"])) {
                $bdTemp["users"][$row->username] = 0;
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
?>