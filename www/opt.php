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
    <p><input type="submit" value="Submit"/></p>
</form>
<p>

<button onclick="testData()">Test</button>
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
                        "stroke": {value: "red"},
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

    vg.parse.spec(spec, function (chart) {
        vis = chart({el: "#vis", data: data}).update();
    });

    sliderIds.forEach(function (id){
        sliders[id] = new Slider("#" + id, {});
        sliders[id].element.name = id;
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
</script>
</body>
</html>