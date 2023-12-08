<?php

// cd ~/Dropbox/Scripts/Nightscout && docker run --rm -u "$(id -u):$(id -g)" -v $(pwd):/var/www/html -w /var/www/html laravelsail/php81-composer:latest php monitor-aimi.php --ignore-platform-reqs

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use ArrayHelpers\Arr;

$cacheFile = 'cache/' . md5(__FILE__) . ".txt";

function getTimeAgo($ptime)
{
    $estimate_time = time() - ($ptime / 1000);

    if ($estimate_time < 1) {
        return '--';
        //less 1 minute
    }

    $condition = array(
        12 * 30 * 24 * 60 * 60  =>  'y',
        30 * 24 * 60 * 60       =>  'mo',
        24 * 60 * 60            =>  'd',
        60 * 60                 =>  'h',
        60                      =>  'm',
        1                       =>  's'
    );

    foreach ($condition as $secs => $str) {
        $d = $estimate_time / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . $str;
        }
    }
}

function getPercent($value, $total)
{
    if (!$value) {
        return 0;
    }

    $percent = (100 * (int)$value) / (int)$total;
    return ceil($percent);
}

function getOutputLine($responses)
{
    //var_dump($responses['status']->getBody());

    //
    $statusData = json_decode($responses['status']->getBody(), true);
    $propertiesData = json_decode($responses['properties']->getBody(), true);
    $currentData = json_decode($responses['current']->getBody(), true);
    $svgData = json_decode($responses['svg']->getBody(), true);

    $bgHigh = Arr::get($statusData, 'settings.thresholds.bgHigh', null);
    $bgTargetTop = Arr::get($statusData, 'settings.thresholds.bgTargetTop', null);
    $bgTargetBottom = Arr::get($statusData, 'settings.thresholds.bgTargetBottom', null);
    $bgLow = Arr::get($statusData, 'settings.thresholds.bgLow', null);
    $units = Arr::get($statusData, 'settings.units', null);

    $iob = Arr::get($propertiesData, 'iob.display', null);
    $cob = Arr::get($propertiesData, 'cob.display', null);
    $arrow = Arr::get($propertiesData, 'direction.label', null);
    $pumpReservoir = Arr::get($propertiesData, 'pump.pump.reservoir', null);

    $pumpStatus = Arr::get($propertiesData, 'pump.pump.status.status', null);
    $pumpBatteryPercent = Arr::get($propertiesData, 'pump.pump.battery.percent', null);
    $cage = Arr::get($propertiesData, 'cage.display', null);
    $sage = Arr::get($propertiesData, 'sage.Sensor Change.display', null);
    $iage = Arr::get($propertiesData, 'iage.display', null);
    $tomatoBattery = Arr::get($propertiesData, 'upbat.devices.Tomato.statuses.0.uploader.display', null);
    $phoneBattery = Arr::get($propertiesData, 'upbat.devices.samsung SM-J701MT.statuses.0.uploader.display', null);

    $currentSgv = Arr::get($currentData, '0.sgv', null);
    $currentDelta = Arr::get($currentData, '0.delta', null);
    $currentTimeAgo = getTimeAgo(Arr::get($currentData, '0.date', null));


    //svgs
    $validSgvs = array_filter($svgData, function ($e) {
        return isset($e['sgv']) && $e['sgv'];
    });

    $listOnlySvg = array_map(function ($e) {
        return $e['sgv'];
    }, $validSgvs);

    $hipoSgvs = array_filter($listOnlySvg, function ($e) use ($bgTargetBottom) {
        return ((int)$e < (int)$bgTargetBottom);
    });

    $hiperSgvs = array_filter($listOnlySvg, function ($e) use ($bgTargetTop) {
        return ((int)$e > (int)$bgTargetTop);
    });


    $hipoSgvsCount = sizeof($hipoSgvs);
    $hiperSgvsCount = sizeof($hiperSgvs);
    $targetSvgsCount = sizeof($listOnlySvg) - ($hipoSgvsCount + $hiperSgvsCount);

    $hipoSgvsPercent = getPercent($hipoSgvsCount, sizeof($listOnlySvg));
    $hiperSgvsPercent = getPercent($hiperSgvsCount, sizeof($listOnlySvg));
    $targetSvgsPercent = 100 - ($hipoSgvsPercent + $hiperSgvsPercent);

    $currentDelta = is_null($currentDelta) ? 0 : $currentDelta;
    
    $currentDelta = number_format($currentDelta, 2);


    // 📟 📟 🔋 🔋 🔋  🧪 🔘 🔳 🩹
    // https://unicode.org/emoji/charts/full-emoji-list.html
    //$chart = \Sparkline\Spark::getString($barValues);
    //"▁▃▄▇▄▃▄█  "
    //$chart = "";

    // $outputLine = " 🩸$currentSgv $arrow ($currentDelta) $units "
    //     . "- 💉 $iob "
    //     . "- 🏁 " . $targetSvgsPercent . "% / 24h "
    //     . "- 📟 " . $pumpBatteryPercent . "% " . $pumpReservoir . "U " . $iage . " "
    //     . "- 💿 " . $sage . " "
    //     . "- 🩹 " . $cage . " "
    //     . "- 🕑 $currentTimeAgo ";

    
    // $outputLine = " 🩸 ".$currentSgv.$arrow."(".$currentDelta.")".$units." "
    //     . "- 💉 ".$iob." " 
    //     . "- 🏁 ".$targetSvgsPercent. "%/24h "
    //     . "- 📟 " . $pumpBatteryPercent . "% " . $pumpReservoir . "U "
    //     . "- 💿 " . $sage . " "
    //     . "- 🩹 " . $cage . " "
    //     . "- 🕑 $currentTimeAgo ";

    $outputLine = " 🩸 ".$currentSgv.$arrow."(".$currentDelta.")".$units." "
        . "- 🏁 ".$targetSvgsPercent. "%/24h "
        . "- 🕑 $currentTimeAgo ";


    return $outputLine;
}

$client = new Client([
    //'base_uri' => 'https://YOUNIGHTSCOUT.herokuapp.com',
    'base_uri' => 'https://YOUNIGHTSCOUT.fly.dev',
    'timeout'  => 2.0,
]);

try {

    $promises = [
        'status' => $client->getAsync('/api/v1/status.json'),
        'current'   => $client->getAsync('/api/v1/entries/current.json'),
        'svg'  => $client->getAsync('/api/v1/entries/sgv.json?count=300'),
        'properties'  => $client->getAsync('/api/v2/properties/')
    ];

    $responses = Promise\Utils::unwrap($promises);
    $outputLine = getOutputLine($responses);
    echo $outputLine;
    file_put_contents($cacheFile, $outputLine." *");
} catch (Exception $e) {

    if (file_exists($cacheFile)) {
        $outputLine = file_get_contents($cacheFile);
        $p = explode("🕑", $outputLine);

        $cacheAge = filemtime($cacheFile);
        $cacheAge = getTimeAgo($cacheAge * 1000);
        $outputLine = $p[0]." 🕑 ".$cacheAge." *";       
        
        echo $outputLine;
    }
}
