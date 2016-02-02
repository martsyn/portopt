<?php

require_once(__DIR__.'/../api/common.php');

function getApiKey($userId) {
    $db = dbConnect();
    $stmt = $db->prepare("select AccessToken from Mailchimp where UserId=?");
    bind_param_array($stmt, 'i', $userId);
    $success = $stmt->execute();
    if (!$success)
        err('Failed to retrieve save access token');
    $stmt->bind_result($apiKey);
    if (!$stmt->fetch())
        err('User missing mailchimp access token');
    return $apiKey;
}

function getListEmails($listId){
    $db = dbConnect();
    $stmt = $db->prepare("select vEmail, dModifiedDate, eValidEmail from hfin_list_emails where iListID=?");
    bind_param_array($stmt, 'i', $listId);
    $success = $stmt->execute();
    if (!$success)
        err('Failed to retrieve save access token');
    $stmt->bind_result($email, $modifiedDate, $valid);

    $result = [];
    while ($stmt->fetch()) {
        $result[strtolower($email)] = (object) [
            'Email' => $email,
            'ModifiedDate' => $modifiedDate,
            'Subscribed' => $valid == 'Yes',
            'Connection' => false,
            'Follower' => false
        ];
    }
    return $result;
}

function insertEmails($listId, $emails){
    $db = dbConnect();
    foreach ($emails as $email => $subscribed) {
        $e = $db->escape_string($email);
        $s = $subscribed ? 'Yes' : 'No';
        $sql = "insert into hfin_list_emails (iListID, vEmail, eValidEmail) values ($listId, '$e', '$s')";
        $db->query($sql);
    }
}

function updateEmails($listId, $emails){
    $db = dbConnect();
    foreach ($emails as $email => $subscribed) {
        $e = $db->escape_string($email);
        $s = $subscribed ? 'Yes' : 'No';
        $sql = "update hfin_list_emails set eValidEmail='$s' where iListID = $listId and vEmail = '$e'";
        echo $sql;
        $db->query($sql);
    }
}