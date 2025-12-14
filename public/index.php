<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/../src/GitHubClient.php';
$config = require '/etc/kuzfollow/config.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$user  = (string)($config['github_user'] ?? '');
$token = (string)($config['github_token'] ?? '');

if ($user === '' || $token === '' || $token === 'REMPLACE_PAR_NOUVEAU_TOKEN') {
  http_response_code(500);
  exit('Server not configured: /etc/kuzfollow/config.php');
}

$gh = new GitHubClient($token);

$preview = null;

/* ===== ACTIONS + DRY-RUN ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $users  = $_POST['users'] ?? [];
  $dry    = isset($_POST['dry']);

  if (!is_array($users)) $users = [];

  $users = array_values(array_filter(array_map('trim', array_map('strval', $users))));
  $users = array_values(array_unique($users));

  if ($dry) {
    $preview = ['action' => $action, 'users' => $users];
  } else {
    foreach ($users as $u) {
      if ($action === 'follow')   $gh->follow($u);
      if ($action === 'unfollow') $gh->unfollow($u);
      usleep(300000);
    }
    header('Location: /');
    exit;
  }
}

/* ===== DATA ===== */
$followers = $gh->followers($user);
$following = $gh->following($user);
$repos     = $gh->reposOwnerSorted($user);
$events    = $gh->eventsPublic($user, 30);

/* ===== DIFFS ===== */
$fol = array_column($followers, 'login');
$ing = array_column($following, 'login');

$youDontFollowBack = array_values(array_diff($fol, $ing)); // followers you don't follow
$theyDontFollowYou = array_values(array_diff($ing, $fol)); // following who don't follow back

sort($youDontFollowBack);
sort($theyDontFollowYou);

/* ===== 10 RECENT FOLLOWERS ===== */
$recentFollowers = array_slice($followers, 0, 10);

/* ===== DAILY SNAPSHOT (followers over time) ===== */
$dataDir = __DIR__ . '/../data';
$historyFile = $dataDir . '/followers_history.json';
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);

$today = gmdate('Y-m-d');
$countFollowers = count($followers);

$hist = [];
if (is_file($historyFile)) {
  $raw = file_get_contents($historyFile);
  $j = json_decode($raw ?: '[]', true);
  if (is_array($j)) $hist = $j;
}

$last = end($hist);
$needsAppend = !is_array($last) || (($last['date'] ?? '') !== $today);

if ($needsAppend) {
  $hist[] = ['date' => $today, 'followers' => $countFollowers];
  if (count($hist) > 180) $hist = array_slice($hist, -180);
  file_put_contents($historyFile, json_encode(array_values($hist), JSON_UNESCAPED_SLASHES));
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>KUZFOLLOW</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
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

<?php if ($preview): ?>
<div class="card">
  <h2>DRY-RUN PREVIEW</h2>
  <div class="meta">action: <?=h($preview['action'])?> · targets: <?=count($preview['users'])?></div>
  <?php foreach ($preview['users'] as $u): ?>
    <div class="item"><?=h($u)?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid">

  <div class="card">
    <h2>10 RECENT FOLLOWERS</h2>
    <?php if (!$recentFollowers): ?>
      <div class="item"><div class="meta">none</div></div>
    <?php else: ?>
      <?php foreach ($recentFollowers as $f): ?>
        <div class="item"><?=h((string)($f['login'] ?? ''))?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>FOLLOW BACK</h2>
    <form method="post">
      <input type="hidden" name="action" value="follow">
      <?php if (!$youDontFollowBack): ?>
        <div class="item"><div class="meta">none</div></div>
      <?php else: ?>
        <?php foreach ($youDontFollowBack as $u): ?>
          <div class="item"><label><input type="checkbox" name="users[]" value="<?=h($u)?>"> <?=h($u)?></label></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <label class="meta"><input type="checkbox" name="dry"> dry-run</label><br>
      <button class="btn">FOLLOW SELECTED</button>
    </form>
  </div>

  <div class="card">
    <h2>UNFOLLOW (NON MUTUAL)</h2>
    <form method="post">
      <input type="hidden" name="action" value="unfollow">
      <?php if (!$theyDontFollowYou): ?>
        <div class="item"><div class="meta">none</div></div>
      <?php else: ?>
        <?php foreach ($theyDontFollowYou as $u): ?>
          <div class="item"><label><input type="checkbox" name="users[]" value="<?=h($u)?>"> <?=h($u)?></label></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <label class="meta"><input type="checkbox" name="dry"> dry-run</label><br>
      <button class="btn alt">UNFOLLOW SELECTED</button>
    </form>
  </div>

  <div class="card">
    <h2>REPOSITORIES (OWNER / UPDATED)</h2>
    <?php foreach (array_slice($repos, 0, 12) as $r): ?>
      <div class="item">
        <a href="<?=h((string)($r['html_url'] ?? '#'))?>" target="_blank"><?=h((string)($r['name'] ?? 'repo'))?></a>
        <div class="meta">★<?= (int)($r['stargazers_count'] ?? 0) ?> · forks <?= (int)($r['forks_count'] ?? 0) ?> · <?=h((string)($r['language'] ?? ''))?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card" style="grid-column:1/-1;">
    <h2>FOLLOWERS OVER TIME</h2>
    <div class="item" style="padding:10px;">
      <img src="/graph.svg.php" alt="Followers over time" style="width:100%;height:auto;display:block;">
      <div class="meta">daily snapshot (UTC): <?=h($today)?> · current followers: <?=count($followers)?></div>
    </div>
  </div>

</div>
</div>

<div id="events" class="modal">
  <div class="box">
    <div class="head">
      <h2>EVENTS</h2>
      <button class="btn" onclick="closeEvents()">CLOSE</button>
    </div>
    <div class="body">
      <?php foreach (array_slice($events, 0, 30) as $e): ?>
        <div class="item">
          <?=h((string)($e['type'] ?? 'Event'))?>
          <div class="meta"><?=h((string)($e['repo']['name'] ?? ''))?></div>
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
