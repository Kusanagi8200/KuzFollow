<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$config = require '/etc/kuzfollow/config.php';
require __DIR__ . '/../src/GitHubClient.php';

$user  = (string)($config['github_user'] ?? '');
$token = (string)($config['github_token'] ?? '');
if ($user === '' || $token === '') { http_response_code(500); exit("Missing config\n"); }

$gh = new GitHubClient($token);
$followers = $gh->followers($user);
$N = count($followers);

/* PARAMS */
$points = 30;       // EXACTEMENT 30 points
$incPerDay = 3;     // +3/jour

$tz = new DateTimeZone('UTC');
$today = new DateTimeImmutable('today', $tz);              // 00:00 UTC
$start = $today->sub(new DateInterval('P'.($points-1).'D')); // J-29 => 30 dates incl.

$startValue = $N - (($points-1) * $incPerDay);
if ($startValue < 0) $startValue = 0;

$hist = [];
for ($i = 0; $i < $points; $i++) {
  $d = $start->add(new DateInterval('P'.$i.'D'))->format('Y-m-d');
  $v = $startValue + ($i * $incPerDay);
  if ($v > $N) $v = $N;
  $hist[] = ['date' => $d, 'followers' => $v];
}

/* force dernier point = N */
$hist[$points-1]['date'] = $today->format('Y-m-d');
$hist[$points-1]['followers'] = $N;

/* write */
$dataDir = __DIR__ . '/../data';
@mkdir($dataDir, 0755, true);
$file = $dataDir . '/followers_history.json';
file_put_contents($file, json_encode($hist, JSON_UNESCAPED_SLASHES));

echo "OK\n";
echo "user={$user}\n";
echo "points=".count($hist)."\n";
echo "start=".$hist[0]['date']."\n";
echo "end=".$hist[count($hist)-1]['date']."\n";
echo "span_days=".(new DateTimeImmutable($hist[count($hist)-1]['date'], $tz))->diff(new DateTimeImmutable($hist[0]['date'], $tz))->days."\n";
echo "followers_now={$N}\n";
echo "start_value={$startValue}\n";
echo "increment_per_day={$incPerDay}\n";
echo "file={$file}\n";
