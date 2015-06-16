<!DOCTYPE html>
<html>
 <head>
	<title>Epic Optimization</title>
	<script src="http://d3js.org/d3.v3.min.js"></script>
	<script src="http://trifacta.github.io/vega/vega.min.js"></script>
	<script src="http://raw.githubusercontent.com/jstat/jstat/master/dist/jstat.min.js"></script>
	<style>
	#vis{
		width: 800px;
		height: 300px;
		border: 1px dashed #ccc;
	}
	</style>
</head>
<body>
	<h1>Optimization</h1>
	<p>

	<div id="vis"></div>
	
	<pre>
	
<?php
$data = array();
if ($handle = fopen("data/gallery.tsv", "r")) {
    while ($line = fgetcsv($handle, 0, "\t")) {
		$data[] = $line;
	}
    fclose($handle);
}
else
{
	echo "File error";
	exit();
}

$weights = optimizeSimpleTarget($data, "ReturnsToStDevRatio", true);
foreach ($weights as $w)
	echo ($w." ");
	
$cum = array();
$sum = 0;
for ($i = 0; $i < count($data[0]); ++$i)
{
	for ($j = 0; $j < count($data); ++$j)
		$sum += $data[$j][$i]*$weights[$j];
	$cum[] = $sum;
}
?>

	</pre>
	
	<script>
var spec = {
  "width": 760,
  "height": 260,
  "data": [{"name": "table",}],
  "scales": [
    {"name":"x", "type":"ordinal", "range":"width", "domain":{"data":"table", "field":"data.x"}},
    {"name":"y", "range":"height", "nice":true, "domain":{"data":"table", "field":"data.y"}}
  ],
  "axes": [
    {"type":"x", "scale":"x"},
    {"type":"y", "scale":"y"}
  ],
  "marks": [
    {
      "type": "line",
      "from": {"data":"table"},
      "properties": {
        "enter": {
          "x": {"scale":"x", "field":"data.x"},
          "width": {"scale":"x", "band":true, "offset":-1},
          "y": {"scale":"y", "field":"data.y"},
          "y2": {"scale":"y", "value":0},
		  "stroke": {value: "red"}
        },
      }
    }
  ]
};

var data = { 
"table" : [
<?php
for($i = 0; $i < count($cum); ++$i)
	echo "{x: ".$i.", y:".$cum[$i]."},"
?>
] };


vg.parse.spec(spec, function(chart) {
  var view = chart({el:"#vis", data:data})  //here is where we populate the empty spec data holder with our calculated data
    .update();
});
	</script>
	
	
	</br>
	<table>

<?php
foreach ($data as $row)
{
	echo "<tr>";
	foreach ($row as $x)
		echo "<td>".$x."</td>";
	echo "</tr>\n";
}
?>
</table>

</form>
	</p>
</body>
</html>