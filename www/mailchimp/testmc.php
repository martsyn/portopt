<?php

require_once('db.php');
require_once('MailchimpWrapper.php');

addJsonHeader();

$userId = 1;
$dbListId = 1;
$mcListId = '1a68380cc0';

$dbMembers = getListEmails($dbListId);

$mc = new MailchimpWrapper(getApiKey($userId));
$mcGroupIds = $mc->checkHfinGroups($mcListId);
$mcMembers = $mc->getMembers($mcListId, $mcGroupIds);

$dbUpdates = [];
$dbInserts = [];

foreach ($mcMembers as $email => $mcMember) {
    if (array_key_exists($email, $dbMembers)){
        $dbMember = $dbMembers[$email];
        if ($dbMember->Subscribed != $mcMember->Subscribed)
            $dbUpdates[$dbMember->Email] = $mcMember->Subscribed;
    }
    else{
        if ($mcMember->Subscribed)
            $dbInserts[$mcMember->Email] = $mcMember->Subscribed;
    }
}

insertEmails($dbListId, $dbInserts);
updateEmails($dbListId, $dbUpdates);

