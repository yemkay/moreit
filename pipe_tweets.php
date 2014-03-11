<?php

require_once('pipe_processor.php');
require_once('match_action.php');

class pipe_tweets extends pipe_processor
{
    function pipe_tweets()
    {
        parent::pipe_processor();
        $this->file_name = 'pipe_tweets_daemon.php';
    }

    function process($data)
    {
        global $log;
        try
        {
            $log->logDebug('Processing tweet: ', $data);
            
            $tweet = new Twitter_Message($data);
            
            $match = new match_action($tweet);
            
            if ($match->process())
            {
                $log->logInfo('Matched a tweet');
                
                $tweet_id = $tweet->save();
            }
            else
            {
                $log->logInfo('Tweet is not matched with any keyword..');
                
                //ping the db
                global $DB;
                $DB->query('SELECT NOW()');
            }
        }
        catch (Exception $exp)
        {
            exception_handler($exp);
            $log->logCrit("Exception in pipe_tweets", $exp);
        }

        $log->logInfo('done processing!!');
    }

 
}


?>