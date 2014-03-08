<?php

require_once('pipe_tweets.php');

$obj = new pipe_partner_tweets();
$obj->run(PIPE_ID_TWEETS);

?>