<?php

/* Represents a Tweet or a Direct Message */
class Twitter_Message {

    var $location = '';
    var $hashtags = array();
    
    var $tweet;

    var $is_dm = false;

    var $keyword = '';

    var $location_source = LOCATION_NONE;
    var $location_bounds = '';
    var $matched_query_tag = '';
    var $query_type = '';

    function Twitter_Message($data)
    {
        trace('Twitter_message instantiated with '.print_r($data, true));
        //replace t.co URLs with longer versions
        if (!empty($data->entities->urls))
        {
            foreach ($data->entities->urls as $url)
            {
                if (!empty($url->display_url))
                    $data->text = str_ireplace($url->url, $url->display_url, $data->text);
            }

            trace('After substituting oringal URLs: '.$data->text);
        }

        $this->tweet = $data;
        $this->extractTags(); //should be first

        if (isset($data->text))
        {
            trace('Received a tweet');
            trace($data->user->screen_name.': '.$data->text);
        }
        else
        {
            trace('Received: '.print_r($data, true));
        }

        //initialize keyword to full text
        $this->keyword = $this->text();
        trace('Keyword initialized to: '.$this->keyword());

        trace('Sender: '.$this->sender().', recipient: '.$this->recipient());
    }

    function sender()
    {
        return ($this->is_dm)? $this->tweet->sender->screen_name:$this->tweet->user->screen_name;
    }

    function sender_user()
    {
        return ($this->is_dm)? $this->tweet->sender:$this->tweet->user;
    }

    function sender_id()
    {
        return ($this->is_dm)? $this->tweet->sender->id:$this->tweet->user->id;
    }

    function sender_name()
    {
        return ($this->is_dm)? $this->tweet->sender->name:$this->tweet->user->name;
    }

    function sender_bio()
    {
        return ($this->is_dm)? $this->tweet->sender->description:$this->tweet->user->description;
    }

    function sender_web()
    {
        return ($this->is_dm)? $this->tweet->sender->url:$this->tweet->user->url;
    }

    function recipient()
    {
        if ($this->is_dm)
            return $this->tweet->recipient->screen_name;

        if (isset($this->tweet->retweeted_status))
            return $this->tweet->retweeted_status->user->screen_name;

        if (!empty($this->tweet->in_reply_to_screen_name))
            return $this->tweet->in_reply_to_screen_name;

        if (!empty($this->ghost_recipients))
        {
            return $this->ghost_recipients['userName'];
        }

        trace('SHOULDNOT HIT THIS PLACE');
        return TWITTER_USER;
    }

    function recepient_id() //returns twitter_id of reply_to user. if DM, returns the id of the receipeint
    {
        if ($this->is_dm)
            return $this->recipient->id;

        if (isset($this->tweet->retweeted_status))
            return $this->tweet->retweeted_status->user->id;

        if (!empty($this->tweet->in_reply_to_user_id_str))
            return $this->tweet->in_reply_to_user_id_str;

        if (!empty($this->ghost_recipients))
        {
            return $this->ghost_recipients['twitter_id'];
        }

        /*
        $mentioned_ids = array();
        // "entities":{"urls":[],"user_mentions":[{"indices":[3,18],"id_str":"247884504","name":"SHARMILAFARUQUI",
        // "screen_name":"sharmilafaruqi","id":247884504}],"hashtags":[]}}
        if (isset($data->entities) && isset($data->entities->user_mentions))
        {
            foreach ($data->entities->user_mentions as $mention)
            {
                if (!empty($mention->id_str))
                    $mentioned_ids[] = $mention->id_str;
            }
        }
        return $mentioned_ids;*/
    }


    function image()
    {
        if ($this->is_dm)
            return null;

        if (!empty($this->tweet->entities) &&
                !empty($this->tweet->entities->media) &&
                        !empty($this->tweet->entities->media[0]->media_url))
           return $this->tweet->entities->media[0]->media_url;
    }

    function twitterLocation()
    {
        if ($this->is_dm)
            return null;

        if (!empty($this->tweet->place) &&
                !empty($this->tweet->place->full_name))
           return $this->tweet->place->full_name;
    }

    function extractTags()
    {
        $this->hashtags = array();

        if (preg_match_all('/(#\w+)/', mb_strtolower($this->tweet->text), $matches) > 0 && !empty($matches[0]))
            $this->hashtags = $matches[0];

        trace('Tags found: '.implode(', ', $this->hashtags));
    }


    //substitutes a regex match in tweet text with empty string
    function replaceRegex($regex)
    {
        trace('Regex on keyword text: '.$regex);
        $this->keyword = preg_replace($regex, '', ' '.$this->keyword.' ');
	$this->keyword = trim($this->keyword);
    }

