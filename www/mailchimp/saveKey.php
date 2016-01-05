<?php
$webService = true;

require_once(__DIR__.'/../api/common.php');

$userId = $_GET['userId'];
$apiKey = $_GET['apiKey'];

if (!is_numeric($userId))
	die('bad userId');

$db = dbConnect();
if ($db->connect_error)
	err("Connection failed: " . $db->connect_error, 500);

$returnsStmt = $db->prepare("insert Mailchimp (UserId, AccessToken) values (?,?) on duplicate key update AccessToken=?");

bind_param_array(
	$returnsStmt,
	'i', $userId,
	's', $apiKey,
	's', $apiKey);
$success = $returnsStmt->execute();

$result = new stdClass();
$result->success = $success;

echo json_encode($result);
