<!doctype html>
<html lang=en>
<head>
<meta charset=utf-8>
<title>"Nightly" Scribus Appimage</title>
</head>
<body>
<h1>"Nightly" Scribus Appimage</h1>

<?php

include_once( __DIR__ .'/../vendor/autoload.php');

ini_set('display_startup_errors',1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

use \Curl\Curl;

function debug($label, $value)
{
    echo('<pre>'.$label.":\n".htmlspecialchars(print_r($value, 1))."</pre>\n");
}

function getCurl($url, $type = 'json')
{
    $curl = new Curl();
    $curl->setUserAgent('impagina-get-latest-scribus/0.0.1 (+http://impagina.org/travis)');
    $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
    if ($type === 'json') {
        $curl->setHeader('Accept:', 'application/vnd.travis-ci.2+json');
    } elseif ($type === 'txt') {
        $curl->setHeader('Accept:', 'text/plain');
    }
    $curl->get($url);

    if ($curl->error) {
        echo '<p>Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "</p>\n";
    } else {
        return $curl->response;
    }
return null;

}

function getCache()
{
    $result = [
        'build-id' => 0,
        'date' => '',
        'link' => [
        ]
    ];
        
    if (!file_exists('cache')) {
        mkdir('cache', 0777, true);
    }
    if (file_exists('cache/latest.json')) {
        $result = file_get_contents('cache/latest.json');
        $result = json_decode($result, true);
    }
    return $result;
}

function setCache($cache)
{
    file_put_contents('cache/latest.json', json_encode($cache));
}

$cache = getCache();
// debug('cache', $cache);

$scribusProject = getCurl('https://api.travis-ci.org/repos/scribusproject/scribus', '');
// debug('scribusProject', $scribusProject);
if ($scribusProject->last_build_id !== $cache['build-id'] && $scribusProject->last_build_finished_at) {
    // debug('lastBuild', $scribusProject->last_build_id);
    $cache['build-id'] = $scribusProject->last_build_id;
    // debug('finished', $scribusProject->last_build_finished_at);
    $cache['date'] = $scribusProject->last_build_finished_at;
    $cache['link'] = [];
    $scribusBuild = getCurl('https://api.travis-ci.org/builds/'.$scribusProject->last_build_id);
    // debug('scribusBuild', $scribusBuild);
    foreach ($scribusBuild->jobs as $item) {
        // debug('item', $item);
        $jobOs = $item->config->os;
        // debug('job os', $jobOs);
        // debug('job config', $item->config);
        $jobCompiler = is_array($item->config->compiler) ? implode(', ', $item->config->compiler) : $item->config->compiler;
        // debug('job compiler', $jobCompiler);
        if ($item->state === 'passed') {
            $scribusJob = getCurl('https://api.travis-ci.org/jobs/'.$item->id.'/log', 'txt');
            // debug('scribusJob', $scribusJob);
            $matches = [];
            if (preg_match('#(https://transfer.sh/.*?)\+rm#', $scribusJob, $matches)) {
                // debug('matches', $matches);
                $cache['link'][] = [
                    'os' => $jobOs,
                    'compiler' => $jobCompiler,
                    'url' => $matches[1],
                ];
            }
        }
    }
}

$date = new DateTime($cache['date']);
$now = new DateTime();
// TODO: implement a diff in hours or days depending on the length
// debug('date', $date);
// debug('now', $now);
// debug('diff', $date->diff($now)->format('%h'));

if (!empty($cache['link'])) {
?>
<p>Appimages created on the <?= $date->format('jS F Y') ?> at <?= $date->format('H:i') ?>.</p>
<ul>
<?php foreach($cache['link'] as $item) : ?>
    <li><a href="<?= $item['url'] ?>"><?= $item['os'] ?></a> (<?= $item['compiler'] ?>)</li>
<?php endforeach; ?>
</ul>
<?php
}

setCache($cache);
?>
</body>
</html>
