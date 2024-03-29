<?php (PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');
    include(dirname(__FILE__).'/config.php');
    date_default_timezone_set('Europe/Berlin');

    $stats = new Stats($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE, $INSTANCE_URL);
    file_put_contents(dirname(__FILE__).'/stats.json', json_encode($stats->getStats()));

    class Stats {
        private $con;
        private $instanceURL;

        public function __construct($dbHost, $dbUser, $dbPassword, $dbDatabase, $instanceURL) {
            $con_str = "pgsql:host=$dbHost dbname=$dbDatabase";
            $this->con = new PDO($con_str, $dbUser, $dbPassword);
            if (!$this->con) throw new Error('Failed to connect to the MySQL Database');
            $this->instanceURL = $instanceURL;
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
                $tempBoardInfo = json_decode(file_get_contents($this->instanceURL . '/info'));
                $boardInfo = [
                    'width' => $tempBoardInfo->width,
                    'height' => $tempBoardInfo->height,
                    'palette' => $tempBoardInfo->palette
                ];
            } catch (Exception $e) { /* ignored */ }
            echo "    Grabbing general stats...\n";
            $toRet = [
                'general' => [
                    'total_users' => $this->con->query("SELECT COUNT(id) AS total FROM users;")->fetch()['total'],
                    'total_pixels_placed' => $this->con->query("SELECT COUNT(id) AS total FROM pixels WHERE mod_action = false AND rollback_action = false AND undo_action = false AND undone = false;")->fetch()['total'],
                    'users_active_this_canvas' => $this->con->query("SELECT COUNT(id) AS total FROM users WHERE pixel_count>0 AND NOT (is_shadow_banned OR (ban_expiry IS NOT NULL AND (ban_expiry <= to_timestamp(0) OR ban_expiry >= now())));")->fetch()['total'],
                    'total_factions' => $this->con->query("SELECT COUNT(id) AS total FROM faction;")->fetch()['total'],
                    'nth_list' => [
                        $this->generateNth(1),
                        $this->generateNth(100),
                        $this->generateNth(1000),
                        $this->generateNth(10000),
                        $this->generateNth(25000),
                        $this->generateNth(50000),
                        $this->generateNth(75000),
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
                'breakdown' => [
                    'last15m' => [],
                    'lastHour' => [],
                    'lastDay' => [],
                    'lastWeek' => []
                ],
                'toplist' => [
                    'alltime' => [],
                    'canvas' => []
                ],
                'factions' => $this->con->query("select f.id as \"fid\",f.name as \"Faction\", z.canvas_pixels as \"Canvas_Pixels\", z.alltime_pixels as \"Alltime_Pixels\", z.member_count as \"Member_Count\" from (select fm.fid as \"fid\",sum(u.pixel_count) as \"canvas_pixels\", sum(u.pixel_count_alltime) as \"alltime_pixels\", count(fm.uid) as \"member_count\" from faction_membership fm inner join users u on fm.uid=u.id group by fm.fid) z inner join faction f on f.id=z.fid order by z.canvas_pixels desc, z.alltime_pixels desc, z.fid limit 1000;")->fetchAll(\PDO::FETCH_ASSOC),
                'board_info' => $boardInfo,
            ];
            echo "    Grabbing breakdown stats...\n";
            $toRet['breakdown']['last15m'] = $this->getBreakdownForTime(900);
            $toRet['breakdown']['lastHour'] = $this->getBreakdownForTime(3600);
            $toRet['breakdown']['lastDay'] = $this->getBreakdownForTime(86400);
            $toRet['breakdown']['lastWeek'] = $this->getBreakdownForTime(604800);

            echo "    Grabbing leaderboard stats...\n";
            $qToplistall = $this->con->query("SELECT username, pixel_count_alltime AS pixels, login_with_ip FROM users WHERE pixel_count_alltime > 0 AND NOT (is_shadow_banned OR (ban_expiry IS NOT NULL AND (ban_expiry <= to_timestamp(0) OR ban_expiry >= now()))) ORDER BY pixel_count_alltime DESC LIMIT 1000;");
            $i = 1;
            while($row = $qToplistall->fetch(\PDO::FETCH_ASSOC)) {
                $this->filterUsernameInRow($row);
                $row['place'] = $i++;
                $row['pixels'] = intval($row['pixels']);
                $toRet['toplist']['alltime'][] = $row;
            }

            $qToplistCanvas = $this->con->query("SELECT username, pixel_count AS pixels, login_with_ip FROM users WHERE pixel_count > 0 AND NOT (is_shadow_banned OR (ban_expiry IS NOT NULL AND (ban_expiry <= to_timestamp(0) OR ban_expiry >= now()))) ORDER BY pixel_count DESC LIMIT 1000;");
            $i = 1;
            while($row = $qToplistCanvas->fetch(\PDO::FETCH_ASSOC)) {
                $this->filterUsernameInRow($row);
                $row['place'] = $i++;
                $row['pixels'] = intval($row['pixels']);
                $toRet['toplist']['canvas'][] = $row;
            }

            $toRet['generatedAt'] = date('Y/m/d - H:i:s (e)');

            echo "\nJob Done.\n";

            return $toRet;
        }

        private function getBreakdownForTime($time) {
            $query = $this->con->query("SELECT p.x,p.y,p.color,p.who AS \"uid\", u.username AS \"username\", u.login_with_ip as \"login_with_ip\" FROM pixels p INNER JOIN users u ON p.who=u.id WHERE p.time BETWEEN (current_timestamp - interval '".$time." seconds') AND (current_timestamp) AND NOT p.undone AND NOT p.undo_action AND NOT p.mod_action AND NOT p.rollback_action AND NOT (is_shadow_banned OR (ban_expiry IS NOT NULL AND (ban_expiry <= to_timestamp(0) OR ban_expiry >= now())));");
            $bdTemp = [
                'colors' => [],
                'users' => [],
                'temp' => [],
                'loginMap' => []
            ];
            while ($row = $query->fetch()) {
                if (!array_key_exists($row['username'], $bdTemp['users'])) {
                    $bdTemp['users'][$row['username']] = 0;
                    $bdTemp['loginMap'][$row['username']] = $row['login_with_ip'];
                }
                if (!array_key_exists($row['color'], $bdTemp['colors'])) {
                    $bdTemp['colors'][$row['color']] = 0;
                }
                ++$bdTemp['users'][$row['username']];
                ++$bdTemp['colors'][$row['color']];
            }
            arsort($bdTemp['users']);
            arsort($bdTemp['colors']);
            $users = [];
            $i = 1;
            foreach (array_slice($bdTemp['users'], 0, 10, true) as $key => $value) {
                if ($bdTemp['loginMap'][$key]) {
                    $key = '-snip-';
                }
                $users[] = [
                    'username' => $key,
                    'pixels' => $value,
                    'place' => $i++
                ];
            }

            $colors = [];
            $i = 1;
            foreach(array_slice($bdTemp['colors'], 0, 10, true) as $key => $value) {
                $colors[] = [
                    'colorID' => $key,
                    'count' => $value,
                    'place' => $i++
                ];
            }
            return [
                'users' => $users,
                'colors' => $colors
            ];
        }

        private function generateNth($Nth) {
            $Nth = intval($Nth);
            return [
                'pretty' => number_format($Nth).$this->ordinal_suffix($Nth),
                'intval' => $Nth,
                'res' => $this->grabNthPixel($Nth-1)
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
            $pixel = $query->fetch();
            if (!$pixel) return false;
            return $this->getUsernameFromID($pixel['who']);
        }

        private function getUsernameFromID($id) {
            if (empty($id)) {
                return false;
            }
            $row = $this->con->query("SELECT username, login_with_ip FROM users WHERE id=$id LIMIT 1;");
            if (!$row) return false;
            $row = $row->fetch();
            if ($row['login_with_ip']) {
                $row['username'] = '-snip-';
            }
            return $row['username'];
        }

        private function filterUsernameInRow(&$row) {
            if (isset($row['login_with_ip'])) {
                if ($row['login_with_ip']) {
                    $row['username'] = '-snip-';
                }
                $row['login_with_ip'] = null;
                unset($row['login_with_ip']);
            }
        }
    }
?>
