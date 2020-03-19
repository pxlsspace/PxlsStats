<?php (PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');
    include(dirname(__FILE__).'/config.php');
    date_default_timezone_set("Europe/Berlin");

    $stats = new Stats($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE);
    file_put_contents(dirname(__FILE__)."/stats.json", json_encode($stats->getStats()));

    class Stats {
        private $con;

        public function __construct($dbHost, $dbUser, $dbPassword, $dbDatabase) {
            $con_str = "pgsql:host=$dbHost dbname=$dbDatabase";
            $this->con = new PDO($con_str, $dbUser, $dbPassword);
            if (!$this->con) throw new Error('Failed to connect to the MySQL Database');
        }

        public function __destruct() {
            try {
                $this->con = null;
            } catch (Exception $e) {
                //
            }
        }

        public function getStats() {
            echo "\nStats collection starting...\n";
            echo "    Caching board info\n";
            $boardInfo = [];
            try {
                $tempBoardInfo = json_decode(file_get_contents("https://pxls.space/info"));
                $boardInfo = [
                    "width" => $tempBoardInfo->width,
                    "height" => $tempBoardInfo->height,
                    "palette" => $tempBoardInfo->palette
                ];
            } catch (Exception $e) { /* ignored */ }
            echo "    Grabbing general stats...\n";
            $toRet = [
                "general" => [
                    "total_users" => $this->con->query("SELECT COUNT(id) AS total FROM users;")->fetch()['total'],
                    "total_pixels_placed" => $this->con->query("SELECT COUNT(id) AS total FROM pixels WHERE mod_action = false AND rollback_action = false AND undo_action = false AND undone = false;")->fetch()['total'],
                    "users_active_this_canvas" => $this->con->query("SELECT COUNT(id) AS total FROM users WHERE pixel_count>0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry AND ban_expiry IS NOT NULL));")->fetch()['total'],
                    "nth_list" => [
                        $this->generateNth(1),
                        $this->generateNth(100),
                        $this->generateNth(1000),
                        $this->generateNth(15000),
                        $this->generateNth(25000),
                        $this->generateNth(100000),
                        $this->generateNth(250000),
                        $this->generateNth(500000),
                        $this->generateNth(750000),
                        $this->generateNth(1000000),
                        $this->generateNth(1500000),
                        $this->generateNth(2000000),
                        $this->generateNth(2500000),
                        $this->generateNth(3000000),
                        $this->generateNth(3500000),
                        $this->generateNth(4000000),
                        $this->generateNth(4500000),
                        $this->generateNth(5000000),
                        $this->generateNth(5500000)
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
            $toRet["breakdown"]["last15m"] = $this->getBreakdownForTime(900);
            $toRet["breakdown"]["lastHour"] = $this->getBreakdownForTime(3600);
            $toRet["breakdown"]["lastDay"] = $this->getBreakdownForTime(86400);
            $toRet["breakdown"]["lastWeek"] = $this->getBreakdownForTime(604800);

            echo "    Grabbing leaderboard stats...\n";
            $qToplistall = $this->con->query("SELECT username, pixel_count_alltime AS pixels, login FROM users WHERE pixel_count_alltime > 0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry AND ban_expiry IS NOT NULL)) ORDER BY pixel_count_alltime DESC LIMIT 1000;");
            $i = 1;
            while($row = $qToplistall->fetch()) {
                // TODO: Find out why the row includes numbered results
                unset($row[0], $row[1], $row[2]);
                $this->filterUsernameInRow($row);
                $row['place'] = $i++;
                $row['pixels'] = intval($row['pixels']);
                $toRet["toplist"]["alltime"][] = $row;
            }

            $qToplistCanvas = $this->con->query("SELECT username, pixel_count AS pixels, login FROM users WHERE pixel_count > 0 AND NOT (role='BANNED' OR role='SHADOWBANNED' OR (now() < ban_expiry AND ban_expiry IS NOT NULL)) ORDER BY pixel_count DESC LIMIT 1000;");
            $i = 1;
            while($row = $qToplistCanvas->fetch()) {
                // TODO: Find out why the row includes numbered results
                unset($row[0], $row[1], $row[2]);
                $this->filterUsernameInRow($row);
                $row['place'] = $i++;
                $row['pixels'] = intval($row['pixels']);
                $toRet["toplist"]["canvas"][] = $row;
            }

            $toRet["generatedAt"] = date("Y/m/d - H:i:s (e)");

            echo "\nJob Done.\n";

            return $toRet;
        }

        private function getBreakdownForTime($time) {
            $query = $this->con->query("SELECT p.x,p.y,p.color,p.who AS \"uid\", u.username AS \"username\", u.login as \"login\" FROM pixels p INNER JOIN users u ON p.who=u.id WHERE p.time BETWEEN (current_timestamp - interval '".$time." seconds') AND (current_timestamp) AND NOT p.undone AND NOT p.undo_action AND NOT p.mod_action AND NOT p.rollback_action AND NOT (u.role='BANNED' OR u.role='SHADOWBANNED' OR (now() < u.ban_expiry AND u.ban_expiry IS NOT NULL));");
            $bdTemp = [
                "colors" => [],
                "users" => [],
                "temp" => [],
                "loginMap" => []
            ];
            while ($row = $query->fetch()) {
                if (!array_key_exists($row['username'], $bdTemp["users"])) {
                    $bdTemp["users"][$row['username']] = 0;
                    $bdTemp["loginMap"][$row['username']] = $row['login'];
                }
                if (!array_key_exists($row['color'], $bdTemp["colors"])) {
                    $bdTemp["colors"][$row['color']] = 0;
                }
                ++$bdTemp["users"][$row['username']];
                ++$bdTemp["colors"][$row['color']];
            }
            arsort($bdTemp["users"]);
            arsort($bdTemp["colors"]);
            $users = [];
            $i = 1;
            foreach (array_slice($bdTemp["users"], 0, 10, true) as $key => $value) {
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
            foreach(array_slice($bdTemp["colors"], 0, 10, true) as $key => $value) {
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

        private function generateNth($Nth) {
            $Nth = intval($Nth);
            return [
                "pretty" => number_format($Nth).$this->ordinal_suffix($Nth),
                "intval" => $Nth,
                "res" => $this->grabNthPixel($Nth)
            ];
        }

        private function ordinal_suffix($num){ //https://stackoverflow.com/a/6604934
            $num = $num % 100; // protect against large numbers
            if($num < 11 || $num > 13){
                switch($num % 10){
                    case 1: return 'st';
                    case 2: return 'nd';
                    case 3: return 'rd';
                }
            }
            return 'th';
        }

        private function grabNthPixel($Nth) {
            $query = $this->con->query("SELECT * FROM pixels WHERE mod_action=false AND rollback_action=false AND undo_action=false AND undone=false ORDER BY id LIMIT 1 OFFSET $Nth;");
            if (!$query) return false;
            return $this->getUsernameFromID($query->fetch()['who']);
        }

        private function getUsernameFromID($id) {
            if (empty($id)) {
                return '-unknown username-';
            }
            $row = $this->con->query("SELECT username,login FROM users WHERE id=$id LIMIT 1;");
            if (!$row) return false;
            $row = $row->fetch();
            if (substr($row['login'], 0, 2) == "ip") {
                $row['username'] = "-snip-";
            }
            return $row['username'];
        }

        private function filterUsernameInRow(&$row) {
            if (isset($row['login'])) {
                if (substr($row['login'], 0, 2) == "ip") {
                    $row['username'] = "-snip-";
                }
                $row['login'] = null;
                unset($row['login']);
            }
        }
    }
?>
