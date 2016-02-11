<?php

include 'common.php';

addJsonHeader();
header('Access-Control-Allow-Origin: *');

$req = getPostReq();

$funds = array();
$constraints = array();
$returns = array();
foreach ($req->funds as $fund) {
    $funds[] = (int) $fund->id;
    $required = isset($fund->required) ? (bool) $fund->required : false;
    $min = isset($fund->min) ? (float) $fund->min : 0;
    $max = isset($fund->max) ? (float) $fund->max : 1;
    $constraints[] = new OptimizationConstraint($required, $min, $max);
    $returns[] = $fund->returns;
}

if (count($funds) < 1)
    err('At lease one fund is required');
if (count($funds) > 100)
    err('Too many funds to optimize: '.count($funds).' (max=100)');

$optType = $req->optType;
if ($optType != 'custom')
    err("Unknown optType $optType");

$optParams = get_object_vars($req->optParams);

$weights = optimizeCustomTarget($constraints, $returns, $optParams);

$result = new stdClass;
$result->weights = $weights;

echo json_encode($result);
