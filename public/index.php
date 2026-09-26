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
<title>KUZFOLLOW - GITHUB NETWORK CONTROL</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#000000">
<meta name="color-scheme" content="dark">
<link rel="stylesheet" href="/assets/style.css?v=3.0.1">
</head>
<body>
<div class="site-shell">

  <header class="site-header" aria-label="KuzFollow header">
    <div class="brand-mark" aria-label="The Kuz Network">
      <img class="brand-mark__logo" src="/assets/kuz_network_logo_transparent.svg" alt="KUZ Network logo" width="112" height="112">
    </div>

    <div class="header-title-block" aria-label="Project title">
      <h1 class="header-title-block__title">KUZFOLLOW - GITHUB CONTROL</h1>
      <p class="header-title-block__meta">A KUZ NETWORK SOLUTION - LOCAL / SELF-HOSTED</p>
    </div>

    <div class="site-header__right-spacer" aria-hidden="true"></div>
  </header>

  <main class="site-main">
    <section class="project-statement dynamic-invert" aria-label="KuzFollow status">
      <p>KUZFOLLOW / GITHUB NETWORK CONTROL / <?=count($followers)?> FOLLOWERS / <?=count($following)?> FOLLOWING / <?=count($repos)?> REPOSITORIES</p>
    </section>

    <section class="meta-grid" aria-label="GitHub account summary">
      <div class="meta-card dynamic-invert"><span>GITHUB ACCOUNT</span><strong><?=h($user)?></strong></div>
      <div class="meta-card dynamic-invert"><span>FOLLOWERS</span><strong><?=count($followers)?></strong></div>
      <div class="meta-card dynamic-invert"><span>FOLLOWING</span><strong><?=count($following)?></strong></div>
      <a class="meta-card dynamic-invert" href="#" onclick="openEvents();return false;"><span>PUBLIC ACTIVITY</span><strong>EVENTS</strong></a>
    </section>

    <?php if ($preview): ?>
      <section class="kuz-panel preview-panel">
        <div class="panel-title dynamic-invert">
          <span class="panel-index">DRY</span>
          <div><p class="section-kicker">SAFE PREVIEW</p><h2><?=h(strtoupper($preview['action']))?> / <?=count($preview['users'])?> TARGETS</h2></div>
        </div>
        <div class="target-list">
          <?php foreach ($preview['users'] as $u): ?>
            <div class="target-item"><?=h($u)?></div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="section-title dynamic-invert" aria-labelledby="repo-section-title">
      <p class="section-kicker section-kicker--offset">CODE ACTIVITY</p>
      <div class="section-heading-line">
        <span class="section-number">01</span>
        <h2 id="repo-section-title">REPOSITORIES</h2>
      </div>
      <p class="section-description section-description--offset">Owner repositories ranked by latest commit activity.</p>
    </section>

    <section class="kuz-panel repo-panel">
      <div class="panel-heading">
        <div>
          <p class="section-kicker">OWNER / LATEST COMMIT / UTC</p>
          <h3>LATEST OWNER REPOSITORIES</h3>
          <p class="panel-description">Repository identity, code activity, commit author and timestamp.</p>
        </div>
        <div class="panel-counter"><?=count($repoCards)?> / <?=count($repos)?> SHOWN</div>
      </div>

      <?php if (!$repoCards): ?>
        <div class="empty-state">NO REPOSITORIES AVAILABLE</div>
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
            <article class="repo-card dynamic-invert">
              <div class="repo-card__top">
                <div>
                  <span class="repo-card__label">REPOSITORY</span>
                  <a class="repo-card__name" href="<?=h((string)($r['html_url'] ?? '#'))?>" target="_blank" rel="noopener noreferrer"><?=h((string)($r['name'] ?? 'repo'))?></a>
                </div>
                <span class="repo-card__language"><?=h((string)($r['language'] ?? 'N/A'))?></span>
              </div>

              <div class="repo-card__stats">
                <span>STARS <strong><?= (int)($r['stargazers_count'] ?? 0) ?></strong></span>
                <span>FORKS <strong><?= (int)($r['forks_count'] ?? 0) ?></strong></span>
              </div>

              <?php if ($commit): ?>
                <div class="commit-block">
                  <span class="repo-card__label">LATEST COMMIT</span>
                  <p class="commit-message"><?=h($message !== '' ? $message : 'NO COMMIT MESSAGE')?></p>
                  <div class="commit-meta">
                    <a href="<?=h((string)($commit['html_url'] ?? '#'))?>" target="_blank" rel="noopener noreferrer">COMMIT <?=h($sha)?></a>
                    <span>AUTHOR <?=h($author)?></span>
                    <time datetime="<?=h($commitDate)?>"><?=h($displayDate)?></time>
                  </div>
                </div>
              <?php else: ?>
                <div class="commit-block">
                  <span class="repo-card__label">LATEST COMMIT</span>
                  <p class="commit-message">COMMIT UNAVAILABLE</p>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="section-title dynamic-invert" aria-labelledby="network-section-title">
      <p class="section-kicker section-kicker--offset">NETWORK CONTROL</p>
      <div class="section-heading-line">
        <span class="section-number">02</span>
        <h2 id="network-section-title">FOLLOWERS &amp; FOLLOWING</h2>
      </div>
      <p class="section-description section-description--offset">Review reciprocity, apply selected changes, and monitor follower history.</p>
    </section>

    <div class="network-grid">
      <section class="kuz-panel network-card">
        <div class="panel-title dynamic-invert">
          <span class="panel-index">IN</span>
          <div><p class="section-kicker">INBOUND</p><h2>FOLLOW BACK</h2></div>
          <span class="panel-counter"><?=count($youDontFollowBack)?> TARGETS</span>
        </div>

        <form method="post">
          <input type="hidden" name="action" value="follow">
          <div class="target-list">
            <?php if (!$youDontFollowBack): ?>
              <div class="target-item empty-state">NO ACCOUNTS TO FOLLOW BACK</div>
            <?php else: ?>
              <?php foreach ($youDontFollowBack as $u): ?>
                <label class="target-item target-item--selectable"><input type="checkbox" name="users[]" value="<?=h($u)?>"><span><?=h($u)?></span></label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="form-actions">
            <label class="dry-run"><input type="checkbox" name="dry"> DRY-RUN</label>
            <button class="action-button" type="submit">FOLLOW SELECTED</button>
          </div>
        </form>
      </section>

      <section class="kuz-panel network-card">
        <div class="panel-title dynamic-invert">
          <span class="panel-index">OUT</span>
          <div><p class="section-kicker">OUTBOUND</p><h2>UNFOLLOW NON-MUTUAL</h2></div>
          <span class="panel-counter"><?=count($theyDontFollowYou)?> TARGETS</span>
        </div>

        <form method="post">
          <input type="hidden" name="action" value="unfollow">
          <div class="target-list">
            <?php if (!$theyDontFollowYou): ?>
              <div class="target-item empty-state">NO NON-MUTUAL ACCOUNTS</div>
            <?php else: ?>
              <?php foreach ($theyDontFollowYou as $u): ?>
                <label class="target-item target-item--selectable"><input type="checkbox" name="users[]" value="<?=h($u)?>"><span><?=h($u)?></span></label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="form-actions">
            <label class="dry-run"><input type="checkbox" name="dry"> DRY-RUN</label>
            <button class="action-button" type="submit">UNFOLLOW SELECTED</button>
          </div>
        </form>
      </section>

      <section class="kuz-panel graph-card">
        <div class="panel-title dynamic-invert">
          <span class="panel-index">HIS</span>
          <div><p class="section-kicker">HISTORY</p><h2>FOLLOWERS OVER TIME</h2></div>
          <span class="panel-counter">180 DAYS MAX</span>
        </div>
        <div class="graph-frame"><img src="/graph.svg.php?v=3.0.0" alt="Followers over time"></div>
      </section>
    </div>
  </main>

  <footer class="site-footer site-footer--dynamic" aria-label="KUZ Network footer">
    <div class="site-footer__inner">
      <p class="site-footer__text">THE KUZ NETWORK - @2026 / BUILD LOCAL / KEEP CONTROL / OWN THE STACK / KUZFOLLOW</p>
    </div>
  </footer>
</div>

<div id="events" class="modal" role="dialog" aria-modal="true" aria-labelledby="events-title">
  <div class="modal__box">
    <div class="modal__head">
      <div><p class="section-kicker">PUBLIC ACTIVITY</p><h2 id="events-title">EVENTS</h2></div>
      <button class="action-button" type="button" onclick="closeEvents()">CLOSE</button>
    </div>
    <div class="modal__body">
      <?php foreach (array_slice($events, 0, 30) as $e): ?>
        <div class="event-item dynamic-invert">
          <strong><?=h((string)($e['type'] ?? 'Event'))?></strong>
          <span><?=h((string)($e['repo']['name'] ?? ''))?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function openEvents(){document.getElementById('events').classList.add('is-open')}
function closeEvents(){document.getElementById('events').classList.remove('is-open')}
</script>
</body>
</html>
