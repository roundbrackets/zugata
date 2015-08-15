<?
define('PATH', dirname(__FILE__));
define('CREDS', PATH.'/secret.json');
set_include_path(get_include_path() . PATH_SEPARATOR . PATH.'/google-api-php-client/src');
require 'Google/autoload.php';
define("TOKENS", '/var/tmp/TOKENS');
define("REQUESTS", '/var/tmp/REQUESTS');

if (!file_exists(TOKENS)) {
    mkdir(TOKENS); 
    mkdir(REQUESTS); 
}

function tokenFile($tkn) {
    return sprintf('%s/%s', TOKENS, $tkn);
}

function requestFile($req) {
    return sprintf('%s/%s', REQUESTS, $req);
}

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

    $token_file = tokenFile($access_token);
    if (!file_exists($token_file)) {
        if ($fake_it) {
            $accessToken = sprintf($tmpl, $access_token, time());
        }
    } else {
        $accessToken = file_get_contents($token_file);
    }

    var_dump($accessToken);
    exit;

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
                file_put_contents(tokenFile($access_token));
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

function getUniqueSenders($postdata) {
    $err        = 
    $requestId  = 
    $code       = false;

    try {
        $token = getToken($postdata);

        $client = getClientForToken($token, true);
        if (is_null($client)) {
            $code = 404;
            throw new Exception("Token not found.");
        }


        // For every time through, we send a new query to gmail which excludes 
        // the emails we have already found. When there is no PageToken, we're 
        // done. It doesn't seem like there are a lot of different senderd
        // so I am setting the max reults lower.

        $emails = '';
        $requestId = getRequestId($gmailService);
        $arr = [];
        $q = "newer_than:2m -is:draft -in:sent -is:chat";

        while (true) {
            $resp = $gmailService->users_messages->listUsersMessages("me", array(
                "q" => $q." ".$emails,
                "includeSpamTrash" => false, 'maxResults' => 100
            ));

            foreach ( $resp->getMessages() as $m ) {
                $x = $gmailService->users_messages->get("me", $m->getId(), 
                    array( "format" => "metadata", "metadataHeaders" => "from" ));
                $h = $x->getPayload()->getHeaders();
                $email = $h[0]->getValue();
                $email = strtolower(trim(preg_replace("/^.*</", "", $email), '>'));
                $arr[$email] = true;
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

        // We got something and we saved it.
        file_put_contents(requestFile($requestId), json_encode($obj, true));
        $code = 201;
    } catch (Google_Auth_Exception $e) {
        // Google doesn't like the token.
        $err = $e->getMessage();
        $code = 401;
    } catch (Exception $e) {
        // Something else happened.
        $err = $e->getMessage();
        if (empty($code)) {
            $code = 418; // Apache doesn't like teapots.
        }
    }
    return [ $requestId, $err, $code ];
}

// Extract the bit of the url after the /
function extractRequestId ($uri) {
    $parts = explode("/", $uri);
    $requestId = urldecode(array_pop($parts));
    return trim($requestId, "{}");
}


function ShowAllTheEmailsWeFound ($requestId) {
    $data = requestFile($requestId);
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
