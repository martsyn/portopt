<?php

include 'common.php';

$allocStr = getPostVar('weights', NULL);
if (!$allocStr)
    err('Missing allocations');

$funds = [];
$weights = [];

foreach (explode(' ', $allocStr) as $p){
    $parts = explode(':', $p);
    $funds[] = intval($parts[0]);
    $weights[] = floatval($parts[1]);
}
$total = array_sum($weights);
if (!($total > 0.999 && $total < 1.001))
    err("Total weights = $total");

$returns = loadReturns($funds, $minDate, $maxDate);

$res = array();
$cum = array();
$runningSum = 0;
for ($i = 0; $i < count($returns[0]); ++$i) {
    $sum = 0.0;
    for ($j = 0; $j < count($returns); ++$j)
        $sum += $returns[$j][$i] * $weights[$j];
    $res[] = $sum;
    $runningSum += $sum;
    $cum[] = $runningSum;
}

echo '{"stats":'.json_encode(getStats($res)).',';
echo '"chart": {"table":[';
for($i = 0; $i < count($cum); ++$i) {
    echo '{"x": ' . $i . ', "y":' . $cum[$i] . '}';
    if ($i < count($cum) - 1)
        echo ',';
}
echo ']}}';
