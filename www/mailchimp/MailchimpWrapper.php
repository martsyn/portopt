<?php

require_once('MailchimpMember.php');

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

    private function get($resource, $params = NULL, $log = false){
        return $this->request('GET', $resource, $params, NULL, $log);
    }

    private function post($resource, $params = NULL, $data = NULL, $log = false){
        return $this->request('POST', $resource, $params, $data, $log);
    }

    private function patch($resource, $params = NULL, $data = NULL, $log = false){
        return $this->request('PATCH', $resource, $params, $data, $log);
    }

    private function delete($resource, $log = false){
        return $this->request('DELETE', $resource, NULL, NULL, $log);
    }

    public function checkHfinGroups($mcListId){
        $categoryTitle = 'Cloud Docs';
        $connectionGroupTitle = 'Connection';
        $followerGroupTitle = 'Follower';

        $cats = $this->get("lists/$mcListId/interest-categories", ['fields' => 'categories.id,categories.title']);
        $catId = NULL;
        foreach ($cats->categories as $cat) {
            if ($cat->title == $categoryTitle) {
                $catId = $cat->id;
                break;
            }
        }

        $connectionId = NULL;
        $followerId = NULL;

        if ($catId) {
            $interests = $this->get("lists/$mcListId/interest-categories/$catId/interests", ['fields' => 'interests.id,interests.name'])
                ->interests;

            foreach ($interests as $interest) {
                if ($interest->name == $connectionGroupTitle)
                    $connectionId = $interest->id;
                elseif ($interest->name == $followerGroupTitle)
                    $followerId = $interest->id;
            }

            if (!$connectionId || !$followerId) {
                $this->delete("lists/$mcListId/interest-categories/$catId");
                $catId = NULL;
            }
        }

        if (!$catId){
            $catId = $this->post("lists/$mcListId/interest-categories", ['fields' => 'id'],
                [
                    'title' => $categoryTitle,
                    'type' =>'checkboxes'
                ])->id;
            $connectionId = $this->post("lists/$mcListId/interest-categories/$catId/interests", ['fields' => 'id'],
                [
                    'name' => $connectionGroupTitle
                ])->id;
            $followerId = $this->post("lists/$mcListId/interest-categories/$catId/interests", ['fields' => 'id'],
                [
                    'name' => $followerGroupTitle
                ])->id;
        }

        return (object) ['connection' => $connectionId, 'follower' => $followerId];
    }

    public function getMembers($mcListId, $mcSegmentId, $mcGroupIds) {
        $resource = $mcSegmentId ? "lists/$mcListId/segments/$mcSegmentId/members" : "lists/$mcListId/members";
        $members = $this->get($resource,
            ['fields' => 'members.id,members.email_address,members.status,members.last_changed,members.interests','count'=>2000])
            ->members;

        $result = [];

        foreach ($members as $member){
            $val = new MailchimpMember(
                $member->id, $member->email_address, $member->last_changed, $member->status == 'subscribed', false, false);
            foreach ($mcGroupIds as $name => $id){
                $val->$name = isset($member->interests->$id) && $member->interests->$id;
            }

            $result[strtolower($member->email_address)] = $val;
        }
        return$result;
    }

    public function addMembers($listId, $newMembers, $mcGroupIds)
    {
        foreach ($newMembers as $member){
            $rest = [
                'email_address' => $member->email,
                'status' => $member->subscribed ? 'subscribed' : 'unsubscribed',
            ];

            $interests = [];
            foreach ($mcGroupIds as $name => $id)
                if ($member->$name)
                    $interests[$id] = true;
            if (count($interests) > 0)
                $rest['interests'] = $interests;

            $this->post("lists/$listId/members", NULL, $rest);
        }
    }

    public function changeSubscriptions($mcListId, $subChanges)
    {
        foreach ($subChanges as $id => $isSubscribed){
            $this->patch("lists/$mcListId/members/$id", NULL, ['status' => $isSubscribed ? 'subscribed' : 'unsubscribed']);
        }
    }

    public function changeGroups($mcListId, $groupChanges)
    {
        foreach ($groupChanges as $id => $group){
            echo $this->patch("lists/$mcListId/members/$id", NULL, ['interests' => [$group->group => $group->value]])/"\n\n";
        }
    }

    public function getLists()
    {
        $lists = $this->get('lists', ['fields' => 'lists.id,lists.name,lists.stats.member_count'])
            ->lists;
        $result = [];
        foreach($lists as $list) {
            $result[] = (object)[
                'id' => $list->id,
                'title' => $list->name,
                'count' => $list->stats->member_count,
            ];
            $segments = $this->get("lists/$list->id/segments", ['fields' => 'segments.id,segments.name,segments.member_count'])
                ->segments;
            foreach ($segments as $segment) {
                $result[] = (object)[
                    'id' => $list->id . '.' . $segment->id,
                    'title' => $list->name . '.' . $segment->name,
                    'count' => $segment->member_count];
            }
        }
        return $result;
    }

    public function getAccountDetails()
    {
        $result = $this->get('', ['fields' => 'account_name']);
        return $result->account_name;
    }
}