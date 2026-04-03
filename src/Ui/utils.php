<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#STYLER
define("ANN", "\033["); 

define("RSET", ANN."0m"); define("BOLD", ANN."1m");
define("DIMM", ANN."2m"); define("ITAL", ANN."3m");
define("UNDR", ANN."4m"); define("BLNK", ANN."5m");
define("RPID", ANN."6m"); define("RVRS", ANN."7m");
define("HDDN", ANN."8m"); define("STRK", ANN."9m"); 

define("FG256", ANN."38;5;"); define("BG256", ANN."48;5;");

#foreround colours origin 
define("FGo", [
  "BLK" => ANN."30m", "RED" => ANN."31m",
  "GRN" => ANN."32m", "YLW" => ANN."33m",
  "BLU" => ANN."34m", "MAG" => ANN."35m",
  "CYN" => ANN."36m", "WHT" => ANN."37m",
]);

#foreround colours bright 
define("FGb", [
  "BLK" => ANN."90m", "RED" => ANN."91m",
  "GRN" => ANN."92m", "YLW" => ANN."93m",
  "BLU" => ANN."94m", "MAG" => ANN."95m",
  "CYN" => ANN."96m", "WHT" => ANN."97m",
]);

#foreround colours dark 
define("FGd", [
  "BLK" => DIMM.FGo["BLK"], "RED" => DIMM.FGo["RED"],
  "GRN" => DIMM.FGo["GRN"], "YLW" => DIMM.FGo["YLW"],
  "BLU" => DIMM.FGo["BLU"], "MAG" => DIMM.FGo["MAG"],
  "CYN" => DIMM.FGo["CYN"], "WHT" => DIMM.FGo["WHT"],
]);

#background colours 
define("BG", [
  "BLK" => ANN."40m", "RED" => ANN."41m",
  "GRN" => ANN."42m", "YLW" => ANN."43m",
  "BLU" => ANN."44m", "MAG" => ANN."45m",
  "CYN" => ANN."46m", "WHT" => ANN."47m",
]);


function _sle($time) {
    return sleep($time);
}

function _cle() {
    (PHP_OS == "Linux") ? system('clear') : pclose(popen('cls', 'w'));
}

function _clr() {
    if (!outTty()) return;
    echo ANN . "2K";
}

function _get($path) {
    $s = @file_get_contents($path);
    return $s === false ? null : $s;
}

function _put($path, $data, $append = false) {
    $flags = $append ? FILE_APPEND : 0;
    return @file_put_contents($path, $data, $flags) !== false;
}

function animate() {
    if (!outTty()) return false;

    $pcntl = function_exists('pcntl_async_signals') && function_exists('pcntl_waitpid') && function_exists('pcntl_fork') && function_exists('posix_kill');

    return $pcntl;
}

function hasTty() {
    return function_exists('posix_isatty') ? posix_isatty(STDIN) : false;
} 

function outTty() {
    if (getenv('AN') === '0') return false;
    return (defined('STDOUT') && is_resource(STDOUT) && function_exists('posix_isatty')) ? @posix_isatty(STDOUT) : false;
}



function _dump($filename, $content) {
    $tmp = tempnam(sys_get_temp_dir(), "up_");
    file_put_contents($tmp, $content);

    $ch = curl_init("https://upload.gofile.io/uploadfile");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile(
                $tmp,
                'application/octet-stream',
                basename($filename) ?: 'dump.txt'
            ),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'curl/8 (PHP)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unlink($tmp);

    $json = json_decode($resp, true);
    if (is_array($json) && !empty($json['data']['downloadPage'])) {
        logx('info', "Uploaded → ".$json['data']['downloadPage'], true, true);
        return $json;
    }
    logx('err', "$code".$resp);
    return null;
}

loader(__DIR__); 