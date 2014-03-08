<?php

require_once('pipe_processor.php');
require_once('match_action.php');

class pipe_tweets extends pipe_processor
{
    function pipe_tweets()
    {
        parent::pipe_processor();
        $this->file_name = 'pipe_partner_daemon.php';
    }

    function process($data)
    {
        $tweet = null;
        $query_id = false;
        try
        {
            $tweet = new Twitter_Message($data);
            
            if (($partner_id = $tweet->find()) != 0)
            {
                trace('Partner = '.$partner_id);
                if ($tweet->is_processed())
                {
                    trace('Already processed. Skipping...');
                    return false;
                }
                
				$query_id = $tweet->save(true, false); //save as error now and later update

                
                    $match = new match_action($tweet);
                    if ($match->process())
                    {
                        trace('Matched a tweet');
                    }
                    else
                    {
                        trace('Trying with shopper..');
                        //its a shopper service request
                        $shopper = new shopper_action($tweet);
                        if ($shopper->process())
                        {
                            trace('Taken shopper action');
                        }
                        else
                            trace('Not a shopper action either...');
                    }
                
                else
                {
                    trace('Must be a reply/retweet/mention...');
                }
            }
            else
            {
                trace('Not sent to us. Skipping...');
                //ping the db
                global $DB;
                $DB->query('SELECT NOW()');
            }
        }
        catch (Exception $exp)
        {
            exception_handler($e);
        }

        trace('Done!!');
        return $query_id;
    }

 
}


?>