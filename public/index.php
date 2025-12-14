<?php
declare(strict_types=1);

session_start();

$config = require '/etc/kuzfollow/config.php';
require __DIR__ . '/../src/GitHubClient.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  $t = (string)($_POST['csrf'] ?? '');
  if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $t)) {
    http_response_code(403);
    exit('CSRF');
  }
}

$user  = (string)($config['github_user'] ?? '');
$token = (string)($config['github_token'] ?? '');
$perPage = (int)($config['per_page'] ?? 100);

if ($user === '' || $token === '' || $token === 'REMPLACE_PAR_NOUVEAU_TOKEN') {
  http_response_code(500);
  exit('Server not configured: set /etc/kuzfollow/config.php');
}

$gh = new GitHubClient($token, $perPage);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

$status = $_SESSION['status'] ?? null;
unset($_SESSION['status']);

try {
  if ($path === '/action/follow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $targets = $_POST['users'] ?? [];
    if (!is_array($targets)) $targets = [];
    $ok=0; $fail=0;

    foreach ($targets as $u) {
      $u = trim((string)$u);
      if ($u === '' || strlen($u) > 39) continue;
      try { $gh->follow($u); $ok++; } catch (Throwable) { $fail++; }
      usleep(350000);
    }
    $_SESSION['status'] = "Follow: OK={$ok} FAIL={$fail}";
    header('Location: /'); exit;
  }

  if ($path === '/action/unfollow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $targets = $_POST['users'] ?? [];
    if (!is_array($targets)) $targets = [];
    $ok=0; $fail=0;

    foreach ($targets as $u) {
      $u = trim((string)$u);
      if ($u === '' || strlen($u) > 39) continue;
      try { $gh->unfollow($u); $ok++; } catch (Throwable) { $fail++; }
      usleep(350000);
    }
    $_SESSION['status'] = "Unfollow: OK={$ok} FAIL={$fail}";
    header('Location: /'); exit;
  }

  $me = $gh->getUser($user);
  $followers = $gh->followers($user);
  $following = $gh->following($user);

  $followersLogins = array_flip(array_map(fn($x) => $x['login'], $followers));
  $followingLogins = array_flip(array_map(fn($x) => $x['login'], $following));

  $notFollowingBack = [];
  foreach ($followingLogins as $login => $_) {
    if (!isset($followersLogins[$login])) $notFollowingBack[] = $login;
  }

  $youDontFollowBack = [];
  foreach ($followersLogins as $login => $_) {
    if (!isset($followingLogins[$login])) $youDontFollowBack[] = $login;
  }

  sort($notFollowingBack);
  sort($youDontFollowBack);

} catch (Throwable $e) {
  http_response_code(500);
  exit("Error: " . h($e->getMessage()));
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>KuzFollow</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family:sans-serif;max-width:1100px;margin:30px auto;padding:0 12px;">
  <h1>KuzFollow</h1>

  <?php if ($status): ?>
    <div style="padding:10px;border:1px solid #999;margin:10px 0;"><?=h($status)?></div>
  <?php endif; ?>

  <h2>User</h2>
  <ul>
    <li><b>Login:</b> <?=h((string)($me['login'] ?? 'N/A'))?></li>
    <li><b>Name:</b> <?=h((string)($me['name'] ?? 'N/A'))?></li>
    <li><b>Followers:</b> <?=count($followers)?></li>
    <li><b>Following:</b> <?=count($following)?></li>
  </ul>

  <h2>Non mutual (you follow, they don't)</h2>
  <form method="post" action="/action/unfollow" onsubmit="return confirm('Unfollow selected accounts?');">
    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
    <?php if (count($notFollowingBack) === 0): ?>
      <div>None.</div>
    <?php else: ?>
      <?php foreach ($notFollowingBack as $u): ?>
        <label style="display:block"><input type="checkbox" name="users[]" value="<?=h($u)?>"> <?=h($u)?></label>
      <?php endforeach; ?>
      <button type="submit" style="margin-top:10px">Unfollow selected</button>
    <?php endif; ?>
  </form>

  <h2>Followers you don’t follow back</h2>
  <form method="post" action="/action/follow" onsubmit="return confirm('Follow selected accounts?');">
    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
    <?php if (count($youDontFollowBack) === 0): ?>
      <div>None.</div>
    <?php else: ?>
      <?php foreach ($youDontFollowBack as $u): ?>
        <label style="display:block"><input type="checkbox" name="users[]" value="<?=h($u)?>"> <?=h($u)?></label>
      <?php endforeach; ?>
      <button type="submit" style="margin-top:10px">Follow selected</button>
    <?php endif; ?>
  </form>
</body>
</html>
