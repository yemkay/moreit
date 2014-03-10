<?php

require_once('twitter_message.php');

class tweet_action
{
    var $m_tweet = null;
    var $last_error = '';
    var $last_error_code = 0;
    
    function tweet_action($tweet)
    {
        $this->m_tweet = $tweet;
    }
    
    function reset_error()
    {
        $this->last_error = '';
        $this->last_error_code = 0;
    }

    function set_error($response)
    {
        $this->last_error = $response->getError();
        $this->last_error_code = $response->getErrorCode();
    }

    function get_last_error()
    {
        return $this->last_error;
    }

    function get_last_error_code()
    {
        return $this->last_error_code;
    }


    
}

?>