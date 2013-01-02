<?php
    // Begin - Configuration
    // Needs TwitterOAuth library: https://github.com/abraham/twitteroauth
    define('TWITTEROAUTH_PATH', '/lib/twitteroauth.php');

    // Logging settings
    define('NEWLINE', "\n");
    define('LAST_LOG', "last_github_hook.log");
    define('DEBUG', false);

    // Twitter settings
    define('CONSUMER_KEY', '');
    define('CONSUMER_SECRET', '');
    define('ACCESS_TOKEN', '');
    define('ACCESS_TOKEN_SECRET', '');

    define('TW_API_UPDATE', 'https://api.twitter.com/1.1/statuses/update.json');

    // github IPs
    $GITHUB_IPS = array('207.97.227.253', '50.57.128.197', '108.171.174.178');
    // End - Configuration


    // turn off error reporting
    error_reporting(0);
    require_once TWITTEROAUTH_PATH;

    // check from github IP
    if (!in_array($_SERVER['REMOTE_ADDR'], $GITHUB_IPS)){
        header("HTTP/1.1 404 Not Found");
        exit;
    }

    // begen new logfile
    addLogLine("==BEGEN REQUEST==", true);

    if (!isset($_POST['payload'])){
        addLogLine("Error: Request error (POST['payload'])");
        exit;
    }

    $payload_raw = $_POST['payload'];
    try{
        $payload = json_decode($payload_raw); // json_decode(stripslashes($payload_raw));
    }catch(Exception $ex){
        addLogLine("Error: Occred exception while json_decode!");
        addLogLine(print_r($ex, TRUE));
        exit;
    }

    if (!is_object($payload) || !is_array($payload->commits)){
        addLogLine("Error: Empty or no commits payload!");
        exit;
    }

    addLogLine("Payload Contents: ");
    addLogLine(print_r($payload, TRUE));

    // First, get OAuth instance
    $oa = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

    // get detail
    /* Pusher */
    $pusher_name = $payload->pusher->name;
    $pusher_email = $payload->pusher->email;

    /* Repository */
    $repo_name = $payload->repository->name;
    $repo_url = $payload->repository->url;

    /* Etc */
    $ref = $payload->ref; // check pushed to master? if(=== 'refs/heads/master'){}
    $compare_url = $payload->compare;

    debug("Starting foreach loop");
    foreach($payload->commits as $commit) {
        /* Commits */
        $commit_url = $commit->url;
        $commit_msg = $commit->message;
        $committer_name = $commit->author->name;
        $committer_username = $commit->author->username;
        $committer_email = $commit->author->email;

        debug("Building update message");

        // Tweet
        $tweet = "[".$repo_name."] ".$commit_url." ".$committer_username." - ".trim($commit_msg);
        if (mb_strlen($tweet) > 140){
            $tweet = mb_substr($tweet, 0, 138)."..";
        }

        debug("Tweeting..: ".$tweet);
        
        $req = $oa->OAuthRequest(TW_API_UPDATE, "POST", array("status"=>$tweet));
        $result = json_decode($req);
        debug("Tweet Result: ".print_r($result, TRUE));
    }

    // end of logfile
    addLogLine("==END==");

    /****/
    function addLogLine($line, $newFile = false){
        if ($newFile){
            file_put_contents(LAST_LOG, $line.NEWLINE);
        }else{
            file_put_contents(LAST_LOG, $line.NEWLINE, FILE_APPEND);
        }
    }
    function debug($line){
        if (DEBUG){
            file_put_contents(LAST_LOG, "[DEBUG] ".$line.NEWLINE, FILE_APPEND);
        }
    }
?>
