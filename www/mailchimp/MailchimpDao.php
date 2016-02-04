<?php

require_once('MailchimpMember.php');
require_once(__DIR__.'/../api/common.php');

class MailchimpDao
{
    function getApiKey($userId)
    {
        $db = dbConnect();
        $stmt = $db->prepare("SELECT AccessToken FROM Mailchimp WHERE UserId=?");
        bind_param_array($stmt, 'i', $userId);
        $success = $stmt->execute();
        if (!$success)
            err('Failed to retrieve save access token');
        $stmt->bind_result($apiKey);
        if (!$stmt->fetch())
            err('User missing mailchimp access token');
        return $apiKey;
    }

    function getListMembers($listId)
    {
        $db = dbConnect();
        $stmt = $db->prepare("SELECT vEmail, dModifiedDate, eValidEmail FROM hfin_list_emails WHERE iListID=?");
        bind_param_array($stmt, 'i', $listId);
        $success = $stmt->execute();
        if (!$success)
            err('Failed to retrieve save access token');
        $stmt->bind_result($email, $modifiedDate, $valid);

        $result = [];
        while ($stmt->fetch()) {
            $result[strtolower($email)] = new MailchimpMember(
                NULL, $email, $modifiedDate, $valid == 'Yes', false, false);
        }
        return $result;
    }

    function insertEmails($listId, $members)
    {
        $db = dbConnect();
        foreach ($members as $member) {
            $e = $db->escape_string($member->email);
            $s = $member->subscribed ? 'Yes' : 'No';
            $d = $db->escape_string($member->modifiedDate);
            $sql = "insert into hfin_list_emails (iListID, vEmail, eValidEmail, dModifiedDate) values ($listId, '$e', '$s', '$d')";
            $db->query($sql);
        }
    }

    function updateEmails($listId, $members)
    {
        $db = dbConnect();
        foreach ($members as $member) {
            $e = $db->escape_string($member->email);
            $s = $member->subscribed ? 'Yes' : 'No';
            $d = $db->escape_string($member->modifiedDate);
            $sql = "update hfin_list_emails set eValidEmail = '$s', dModifiedDate = '$d' where iListID = $listId and vEmail = '$e'";
            $db->query($sql);
        }
    }

    public function getMembersFromEmails($dbListId, $newEmails)
    {
        // todo: go to DB and check connections and followers

        $result = [];

        foreach ($newEmails as $email){
            $result[strtolower($email)] = new MailchimpMember(NULL, $email, '', true, false, false);
        }

        return $result;
    }
}