<?php
session_start();
require __DIR__.'/../src/GitHubClient.php';
$config = require '/etc/kuzfollow/config.php';

$gh = new GitHubClient($config['github_token']);
$user = $config['github_user'];

$followers = $gh->followers($user);
$following = $gh->following($user);
$repos     = $gh->repos($user);
$events    = $gh->events($user);

$fol = array_column($followers,'login');
$ing = array_column($following,'login');

$notBack = array_diff($ing,$fol);
$youDont = array_diff($fol,$ing);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>KUZFOLLOW</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="wrap">

<div class="card">
  <h2>KUZFOLLOW · <?=htmlspecialchars($user)?></h2>
  <div class="meta">
    followers <?=count($followers)?> · following <?=count($following)?> · repos <?=count($repos)?>
  </div>
</div>

<div class="grid">

<div class="card">
<h2>FOLLOWERS YOU DON’T FOLLOW BACK</h2>
<?php foreach($youDont as $u): ?>
  <div class="item"><?=$u?></div>
<?php endforeach; ?>
</div>

<div class="card">
<h2>YOU FOLLOW, THEY DON’T</h2>
<?php foreach($notBack as $u): ?>
  <div class="item"><?=$u?></div>
<?php endforeach; ?>
</div>

<div class="card">
<h2>RECENT EVENTS</h2>
<?php foreach(array_slice($events,0,12) as $e): ?>
  <div class="item">
    <?=htmlspecialchars($e['type'])?>
    <div class="meta"><?=htmlspecialchars($e['repo']['name'] ?? '')?></div>
  </div>
<?php endforeach; ?>
</div>

<div class="card">
<h2>REPOSITORIES</h2>
<?php foreach(array_slice($repos,0,15) as $r): ?>
  <div class="item">
    <a href="<?=$r['html_url']?>" target="_blank"><?=$r['name']?></a>
    <div class="meta">
      ★<?=$r['stargazers_count']?> · forks <?=$r['forks_count']?> · <?=$r['language']?>
    </div>
  </div>
<?php endforeach; ?>
</div>

</div>
</div>
</body>
</html>
