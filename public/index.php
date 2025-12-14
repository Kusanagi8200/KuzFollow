<?php
session_start();
require __DIR__.'/../src/GitHubClient.php';
$config = require '/etc/kuzfollow/config.php';

function h($s){return htmlspecialchars($s,ENT_QUOTES);}

$gh   = new GitHubClient($config['github_token']);
$user = $config['github_user'];

/* ===== ACTIONS ===== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $users = $_POST['users'] ?? [];
    $act   = $_POST['action'] ?? '';

    foreach ($users as $u) {
        if ($act==='follow')   $gh->follow($u);
        if ($act==='unfollow') $gh->unfollow($u);
        usleep(300000); // anti rate-limit
    }
    header('Location: /');
    exit;
}

/* ===== DATA ===== */
$followers = $gh->followers($user);
$following = $gh->following($user);
$repos     = $gh->repos($user);
$events    = $gh->events($user);

$fol = array_column($followers,'login');
$ing = array_column($following,'login');

$youDontFollowBack = array_diff($fol,$ing);
$theyDontFollowYou = array_diff($ing,$fol);
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
  <h2>KUZFOLLOW · <?=h($user)?></h2>
  <div class="meta">
    followers <?=count($followers)?> · following <?=count($following)?> · repos <?=count($repos)?>
  </div>
</div>

<div class="grid">

<!-- FOLLOW BACK -->
<div class="card">
<h2>FOLLOW BACK</h2>
<form method="post">
<input type="hidden" name="action" value="follow">
<?php foreach($youDontFollowBack as $u): ?>
  <div class="item">
    <label>
      <input type="checkbox" name="users[]" value="<?=$u?>">
      <?=$u?>
    </label>
  </div>
<?php endforeach; ?>
<button class="btn">FOLLOW SELECTED</button>
</form>
</div>

<!-- UNFOLLOW -->
<div class="card">
<h2>UNFOLLOW (NON MUTUAL)</h2>
<form method="post">
<input type="hidden" name="action" value="unfollow">
<?php foreach($theyDontFollowYou as $u): ?>
  <div class="item">
    <label>
      <input type="checkbox" name="users[]" value="<?=$u?>">
      <?=$u?>
    </label>
  </div>
<?php endforeach; ?>
<button class="btn">UNFOLLOW SELECTED</button>
</form>
</div>

<div class="card">
<h2>RECENT EVENTS</h2>
<?php foreach($events as $e): ?>
  <div class="item">
    <?=h($e['type'])?>
    <div class="meta"><?=h($e['repo']['name'] ?? '')?></div>
  </div>
<?php endforeach; ?>
</div>

<div class="card">
<h2>REPOSITORIES</h2>
<?php foreach($repos as $r): ?>
  <div class="item">
    <a href="<?=$r['html_url']?>" target="_blank"><?=$r['name']?></a>
    <div class="meta">★<?=$r['stargazers_count']?> · forks <?=$r['forks_count']?></div>
  </div>
<?php endforeach; ?>
</div>

</div>
</div>
</body>
</html>
