<?
set_time_limit (0);

require_once dirname(dirname(dirname(__FILE__)))."/inc.php";

$code =
$body = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $postdata = file_get_contents("php://input"); 
    $token = getToken ($postdata);
    list($requestId, $err, $code) = getUniqueSender($postdata, true);

    if (!empty($err)) {
        $body = json_encode([
            "token" => $token,
            "error" => $err,
        ], true);

    } else {
        $body = json_encode([
            "requestId" => $requestId
        ], true);
    }

} else {
    $requestId = extractRequestId ($_SERVER["REQUEST_URI"]);
    $body = ShowAllTheEmailsWeFound ($requestId);
    $code = 200;

    if (empty($body)) {
        $code = 404;
        $body = json_encode([
            "requestId" => $requestId,
            "error" => "No such request id."
        ], true);
    }
}

http_response_code($code);
header("Content-Type: application/json; charset=UTF-8");
header("Content-Length: ".strlen($body));
echo "$body";
