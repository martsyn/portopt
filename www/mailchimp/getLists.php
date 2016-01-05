<?php
$webService = true;

require_once(__DIR__.'/../api/common.php');
require_once('miniMCAPI.class.php');

$userId = $_GET['userId'];

if (!is_numeric($userId))
	die('bad userId');

$db = dbConnect();
if ($db->connect_error)
	err("Connection failed: " . $db->connect_error, 500);

$stmt = $db->prepare("select AccessToken, ListId from Mailchimp where UserId=?");

bind_param_array($stmt, 'i', $userId);
$success = $stmt->execute();

$stmt->bind_result($apikey, $listId);

$result = new stdClass();

$result->hasToken = $stmt->fetch();

if ($result->hasToken && $apikey) {
	$result->activeList=$listId;

	$api = new MCAPI($apikey);
	$api->useSecure(true);

	$lists = $api->lists();
	$result->lists = [];
	foreach($lists['data'] as $list){
		$result->lists[$list['id']] = $list['name'];
	}
}

echo json_encode($result);
