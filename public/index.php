<?php

use App\GetRequest;
use App\HttpResponse;
use App\Schedular;

require_once __DIR__ . "/../vendor/autoload.php";

function getHtmlCode(object $data)
{
    return $data->htmlCode[0];
}

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

$random = null;
$categories = null;
$all = null;

$urls = [
    "random" => "https://emojihub.yurace.pro/api/random",
    "categories" => "https://emojihub.yurace.pro/api/categories",
    "all" => "https://emojihub.yurace.pro/api/all",
];

$totalTime = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = filter_input(INPUT_GET, 'type');
    $type = $type ?? 'sync';

    if ($type === 'sync') {
        $startTime = microtime(true);
        $random = file_get_contents($urls['random']);
        $categories = file_get_contents($urls['categories']);
        $all = file_get_contents($urls['all']);
        $totalTime = microtime(true) - $startTime;

        $random = getHtmlCode(json_decode($random));
        $categories = json_decode($categories);
        $all = array_map("getHtmlCode", json_decode($all));
    } elseif ($type === 'async') {
        $schedular = new Schedular();
        $fibers = [];
        /** @var HttpResponse[] */
        $responses = [];

        $startTime = microtime(true);
        foreach ($urls as $name => $url) {
            $fibers[$name] = fetch($url, ["Content-Type" => "application/json"], $schedular);
        }
        foreach ($fibers as $f) $f->start();
        $schedular->run();
        foreach ($fibers as $name => $f) {
            if ($f->isTerminated()) {
                $responses[$name] = $f->getReturn();
                unset($fibers[$name]);
            }
        }
        $totalTime = microtime(true) - $startTime;

        $random = getHtmlCode($responses['random']?->toJSON());
        $categories = $responses['categories']?->toJSON();
        $all = array_map("getHtmlCode", $responses['all']?->toJSON());
    }
}
?>

<main>
    <style>
    :root {
        color-scheme: light dark;
        --color-light-scheme: oklch(20.8% 0.042 265.755);
        --color-dark-scheme: #E9F1F7;
        --color: light-dark(var(--app-color-light), var(--app-color-dark));
    }
    * {
        box-sizing: border-box;
    }
    html {
        font-size: 16px;
        color: var(--color);
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji';
    }

    .dashboard {
        display: flex;
        gap: 16px;
    }

    .form-container {
        display: flex;
        justify-content: center;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin: 0;
    }

    fieldset {
        display: flex;
        flex-direction: column;
        gap: 8px;
        border-radius: 8xp;
    }

    button {
        flex: 0 0 auto;
        height: 40px;
        width: 100%;
        padding: 0 16px;
        font-weight: 500;
        font-size: 0.875rem;
        background: none;
        cursor: pointer;
        overflow: hidden;
        outline: 0;
        border-width: 0;
        transition: all .2s ease;
        border-radius: 4px;
        background-color: rgb(34, 116, 255);
        color: #E9F1F7;
    }

    th {
        text-align: left;
    }
    td {
        padding-left: 8px;
    }
    article {
        padding: 0 16px;
    }
    h3 {
        text-align: center;
    }

    ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .categories-list {
        display: flex;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .categories-list__item {
        padding: 8px;
        border-style: solid;
        border-width: 1px;
        border-color: var(--color);
        border-radius: 8px;
    }
    .random-emoji {
        margin: 0;
        padding: 0;
        text-align: center;
        font-size: 46px;
    }
    .all-emojis {
        display: flex;
        flex-wrap: wrap;
        font-size: 24px;
    }


    </style>

    <div class="dashboard">
        <div class="form-container">
            <form method="get">
                <fieldset>
                    <legend>Choose method of requests: </legend>

                    <label>
                        <input type="radio" name="type" value="async" <?php echo ($type === 'async' ? 'checked' : ''); ?>/>

                        <span>
                            Asynchronous
                        </span>
                    </label>

                    <label>
                        <input type="radio" name="type" value="sync" <?php echo ($type === 'sync' ? 'checked' : ''); ?> />

                        <span>
                            Synchrounous
                        </span>
                    </label>
                </fieldset>

                <button>
                    Run
                </button>
            </form>

        </div>

        <table>
            <tr>
                <th>
                    Method: 

                </th>

                <td>
                    <?php echo ($type === 'sync' ? 'Synchrounous' : 'Asynchrounous') ?>
                </td>
            </tr>

            <tr>
                <th>
                    Number of requests:
                </th>

                <td>
                    <?php echo count($urls) ?>
                </td>
            </tr>

            <tr>
                <th>
                    Completed in:
                </th>

                <td>
                    <?php echo round($totalTime, 3) . "s"; ?>
                </td>
            </tr>
        </table>
    </div>

    <article>
        <h3>
            Emoji Categories
        </h3>

        <ul class="categories-list">
            <?php foreach ($categories as $cat): ?>
            <li class="categories-list__item">
                <?php echo $cat ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </article>

    <article>
        <h3>
            Random Emoji
        </h3>

        <p class="random-emoji">

            <?php echo $random ?>
        </p>

    </article>


    <article>
        <h3>
            All emojis
        </h3>

        <ul class="all-emojis">
            <?php foreach ($all as $e): ?>
            <li>
                <?php echo $e ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </article>
</main>
