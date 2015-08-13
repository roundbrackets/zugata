<?
require_once dirname(__FILE__)."/inc.php";

$client = getClient();

$body = [
    'error' => "It's all your fault.",
];
$code = 400;

try {
    if (isset($_GET['code'])) {
        $code = $_GET['code'];
        $client->authenticate($code);
        $access_token = $client->getAccessToken();
        $token = json_decode($access_token, true);
        file_put_contents("./tokens/".$token["access_token"], $access_token);
        header('Location: ' . $_SERVER['SCRIPT_NAME']."?tkn={$token["access_token"]}" );
        xit;
    } else if (isset($_GET['tkn'])) {
        $body = [
            'token' => $_GET['tkn'],
        ];
        $code = 201;
    } else {
        $client->addScope(Google_Service_Gmail::GMAIL_READONLY);
        $client->setAccessType('offline');

        $auth_url = $client->createAuthUrl();
        header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
        exit;
    }
} catch (Exception $e) {
    $code = 500;
    $body = [
        'error' => $e->getMessage(),
    ];
}

$body = json_encode($body, true);

http_response_code($code);
header("Content-Type: application/json; charset=UTF-8");
header("Content-Length: ".strlen($body));
echo "$body";

/*
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
