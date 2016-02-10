<?php

function addJsonHeader()
{
    header('Content-Type:application/json');
}

/*
$minDate = strtotime(getPostVar('minDate', '2009-12-01'));
$maxDate = strtotime(getPostVar('maxDate', '2015-10-01'));
*/

function err($msg, $code = 400)
{
    http_response_code($code);
    die($msg);
}

//
// parse params
//

function getPostVar($name, $default = NULL)
{
    return array_key_exists($name, $_POST)
        ? $_POST[$name]
        : $default;
}

function getGetVar($name, $default = NULL)
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

function dbConnect()
{
    //$db = new mysqli('localhost', 'avm', 'uZlyr8RoOiURQKSLdLoO', 'hfinone');
    $db = new mysqli('localhost', 'root', 'n3W*s3rv3r2015!', 'hfin');
    if ($db->connect_error)
        err("Connection failed: " . $db->connect_error, 500);

    return $db;
}

/*
 * load returns
 */
function loadReturns($funds, $minDate, $maxDate)
{
    $db = dbConnect();

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

function linReg($xs, $ys)
{
    $xSum = 0;
    $ySum = 0;
    $xxSum = 0;
    $xySum = 0;

    $c = count($xs);

    for ($i = 0; $i < $c; ++$i)
    {
        $x = $xs[$i];
        $y = $ys[$i];

        $xSum += $x;
        $ySum += $y;
        $xxSum += $x*$x;
        $xySum += $x*$y;
    }

    $result = new stdClass();
    $result->slope = ($c*$xySum - $xSum*$ySum)/($c*$xxSum - $xSum*$xSum);
    $result->intercept = ($ySum - $result->slope*$xSum)/$c;

    return $result;
}

function httpRequest($verb, $url, $urlParams = NULL, $headers = NULL, $data = NULL, $log = false)
{
    $fullUrl = $url;
    if ($urlParams)
        $fullUrl .= '?'.http_build_query($urlParams);
    $curl = curl_init();

    $curl_log = 0;
    if ($log) {
        $curl_log = fopen("/home/apache/curl.log", 'a');
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_STDERR, $curl_log);
    }

    curl_setopt($curl, CURLOPT_URL, $fullUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
    curl_setopt($curl, CURLOPT_ENCODING, ''); // decode automatically if zipped

    if ($data)
        curl_setopt($curl, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
    if ($headers)
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($curl);
    if ($log)
        fclose($curl_log);
    if (curl_errno($curl))
    {
        error_log("'$verb' request to $url failed with CURL error: ".curl_error($curl));
        return NULL;
    }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($status >= 300) {
        error_log("'$verb' request to $url failed with status $status and message $response");
        return NULL;
    }
    return json_decode($response);
}
