<?php

require_once(__DIR__.'/../api/common.php');
require_once('MailchimpDatabase.php');

addJsonHeader();

$userId = getGetVar('user');
$title = trim(getGetVar('title'));

if (!is_numeric($userId))
    die('bad userId');

if (!(strlen($title) > 0))
    die('bad title');

$db = new MailchimpDatabase();

$result = new stdClass();

try {
    $result->id = $db->createList($userId, $title);
}
catch (Exception $x){
    $result->error = $x->getMessage();
}

echo json_encode($result);