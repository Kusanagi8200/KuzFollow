<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/../src/GitHubClient.php';
$config = require '/etc/kuzfollow/config.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$user  = (string)($config['github_user'] ?? '');
$token = (string)($config['github_token'] ?? '');
if ($user === '' || $token === '') { http_response_code(500); exit('Server not configured'); }

$gh = new GitHubClient($token);
$preview = null;

/* ===== ACTIONS + DRY-RUN ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $users  = $_POST['users'] ?? [];
  $dry    = isset($_POST['dry']);

  if (!is_array($users)) $users = [];
  $users = array_values(array_unique(array_values(array_filter(array_map('trim', array_map('strval', $users))))));

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
$repoCards = $gh->reposWithLatestCommits($repos, 12);
$events    = $gh->eventsPublic($user, 30);

$fol = array_column($followers, 'login');
$ing = array_column($following, 'login');

$youDontFollowBack = array_values(array_diff($fol, $ing));
$theyDontFollowYou = array_values(array_diff($ing, $fol));

sort($youDontFollowBack);
sort($theyDontFollowYou);

/* ===== DAILY SNAPSHOT (followers over time) ===== */
$historyFile = '/var/lib/kuzfollow/followers_history.json';

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
<html lang="en">
<head>
<meta charset="utf-8">
<title>KUZFOLLOW-GITHUB</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="wrap">

<header class="control-header">
  <div class="control-header__top">
    <div class="brand-block">
      <div class="brand-kicker">THE KUZ NETWORK // LOCAL CONTROL INTERFACE</div>
      <div class="brand-title">KUZFOLLOW</div>
      <div class="brand-subtitle">GITHUB NETWORK OPERATIONS // LOCAL / SELF-HOSTED</div>
    </div>
    <div class="header-status">
      <span class="status-led" aria-hidden="true"></span>
      <span>ONLINE</span>
      <span class="status-separator">//</span>
      <span>BETA-0.1.2025</span>
    </div>
  </div>

  <div class="control-header__account">
    <div class="account-identity">
      <div class="eyebrow">GITHUB ACCOUNT</div>
      <h1><?=h($user)?></h1>
      <div class="account-caption">NETWORK RECIPROCITY / REPOSITORY ACTIVITY / FOLLOW CONTROL</div>
    </div>

    <div class="header-metrics">
      <div class="header-metric">
        <span class="header-metric__label">FOLLOWERS</span>
        <strong><?=count($followers)?></strong>
      </div>
      <div class="header-metric">
        <span class="header-metric__label">FOLLOWING</span>
        <strong><?=count($following)?></strong>
      </div>
      <div class="header-metric">
        <span class="header-metric__label">REPOSITORIES</span>
        <strong><?=count($repos)?></strong>
      </div>
      <a class="header-metric header-metric--link" href="#" onclick="openEvents();return false;">
        <span class="header-metric__label">PUBLIC ACTIVITY</span>
        <strong>EVENTS</strong>
      </a>
    </div>
  </div>
</header>

<?php if ($preview): ?>
<section class="card preview-card">
  <div class="eyebrow">SAFE PREVIEW</div>
  <h2>DRY-RUN</h2>
  <div class="meta">ACTION <?=h(strtoupper($preview['action']))?> // TARGETS <?=count($preview['users'])?></div>
  <?php foreach ($preview['users'] as $u): ?>
    <div class="item"><?=h($u)?></div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="section-header">
  <div class="section-number">01</div>
  <div class="section-heading">
    <div class="section-kicker">CODE ACTIVITY</div>
    <h2>REPOSITORIES</h2>
    <p>Owner repositories ranked by latest commit activity.</p>
  </div>
  <div class="section-tech">
    <span>OWNER</span>
    <span>LATEST COMMIT</span>
    <span>UTC</span>
  </div>
</section>

