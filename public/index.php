<?php
session_start();
require __DIR__.'/../src/GitHubClient.php';
$config = require '/etc/kuzfollow/config.php';
function h($s){return htmlspecialchars($s,ENT_QUOTES);}

$gh = new GitHubClient($config['github_token']);
$user = $config['github_user'];

/* ACTIONS + DRY-RUN */
$preview = null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $act=$_POST['action']??''; $users=$_POST['users']??[]; $dry=isset($_POST['dry']);
  if($dry){
    $preview = ['action'=>$act,'users'=>$users];
  }else{
    foreach($users as $u){
      if($act==='follow') $gh->follow($u);
      if($act==='unfollow') $gh->unfollow($u);
      usleep(300000);
    }
    header('Location:/'); exit;
  }
}

/* DATA */
$followers = $gh->followers($user);
$following = $gh->following($user);
$repos     = $gh->repos($user);
$events    = $gh->events($user);

$fol = array_column($followers,'login');
$ing = array_column($following,'login');

$youDontFollowBack = array_values(array_diff($fol,$ing));
$theyDontFollowYou = array_values(array_diff($ing,$fol));

$recentFollowers = array_slice($followers,0,10);
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
  <div class="kv">
    <span class="pill">followers <?=count($followers)?></span>
    <span class="pill">following <?=count($following)?></span>
    <span class="pill">repos <?=count($repos)?></span>
    <span class="pill"><a href="#" onclick="openEvents();return false;">events</a></span>
  </div>
</div>

<?php if($preview): ?>
<div class="card">
  <h2>DRY-RUN PREVIEW</h2>
  <div class="meta">action: <?=$preview['action']?></div>
  <?php foreach($preview['users'] as $u): ?><div class="item"><?=$u?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid">

<div class="card">
<h2>10 RECENT FOLLOWERS</h2>
<?php foreach($recentFollowers as $f): ?>
  <div class="item"><?=$f['login']?></div>
<?php endforeach; ?>
</div>

<div class="card">
<h2>FOLLOW BACK</h2>
<form method="post">
<input type="hidden" name="action" value="follow">
<?php foreach($youDontFollowBack as $u): ?>
  <div class="item"><label><input type="checkbox" name="users[]" value="<?=$u?>"> <?=$u?></label></div>
<?php endforeach; ?>
<label class="meta"><input type="checkbox" name="dry"> dry-run</label>
<button class="btn">FOLLOW</button>
</form>
</div>

<div class="card">
<h2>UNFOLLOW (NON MUTUAL)</h2>
<form method="post">
<input type="hidden" name="action" value="unfollow">
<?php foreach($theyDontFollowYou as $u): ?>
  <div class="item"><label><input type="checkbox" name="users[]" value="<?=$u?>"> <?=$u?></label></div>
<?php endforeach; ?>
<label class="meta"><input type="checkbox" name="dry"> dry-run</label>
<button class="btn alt">UNFOLLOW</button>
</form>
</div>

<div class="card">
<h2>REPOSITORIES</h2>
<?php foreach(array_slice($repos,0,12) as $r): ?>
  <div class="item">
    <a href="<?=$r['html_url']?>" target="_blank"><?=$r['name']?></a>
    <div class="meta">★<?=$r['stargazers_count']?> · forks <?=$r['forks_count']?></div>
  </div>
<?php endforeach; ?>
</div>

</div>
</div>

<!-- MODAL EVENTS -->
<div id="events" class="modal">
  <div class="box">
    <div class="head">
      <h2>EVENTS (LATEST)</h2>
      <button class="btn" onclick="closeEvents()">CLOSE</button>
    </div>
    <div class="body">
      <?php foreach(array_slice($events,0,30) as $e): ?>
        <div class="item">
          <?=h($e['type'])?>
          <div class="meta"><?=h($e['repo']['name']??'')?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function openEvents(){document.getElementById('events').classList.add('open')}
function closeEvents(){document.getElementById('events').classList.remove('open')}
</script>

</body>
</html>
