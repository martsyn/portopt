<?php

header('Content-Type:application/json; charset=UTF-8');

$minDate = strtotime(getVar('minDate', '2009-12-01'));
$maxDate = strtotime(getVar('maxDate', '2015-10-01'));

function err($msg, $code = 400)
{
    http_response_code($code);
    die($msg);
}

/*
 * parse params
 */

function getVar($name, $default)
{
    return array_key_exists($name, $_POST)
        ? $_POST[$name]
        : $default;
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
    while ($returnsStmt->fetch()) {
        if (!array_key_exists($fundId, $fundIndexes)) {
            $fundIdx = count($returns);
            $returns[] = array_fill(0, count($monthIndexes), 0.0);
            $fundIndexes[$fundId] = $fundIdx;
        } else
            $fundIdx = $fundIndexes[$fundId];
        $returns[$fundIdx][$monthIndexes[$date]] = $return;
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
