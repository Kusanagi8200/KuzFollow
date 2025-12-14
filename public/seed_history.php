<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$config = require '/etc/kuzfollow/config.php';
require __DIR__ . '/../src/GitHubClient.php';

$user  = (string)($config['github_user'] ?? '');
$token = (string)($config['github_token'] ?? '');

if ($user === '' || $token === '') {
  http_response_code(500);
  exit("Missing config\n");
}

$gh = new GitHubClient($token);
$followers = $gh->followers($user);
$N = count($followers);

// Date de création du compte
$api = 'https://api.github.com/users/'.$user;
$ch = curl_init($api);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Accept: application/vnd.github+json',
    'User-Agent: kuzfollow',
    'Authorization: Bearer '.$token,
  ],
  CURLOPT_TIMEOUT => 25,
]);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$j = json_decode($res ?: '{}', true);
if ($code >= 400) {
  http_response_code(500);
  exit("GitHub API error\n");
}

$created = (string)($j['created_at'] ?? '');
if ($created === '') {
  http_response_code(500);
  exit("Cannot read created_at\n");
}

$today = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$start = $today->sub(new DateInterval('P30D'));
$createdDt = new DateTimeImmutable($created, new DateTimeZone('UTC'));
if ($createdDt > $start) $start = $createdDt;

$days = (int)$today->diff($start)->days;
if ($days < 2) { $start = $today->sub(new DateInterval('P2D')); $days = 2; }

$base = (int)max(0, floor($N * 0.60));
$hist = [];

for ($i=0; $i <= $days; $i++) {
  $d = $start->add(new DateInterval('P'.$i.'D'));
  $t = $days === 0 ? 1.0 : ($i / $days);
  $v = (int)round($base + ($N - $base) * $t);
  $hist[] = ['date' => $d->format('Y-m-d'), 'followers' => $v];
}

/* IMPORTANT: même chemin que graph.svg.php */
$dataDir = realpath(__DIR__ . '/..') . '/data';
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);

$file = $dataDir . '/followers_history.json';
file_put_contents($file, json_encode($hist, JSON_UNESCAPED_SLASHES));

echo "OK\n";
echo "user={$user}\n";
echo "points=".count($hist)."\n";
echo "range=".$hist[0]['date']." -> ".$hist[count($hist)-1]['date']."\n";
echo "followers_now={$N}\n";
echo "file={$file}\n";