    //substitutes a hash tag in the tweet text with empty string
    function replaceText($text)
    {
        $this->keyword = trim(str_ireplace($text, '', $this->keyword)); //@todo test this
    }

    function getHashtags()
    {
        return implode(' ', $this->hashtags);
    }

    function text()
    {
        return $this->tweet->text;
    }

    function keyword()
    {
        return $this->keyword;
    }
    
    function is_reply()
    {
        return (!empty($this->tweet->in_reply_to_status_id_str));
    }

    function is_mention()
    {
        return $this->is_mention;
    }

    function id()
    {
        return $this->tweet->id;
    }

    //considers only explicitly specified location OR postal code as a "location specific query".
    //Location read from user's profile is not used to limit the query results
    function is_location_explicit()
    {
        return in_array($this->location_source, array(LOCATION_TEXT, LOCATION_POSTALCODE));
    }

    //returns true, if location is found from any of the location source enabled by partner
    function has_location()
    {
        return $this->location_source!=LOCATION_NONE;
    }

    function location_text()
    {
        $text = $this->location.' '.$this->postalcode;
        return trim($text);
    }

    function get_time()
    {
        return $this->tweet->created_at;
    }

    function save($is_error, $is_complete)
    {
        global $DB;

        $u = array(
            'twitter_id' => $this->sender_id(),
            'name'       => $this->sender_name(),
            'screen_name' => $this->sender(),
            'last_active' => from_RESTdate($this->tweet->created_at, true),
            'location' => $this->location_text(),
            'json' => json_encode($this->tweet),
            'is_shopper' => 1,
            'bio' => $this->sender_bio(),
            'web' => $this->sender_web(),
            'partner_id' => $this->getPartnerId()
        );

        $a = array(
            'twitter_id' => $this->sender_id(),
            'created_at_epoch' => from_RESTdate($this->tweet->created_at, true),
            'query' => $this->keyword(),
            'tweet_id' => $this->id(),
            'location' => $this->location_text(),
            'json' => json_encode($this->tweet),
            'is_error' => $is_error? 1:0,
            'is_complete' => $is_complete? 1:0,
            'partner_id' => $this->getPartnerId()
        );

        $id = $DB->query('INSERT IGNORE INTO shoppers(?#) VALUES (?a)
                            ON DUPLICATE KEY UPDATE last_active=VALUES(last_active), location = VALUES(location),
                            json = VALUES(json), is_shopper = VALUES(is_shopper), 
                            bio = VALUES(bio), web = VALUES(web), screen_name = VALUES(screen_name),
                            partner_id=VALUES(partner_id)',
                array_keys($u), array_values($u));

        $id = $DB->query('INSERT IGNORE INTO shopper_queries(?#) VALUES (?a)
                          ON DUPLICATE KEY UPDATE is_error=VALUES(is_error),
                          is_complete=VALUES(is_complete)', array_keys($a), array_values($a));
        trace('Query logged as '.$id);

        return $id;
    }

    function updateSinceId()
    {
        $key = $this->is_dm? 'since_id_dm':'since_id_mention';
        setSetting($key, $this->id());
    }

    

    function lat()
    {
        if (empty($this->tweet->coordinates) ||
                empty($this->tweet->coordinates->coordinates) ||
                        empty($this->tweet->coordinates->coordinates[1]))
                                return false;

        return $this->tweet->coordinates->coordinates[1];
    }

    function lng()
    {
        if (empty($this->tweet->coordinates) ||
                empty($this->tweet->coordinates->coordinates) ||
                        empty($this->tweet->coordinates->coordinates[0]))
                                return false;

        return $this->tweet->coordinates->coordinates[0];
    }


    //returns array of keywords present in the tweet
    function contains($keywords)
    {
        $matches = array();
        foreach ($keywords as $keyword)
        {
            if (preg_match_all('/\\b'.trim($keyword).'\\b/i', ' '.$this->tweet->text.' ', $matches)>0)
            {
                trace('Matched '.$keyword);
                $matches[] = $keyword;
            }
        }
        
        global $log;
        $log->logDebug("Matches found: ".count($matches)." => ".implode(', ', $matches));
        
        return empty($matches)? false:$matches;
    }

    function is_test()
    {
        return isset($this->tweet->is_test) && $this->tweet->is_test==true;
    }

   
    function mention_starts_with_username()
    {
        return strcasecmp($this->recipient(), $this->tweet->in_reply_to_screen_name)===0;
    }

   
    //replaces the pattern and returns the result
    function extractInput($pattern)
    {
        if (preg_match_all($pattern, ' '.$this->text().' ', $matches) > 0)
        {
            $this->replaceRegex($pattern);
            trace('Found input: '.$matches[1][0]);
            return $matches[1][0];
        }
        return false;
    }
}
?>