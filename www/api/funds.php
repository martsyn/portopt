<?php

include 'common.php';

$portfolio = getGetVar('portfolio', 111);

if ($portfolio != 111){
    err("Unknown portfolio $portfolio");
}

$funds =  array(50009708,61151353,85784797,68228019,52603728,13848758,18437237,64020156,25959302,1076539,99548767,7269211,49571130,57639905,7729044,61447860,41475626,98755966,55690796,35416912,77025658,86178573,36696020,26636080,67945254,29154909,30219613,93565016,68622463,22726391,80144237);

$returns = loadReturns($funds, $minDate, $maxDate);
$stats = array();

for ($i = 0; $i < count($funds); ++$i)
    $stats[$funds[$i]] = getStats($returns[$i]);

echo json_encode($stats);