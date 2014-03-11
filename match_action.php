<?php

require_once('twitter_message.php');
require_once('tweet_action.php');
require_once('data_helper.php');

class match_action extends tweet_action
{
    
    var $matches = array();
    
    function process()
    {
        global $log;
        
        $log->logInfo('');
        $log->logInfo('');
        $log->logInfo('Starting Match Action!!!!');
        
        $keywords = DataHelper::getKeywords(true);
        $keywords = array_keys($keywords);
        
        $this->matches = $this->m_tweet->contains($keywords);

        $log->logInfo('Done Match Action!!!!');
        $log->logInfo('');
        $log->logInfo('');

        return $this->get_last_error_code()==0 && !empty($this->matches);
    }
    
    function save()
    {
        if (empty($this->matches))
            return false;
        
        global $log;
        
        try
        {
            //Save tweet
            $tweet_id = $this->tweet->save();

            //Save the keyword relation
            global $DB;

            foreach ($this->matches as $matched_keyword)
            {
                $insert_id = $DB->query("INSERT IGNORE INTO tweet_relations(tweet_id, keyword_id) VALUES (?, SELECT id FROM keywords where keyword = ?)", 
                        $tweet_id, $matched_keyword);

                $log->logInfo("Added tweet relation of " . $matched_keyword . " with " . $tweet_id . ". Relation id = " . $$insert_id);
            }

            return true;
        }
        catch (Exception $exp)
        {
            exception_handler($exp);
            $log->logCrit("Exception in match_action::save", $exp);
            return false;
        }
    }
}


?>