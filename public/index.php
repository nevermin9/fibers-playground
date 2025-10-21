<?php

use App\GetRequest;
use App\HttpResponse;
use App\Schedular;

require_once __DIR__ . "/../vendor/autoload.php";

$data = file_get_contents("https://emojihub.yurace.pro/api/random");
$data = json_decode($data);

// $fp = stream_socket_client("tls://emojihub.yurace.pro:443", $errno, $errmsg);
//
// $new_data = "";
// $headers = [
//     "Host" => "emojihub.yurace.pro",
//     "Accept" => "application/json",
//     "Connection" => "close",
// ];
// $headers = array_map(
//     fn(string $k, string $v) => "{$k}: {$v}",
//     array_keys($headers),
//     array_values($headers),
// );
// $headers = implode("\r\n", $headers);
// $headers .= "\r\n\r\n";
// if (!$fp) {
//     echo "$errno : $errmsg";
// } else {
//     echo "Socket name (remote): " . stream_socket_get_name($fp, true) . PHP_EOL;
//     echo "Socket name (local): " . stream_socket_get_name($fp, false) . PHP_EOL;
//     fwrite($fp, "GET /api/random HTTP/1.1\r\n{$headers}");
//     while (!feof($fp)) {
//         $new_data .= fgets($fp, 1024);
//     }
//     [$respHeaders, $body] = explode("\r\n\r\n", $new_data);
//     fclose($fp);
// }
// $new_data = json_decode();

// ===========

function fetch(string $url, array $headers, Schedular $schedular)
{
    $cb = function () use ($url, $headers, $schedular) {
        $req = new GetRequest($url, $headers);
        if (!$req->connect()) return $req->result;
        $req->fiber = Fiber::getCurrent();
        $schedular->add($req);
        /** @var GetRequest */
        $req = $req->fiber->suspend();
        return new HttpResponse($req->responseAsString);
    };

    return new Fiber($cb);
}

$schedular = new Schedular();

$fiber = fetch("https://emojihub.yurace.pro/api/random", ["Content-Type" => "application/json"], $schedular);
$fiber->start();
$schedular->run();
/** @var HttpResponse */
$response = $fiber->getReturn();
var_dump($response);
?>

<div>
    <span style="font-size: 46px">
        <?php echo $data->htmlCode[0]; ?>
    </span>
</div>
