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
        global $log;
        $log->logInfo('Twitter_message instantiated with '.print_r($data, true));
        //replace t.co URLs with longer versions
        if (!empty($data->entities->urls))
        {
            foreach ($data->entities->urls as $url)
            {
                if (!empty($url->display_url))
                    $data->text = str_ireplace($url->url, $url->display_url, $data->text);
            }

            $log->logInfo('After substituting oringal URLs: '.$data->text);
        }

        $this->tweet = $data;
        $this->extractTags(); //should be first

        if (isset($data->text))
        {
            $log->logInfo('Received a tweet');
            $log->logInfo($data->user->screen_name.': '.$data->text);
        }
        else
        {
            $log->logInfo('Received: '.print_r($data, true));
        }

        //initialize keyword to full text
        $this->keyword = $this->text();
        $log->logInfo('Keyword initialized to: '.$this->keyword());

        $log->logInfo('Sender: '.$this->sender().', recipient: '.$this->recipient());
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
        global $log;
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

        $log->logInfo('SHOULDNOT HIT THIS PLACE');
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
        global $log;
        $this->hashtags = array();

        if (preg_match_all('/(#\w+)/', mb_strtolower($this->tweet->text), $matches) > 0 && !empty($matches[0]))
            $this->hashtags = $matches[0];

        $log->logInfo('Tags found: '.implode(', ', $this->hashtags));
    }


    //substitutes a regex match in tweet text with empty string
    function replaceRegex($regex)
    {
        global $log;
        $log->logInfo('Regex on keyword text: '.$regex);
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

    function save()
    {
        global $DB;
        global $log;
        
        $tweet = $this->tweet;

        $tweet_record = array(
            'id'		=>  $tweet->id,
            'twitter_id'	=>  $tweet->user->id,
            'screen_name'	=>  $tweet->user->screen_name,
            'created_at'	=>  from_RESTdate($tweet->created_at),
            'in_reply_to_user_id'	    =>  @$tweet->in_reply_to_user_id,
            'in_reply_to_status_id'	    =>  @$tweet->in_reply_to_status_id,
            'in_reply_to_screen_name'   =>  @$tweet->in_reply_to_screen_name,
            'source'		    =>  @html_entity_decode($tweet->source),
            'lat'			    =>  @$tweet->geo->coordinates[0],
            'lng'			    =>  @$tweet->geo->coordinates[1],
            'location'		    =>  @$tweet->user->location,
            'unix_created_at'	    =>  from_RESTdate($tweet->created_at, true),
            'profile_image_url'	    =>	@$tweet->user->profile_image_url,
            'message'		    =>	html_entity_decode($tweet->text),
            'lang'			    =>  @$tweet->lang
        );

   
	$user_record = array(
	    'twitter_id'	=>  $tweet->user->id,
	    'screen_name'	=>  $tweet->user->screen_name,
	    'name'		=>  @$tweet->user->name,
	    'statuses_count'=>  $tweet->user->statuses_count,
	    'location'	=>  @$tweet->user->location,
	    'profile_background_image_url'	=>  @$tweet->user->profile_background_image_url,
	    'description'	=>  @$tweet->user->description,
	    'url'		=>  @$tweet->user->url,
	    'followers_count'   =>  @$tweet->user->followers_count,
	    'friends_count'	    =>  @$tweet->user->friends_count,
	    'favourites_count'   =>  @$tweet->user->favourites_count,
	    'profile_image_url' =>  @$tweet->user->profile_image_url,
	    'time_zone'	    =>  @$tweet->user->time_zone,
	    'utc_offset'	    =>  @$tweet->user->utc_offset,
	    'created_at'	=>  from_RESTdate($tweet->created_at),
            'verified'          => @$tweet->user->verified
	);

	$DB->query('INSERT IGNORE INTO twitter_users(?#) VALUES (?a)
		ON DUPLICATE KEY UPDATE
		    screen_name = VALUES(screen_name),
		    name = VALUES(name),
		    statuses_count = VALUES(statuses_count),
		    location = VALUES(location),
		    profile_background_image_url = VALUES(profile_background_image_url),
		    description = VALUES(description),
		    url = VALUES(url),
		    followers_count = VALUES(followers_count),
		    friends_count = VALUES(friends_count),
		    profile_image_url = VALUES(profile_image_url),
		    time_zone = VALUES(time_zone),
		    utc_offset = VALUES(utc_offset),
		    verified = VALUES(verified)
		',
		array_keys($user_record),array_values($user_record));
   
        $DB->query('INSERT IGNORE INTO tweets(?#) VALUES (?a)',array_keys($tweet_record),array_values($tweet_record));

        $log->logInfo("Saved tweet: ".$tweet->id);
        
        return $tweet->id;

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
        global $log;
        $matches = array();
        foreach ($keywords as $keyword)
        {
            if (preg_match_all('/\\b'.trim($keyword).'\\b/i', ' '.$this->tweet->text.' ', $matches)>0)
            {
                $log->logInfo('Matched '.$keyword);
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
        global $log;
        if (preg_match_all($pattern, ' '.$this->text().' ', $matches) > 0)
        {
            $this->replaceRegex($pattern);
            $log->logInfo('Found input: '.$matches[1][0]);
            return $matches[1][0];
        }
        return false;
    }
}
?>