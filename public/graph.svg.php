<?php
declare(strict_types=1);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store');

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$BUILD = 'KUZFOLLOW_GRAPH_BUILD_2025-12-14_1';

$W = 980; $H = 260;
$historyPath = __DIR__ . '/../data/followers_history.json';

$history = [];
$err = null;

try {
    if (!is_file($historyPath)) throw new RuntimeException('history file not found');
    if (!is_readable($historyPath)) throw new RuntimeException('history file not readable');

    $raw = file_get_contents($historyPath);
    if ($raw === false || trim($raw) === '') throw new RuntimeException('history file empty');

    $j = json_decode($raw, true);
    if (!is_array($j)) throw new RuntimeException('history json invalid');

    foreach ($j as $p) {
        if (!is_array($p)) continue;
        $d = (string)($p['date'] ?? '');
        $v = (int)($p['followers'] ?? -1);
        if ($d === '' || $v < 0) continue;
        $history[] = ['date' => $d, 'followers' => $v];
    }

    if (count($history) < 2) throw new RuntimeException('not enough points (<2)');
} catch (Throwable $e) {
    $err = $e->getMessage();
}

echo '<?xml version="1.0" encoding="UTF-8"?>';

if ($err !== null) {
    $size = is_file($historyPath) ? (string)filesize($historyPath) : 'N/A';
    ?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $W ?>" height="<?= $H ?>" viewBox="0 0 <?= $W ?> <?= $H ?>">
  <rect x="0" y="0" width="<?= $W ?>" height="<?= $H ?>" fill="rgba(120,200,255,0.12)" stroke="rgba(120,220,255,0.55)"/>
  <text x="18" y="28" fill="#7fdcff" font-family="system-ui,Segoe UI,Roboto" font-size="12" letter-spacing="2">FOLLOWERS OVER TIME</text>
  <text x="18" y="52" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="11"><?= esc($BUILD) ?></text>
  <text x="18" y="78" fill="#ffffff" font-family="ui-monospace,Menlo,Consolas" font-size="12"><?= esc("ERROR: ".$err) ?></text>
  <text x="18" y="102" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="11"><?= esc("FILE: ".$historyPath) ?></text>
  <text x="18" y="120" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="11"><?= esc("SIZE: ".$size." bytes") ?></text>
</svg>
<?php
    exit;
}

$padL = 64; $padR = 18; $padT = 28; $padB = 52;
$plotW = $W - $padL - $padR;
$plotH = $H - $padT - $padB;

$vals = array_map(fn($p) => (int)$p['followers'], $history);
$minV = min($vals); $maxV = max($vals);
if ($minV === $maxV) { $minV -= 1; $maxV += 1; }

$n = count($history);
$pts = [];
for ($i=0; $i<$n; $i++) {
    $x = $padL + ($plotW * ($i / max($n-1,1)));
    $v = (int)$history[$i]['followers'];
    $t = ($v - $minV) / ($maxV - $minV);
    $y = $padT + ($plotH * (1.0 - $t));
    $pts[] = [$x, $y, $v, (string)$history[$i]['date']];
}

$poly = implode(' ', array_map(fn($p)=>round($p[0],2).','.round($p[1],2), $pts));
$first = $pts[0][2]; $last = $pts[$n-1][2];
$delta = $last - $first; $sign = $delta >= 0 ? '+' : '';
$rangeLabel = $pts[0][3] . ' → ' . $pts[$n-1][3];

?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $W ?>" height="<?= $H ?>" viewBox="0 0 <?= $W ?> <?= $H ?>">
  <defs>
    <linearGradient id="area" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="rgba(120,220,255,0.30)"/>
      <stop offset="1" stop-color="rgba(120,220,255,0.04)"/>
    </linearGradient>
    <filter id="glow" x="-40%" y="-40%" width="180%" height="180%">
      <feGaussianBlur stdDeviation="2.8" result="b"/>
      <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
  </defs>

  <rect x="0" y="0" width="<?= $W ?>" height="<?= $H ?>" fill="rgba(120,200,255,0.12)" stroke="rgba(120,220,255,0.55)"/>

  <text x="18" y="28" fill="#7fdcff" font-family="system-ui,Segoe UI,Roboto" font-size="12" letter-spacing="2">FOLLOWERS OVER TIME</text>
  <text x="18" y="52" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="11"><?= esc($BUILD) ?></text>
  <text x="18" y="76" fill="#ffffff" font-family="ui-monospace,Menlo,Consolas" font-size="12"><?= esc($rangeLabel) ?></text>
  <text x="<?= $W-18 ?>" y="28" text-anchor="end" fill="#ffffff" font-family="ui-monospace,Menlo,Consolas" font-size="12">now <?= (int)$last ?> (<?= $sign.(int)$delta ?>)</text>

  <?php for ($g=0; $g<=4; $g++):
      $y = $padT + ($plotH * ($g/4));
      $v = (int)round($maxV - (($maxV-$minV)*($g/4)));
  ?>
    <line x1="<?= $padL ?>" y1="<?= round($y,2) ?>" x2="<?= $padL+$plotW ?>" y2="<?= round($y,2) ?>" stroke="rgba(255,255,255,0.10)"/>
    <text x="<?= $padL-10 ?>" y="<?= round($y+4,2) ?>" text-anchor="end" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="10"><?= $v ?></text>
  <?php endfor; ?>

  <path d="<?php
      $d = 'M '.$padL.' '.($padT+$plotH).' L ';
      foreach ($pts as $p) $d .= round($p[0],2).' '.round($p[1],2).' L ';
      $d .= ($padL+$plotW).' '.($padT+$plotH).' Z';
      echo $d;
    ?>" fill="url(#area)" />

  <polyline points="<?= $poly ?>" fill="none" stroke="#33ccff" stroke-width="2.3" filter="url(#glow)"/>

  <?php $lp = $pts[$n-1]; ?>
  <circle cx="<?= round($lp[0],2) ?>" cy="<?= round($lp[1],2) ?>" r="4.8" fill="#ffffff" stroke="#33ccff" stroke-width="2"/>

  <text x="<?= $padL ?>" y="<?= $H-18 ?>" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="10"><?= esc($pts[0][3]) ?></text>
  <text x="<?= $padL+$plotW ?>" y="<?= $H-18 ?>" text-anchor="end" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="10"><?= esc($pts[$n-1][3]) ?></text>
</svg>
