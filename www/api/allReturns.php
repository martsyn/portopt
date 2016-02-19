<?php

var_dump([]);

/*
include 'common.php';

$allocStr = getPostVar('weights', NULL);
if (!$allocStr)
    err('Missing allocations');

$funds =  array(50009708,61151353,85784797,68228019,52603728,13848758,18437237,64020156,25959302,1076539,99548767,7269211,49571130,57639905,7729044,61447860,41475626,98755966,55690796,35416912,77025658,86178573,36696020,26636080,67945254,29154909,30219613,93565016,68622463,22726391,80144237);

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
*/