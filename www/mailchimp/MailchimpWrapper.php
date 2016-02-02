<?php

require_once('miniMCAPI.class.php');

class MailchimpWrapper
{
    private $apiKey;
    private $dc;

    function __construct($apiKey){
        $this->apiKey = $apiKey;
        $this->dc = 'us1';
        $dcIdx = strpos($this->apiKey, "-");
        if ($dcIdx){
            $this->dc = substr($this->apiKey, $dcIdx + 1);
        }
    }

    private function request($verb, $resource, $params = NULL, $data = NULL, $log = false)
    {
        $url = 'https://'.$this->dc.'.api.mailchimp.com/3.0/'.$resource;
        if ($params)
            $url .= '?'.http_build_query($params);
        $headers = ['Authorization: apikey '.$this->apiKey, 'content-type: application/json'];
        $curl = curl_init();

        $curl_log = 0;
        if ($log) {
            $curl_log = fopen("/home/apache/curl.log", 'a');
            curl_setopt($curl, CURLOPT_VERBOSE, true);
            curl_setopt($curl, CURLOPT_STDERR, $curl_log);
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);

        if ($data)
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        if ($log)
            fclose($curl_log);
        if (curl_errno($curl))
        {
            error_log("'$verb' request to $url failed with CURL error: ".curl_error($curl));
            return NULL;
        }
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($status >= 300) {
            error_log("'$verb' request to $url failed with status $status and message $response");
            return NULL;
        }
        return json_decode($response);
    }

    public function checkHfinGroups($mcListId){
        $cats = $this->request('GET', "lists/$mcListId/interest-categories", ['fields' => 'categories.id,categories.title']);
        $catId = NULL;
        foreach ($cats->categories as $cat) {
            if ($cat->title == 'HFIN One') {
                $catId = $cat->id;
                break;
            }
        }

        $connectionId = NULL;
        $followerId = NULL;

        if ($catId) {
            $interests = $this->request('GET', "/lists/$mcListId/interest-categories/$catId/interests", ['fields' => 'interests.id,interests.name'])
                ->interests;

            foreach ($interests as $interest) {
                if ($interest->name == 'Connection')
                    $connectionId = $interest->id;
                elseif ($interest->name == 'Follower')
                    $followerId = $interest->id;
            }

            if (!$connectionId || !$followerId) {
                $this->request('DELETE', "/lists/$mcListId/interest-categories/$catId");
                $catId = NULL;
            }
        }

        if (!$catId){
            $catId = $this->request('POST', "lists/$mcListId/interest-categories", ['fields' => 'id'],
                [
                    'title' => 'HFIN One',
                    'type' =>'checkboxes'
                ])->id;
            $connectionId = $this->request('POST', "/lists/$mcListId/interest-categories/$catId/interests", ['fields' => 'id'],
                [
                    'name' => 'Connection'
                ])->id;
            $followerId = $this->request('POST', "/lists/$mcListId/interest-categories/$catId/interests", ['fields' => 'id'],
                [
                    'name' => 'Follower'
                ])->id;
        }

        return (object) ['Connection' => $connectionId, 'Follower' => $followerId];
    }

    public function getMembers($mcListId, $mcGroupIds) {
        $members = $this->request('GET', "/lists/$mcListId/members", ['fields' => 'members.id,members.email_address,members.status,members.last_changed,members.interests'])
            ->members;

        $result = [];

        foreach ($members as $member){
            $val = (object) [
                'Id' => $member->id,
                'Email' => $member->email_address,
                'Subscribed' => $member->status == 'subscribed',
                'ModifiedDate' => $member->last_changed,
            ];
            foreach ($mcGroupIds as $name => $id){
                $val->$name = isset($member->interests->$id) && $member->interests->$id;
            }

            $result[strtolower($member->email_address)] = $val;
        }
        return$result;
    }
}