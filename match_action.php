<?php

require_once('twitter_message.php');
require_once('tweet_action.php');
require_once('data_helper.php');

class match_action extends tweet_action
{
    
    function process()
    {
        global $log;
        
        $log->logInfo('');
        $log->logInfo('');
        $log->logInfo('Starting Match Action!!!!');
        
        $keywords = DataHelper::getKeywords(true);
        
        $this->matches = $this->m_tweet->contains($keywords);

        $log->logInfo('Done Match Action!!!!');
        $log->logInfo('');
        $log->logInfo('');

        return $this->get_last_error_code()==0 && !empty($this->matches);
    }
}


?>