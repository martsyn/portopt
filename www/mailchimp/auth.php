<?php
require_once('MC_OAuth2Client.php');
$client = new MC_OAuth2Client();
$userId = 1;
?>
<!DOCTYPE html>
<html>
<head>
<title>Mailchimp integration</title>
<style>
#mailchimpFrame {
    position: absolute;
    top: 50%;
    left: 50%;
    margin-right: -50%;
    transform: translate(-50%, -50%);
    width: 400px;
    height: 600px;
    border: 1px solid #ccc;
}
.placeholder {
    font-style: italic;
    color: darkgray;
}
</style>
</head>
<body>
<label for="userId">UserId:</label><input type="number" id="userId" value="1"><br/>
<div id="linkNewAccount" style="display:none">
	<button id="linkButton" type="button" onclick="startMailchimp()">Link my Mailchimp account</button>
	<br/>
	<span id="keySavingResult"></span>
</div>
<div id="accountLinked" style="display:none">
	<span>Account linked.</span>
	<br/>
    <label for="listSelect">List to sync with:</label><select id="listSelect" onchange="listChanged()">
		<option value="" class="placeholder">Select list to sync</option>
	</select>
	<button id="setActiveListButton" style="display: none" onclick="setActiveList()">Set</button>
</div>

<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>

<script>

var userId = <?=$userId?>;
var activeList = '';

$(document).ready(function() {
	$('#userId').val(userId);
	showLists();
});

function startMailchimp(){
	var url = "<?=$client->getLoginUri()?>";
	//window.location.href = url;
	//var url = "https://login.mailchimp.com/oauth2/authorize?response_type=code&client_id=755469067161&redirect_uri=https%3A%2F%2Fapi.hfinone.com%2Fmailchimp%2Fcomplete.php%3FuserId%3D" + userId;
	$('<iframe id="mailchimpFrame" src="' + url + '" />')
		.appendTo('body');
}

function showLists(){
	$.ajax({
		url: "getLists.php",
		data: {userId: userId},
		dataType: "json",
		success: function (result) {
			if (!result.hasToken) {
				$('#linkNewAccount').show();
				$('#accountLinked').hide();
			}
			else {
				$('#linkNewAccount').hide();
				$('#accountLinked').show();

				for (var id in result.lists){
					$('#listSelect')
						.append($('<option>', {value:id})
						.text(result.lists[id]));
				}

				activeList = result.activeList;

				if (activeList)
					$('#listSelect').val(result.activeList);
			}
		}});
}

function listChanged()
{
	var listId = $('#listSelect').val();
	if (listId && listId != activeList)
		$("#setActiveListButton").show();
	else
		$("#setActiveListButton").hide();
}

function setActiveList()
{
	activeList = $('#listSelect').val();
	$.ajax({
		url: "saveList.php",
		data: {userId: userId, listId: activeList},
		dataType: "json",
		success: function (result) {
			listChanged();
		}});
}

// called from child window rendered by complete.php
function receiveCode(code) {
	$('#mailchimpFrame').remove();

	$.ajax({
		url: "saveKey.php",
		data: {userId: userId, apiKey: code},
		dataType: "json",
		success: function (result) {
			$('#keySavingResult').text(result.success ? "Great success" : "Epic failure");
			if (!result.success)
				return;
			showLists();
		}});
}
</script>
</body>
</html>

