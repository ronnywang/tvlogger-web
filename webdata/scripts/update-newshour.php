<?php

include(__DIR__ . '/../init.inc.php');
$channels = array('ctitv', 'set', 'formosa', 'tvbs', 'ebc');
foreach ($channels as $channel) {
    $cmd = "s3cmd ls s3://tw-tvnews/reports/{$channel}/";
    $ret = `$cmd`;
    preg_match_all("#/{$channel}/([0-9]*)#", $ret, $matches);

    foreach ($matches[1] as $time) {
        if (NewsHour::find(array($channel, $time))) {
            continue;
        }

        $obj = json_decode(file_get_contents("https://tw-tvnews.s3.amazonaws.com/reports/{$channel}/{$time}/data.json"));
        if (!$obj) {
            continue;
        }

        $data = new StdClass;
        $data->sections = $obj;
        $data->time = time();
        NewsHour::insert(array(
            'channel' => $channel,
            'time' => $time,
            'data' => json_encode($data)
        ));
    }
}
