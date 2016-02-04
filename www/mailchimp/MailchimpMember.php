<?php

class MailchimpMember
{
    public $id;
    public $email;
    public $modifiedDate;
    public $subscribed;
    public $connection;
    public $follower;

    function __construct($id, $email, $modifiedDate, $subscribed, $connection, $follower){
        $this->id = $id;
        $this->email = $email;
        $this->modifiedDate = $modifiedDate;
        $this->subscribed = $subscribed;
        $this->connection = $connection;
        $this->follower = $follower;
    }
}