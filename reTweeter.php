<?php
	echo "Checking for new tweets from The Worst Fans...\n";

	require "vendor/autoload.php";

	use Abraham\TwitterOAuth\TwitterOAuth;
    
    $PATH_TO_TWITTER_API_CONFIG_FILE = 'F:\Projects\reTweeter\reTweeter.cfg';
    $PATH_TO_CONFIG_FILE = 'F:\Projects\reTweeter\tweetChannels.cfg';
	$DATABASE_FILE = "F:\\Projects\\reTweeter\\db.csv";
	
	/**
	* Function that retrieves a mapping of username to last tweet ID.
	*
	* @param 	void
	* @returns 	HashMap[username] = id
	*/
	function getLastTweetsMap()
	{
		global $DATABASE_FILE;
		echo "Retrieving lastest tweet info from database file [$DATABASE_FILE]\n";
		$lastTweetsMap = array();
		$latestTweetsData = file_get_contents($DATABASE_FILE);
		$latestTweetsArray = explode("\n", $latestTweetsData);
		foreach ($latestTweetsArray as $latestTweetString)
		{
			$latestTweet = explode(',', $latestTweetString);
			if($latestTweet[0] == '')
			{
				break;
			}
			#echo "name : [$latestTweet[0]] \n";
			#echo "id : [$latestTweet[1]] \n";
			$lastTweetsMap[$latestTweet[0]] = $latestTweet[1];
		}
		echo "getLastTweetsMap lastTweetsMap : [" . print_r($lastTweetsMap, true) . "]\n";
		return $lastTweetsMap;
	}
    
    /*
        reads in tweetChannels.cfg which contains keywords assigned to each channel for The Worst Fans    
    */
    function getTwitterChannelKeywords()
    {
        global $PATH_TO_CONFIG_FILE;
        $dataString = file_get_contents($PATH_TO_CONFIG_FILE);
        #echo $dataString;
        $twitterChannelData = explode("\n",$dataString);
        $twitterChannelInfoMap = array();
        foreach($twitterChannelData as $twitterChannelLine)
        {
            $twitterChannelLineInfo = explode("=", $twitterChannelLine);
			$keywords = array();
			foreach(explode(",", $twitterChannelLineInfo[1]) as $keyword)
			{
				$keywords[] = str_replace(array("\n", "\r"), '', $keyword);
			}
            $twitterChannelInfoMap[$twitterChannelLineInfo[0]] = $keywords;
			#gotta strip outnew line characters in the keywords
        }
        print_r($twitterChannelInfoMap);
        return $twitterChannelInfoMap;
    }
    
    /*
        reads in reTweeter.cfg which contains twitter api info for the different accounts
    */
    function getTwitterConfig()
    {
        global $PATH_TO_TWITTER_API_CONFIG_FILE;
        $dataString = file_get_contents($PATH_TO_TWITTER_API_CONFIG_FILE);
        #echo "getTwitterConfig : [" . $dataString . "]";
        $twitterConfigData = explode("\n",$dataString);
        $twitterConfigInfoMap = array();
        foreach($twitterConfigData as $twitterConfigDataLine)
        {
            #echo "twitterConfigDataLine [" . print_r($twitterConfigDataLine) . "]cc\n";
            $twitterConfigDataLineInfo = explode("=", $twitterConfigDataLine);
            #echo "dd twitterConfigDataLineInfo [" . print_r($twitterConfigDataLineInfo) . "]\n";
            $accountInfo = explode(".", $twitterConfigDataLineInfo[0]);
            #echo "name  [" . $accountInfo[0] . "] type [" . $accountInfo[1] . "] val  [" . $twitterConfigDataLineInfo[1] . "]\n";
            #echo "accountInfo [" . print_r($accountInfo) . "]\n";
            if($accountInfo[0] != '')
            {
                $twitterConfigInfoMap[$accountInfo[0]][$accountInfo[1]] = $text = preg_replace("/\r|\n/", "", $twitterConfigDataLineInfo[1]);
            }
        }
        echo "twitterConfigInfoMap [" . print_r($twitterConfigInfoMap) . "]\n";
        return $twitterConfigInfoMap;
    }
	
	/*
	* Stored last tweet db info
	*/
	function setLastTweetsMap($lastTweetsMap)
	{
		global $DATABASE_FILE;
		#echo "setLastTweetsMap lastTweetsMap : [" . print_r($lastTweetsMap, true) . "]\n";
		$databaseData = '';
		foreach($lastTweetsMap as $twitterHandle => $tweetId)
		{
			#echo "setLastTweetsMap twitterHandle [$twitterHandle] tweetId [$tweetId]\n";
			$databaseData .= "$twitterHandle,$tweetId\n";
		}
		if($databaseData == '')
		{
			die("Some error occured. The program just tried to save an empty database, that's...not good.\n");
		}
		file_put_contents($DATABASE_FILE, $databaseData);
	}
	
	/*
	* Performs api call to get last 200 tweets from the given user since the last recorded retweet (200 is the max)
	*/
	function getTweets($connection, $twitterHandle, $latestTweetId)
	{
		echo "Getting tweets for [$twitterHandle] since tweet id [$latestTweetId]\n";
		$tweets = array();
		$tweets = $connection->get("statuses/user_timeline", ["count" => 200, "tweet_mode" => "extended", "exclude_replies" => false, "screen_name" => $twitterHandle, "include_rts" => true, "since_id" => $latestTweetId]);
		echo "tweets : [" . print_r($tweets,true) . "]\n";
		return $tweets;
	}
	
	/*
	* Retweets to the worst fans account
	*/
	function createRetweetAndLike($connection, $tweetId)
	{
		echo "connection [" . print_r($connection, true) . "]\n";
        echo "Retweeting [$tweetId]\n";
		$result = $connection->post("statuses/retweet", ["id" => $tweetId]);
        echo "result [" . print_r($result, true) . "]\n";
		$connection->post("favorites/create", ["id" => $tweetId]);
	}
    
    /*
	* Builds an API connection
	*/
	function buildConnection($twitterConfig)
	{
        //connect
        #echo "buildConnection : [" . print_r($twitterConfig, true). "] key : [" . $twitterConfig['consumer_key'] . "]\n";
        $connection = new TwitterOAuth($twitterConfig['consumer_key'], $twitterConfig['consumer_secret'], $twitterConfig['access_token'], $twitterConfig['access_token_secret']);
        $content = $connection->get("account/verify_credentials");
		return $connection;
	}
	
	/*
	* Retrieves the full tweet text and formats it
	*/
	function getTweetText($tweet)
	{
		echo "getTweetText tweet : [" . print_r($tweet,true) . "]\n";
		echo "getTweetText got text? : [" . property_exists($tweet, 'text') . "]\n";
		if(property_exists($tweet, 'text'))
		{
			$tweetText = $tweet->text;
		}
		else if(property_exists($tweet, 'full_text'))
		{
			$tweetText = $tweet->full_text;
		}
		echo "getTweetText b4 tweetText : [" . $tweetText . "]\n";
		if($tweet->retweeted_status != '')
		{
			$tweetText = $tweet->retweeted_status->full_text;
		}
		$tweetText = preg_replace('/\b(https?|http):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', '', $tweetText);
		echo "getTweetText aftr tweetText : [" . $tweetText . "]\n";
		return $tweetText;
	}
    
    /*
	* Retweets to a worst fans channel if conditions are met
	*/
	function retweetToChannelIfApplicable($twitterChannelKeywordInfo, $twitterConfig, $newTweet)
	{
		$tweetText = getTweetText($newTweet);
        foreach ($twitterChannelKeywordInfo as $twitterChannel=>$twitterChannelKeywords)
        {
            echo "twitterChannel : [" . $twitterChannel . "]\n";
            foreach ($twitterChannelKeywords as $keyword)
            {
               echo "rt text [" . $tweetText . "] keyword : [" . $keyword . "] \n";
                if(stripos($tweetText, $keyword) !== false)
                {
                    echo "Word [" . $keyword . "] Found! RETWEETING!\n";
                    //retweet
                    createRetweetAndLike(buildConnection($twitterConfig[$twitterChannel]), $newTweet->id);
                    break;
                }
                else
                {
                    echo "Word [" . $keyword . "] Not Found!\n";
                }
            }
        }
	}
	#MAIN
	$theWorstFansTwitterHandles = array('JTKirkmanWF', 'WilsonWildingWF', 'WFSly');
	$tweetsToRetweet = array();
	//get the ids of the last tweets for our worst fans
	$lastTweetsMap = getLastTweetsMap();
    $twitterChannelKeywordInfo = getTwitterChannelKeywords();
    $twitterConfig = getTwitterConfig();
    $connection = buildConnection($twitterConfig['main']);
	//loop through each fan
	foreach($theWorstFansTwitterHandles as $twitterHandle)
	{
		$latestTweetId = $lastTweetsMap[$twitterHandle];
		//get their tweets since the last time we checked
		$newTweets = getTweets($connection, $twitterHandle, $latestTweetId);
        #echo "newTweets : [" . print_r($newTweets, true) . "]";
		//go through what we founds
		foreach ($newTweets as $newTweet)
		{
			#echo "newTweet : [" . print_r($newTweet, true) . "]\n";
			if($newTweet->in_reply_to_status_id)
			{
				//This is a reply, ignore
                echo "This is a reply, [" . print_r($newTweet, true) . "] ignoring\n";
			}
			else if(property_exists($newTweet, "retweeted_status"))
			{
				//This is a retweet, check to see if we should retweet it in any specific channels
                echo "RT newTweet : [" . print_r($newTweet, true) . "]\n";
                retweetToChannelIfApplicable($twitterChannelKeywordInfo, $twitterConfig, $newTweet);
			}
			else
			{
				//This is a tweet!
				#echo "last recorded tweet id [" . $lastTweetsMap[$twitterHandle]. "] current tweet id : [" . $newTweet->id . "]\n";
                echo "regular tweet newTweet : [" . print_r($newTweet, true) . "]\n";
				array_push($tweetsToRetweet, $newTweet);
				if($lastTweetsMap[$twitterHandle] < $newTweet->id)
				{
					$lastTweetsMap[$twitterHandle] = $newTweet->id;
				}
			}
		}
	}
	//sort the tweets so we retweet them in order
	sort($tweetsToRetweet);
	//retweet all the tweets we found
	foreach($tweetsToRetweet as $newTweet)
	{
		createRetweetAndLike($connection, $newTweet->id);
        //check to see if any channels should rt this
        retweetToChannelIfApplicable($twitterChannelKeywordInfo, $twitterConfig, $newTweet);
	}
	//store the database for next time
	setLastTweetsMap($lastTweetsMap);
	echo "Fin.";
?>