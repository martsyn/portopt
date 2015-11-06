<?php

include 'common.php';

$req = getPostReq();

$funds = array();
$constraints = array();
foreach ($req->funds as $fund) {
    $funds[] = (int) $fund->id;
    $required = isset($fund->required) ? (bool) $fund->required : false;
    $min = isset($fund->min) ? (float) $fund->min : 0;
    $max = isset($fund->max) ? (float) $fund->max : 1;
    $constraints[] = new OptimizationConstraint($required, $min, $max);
}

if (count($funds) < 1 || count($funds) > 50)
    err('Invalid number of funds');

$optType = $req->optType;
if ($optType != 'custom')
    err("Unknown optType $optType");

$optParams = get_object_vars($req->optParams);
$returns = loadReturns($funds, $minDate, $maxDate);

//
// optimization
//

$optParams = array(
    'return' => 1,
    'volatility' => -1
);

$weights = optimizeCustomTarget($constraints, $returns, $optParams);

$result = new stdClass;
$result->weights = $weights;

echo json_encode($result);
