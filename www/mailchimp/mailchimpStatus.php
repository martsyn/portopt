<?php

require_once(__DIR__.'/../api/common.php');
require_once('MailchimpDatabase.php');
require_once('MailchimpWrapper.php');

addJsonHeader();

$result = (object) ['connected' => false];

$userId = getGetVar('user');
if (!$userId || !is_numeric($userId)) {
    $result->message = "Invalid user";
}
else {
    $db = new MailchimpDatabase();
    $apiKey = $db->getApiKey($userId);
    if (!$apiKey) {
        $result->message = 'Mailchimp account is not linked';
    }
    else {
        $mc = new MailchimpWrapper($apiKey);
        $result->connected = true;
        $result->message = "Account: ".$mc->getAccountDetails();
        $result->cloudLists = $db->getCloudLists();
        $result->mailchimpLists = $mc->getLists();
    }
}
echo json_encode($result);