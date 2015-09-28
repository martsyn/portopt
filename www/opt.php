<!DOCTYPE html>
<html>
<head>
    <title>Epic Optimization</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/4.9.0/css/bootstrap-slider.min.css">
    <style>
        #vis {
            width: 800px;
            height: 300px;
            border: 1px dashed #ccc;
        }
    </style>
</head>
<body>
<div class="container">
<div class="jumbotron">
<h1>Optimization</h1>
</div>

    <script>
        roundingFraction = 1e4; // round to a base-point
        roundingPercent = roundingFraction*0.01;
        var funds = [];
        var stats = {};
    </script>

<?php

$v = array();

function dumpSlider($name, $label, $default)
{
    $val = array_key_exists($name, $_POST)
        ? $_POST[$name]
        : $default;
    global $v;
    $v[$name] = $val;
    echo "<p><label for='$name'>$label:</label><br/>\n";
    echo "<input name='$name' id='$name' data-slider-min='-1' data-slider-max='1' data-slider-step='0.1' data-slider-value='$val'/></p>\n";
}
?>

<form method="post">

<div class="row">
<div class="col-md-4">

<?php

dumpSlider("return", "Return", 1);
dumpSlider("volatility", "Volatility", -1);
dumpSlider("slopeDeviation", "Slope deviation", 0);
dumpSlider("posDeviation", "Positive semi-deviation", 0);
dumpSlider("negDeviation", "Negative semi-deviation", 0);
dumpSlider("drawdown", "Worst drawdown", 0);

?>
    <p><label for="benchmark">Benchmark correlation:</label><br/>
    <input id="benchmark" data-slider-min="-1" data-slider-max="1" data-slider-step="0.1" data-slider-value="0"/></p>
    <p><label>Old-school combos:</label>
    <div class="btn-group" role="group" aria-label="...">
        <button type="button" class="btn btn-default" onclick="setSharpe()">Sharpe-ratio</button>
        <button type="button" class="btn btn-default" onclick="setKRatio()">K-ratio</button>
    </div>
    <p></p>
<!--    <p><input type="submit" value="Submit"/></p> -->

<button type="button" onclick="optimize()">Optimize</button><br/>
<button type="button" onclick="recalculate()">Recalculate</button><br/>

</div>
<div class="col-md-8">

<table class="table-striped" width="100%">
<thead>
<tr><th>Fund</th><th>Allocation</th><th>Annualized Return</th><th>Volatility</th><th>Sharpe</th></tr>
</thead>
<tbody id="fundTable">
</tbody>
<tfoot>
<tr><th>Summary</th><td>&nbsp;</td><td id="summaryRor">&nbsp;</td><td id="summaryVolatility">&nbsp;</td><td id="summarySharpe">&nbsp;</td></tr>
</tfoot>
</table>
</div>
</div>

</form>
<p>

<button onclick="testData()">Test</button>
<p>

<button onclick="testApi()">Test api</button>
<div id="vis"></div>
<pre>

<?php
$returns = array();
if ($handle = fopen("data/gallery.tsv", "r")) {
    while ($line = fgetcsv($handle, 0, "\t")) {
        $returns[] = $line;
    }
    fclose($handle);
} else {
    echo "File error";
    exit();
}

$weights = optimizeCustomTarget($returns, $v);
foreach ($weights as $w)
    echo($w . " ");

$cum = array();
$sum = 0;
for ($i = 0; $i < count($returns[0]); ++$i) {
    for ($j = 0; $j < count($returns); ++$j)
        $sum += $returns[$j][$i] * $weights[$j];
    $cum[] = $sum;
}
?>

	</pre>

<script>
    var spec = {
        "width": 760,
        "height": 260,
        "data": [{"name": "table"}],
        "scales": [
            {"name": "x", "type": "ordinal", "range": "width", "domain": {"data": "table", "field": "data.x"}},
            {"name": "y", "range": "height", "nice": true, "domain": {"data": "table", "field": "data.y"}}
        ],
        "axes": [
            {"type": "x", "scale": "x"},
            {"type": "y", "scale": "y"}
        ],
        "marks": [
            {
                "type": "line",
                "from": {"data": "table"},
                "properties": {
                    "enter": {
                        "x": {"scale": "x", "field": "data.x"},
                        "y": {"scale": "y", "field": "data.y"},
                        "stroke": {value: "red"}
                    }
                }
            }
        ]
    };

    var data = {
        "table": [
            <?php
            for($i = 0; $i < count($cum); ++$i)
                echo "{x: ".$i.", y:".$cum[$i]."},"
            ?>
        ]
    };
</script>


<br/>

