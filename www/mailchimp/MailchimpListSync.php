<?php

require_once('MailchimpDatabase.php');
require_once('MailchimpWrapper.php');

addJsonHeader();

$userId = 1969475452;
$dbListId = 1504494724;
$mcListId = '8b24fa5083';
$mcSegmentId = NULL;

$db = new MailchimpDatabase();
$mc = new MailchimpWrapper($db->getApiKey($userId));

$dbMembers = $db->getListMembers($dbListId);

$mcGroupIds = $mc->checkHfinGroups($mcListId);
$mcMembers = $mc->getMembers($mcListId, $mcSegmentId, $mcGroupIds);

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
            $groupChanges[] = (object) ['id' => $mcMember->id, 'group' => $mcGroupIds->connection, 'value' => $dbMember->connection];

        if ($mcMember->follower != $dbMember->follower)
            $groupChanges[] = (object) ['id' => $mcMember->id, 'group' => $mcGroupIds->follower, 'value' => $dbMember->follower];
    }
    else{
        if ($dbMember->subscribed)
            $newMembers[] = $dbMember;
    }
}

echo "\n\nnew members: ".count($newMembers)."\n";
foreach ($newMembers as $m)
    echo $m->email."\n";

echo "\n\n---------------------------\nsub changes: ".count($subChanges)."\n";
foreach ($subChanges as $e => $s)
    echo $e."->".$s."\n";

echo "\n\n---------------------------\ngroup changes: ".count($groupChanges)."\n";
$idToGroup = array_flip((array) $mcGroupIds);
var_dump($idToGroup);
foreach ($groupChanges as $g)
    echo $g->id."->".$idToGroup[$g->group]." = ".$g->value."\n";

$mc->addMembers($mcListId, $newMembers, $mcGroupIds);
$mc->changeSubscriptions($mcListId, $subChanges);
$mc->changeGroups($mcListId, $groupChanges);

echo "\n\nGreat Success\n";