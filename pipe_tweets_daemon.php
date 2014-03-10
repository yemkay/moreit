<?php

require_once('pipe_tweets.php');

global $log;

$log->logInfo("Starting tweets daemon");

$obj = new pipe_partner_tweets();
$obj->run(PIPE_ID_TWEETS);

?>