<?php
/*
<table>

    <?php
    foreach ($returns as $row) {
        echo "<tr>";
        foreach ($row as $x)
            echo "<td>" . $x . "</td>";
        echo "</tr>\n";
    }
    ?>
</table>
*/
?>

</div>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/vega/1.5.0/vega.min.js"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script type="text/javascript" src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/4.9.0/bootstrap-slider.min.js"></script>
<script type='text/javascript'>

var sliders = {};
var sliderIds = [
<?php
foreach ($v as $name => $val)
    echo "'$name',\n";
?>
];
var vis;

$(document).ready(function() {

    sliderIds.forEach(function (id){
        sliders[id] = new Slider("#" + id, {});
        sliders[id].element.name = id;
    });

    $.get('api/funds.php', function(data){
        stats = data;
        for (var fundId in stats)
            funds.push(fundId);

        var html = "";
        var runSum = 0.0;
        var i = 0;
        for (fundId in stats) {
            ++i;
            var stat = stats[fundId];
            var newSum = round(i/funds.length);
            var part = round(newSum - runSum);
            html += "<tr>"
                + "<td>" + fundId + "</td>"
                + "<td><input type='number' id='weight" + fundId + "' min='0' max='100' step='0.01' value='" + part*100.0 + "' /></td>"
                + "<td>" + showPercents(stat.ror) + "</td>"
                + "<td>" + showPercents(stat.volatility) + "</td>"
                + "<td>" + showSharpe(stat.sharpe) + "</td>"
                + "</tr>\n";
            runSum = newSum;
        }
        $("#fundTable").html(html);

        recalculate();
    });
});

function resetSliders() {
    sliderIds.forEach(function (id){
        sliders[id].setValue(0, true, true);
    });
}

function setSharpe() {
    resetSliders();
    sliders.return.setValue(1, true, true);
    sliders.volatility.setValue(-1, true, true);
}

function setKRatio() {
    resetSliders();
    sliders.return.setValue(1, true, true);
    sliders.slopeDeviation.setValue(-1, true, true);
}

function testData() {
    for (var i = 0; i < data.table.length; ++i)
        data.table[i].y = data.table[data.table.length - 1 - i].y;

    vg.parse.spec(spec, function (chart) {
        vis = chart({el: "#vis", data: data}).update();
    });
}

function testApi() {
    $.post(
        "optApi.php",
        {"funds":funds.join(" ")},
        function(data){
            vg.parse.spec(spec, function (chart) {
                vis = chart({el: "#vis", data: data}).update();
            });
        }, "json");
}

function optimize() {
    var params = { "funds":funds.join(" ") };

    sliderIds.forEach(function (id){
        params[id] = sliders[id].getValue();
    });

    $.post(
        "api/optimize.php",
        params,
        function(weights) {
            setWeights(weights);
            recalculate();
        }, "json");
}

function round(x) {
    return Math.round(x*roundingFraction)/roundingFraction;
}

function roundPercents(x) {
    return Math.round(x*roundingPercent)/roundingPercent;
}

function showPercents(x) {
    return (x*100.0).toFixed(2);
}

function showSharpe(x) {
    return (x).toFixed(2);
}

function getWeights() {
    var res = [];
    for (var i = 0; i < funds.length; ++i){
        res.push(0.01*parseFloat($("#weight" + funds[i]).val()));
    }
    return res;
}

function setWeights(weights) {
    for (var i = 0; i < weights.length; ++i){
        $("#weight" + funds[i]).val(roundPercents(100.0*weights[i]));
    }
}

function fixWeights() {
    var weights = getWeights();
    var sum = 0.0;
    for (var i = 0; i < weights.length; ++i){
        weights[i] = round(weights[i]);
        sum += weights[i];
    }
    if (sum < 1.0 || sum > 1.0) {
        for (i = weights.length - 1; i >= 0; --i){
            sum -= round(weights[i]);
            if (sum <= 1.0) {
                weights[i] = round(1.0 - sum);
                break;
            }
            weights[i] = 0.0;
        }
    }
    setWeights(weights);
    return weights;
}

function recalculate() {
    var weights = fixWeights();
    var weightsStr = "";
    for (var i = 0; i < weights.length; ++i){
        if (i != 0)
            weightsStr += " ";
        weightsStr += funds[i] + ":" + weights[i];
    }
    $.post(
        "api/returns.php",
        {"weights": weightsStr},
        function(data){
            var stats = data.stats;

            $("#summaryRor").text(showPercents(stats.ror));
            $("#summaryVolatility").text(showPercents(stats.volatility));
            $("#summarySharpe").text(showSharpe(stats.sharpe));

            var chartPoints = data.chart;
            vg.parse.spec(spec, function (chart) {
                vis = chart({el: "#vis", data: chartPoints}).update();
            });
        }, "json");
}
</script>
</body>
</html>