<section class="card repo-panel">
  <div class="panel-heading repo-panel-heading">
    <div>
      <div class="eyebrow">REPOSITORY ACTIVITY</div>
      <h2>LATEST OWNER REPOSITORIES</h2>
      <p class="panel-description">Latest code activity with commit identity, author and UTC timestamp.</p>
    </div>
    <div class="panel-count"><?=count($repoCards)?> / <?=count($repos)?> SHOWN</div>
  </div>

  <?php if (!$repoCards): ?>
    <div class="item empty-state"><div class="meta">NO REPOSITORIES AVAILABLE</div></div>
  <?php else: ?>
    <div class="repo-grid">
      <?php foreach ($repoCards as $r): ?>
        <?php
          $commit = is_array($r['latest_commit'] ?? null) ? $r['latest_commit'] : null;
          $message = $commit ? trim((string)($commit['commit']['message'] ?? '')) : '';
          $message = explode("\n", $message, 2)[0];
          $sha = $commit ? substr((string)($commit['sha'] ?? ''), 0, 7) : '';
          $author = $commit
            ? (string)($commit['author']['login'] ?? $commit['commit']['author']['name'] ?? 'UNKNOWN')
            : '';
          $commitDate = $commit
            ? (string)($commit['commit']['committer']['date'] ?? $commit['commit']['author']['date'] ?? '')
            : '';
          $timestamp = $commitDate !== '' ? strtotime($commitDate) : false;
          $displayDate = $timestamp !== false ? gmdate('Y-m-d H:i \U\T\C', $timestamp) : 'UNKNOWN DATE';
        ?>
        <article class="repo-item">
          <div class="repo-topline">
            <div class="repo-identity">
              <div class="repo-label">REPOSITORY</div>
              <a class="repo-name" href="<?=h((string)($r['html_url'] ?? '#'))?>" target="_blank" rel="noopener noreferrer"><?=h((string)($r['name'] ?? 'repo'))?></a>
            </div>
            <span class="repo-language"><?=h((string)($r['language'] ?? 'N/A'))?></span>
          </div>

          <div class="repo-facts">
            <div><span>STARS</span><strong><?= (int)($r['stargazers_count'] ?? 0) ?></strong></div>
            <div><span>FORKS</span><strong><?= (int)($r['forks_count'] ?? 0) ?></strong></div>
          </div>

          <?php if ($commit): ?>
            <div class="commit-block">
              <div class="commit-label">LATEST COMMIT</div>
              <div class="commit-message"><?=h($message !== '' ? $message : 'NO COMMIT MESSAGE')?></div>
              <div class="commit-meta">
                <a href="<?=h((string)($commit['html_url'] ?? '#'))?>" target="_blank" rel="noopener noreferrer"><?=h($sha)?></a>
                <span><b>AUTHOR</b> <?=h($author)?></span>
                <time datetime="<?=h($commitDate)?>"><b>UTC</b> <?=h($displayDate)?></time>
              </div>
            </div>
          <?php else: ?>
            <div class="commit-block commit-block--empty">
              <div class="commit-label">LATEST COMMIT</div>
              <div class="commit-message">COMMIT UNAVAILABLE</div>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="section-header">
  <div class="section-number">02</div>
  <div class="section-heading">
    <div class="section-kicker">NETWORK CONTROL</div>
    <h2>FOLLOWERS &amp; FOLLOWING</h2>
    <p>Review reciprocity, apply selected changes, and monitor follower history.</p>
  </div>
  <div class="section-tech">
    <span>FOLLOW BACK</span>
    <span>NON-MUTUAL</span>
    <span>HISTORY</span>
  </div>
</section>

<div class="grid network-grid">
  <section class="card network-card">
    <div class="panel-heading">
      <div>
        <div class="eyebrow">INBOUND</div>
        <h2>FOLLOW BACK</h2>
      </div>
      <div class="panel-count"><?=count($youDontFollowBack)?> TARGETS</div>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="follow">
      <?php if (!$youDontFollowBack): ?>
        <div class="item empty-state"><div class="meta">NO ACCOUNTS TO FOLLOW BACK</div></div>
      <?php else: ?>
        <?php foreach ($youDontFollowBack as $u): ?>
          <label class="item selectable"><input type="checkbox" name="users[]" value="<?=h($u)?>"> <span><?=h($u)?></span></label>
        <?php endforeach; ?>
      <?php endif; ?>
      <label class="dry-run"><input type="checkbox" name="dry"> DRY-RUN</label>
      <button class="btn">FOLLOW SELECTED</button>
    </form>
  </section>

  <section class="card network-card">
    <div class="panel-heading">
      <div>
        <div class="eyebrow">OUTBOUND</div>
        <h2>UNFOLLOW NON-MUTUAL</h2>
      </div>
      <div class="panel-count"><?=count($theyDontFollowYou)?> TARGETS</div>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="unfollow">
      <?php if (!$theyDontFollowYou): ?>
        <div class="item empty-state"><div class="meta">NO NON-MUTUAL ACCOUNTS</div></div>
      <?php else: ?>
        <?php foreach ($theyDontFollowYou as $u): ?>
          <label class="item selectable"><input type="checkbox" name="users[]" value="<?=h($u)?>"> <span><?=h($u)?></span></label>
        <?php endforeach; ?>
      <?php endif; ?>
      <label class="dry-run"><input type="checkbox" name="dry"> DRY-RUN</label>
      <button class="btn alt">UNFOLLOW SELECTED</button>
    </form>
  </section>

  <section class="card graph-card">
    <div class="panel-heading">
      <div>
        <div class="eyebrow">HISTORY</div>
        <h2>FOLLOWERS OVER TIME</h2>
      </div>
      <div class="panel-count">180 DAYS MAX</div>
    </div>
    <div class="graph-frame">
      <img src="/graph.svg.php" alt="Followers over time">
    </div>
  </section>
</div>

<footer class="footer-line">THE KUZ NETWORK // BUILD LOCAL // KEEP CONTROL</footer>
</div>

<div id="events" class="modal" role="dialog" aria-modal="true" aria-labelledby="events-title">
  <div class="box">
    <div class="head">
      <div>
        <div class="eyebrow">PUBLIC ACTIVITY</div>
        <h2 id="events-title">EVENTS</h2>
      </div>
      <button class="btn" type="button" onclick="closeEvents()">CLOSE</button>
    </div>
    <div class="body">
      <?php foreach (array_slice($events, 0, 30) as $e): ?>
        <div class="item event-item">
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
