<?php
declare(strict_types=1);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store');

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/* Canvas */
$W = 980; $H = 300;

/* Plot area (réserve header/footer => zéro chevauchement) */
$padL = 76;
$padR = 22;
$padT = 86;
$padB = 58;

$plotW = $W - $padL - $padR;
$plotH = $H - $padT - $padB;

/* History */
$pathA = __DIR__ . '/../data/followers_history.json';
$pathB = __DIR__ . '/followers_history.json';
$historyPath = is_file($pathA) ? $pathA : (is_file($pathB) ? $pathB : $pathA);

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
  <text x="18" y="32" fill="#7fdcff" font-family="system-ui,Segoe UI,Roboto" font-size="13" letter-spacing="2">FOLLOWERS OVER TIME</text>
  <text x="18" y="62" fill="#ffffff" font-family="ui-monospace,Menlo,Consolas" font-size="12"><?= esc("ERROR: ".$err) ?></text>
  <text x="18" y="86" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="11"><?= esc("USING: ".$historyPath." (".$size." bytes)") ?></text>
</svg>
<?php
    exit;
}

/* Compute points */
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
$first = $pts[0][2];
$last  = $pts[$n-1][2];
$delta = $last - $first;
$sign  = $delta >= 0 ? '+' : '';
$rangeLabel = $pts[0][3] . ' → ' . $pts[$n-1][3];
$nowLabel = "now {$last} ({$sign}{$delta})";
$todayUTC = gmdate('Y-m-d');

/* Pill helper INLINE */
$pill = function(float $x, float $y, string $text, int $fs = 12, string $fill = '#ffffff'): string {
    $padX = 10; $padY = 7;
    $w = max(60, (int)(strlen($text) * ($fs * 0.62)) + $padX*2);
    $h = $fs + $padY*2;
    $rx = 10;
    $tX = $x + $padX;
    $tY = $y + $fs + $padY - 2;

    return
      '<rect x="'.round($x,2).'" y="'.round($y,2).'" width="'.$w.'" height="'.$h.'" rx="'.$rx.'" fill="rgba(0,0,0,0.40)" stroke="rgba(120,220,255,0.55)"/>'.
      '<text x="'.round($tX,2).'" y="'.round($tY,2).'" fill="'.$fill.'" font-family="ui-monospace,Menlo,Consolas" font-size="'.$fs.'">'.esc($text).'</text>';
};

/* Area path */
$areaD = 'M '.$padL.' '.($padT+$plotH).' L ';
foreach ($pts as $p) $areaD .= round($p[0],2).' '.round($p[1],2).' L ';
$areaD .= ($padL+$plotW).' '.($padT+$plotH).' Z';

$lp = $pts[$n-1];

?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $W ?>" height="<?= $H ?>" viewBox="0 0 <?= $W ?> <?= $H ?>">
  <defs>
    <linearGradient id="area" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="rgba(120,220,255,0.32)"/>
      <stop offset="1" stop-color="rgba(120,220,255,0.05)"/>
    </linearGradient>
    <filter id="glow" x="-40%" y="-40%" width="180%" height="180%">
      <feGaussianBlur stdDeviation="2.6" result="b"/>
      <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <clipPath id="clipPlot">
      <rect x="<?= $padL ?>" y="<?= $padT ?>" width="<?= $plotW ?>" height="<?= $plotH ?>" rx="10"/>
    </clipPath>
  </defs>

  <rect x="0" y="0" width="<?= $W ?>" height="<?= $H ?>" fill="rgba(120,200,255,0.12)" stroke="rgba(120,220,255,0.55)"/>

  <!-- Header (hors zone plot) -->
  <text x="18" y="30" fill="#7fdcff" font-family="system-ui,Segoe UI,Roboto" font-size="13" letter-spacing="2">FOLLOWERS OVER TIME</text>
  <?= $pill(18, 40, $rangeLabel, 12, '#ffffff') ?>
  <?= $pill($W-260, 40, $nowLabel, 12, '#ffffff') ?>

  <!-- Plot background -->
  <rect x="<?= $padL ?>" y="<?= $padT ?>" width="<?= $plotW ?>" height="<?= $plotH ?>" rx="12" fill="rgba(120,200,255,0.10)" stroke="rgba(255,255,255,0.10)"/>

  <!-- Grid + Y labels -->
  <?php for ($g=0; $g<=4; $g++):
      $y = $padT + ($plotH * ($g/4));
      $v = (int)round($maxV - (($maxV-$minV)*($g/4)));
  ?>
    <line x1="<?= $padL ?>" y1="<?= round($y,2) ?>" x2="<?= $padL+$plotW ?>" y2="<?= round($y,2) ?>" stroke="rgba(255,255,255,0.10)"/>
    <text x="<?= $padL-12 ?>" y="<?= round($y+4,2) ?>" text-anchor="end" fill="#cfefff" font-family="ui-monospace,Menlo,Consolas" font-size="11"><?= $v ?></text>
  <?php endfor; ?>

  <!-- Area + Line (clippés) -->
  <g clip-path="url(#clipPlot)">
    <path d="<?= esc($areaD) ?>" fill="url(#area)"/>
    <polyline points="<?= $poly ?>" fill="none" stroke="#33ccff" stroke-width="2.6" filter="url(#glow)"/>
  </g>

  <!-- Last point -->
  <circle cx="<?= round($lp[0],2) ?>" cy="<?= round($lp[1],2) ?>" r="5.0" fill="#ffffff" stroke="#33ccff" stroke-width="2"/>

  <!-- Footer (hors zone plot) -->
  <?= $pill(18, $H-44, "daily snapshot (UTC): {$todayUTC}", 11, '#cfefff') ?>
  <?= $pill($W-330, $H-44, $rangeLabel, 11, '#cfefff') ?>
</svg>
