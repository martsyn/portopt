<?php

require_once('MailchimpMember.php');
require_once(__DIR__.'/../api/common.php');

class MailchimpDatabase
{
    function getApiKey($userId)
    {
        $db = dbConnect();
        $stmt = $db->prepare("SELECT vAccessToken FROM hfin_list_mailchimp WHERE iManagerID=?");
        bind_param_array($stmt, 'i', $userId);
        $success = $stmt->execute();
        if (!$success)
            err('Failed to retrieve save access token');
        $stmt->bind_result($apiKey);
        if (!$stmt->fetch())
            return false;
        return $apiKey;
    }

    function getListMembers($listId)
    {
        $db = dbConnect();
        $stmt = $db->prepare("
select e.vEmail, e.dModifiedDate, e.iUnsubscribed, exists(
    select 1
    from hfin_list_name n
    join hfin_investment_manager_clients c on n.iManagerID=c.iManagerID
    join hfin_member m on c.iMemberId = m.iMemberId
    where n.iListID = e.iListID and e.vEmail=m.vEmail) isConnection
from hfin_list_emails e
where e.iListID=?");
        bind_param_array($stmt, 'i', $listId);
        $success = $stmt->execute();
        if (!$success)
            err('Failed to retrieve save access token');
        $stmt->bind_result($email, $modifiedDate, $unsubscribed, $isConnection);

        $result = [];
        while ($stmt->fetch()) {
            $result[strtolower($email)] = new MailchimpMember(
                NULL, $email, $modifiedDate, $unsubscribed == 0, $isConnection == 1, false);
        }
        return $result;
    }

    function insertEmails($listId, $members)
    {
        $db = dbConnect();
        foreach ($members as $member) {
            $e = $db->escape_string($member->email);
            $u = $member->subscribed ? 0 : 1;
            $d = $db->escape_string($member->modifiedDate);
            $sql = "insert into hfin_list_emails (iListID, vEmail, iUnsubscribed, eValidEmail, dModifiedDate) values ($listId, '$e', $u, 'Yes', '$d')";
            $db->query($sql);
        }
    }

    function updateEmails($listId, $members)
    {
        $db = dbConnect();
        foreach ($members as $member) {
            $e = $db->escape_string($member->email);
            $u = $member->subscribed ? 0 : 1;
            $d = $db->escape_string($member->modifiedDate);
            $sql = "update hfin_list_emails set iUnsubscribed = $u, dModifiedDate = '$d' where iListID = $listId and vEmail = '$e'";
            $db->query($sql);
        }
    }

    public function getMembersFromEmails($dbListId, $newEmails)
    {
        $members = [];

        if (count($newEmails) > 0) {
            $db = dbConnect();
            $emails = "";
            foreach ($newEmails as $email) {
                if ($emails)
                    $emails .= ',';
                $emails .= "'" . $db->escape_string($email) . "'";
            }

            $sql = "select m.vEmail
from hfin_list_name n
join hfin_investment_manager_clients c on n.iManagerID=c.iManagerID
join hfin_member m on c.iMemberId = m.iMemberId
where n.iListID=$dbListId and m.vEmail in ($emails)";

            $listEmails = $db->query($sql);
            if (!$listEmails) {
                error_log($db->error);
                die();
            }
            while ($row = $listEmails->fetch_assoc())
                $members[strtolower($row['vEmail'])] = NULL;

            var_dump($members);
        }
        $result = [];
        foreach ($newEmails as $email) {
            $result[strtolower($email)] = new MailchimpMember(NULL, $email, '', true, array_key_exists(strtolower($email), $members), false);
        }
        return $result;
    }

    public function saveKey($userId, $apiKey)
    {
        $db = dbConnect();
        $returnsStmt = $db->prepare("insert hfin_list_mailchimp (iManagerID, vAccessToken, dAddedDate) values (?,?,now()) on duplicate key update vAccessToken=?");
        bind_param_array(
            $returnsStmt,
            'i', $userId,
            's', $apiKey,
            's', $apiKey);
        if (!$returnsStmt->execute()){
            error_log($db->error);
        }
    }

    public function removeKey($userId)
    {
        $db = dbConnect();
        //SELECT vAccessToken FROM hfin_list_mailchimp WHERE iManagerID=?
        $returnsStmt = $db->prepare("update hfin_list_mailchimp set iActive=0 where iManagerID=?");
        if ($db->error)
            error_log("Failed to delete: ".$db->error);
        bind_param_array($returnsStmt, 'i', $userId);
        return $returnsStmt->execute();
    }

    public function getCloudLists()
    {
        return [
            ['id' => 1, 'title' => 'Silly list', 'count' => 999]
        ];
    }

    public function createList($userId, $title)
    {
        $db = dbConnect();
        $escapedTitle = $db->escape_string($title);
        $query = "
set @listId = (select conv(left(md5(rand()),8),16,10) AS id from hfin_list_name having id not in (select iListID from hfin_list_name) limit 1);
insert hfin_list_name (iListID, iManagerID, vListName, dCreatedDate) values (@listId, $userId, '$escapedTitle', now());
select @listId;";

        $id = NULL;
        if ($db->multi_query($query))
        {
            do {
                if ($result = $db->store_result()) {
                    if ($row = $result->fetch_row())
                        $id = $row[0];
                    $result->free();
                }
            } while ($db->next_result());
        }
        return $id;
    }
}