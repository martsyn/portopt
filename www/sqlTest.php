<?php

$minDate = strtotime('2009-12-01');
$maxDate = strtotime('2015-10-01');
$funds=array(50009708,61151353,85784797,68228019,52603728,13848758,18437237,64020156,25959302,1076539,99548767,7269211,49571130,57639905,7729044,61447860,41475626,98755966,55690796,35416912,77025658,86178573,36696020,26636080,67945254,29154909,30219613,93565016,68622463,22726391,80144237);

$db = new mysqli('localhost', 'avm', 'uZlyr8RoOiURQKSLdLoO', 'hfinone');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$returnsStmt = $db->prepare("SELECT iFundID, dReturnDate, iReturn FROM hfin_investment_fund_returns
where iFundID in (".implode(',', array_fill(0, count($funds), '?')).")
	and eStatus='F'
    and dReturnDate=last_day(dReturnDate)
    and dReturnDate >= ? and dReturnDate < ?
order by iFundID, dReturnDate");

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
while ($returnsStmt->fetch()){
    if (!array_key_exists($fundId, $fundIndexes)) {
        $fundIdx = count($returns);
        $returns[] = array_fill(0, count($monthIndexes), 0.0);
        $fundIndexes[$fundId] = $fundIdx;
    }
    else
        $fundIdx = $fundIndexes[$fundId];
    $returns[$fundIdx][$monthIndexes[$date]] = $return;
}

echo "<table border='1'>\n";
foreach ($returns as $returns){
    echo "<tr>";
    foreach ($returns as $return)
        echo "<td>$return</td>";
    echo "</tr>\n";
}
echo "</table>\n";