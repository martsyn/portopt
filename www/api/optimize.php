<?php

include 'common.php';

$fundsStr = getVar('funds', '50009708,61151353,85784797,68228019,52603728,13848758,18437237,64020156,25959302,1076539,99548767,7269211,49571130,57639905,7729044,61447860,41475626,98755966,55690796,35416912,77025658,86178573,36696020,26636080,67945254,29154909,30219613,93565016,68622463,22726391,80144237');
if (!$fundsStr)
    err('Missing funds');
$funds = explode(' ', $fundsStr);
foreach ($funds as $i => $id)
    if (!ctype_digit(strval(($id))))
        err('Invalid funds');
if (count($funds) < 1 || count($funds) > 50)
    err('Invalid number of funds');

$v = array();
function readOptVar($name)
{
    global $v;
    $v[$name] = getVar($name, 0.0);
}
readOptVar('return');
readOptVar('volatility');
readOptVar('slopeDeviation');
readOptVar('posDeviation');
readOptVar('negDeviation');
readOptVar('drawdown');

$returns = loadReturns($funds, $minDate, $maxDate);

/*
 * optimization
 */

$weights = optimizeCustomTarget($returns, $v);

/*
 * dump json
 */

header('Content-Type:application/json; charset=UTF-8');

//echo '{"result": "great success"}';

