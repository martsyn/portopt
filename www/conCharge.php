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

/** records of member connections ordered by members, then timestamp. If connection is open prior to the period,
 * record must also be included, otherwise always open connection would not have a single record.
*/
$connections=
    array(
        array("memberID" => 1, "eAction" =>  true, "dTimestamp" => strtotime("2015/05/01")), // opened before period
        array("memberID" => 1, "eAction" => false, "dTimestamp" => strtotime("2015/06/11")),
        array("memberID" => 1, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/16")),
        array("memberID" => 1, "eAction" => false, "dTimestamp" => strtotime("2015/06/16 16:00")),

        array("memberID" => 2, "eAction" =>  true, "dTimestamp" => strtotime("2010/01/01")), // open before, not closed

        array("memberID" => 3, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/21")), // opened during

        array("memberID" => 4, "eAction" => false, "dTimestamp" => strtotime("2015/05/20")), // closed before
        array("memberID" => 4, "eAction" =>  true, "dTimestamp" => strtotime("2015/06/21")), // opened during
    );

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
$state = false;
$openTimestamp = 0;

foreach ($connections as $c){
    $id = $c["memberID"];
    $action = $c["eAction"];
    $timestamp = $c["dTimestamp"];

    if ($timestamp < $periodStart)
        $timestamp = $periodStart;

    if ($id != $currentId){ // new member
        if ($currentId > 0) { // handle previous member
            if ($state)
                $openTimes[] = array($openTimestamp, $periodEnd);
            $totalTime += handleConnection($currentId, $openTimes, $periodLen, $periodCost);
        }

        $openTimes = array();
        $currentId = $id;

        if (!$action) { // ignore first closing action
            $state = false;
            continue;
        }
    }
    else{
        if ($action == $state)
            throw new Exception("Duplicate connection action=$action for memberID=$id, dates: "
                .formatDate($openTimestamp).", ".formatDate($timestamp));

    }

    if ($action){
        $openTimestamp = $timestamp;
    }
    else{
        $openTimes[] = array($openTimestamp, $timestamp);
    }

    $state = $action;
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
    return $at < $bt ? -1 : $at == $bt ? 0 : 1;
}

usort($connections, "cmpTimestamp");

$chartPoints = array();
$openConnections = 0;
$first = true;

foreach ($connections as $c){
    $id = $c["memberID"];
    $action = $c["eAction"];
    $timestamp = $c["dTimestamp"];

    $inPeriod = $timestamp >= $periodStart;

    if ($inPeriod && $first){
        $chartPoints[] = array("Timestamp" => $periodStart, "Connections" => $openConnections);
        $first = false;
    }

    if ($action)
        ++$openConnections;
    else
        --$openConnections;

    if ($inPeriod){
        $chartPoints[] = array(
            "Timestamp" => $timestamp,
            "Connections" => $openConnections,
            "Description" => "Member $id ".($action ? "connected" : "disconnected"));
    }
}
$chartPoints[] = array("Timestamp" => $periodEnd, "Connections" => $openConnections);

echo("<h2>Chart points</h2>\n");
echo("<table border='1'>\n<tr><th>Timestamp</th><th>Connections</th><th>Description</th></tr>\n");

foreach($chartPoints as $p){
    $x = $p["Timestamp"];
    $y = $p["Connections"];
    $desc = array_key_exists("Description", $p) ? $p["Description"] : NULL;
    echo("<tr><td>".formatDate($x)."</td><td>$y</td><td>".($desc ? $desc : "&nbsp")."</td></tr>\n");
}

echo("</table>");