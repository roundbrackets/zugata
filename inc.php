<?
define('PATH', '/opt/Projects/zugata');
define('CREDS', PATH.'/secret.json');
set_include_path(get_include_path() . PATH_SEPARATOR . PATH.'/google-api-php-client/src');
require 'Google/autoload.php';

function getClient() {
    $client = new Google_Client();
    $client->setAuthConfigFile(CREDS);
    return $client;
}

function getClientForToken ($access_token, $fake_it = false) {
    $client = getClient();
    $accessToken = false;

    // Since w're passed the access_token part only,
    // we will fake the token.
    // We don't know when it was created or when it expires,
    // so we fake it.
    $tmpl = '{
    "access_token":"%s",
        "token_type":"Bearer",
        "expires_in":"3600",
        "id_token":"",
        "created":%s}'; 

    $token_file = PATH.'/tokens/'.$access_token;
    if (!file_exists($token_file)) {
        if ($fake_it) {
            $accessToken = sprintf($tmpl, $access_token, time());
        }
    } else {
        $accessToken = file_get_contents(PATH.'/tokens/'.$access_token);
    }

    if (empty($accessToken)) {
        $client = null;
    } else {
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();
            if (!empty($refreshToken)) {
                $client->refreshToken($refreshToken);
                //We'd want to return this.
                $accessToken = $client->getAccessToken();
                file_put_contents(PATH.'/tokens/'.$access_token);
            }
        }
    }

    return $client;
}

function getToken ($postdata) {
    $data = json_decode($postdata, true);
    if (false === $data) {
        return false;
    }
    if (count($data) !== 1) {
        return false;
    }
    if (!isset($data["token"]) || empty($data["token"])) {
        return false;
    }
    return $data["token"];
}

function getRequestId (Google_Service_Gmail $gmail) {
    $profile = $gmail->users->getProfile("me");
    return $profile->getEmailAddress();
}

function doTheThing ($postdata) {
    $err        = 
    $requestId  = 
    $code       = false;

    try {
        $token = getToken($postdata);

        $client = getClientForToken($token);
        if (is_null($client)) {
            $code = 404;
            throw new Exception("Token not found.");
        }

        $gmailService = new Google_Service_Gmail($client);
        $resp = $gmailService->users_messages->listUsersMessages("me", array(
            "q" => "newer_than:1m -is:draft -in:sent -is:chat",
            "includeSpamTrash" => false, 'maxResults' => 1000 
        ));

        $requestId = getRequestId($gmailService);

        $arr = [];

        $q = "newer_than:2m -is:draft -in:sent -is:chat";

        /*
        // Get a list of messages that are not chat not in sent (so preferably 
        // not from ourself), and not draft. includeSpamTrash = false should 
        // eliminate deleted and spam messages.
        $resp = $gmailService->users_messages->listUsersMessages("me", array(
            "q" => $q,
            "includeSpamTrash" => false, 'maxResults' => 1000 
        ));

        // This will loop through every single message.
        while(!empty($resp->getNextPageToken())) {

            foreach ( $resp->getMessages() as $m ) {
                $x = $gmailService->users_messages->get("me", $m->getId(), array( "format" => "metadata", "metadataHeaders" => "from" ));
                $h = $x->getPayload()->getHeaders();
                $email = $h[0]->getValue();
                $email = strtolower(trim(preg_replace("/^.*</", "", $email), '>'));
                if (!isset($arr[$email])) {
                    $arr[$email] = 0;
                }
                // This is how you find out whom to filter to /dev/null
                $arr[$email]++;
                error_log($email.' '.$arr[$email]);
            }

            $obj = [
                "total" => count($arr),
                "values" => array_keys($arr)
            ];

            file_put_contents(PATH.'/requests/'.$requestId, json_encode($obj, true));
        }
         */

        // For every time through, we send a new query to gmail which excludes 
        // the emails we have already found. When there is no PageToken, we're 
        // done. It doesn't seem like there are a lot of different senderd
        // so I am setting the max reults lower.

        $emails = '';

        while (true) {
            $resp = $gmailService->users_messages->listUsersMessages("me", array(
                "q" => $q." ".$emails,
                "includeSpamTrash" => false, 'maxResults' => 100
            ));

            foreach ( $resp->getMessages() as $m ) {
                $x = $gmailService->users_messages->get("me", $m->getId(), array( "format" => "metadata", "metadataHeaders" => "from" ));
                $h = $x->getPayload()->getHeaders();
                $email = $h[0]->getValue();
                $email = strtolower(trim(preg_replace("/^.*</", "", $email), '>'));
                $arr[$email] = true;

                /*
                if (!isset($arr[$email])) {
                    $arr[$email] = 0;
                }
                $arr[$email]++;
                rror_log($email.' '.$arr[$email]);
                 */
            }

            $emails = " -from:".implode(" -from:", array_keys($arr));

            if(empty($resp->getNextPageToken())) {
                break;
            }
        }


        $obj = [
            "total" => count($arr),
            "values" => array_keys($arr)
        ];

        file_put_contents(PATH.'/requests/'.$requestId, json_encode($obj, true));
        $code = 201;
    } catch (Google_Auth_Exception $e) {
        $err = $e->getMessage();
        $code = 401;
    } catch (Exception $e) {
        $err = $e->getMessage();
        if (empty($code)) {
            $code = 418;
        }
    }
    return [ $requestId, $err, $code ];
}

function extractRequestId ($uri) {
    $parts = explode("/", $uri);
    $requestId = urldecode(array_pop($parts));
    return trim($requestId, "{}");
}

function doTheOtherThing ($requestId) {
    $data = PATH.'/requests/'.$requestId;
    if (file_exists($data)) {
        return file_get_contents($data);
    }
    return false;
}

/*
 * From stackoverflow, in case one wanted to run the message munshing and not 
 * leave the requestor hanging (hilarious).
 *
exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));

function isRunning($pid){
    try{
        $result = shell_exec(sprintf("ps %d", $pid));
        if( count(preg_split("/\n/", $result)) > 2){
            return true;
        }
    }catch(Exception $e){}

        return false;
}
 */
