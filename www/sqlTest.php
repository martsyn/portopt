<?php
/*
$link = mysql_connect('127.0.0.1', 'avm', 'uZlyr8RoOiURQKSLdLoO');
//$link = mysql_connect('localhost:3307', 'avm', 'uZlyr8RoOiURQKSLdLoO', 'hfindev2');
//$link = mysql_connect('localhost:3307', 'root', '$horton1$', 'hfinone');
if (!$link) {
  die("Connect Error: ".mysql_error());
}
echo "Connected successfully<br>\n";

$result = mysql_query("select 1 as x;");
var_dump($result);
*/

$minDate = strtotime('2012/01/01');
$maxDate = strtotime('2015/07/01');
$funds=array(50009708,61151353,85784797,68228019,52603728,13848758,18437237,64020156,25959302,1076539,99548767,7269211,49571130,57639905,7729044,61447860,41475626,98755966,55690796,35416912,77025658,86178573,36696020,26636080,67945254,29154909,30219613,93565016,68622463,22726391,80144237);

$db = new mysqli('localhost', 'avm', 'uZlyr8RoOiURQKSLdLoO', 'hfinone');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
echo "Connected successfully<br>\n";
/*
$returnsStmt = $db->prepare('SELECT iFundID, dReturnDate, iReturn FROM hfin_investment_fund_returns
where iFundID in ('.implode(',', array_fill(0, count($funds), '?')).')
	and eStatus=\'F\'
    and dReturnDate=last_day(dReturnDate)
    and dReturnDate >= ? and dReturnDate < ?
order by iFundID, dReturnDate');
*/
$returnsStmt = $db->prepare("SELECT iFundID, dReturnDate, iReturn FROM hfin_investment_fund_returns
where iFundID in (".implode(',', array_fill(0, count($funds), '?')).")
	and eStatus='F'
    and dReturnDate=last_day(dReturnDate)
    and dReturnDate >= ? and dReturnDate < ?
order by iFundID, dReturnDate");

/*
$params = array();
$types = str_repeat('i', count($funds)).'ss';
$params[] = &$types;
foreach ($funds as $idx => $val) $params[] = &$funds[$idx];
$minDateStr = date("Y-m-d", $minDate);
$maxDateStr = date("Y-m-d", $maxDate);
$params[] = &$minDateStr;
$params[] = &$maxDateStr;

call_user_func_array(    array($returnsStmt, 'bind_param'),    $params);
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
            throw new Exception("Match all SQL types with arguments");
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

bind_param_array(
    $returnsStmt,
    'i', $funds,
    's', date("Y-m-d", $minDate),
    's', date("Y-m-d", $maxDate));


/*    refValues(array_merge(
        array(str_repeat('i', count($funds)).'ss'),
        $funds,
        array(date("Y-m-d", $minDate), date("Y-m-d", $maxDate)))));*/

//$returnsStmt->bind_param('ss', date("Y-m-d", $minDate), date("Y-m-d", $maxDate));

$returnsStmt->execute();

$returnsStmt->bind_result($fundId, $date, $return);
while ($returnsStmt->fetch()){
    echo "$fundId | $date (".gettype($date).") | $return<br>\n";
}
