<?php

/*

WFSly,1198309936872263683
JTKirkmanWF,1198263353715691522
WilsonWildingWF,1197626664110432259

*/

	echo "Checking for new tweets from The Worst Fans...\n";

	require "vendor/autoload.php";

	use Abraham\TwitterOAuth\TwitterOAuth;

	$CONSUMER_KEY = "";
	$CONSUMER_SECRET = "";
	$access_token = "";
	$access_token_secret = "";
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
			echo "name : [$latestTweet[0]] \n";
			echo "id : [$latestTweet[1]] \n";
			$lastTweetsMap[$latestTweet[0]] = $latestTweet[1];
		}
		echo "getLastTweetsMap lastTweetsMap : [" . print_r($lastTweetsMap, true) . "]\n";
		return $lastTweetsMap;
	}
	
	/*
	* Stored last tweet db info
	*/
	function setLastTweetsMap($lastTweetsMap)
	{
		global $DATABASE_FILE;
		echo "setLastTweetsMap lastTweetsMap : [" . print_r($lastTweetsMap, true) . "]\n";
		$databaseData = '';
		foreach($lastTweetsMap as $twitterHandle => $tweetId)
		{
			echo "setLastTweetsMap twitterHandle [$twitterHandle] tweetId [$tweetId]\n";
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
		$tweeets = $connection->get("statuses/user_timeline", ["count" => 200, "exclude_replies" => false, "screen_name" => $twitterHandle, "include_rts" => true, "since_id" => $latestTweetId]);
		return $tweeets;
	}
	
	/*
	* Retweets to the worst fans account
	*/
	function createRetweetAndLike($connection, $tweetId)
	{
		echo "Retweeting [$tweetId]\n";
		$connection->post("statuses/retweet", ["id" => $tweetId]);
		$connection->post("favorites/create", ["id" => $tweetId]);
	}
	
	#MAIN
	//connect
	$connection = new TwitterOAuth($CONSUMER_KEY, $CONSUMER_SECRET, $access_token, $access_token_secret);
	$content = $connection->get("account/verify_credentials");
	$theWorstFansTwitterHandles = array('JTKirkmanWF', 'WilsonWildingWF', 'WFSly');
	$tweetsToRetweet = array();
	//get the ids of the last tweets for our worst fans
	$lastTweetsMap = getLastTweetsMap();
	//loop through each fan
	foreach($theWorstFansTwitterHandles as $twitterHandle)
	{
		$latestTweetId = $lastTweetsMap[$twitterHandle];
		//get their tweets since the last time we checked
		$newTweets = getTweets($connection, $twitterHandle, $latestTweetId);
		//go through what we founds
		foreach ($newTweets as $newTweet)
		{
			if($newTweet->in_reply_to_status_id)
			{
				//This is a reply, ignore
			}
			else if(property_exists($newTweet, "retweeted_status"))
			{
				//This is a retweet, ignore
			}
			else
			{
				//This is a tweet!
				echo "last recorded tweet id [" . $lastTweetsMap[$twitterHandle]. "] current tweet id : [" . $newTweet->id . "]\n";
				array_push($tweetsToRetweet, $newTweet->id);
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
	foreach($tweetsToRetweet as $tweetId)
	{
		createRetweetAndLike($connection, $tweetId);
	}
	//store the database for next time
	setLastTweetsMap($lastTweetsMap);
	echo "Fin.";
?>  