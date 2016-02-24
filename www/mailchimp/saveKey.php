<?php

require_once(__DIR__.'/../api/common.php');
require_once('MailchimpDatabase.php');

addJsonHeader();

$userId = getGetVar('user');
$apiKey = getGetVar('apiKey');

if (!is_numeric($userId))
	die('bad userId');

if ($apiKey) {

    if (!strpos($apiKey, '-')) {
        $meta = httpRequest('GET', 'https://login.mailchimp.com/oauth2/metadata', NULL, ["Authorization: OAuth $apiKey"]);
        if (!$meta || !$meta->dc)
            err('Invalid metadata response');
        $apiKey .= '-' . $meta->dc;
    }

    $db = new MailchimpDatabase();
    $db->saveKey($userId, $apiKey);
}
else{
    $db = new MailchimpDatabase();
    $db->removeKey($userId);
}

echo json_encode(['success' => true]);
