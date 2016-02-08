<!DOCTYPE html>
<html lang="en">
<body>
<script>
<?php

require_once(__DIR__.'/../api/common.php');
require_once('MailchimpDatabase.php');

$clientId = '755469067161';
$clientSecret = 'b7cf12433cb3939b40fbf2edf45f341c';

$apiKey = NULL;

$code = getGetVar('code');
if (!$code) {
    $error = 'Code was not supplied';
}
else {
    $data = "grant_type=authorization_code&client_id=$clientId&client_secret=$clientSecret&redirect_uri=https%3A%2F%2Fapi.hfinone.com%2Fmailchimp%2Fcomplete.php&code=$code";
    $response = httpRequest('POST', 'https://login.mailchimp.com/oauth2/token', NULL, NULL, $data);
    if (!$response) {
        $error = 'Code received successfully, but token request failed';
    } else {
        $apiKey = $response->access_token;
    }
}
if ($error)
    echo "window.parent.receiveError('$error');";
else
    echo "window.parent.receiveCode('$apiKey');";
?>
</script>
</body>
</html>
