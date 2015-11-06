<?php

header('Content-Type:application/json; charset=UTF-8');

$minDate = strtotime(getPostVar('minDate', '2009-12-01'));
$maxDate = strtotime(getPostVar('maxDate', '2015-10-01'));

function err($msg, $code = 400)
{
    http_response_code($code);
    die($msg);
}

//
// parse params
//

function getPostVar($name, $default)
{
    return array_key_exists($name, $_POST)
        ? $_POST[$name]
        : $default;
}

function getGetVar($name, $default)
{
    return array_key_exists($name, $_GET)
        ? $_GET[$name]
        : $default;
}

function getPostReq()
{
    $req = json_decode(file_get_contents("php://input"));
    if (!$req)
        err("Invalid JSON request");
    return $req;
}

/*
 * sql
 */

function bind_param_array($stmt){
    $argc = func_num_args();
    $types = '';
    $refs = array(&$types);
    $args = array();
    for ($i = 1; $i < $argc; $i += 2){
        $type = func_get_arg($i);
        if (strlen($type) != 1)
            throw new Exception("Every other parameter should be SQL type");
        if ($i + 1 >= $argc)
            throw new Exception("SQL types should be followed by argument");
        $val = func_get_arg($i + 1);
        if (is_array($val)) {
            $types .= str_repeat($type, count($val));
            foreach ($val as $idx => $x) {
                $args[] = $x;
                $refs[] = &$args[count($args) - 1];
            }
        }
        else{
            $types .= $type;
            $args[] = $val;
            $refs[] = &$args[count($args) - 1];
        }
    }
    call_user_func_array(array($stmt, 'bind_param'), $refs);
}

/*
 * load returns
 */
function loadReturns($funds, $minDate, $maxDate)
{
    /** @noinspection SpellCheckingInspection */
    $db = new mysqli('localhost', 'avm', 'uZlyr8RoOiURQKSLdLoO', 'hfinone');
    if ($db->connect_error)
        err("Connection failed: " . $db->connect_error, 500);

    $returnsStmt = $db->prepare("SELECT iFundID, dReturnDate, iReturn FROM hfin_investment_fund_returns
WHERE iFundID IN (" . implode(',', array_fill(0, count($funds), '?')) . ")
	AND eStatus='F'
    AND dReturnDate=last_day(dReturnDate)
    AND dReturnDate >= ? AND dReturnDate < ?
ORDER BY iFundID, dReturnDate");

    bind_param_array(
        $returnsStmt,
        'i', $funds,
        's', date("Y-m-d", $minDate),
        's', date("Y-m-d", $maxDate));
    $returnsStmt->execute();
    $returnsStmt->bind_result($fundId, $date, $return);

    $monthIndexes = array();
    for ($i = 0, $eom = strtotime(date("Y-m-t", $minDate));
         $eom < $maxDate;
         ++$i, $eom = strtotime(date("Y-m-t", $eom + 24 * 60 * 60))) {
        $monthIndexes[date("Y-m-d", $eom)] = $i;
    }
    $fundIndexes = array();
    $returns = array();
    for ($i = 0; $i < count($funds); ++$i) {
        $fundIndexes[$funds[$i]] = $i;
        $returns[] = array_fill(0, count($monthIndexes), 0.0);
    }

    while ($returnsStmt->fetch()) {
        $returns[$fundIndexes[$fundId]][$monthIndexes[$date]] = $return;
    }

    return $returns;
}


/*
 * debug
 */
function dumpParams(){
    echo '{';
    $first = true;
    foreach ($_POST as $k => $v) {
        if ($first)
            $first = false;
        else
            echo ",";
        echo "'$k': '$v'";
    }
    echo '}';
}

/*
 * stats
 */

function getStats($monthlyReturns){
    $sum = array_sum($monthlyReturns);
    $count = count($monthlyReturns);
    $mean = $sum/$count;

    $devSqSum = 0.0;
    foreach ($monthlyReturns as $r){
        $devSqSum += ($r - $mean)*($r - $mean);
    }

    $ror = $sum/$count*12;
    $volatility = sqrt($devSqSum/$count*12);
    $sharpe = $ror/$volatility;

    return array(
        "ror" => $ror,
        "volatility" => $volatility,
        "sharpe" => $sharpe
    );
}