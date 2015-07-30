<?php

$periodStart = strtotime("2015/06/01");
$periodEnd = strtotime("2015/07/01");
$periodLen = $periodEnd - $periodStart;
$periodCost = 100;

function formatDate($date){
    return date("Y-m-d H:i", $date);
}

function formatDays($seconds){
    return number_format($seconds/(60*60*24), 1);
}

function formatCost($dollars){
    return "$".number_format($dollars, 2);
}

/** records of member connections. If connection is open prior to the period,
 * record must also be included, otherwise always open connection would not have a single record.
*/
$connections =
    array(
        array("memberID" => 1, "fundID" => 100, "eAction" =>  true, "dTimestamp" => strtotime("2015/05/01")), // opened before period
        array("memberID" => 1, "fundID" => 100, "eAction" => false, "dTimestamp" => strtotime("2015/06/11")),
        array("memberID" => 1, "fundID" => 100, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/16")),
        array("memberID" => 1, "fundID" => 100, "eAction" => false, "dTimestamp" => strtotime("2015/06/16 16:00")),

        array("memberID" => 1, "fundID" => 101, "eAction" =>  true, "dTimestamp" => strtotime("2015/05/01")), // opened before period
        array("memberID" => 1, "fundID" => 101, "eAction" => false, "dTimestamp" => strtotime("2015/06/05")),
        array("memberID" => 1, "fundID" => 101, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/10")),
        array("memberID" => 1, "fundID" => 101, "eAction" => false, "dTimestamp" => strtotime("2015/06/25 16:00")),

        array("memberID" => 2, "fundID" => 150, "eAction" =>  true, "dTimestamp" => strtotime("2010/01/01")), // open before, not closed

        array("memberID" => 3, "fundID" => 200, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/21")), // opened during
        array("memberID" => 3, "fundID" => 201, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/21")), // opened during

        array("memberID" => 4, "fundID" => 300, "eAction" => false, "dTimestamp" => strtotime("2015/05/20")), // closed before
        array("memberID" => 4, "fundID" => 300, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/21")), // opened during
    );

// sort by member, then timestamp
function cmpMemberTimestamp($a, $b){
    $am = $a["memberID"];
    $bm = $b["memberID"];
    $at = $a["dTimestamp"];
    $bt = $b["dTimestamp"];

    return $am < $bm
        ? -2
        : ($am > $bm
            ? 2
            : ($at < $bt
                ? -1
                : ($at > $bt
                    ? 1
                    : 0)));
}

usort($connections, "cmpMemberTimestamp");

function handleConnection($id, $openTimes, $periodLen, $periodCost){
    echo("<h3>Connection with member $id</h3>\n");
    $totalTime = 0;
    foreach ($openTimes as $t){
        $open = $t[0];
        $close = $t[1];
        $time = $close - $open;
        $totalTime += $time;
        echo(formatDate($open)." - ".formatDate($close).": ".formatDays($time)." days<br>\n");
    }
    echo("Total: ".formatDays($totalTime)."/".formatDays($periodLen)." days, "
        .formatCost($totalTime/$periodLen*$periodCost)."<br>\n");

    return $totalTime;
}

$totalTime = 0;
$currentId = -1;
$state = array();
$openTimestamp = 0;

foreach ($connections as $c){
    $member = $c["memberID"];
    $fund = $c["fundID"];
    $action = $c["eAction"];
    $timestamp = $c["dTimestamp"];

    if ($timestamp < $periodStart)
        $timestamp = $periodStart;

    if ($member != $currentId){ // new member
        if ($currentId > 0) { // handle previous member
            if ($state)
                $openTimes[] = array($openTimestamp, $periodEnd);
            $totalTime += handleConnection($currentId, $openTimes, $periodLen, $periodCost);
        }

        $openTimes = array();
        $currentId = $member;
        $state = array();
    }

    if ($action){
        if (!$state)
            $openTimestamp = $timestamp;
        $state[$fund] = true;
    }
    else if (array_key_exists($fund, $state)){
        unset($state[$fund]);
        if (!$state)
            $openTimes[] = array($openTimestamp, $timestamp);
    }

//    echo formatDate($timestamp).": $id ($fund) - ".($action ? "on" : "off")."<br>\n";
//    var_dump($state);
}

if ($currentId > 0) { // handle last member
    if ($state)
        $openTimes[] = array($openTimestamp, $periodEnd);
    $totalTime += handleConnection($currentId, $openTimes, $periodLen, $periodCost);
}

echo("<h3>Total</h3>");
echo(formatCost($totalTime/$periodLen*$periodCost)."<br>\n");

// chart

// sort by timestamp
function cmpTimestamp($a, $b){
    $at = $a["dTimestamp"];
    $bt = $b["dTimestamp"];
    return $at < $bt
        ? -1
        : ($at == $bt
            ? 0
            : 1);
}

usort($connections, "cmpTimestamp");

$chartPoints = array();
$oc = array(); // open connections
$idx = 0;
$fundCount = 0;
$timeOfDay = 60*60*24 - 1;

for ($time = $periodStart + $timeOfDay; $time < $periodEnd + $timeOfDay; $time += 60*60*24){
    while ($idx < count($connections)) {
        $c = $connections[$idx];
        $member = $c["memberID"];
        $fund = $c["fundID"];
        $action = $c["eAction"];
        $timestamp = $c["dTimestamp"];

        if ($timestamp > $time)
            break;

//        echo formatDate($timestamp).": $member($fund) - ".($action ? "on" : "off")."<br>\n";

        if ($action) {
            if (!array_key_exists($member, $oc))
                $oc[$member] = array();

            if (!array_key_exists($fund, $oc[$member])) {
                $oc[$member][$fund] = true;
                ++$fundCount;
            }
        } else {
            if (array_key_exists($member, $oc)){
                if (array_key_exists($fund, $oc[$member])){
                    unset($oc[$member][$fund]);
                    --$fundCount;
                    if (!$oc[$member])
                        unset($oc[$member]);
                }
            }
        }

//        var_dump($oc);

        ++$idx;
    }
    $chartPoints[] = array("Timestamp" => $time, "Members" => count($oc), "Funds" => $fundCount);
}

echo("<h2>Chart points</h2>\n");
echo("<table border='1'>\n<tr><th>Timestamp</th><th>Members</th><th>Funds</th></tr>\n");

foreach($chartPoints as $p){
    $x = $p["Timestamp"];
    $y1 = $p["Members"];
    $y2 = $p["Funds"];
    echo("<tr><td>".formatDate($x)."</td><td>$y1</td><td>$y2</td></tr>\n");
}

echo("</table>");