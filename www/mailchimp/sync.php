<?php

require_once(__DIR__.'/../api/common.php');

addJsonHeader();

$userId = $_GET['userId'];
$listId = $_GET['listId'];

if (!is_numeric($userId))
    die('bad userId');

$db = dbConnect();
if ($db->connect_error)
    err("Connection failed: " . $db->connect_error, 500);

$returnsStmt = $db->prepare("update Mailchimp set ListId=? where UserId=?");

bind_param_array(
    $returnsStmt,
    's', $listId,
    'i', $userId);
$success = $returnsStmt->execute();

