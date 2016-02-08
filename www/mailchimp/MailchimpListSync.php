<?php

require_once('MailchimpDatabase.php');
require_once('MailchimpWrapper.php');

addJsonHeader();

$userId = 1;
$dbListId = 1;
$mcListId = '1a68380cc0';

$db = new MailchimpDatabase();
$mc = new MailchimpWrapper($db->getApiKey($userId));

$dbMembers = $db->getListMembers($dbListId);

$mcGroupIds = $mc->checkHfinGroups($mcListId);
$mcMembers = $mc->getListMembers($mcListId, $mcGroupIds);

// update DB

$updates = [];
$inserts = [];

foreach ($mcMembers as $email => $mcMember) {
    if (array_key_exists($email, $dbMembers)){
        $dbMember = $dbMembers[$email];
        if ($dbMember->subscribed != $mcMember->subscribed
            && $dbMember->modifiedDate <= $mcMember->modifiedDate)
            $updates[] = $mcMember;
    }
    else{
        if ($mcMember->subscribed)
            $inserts[] = $mcMember;
    }
}

$db->insertEmails($dbListId, $inserts);
$db->updateEmails($dbListId, $updates);

$newEmails = [];
foreach ($inserts as $mcMember) {
    $newEmails[] = $mcMember->email;
}

$dbMembers = array_merge($dbMembers, $db->getMembersFromEmails($dbListId, $newEmails));

// update MC

$newMembers = [];
$subChanges = [];
$groupChanges = [];

foreach ($dbMembers as $email => $dbMember){
    if (array_key_exists($email, $mcMembers)) {
        $mcMember = $mcMembers[$email];

        if ($mcMember->subscribed != $dbMember->subscribed
            && $mcMember->modifiedDate < $dbMember->modifiedDate)
            $subChanges[$mcMember->id] = $dbMember->subscribed;

        if ($mcMember->connection != $dbMember->connection)
            $groupChanges[$mcMember->id] = (object) ['group' => $mcGroupIds->connection, 'value' => $dbMember->connection];

        if ($mcMember->follower != $dbMember->follower)
            $groupChanges[$mcMember->id] = (object) ['group' => $mcGroupIds->follower, 'value' => $dbMember->connection];
    }
    else{
        if ($dbMember->subscribed)
            $newMembers[] = $dbMember;
    }
}

$mc->addMembers($mcListId, $newMembers, $mcGroupIds);
$mc->changeSubscriptions($mcListId, $subChanges);
$mc->changeGroups($mcListId, $groupChanges);

echo "\nGreat Success\n";