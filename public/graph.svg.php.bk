<?php
declare(strict_types=1);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store');

$historyFile = __DIR__ . '/../data/followers_history.json';
$history = [];

if (is_file($historyFile)) {
  $raw = file_get_contents($historyFile);
  $j = json_decode($raw ?: '[]', true);
  if (is_array($j)) $history = $j;
}

/*
  Format attendu :
  [
    {"date":"2025-12-01","followers":123},
    {"date":"2025-12-02","followers":126},
    ...
  ]
*/

if (count($history) < 2) {
  echo '<?xml version="1.0" encoding="UTF-8"?>';
  echo '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="220" viewBox="0 0 900 220">';
  echo '<rect x="0" y="0" width="900" height="220" fill="rgba(120,200,255,0.12)" stroke="rgba(120,220,255,0.55)"/>';
  echo '<text x="20" y="40" fill="#ffffff" font-family="system-ui,Segoe UI,Roboto" font-size="14">FOLLOWERS OVER TIME</text>';
  echo '<text x="20" y="80" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="12">Not enough data yet. Need at least 2 daily snapshots.</text>';
  echo '</svg>';
  exit;
}

$W = 900; $H = 220;
$padL = 56; $padR = 18; $padT = 22; $padB = 42;
$plotW = $W - $padL - $padR;
$plotH = $H - $padT - $padB;

$vals = array_map(fn($p) => (int)($p['followers'] ?? 0), $history);
$minV = min($vals);
$maxV = max($vals);
if ($minV === $maxV) { $minV = $minV - 1; $maxV = $maxV + 1; }

$n = count($history);
$pts = [];

for ($i=0; $i<$n; $i++) {
  $x = $padL + ($plotW * ($i / max($n-1,1)));
  $v = (int)($history[$i]['followers'] ?? 0);
  $t = ($v - $minV) / ($maxV - $minV);
  $y = $padT + ($plotH * (1.0 - $t));
  $pts[] = [$x, $y, $v, (string)($history[$i]['date'] ?? '')];
}

$poly = implode(' ', array_map(fn($p) => round($p[0],2).','.round($p[1],2), $pts));

$last = $pts[$n-1][2];
$first = $pts[0][2];
$delta = $last - $first;
$sign = $delta >= 0 ? '+' : '';
$rangeLabel = $pts[0][3] . ' → ' . $pts[$n-1][3];

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $W ?>" height="<?= $H ?>" viewBox="0 0 <?= $W ?> <?= $H ?>">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="rgba(120,220,255,0.28)"/>
      <stop offset="1" stop-color="rgba(120,220,255,0.03)"/>
    </linearGradient>
    <filter id="glow" x="-40%" y="-40%" width="180%" height="180%">
      <feGaussianBlur stdDeviation="2.8" result="b"/>
      <feMerge>
        <feMergeNode in="b"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>
  </defs>

  <rect x="0" y="0" width="<?= $W ?>" height="<?= $H ?>" fill="rgba(120,200,255,0.12)" stroke="rgba(120,220,255,0.55)"/>

  <text x="18" y="24" fill="#7fdcff" font-family="system-ui,Segoe UI,Roboto" font-size="12" letter-spacing="2">FOLLOWERS OVER TIME</text>
  <text x="18" y="44" fill="#ffffff" font-family="ui-monospace,Menlo,Consolas" font-size="12"><?= htmlspecialchars($rangeLabel) ?></text>

  <text x="<?= $W-18 ?>" y="24" text-anchor="end" fill="#ffffff" font-family="ui-monospace,Menlo,Consolas" font-size="12">
    now <?= (int)$last ?> (<?= $sign.(int)$delta ?>)
  </text>

  <?php
    // grid: 4 lignes
    for ($g=0; $g<=4; $g++) {
      $y = $padT + ($plotH * ($g/4));
      $v = (int)round($maxV - (($maxV-$minV)*($g/4)));
      echo '<line x1="'.$padL.'" y1="'.round($y,2).'" x2="'.($padL+$plotW).'" y2="'.round($y,2).'" stroke="rgba(255,255,255,0.10)"/>';
      echo '<text x="'.($padL-10).'" y="'.round($y+4,2).'" text-anchor="end" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="10">'.$v.'</text>';
    }
  ?>

  <!-- area -->
  <path d="<?php
      $d = 'M '.$padL.' '.($padT+$plotH).' L ';
      foreach ($pts as $p) $d .= round($p[0],2).' '.round($p[1],2).' L ';
      $d .= ($padL+$plotW).' '.($padT+$plotH).' Z';
      echo $d;
    ?>" fill="url(#g)" />

  <!-- line -->
  <polyline points="<?= $poly ?>" fill="none" stroke="#33ccff" stroke-width="2.2" filter="url(#glow)"/>

  <!-- last point -->
  <?php $lp = $pts[$n-1]; ?>
  <circle cx="<?= round($lp[0],2) ?>" cy="<?= round($lp[1],2) ?>" r="4.5" fill="#ffffff" stroke="#33ccff" stroke-width="2"/>

  <!-- x labels: first & last -->
  <text x="<?= $padL ?>" y="<?= $H-16 ?>" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="10"><?= htmlspecialchars($pts[0][3]) ?></text>
  <text x="<?= $padL+$plotW ?>" y="<?= $H-16 ?>" text-anchor="end" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="10"><?= htmlspecialchars($pts[$n-1][3]) ?></text>
</svg>
