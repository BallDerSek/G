<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#Direct Locally solver
 
function solveECAPTCHA($host, $domain = '') {
return styler("SOLVING ecaptcha", function() use ($host, $domain) {
    global $userAgent, $cookieFile;
    $h = headers($host, '', $domain?: '');
    
    $json = json_decode(
        Net::X($host.'ecaptcha/get_token', 'GET', null, $cookieFile, $h, $host, $userAgent), true);

    $token = $json['token'];
    $task = json_decode(
        Net::X($host.'ecaptcha/get_captcha', 'GET', null, $cookieFile, $h, $host, $userAgent), true);
    if (empty($task['captcha_key']) || empty($task['question'])) return false;

    $sel  = explode(':', $task['question']);
    $answer = strtolower(trim(end($sel))) . '.gif';

    $payload = [
        'selected' => $answer,
        'key'      => $task['captcha_key'],
        'token'    => $token
    ];

    $v = json_decode(
        Net::X($host.'ecaptcha/validate_icon', 'POST', $payload, $cookieFile, $h, $host, $userAgent), true);

    if (($v['status'] ?? '') === 'valid'){
        $final = [
            'captcha'       => 'emoji_captcha',
            'captcha_token' => $token,
            'captcha_key'   => $task['captcha_key'],
            'selected_icon' => $answer
        ];
        return $final;
        //exit;
    } return false;
});
}

function widgetId() {
    $uuid = '';
    for ($n = 0; $n < 32; $n++) {
        if ($n == 8 || $n == 12 || $n == 16 || $n == 20) $uuid .= '-';
        $e = mt_rand(0, 15);
        if ($n == 12) $e = 4;
        elseif ($n == 16) $e = ($e & 0x3) | 0x8;
        $uuid .= dechex($e);
    }
    return $uuid;
}

function webkitId(array $fields, &$boundary) {
    $boundary = '----WebKitFormBoundary' . bin2hex(random_bytes(8));
    $body = '';

    foreach ($fields as $name => $value) {
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
        $body .= $value . "\r\n";
    }

    $body .= "--{$boundary}--\r\n";
    return $body;
}

function solveICAPTCHA($html, $host, $ip = null) {
return styler("SOLVING icaptcha", function() use ($html, $host, $ip) {
    global $cookieFile, $userAgent;

    $endpoint = null;
    $scripts = xScraper::xpath($html, "//script/text()");
    foreach ($scripts as $js) {
        if (preg_match("~IconCaptcha\.init.*?endpoint\s*:\s*['\"]([^'\"]+)['\"]~is", $js, $m)) {
            $endpoint = $m[1]; break;
        }
    } 

    $token = xScraper::xPath($html, "//input[@name='_iconcaptcha-token']/@value")[0];
    
    if (!$endpoint || !$token) {
        #var_dump($endpoint);
        return false; 
    }

    $iconHeaders = ["x-iconcaptcha-token: $token"];
    $widgetID = widgetId();

    // ===== LOAD =====
    $timestamp = round(microtime(true) * 1000);
    $initTimestamp = $timestamp - 2000;
    $json = ["payload" => base64_encode(json_encode([
        "widgetId"      => $widgetID,
        "action"        => "LOAD",
        "theme"         => "light",
        "token"         => $token,
        "timestamp"     => $timestamp,
        "initTimestamp" => $initTimestamp
    ]))];
    $boundary = '';
    $headers = headers($host);
    $headers[] = "x-iconcaptcha-token: $token";
    #print_r($json);
/*
    if (!empty($ip)) {
        $res = Net::X($endpoint, 'POST', $json, $cookieFile, $headers, $host, $userAgent, false, true, $ip, true);
    } else {
            $res = Net::X($endpoint, 'POST', $json, $cookieFile, $headers, $host, $userAgent);
    }
*/
    $res = Net::X($endpoint, 'POST', $json, $cookieFile, $headers, $host, $userAgent, false, false, $ip ?: null);
    
    $r = json_decode(base64_decode($res), true);
    
    if (empty($r['identifier'])) {
        #var_dump($res);
        #var_dump($r);
        return false;
    }
    
    $challengeId = $r['identifier'];

    // ===== SELECTION LOOP =====
    $width = 320; $slots = 5; $y = rand(22, 30);;
    $slotW = $width / $slots;

    for ($i = 0; $i < $slots; $i++) {
        $x = (int)(($i * $slotW) + ($slotW / 2));

        $timestamp = round(microtime(true) * 1000);
        $initTimestamp = $timestamp - 2000;

        $json = base64_encode(json_encode([
            "x" => $x,
            "y" => $y,
            "width" => $width,
            "token" => $token,
            "action" => "SELECTION",
            "widgetId" => $widgetID,
            "timestamp" => $timestamp,
            "challengeId" => $challengeId,
            "initTimestamp" => $initTimestamp
        ]));
        #$body = webkitId($json, $boundary);
        $body = webkitId(["payload" => $json], $boundary);
        $headers = array_merge($iconHeaders, [
            "Content-Type: multipart/form-data; boundary=$boundary"
        ]);
        $headers = array_merge($headers, headers($host)); 

        $s = Net::X($endpoint, 'POST', $body, $cookieFile, $headers, $host, $userAgent, false, false, $ip ?: null);

        $r = json_decode(base64_decode($s , true), true);
        if (!empty($r['completed'])) {
            return [
                '_iconcaptcha-token' => $token,
                'ic-rq'  => 1,
                'ic-wid' => $widgetID,
                'ic-cid' => $challengeId,
                'ic-hp'  => '' 
            ];
        } 
        _sle(1);
    } return false;
});
}








function solveODDCAPTCHA($base64Image) {
    // Decode base64
    $image = imagecreatefromstring(base64_decode($base64Image));
    if (!$image) return false;

    $width  = imagesx($image);
    $height = imagesy($image);

    // 5 segment
    $segmentWidth = intdiv($width, 5);
    $angles = [];

    for ($segmentIndex = 0; $segmentIndex < 5; $segmentIndex++) {
        $xCoords = [];
        $yCoords = [];

        $segmentStartX = $segmentIndex * $segmentWidth;

        for ($y = 0; $y < $height; $y++) {
            for ($x = $segmentStartX; $x < $segmentStartX + $segmentWidth; $x++) {
                $color = imagecolorat($image, $x, $y);

                $red   = ($color >> 16) & 0xFF;
                $green = ($color >> 8)  & 0xFF;
                $blue  = $color & 0xFF;

                // Anggap pixel "gelap" jika salah satu channel < 220
                if ($red < 220 || $green < 220 || $blue < 220) {
                    $xCoords[] = $x - $segmentStartX;
                    $yCoords[] = $y;
                }
            }
        }

        $pointCount = count($xCoords);
        if ($pointCount < 10) {
            $angles[$segmentIndex] = 0.0;
            continue;
        }

        $meanX = array_sum($xCoords) / $pointCount;
        $meanY = array_sum($yCoords) / $pointCount;

        $varX = $varY = $covXY = 0.0;

        for ($i = 0; $i < $pointCount; $i++) {
            $dx = $xCoords[$i] - $meanX;
            $dy = $yCoords[$i] - $meanY;

            $varX  += $dx * $dx;
            $varY  += $dy * $dy;
            $covXY += $dx * $dy;
        }

        // PCA edge
        $angles[$segmentIndex] = ($varY == $varX)
            ? 0.0
            : 0.5 * rad2deg(atan2(2 * $covXY, $varY - $varX));
    }

    // median edge
    $sortedAngles = $angles;
    sort($sortedAngles);
    $medianAngle = $sortedAngles[2];

    // median deviation
    $maxDeviation = -1;
    $selectedSegment = 0;

    for ($segmentIndex = 0; $segmentIndex < 5; $segmentIndex++) {
        $deviation = abs($angles[$segmentIndex] - $medianAngle);
        if ($deviation > 90) $deviation = 180 - $deviation;

        if ($deviation > $maxDeviation) {
            $maxDeviation = $deviation;
            $selectedSegment = $segmentIndex;
        }
    }

    return $selectedSegment;
} 



function getOri($data) {
    $counts = array_count_values($data);
    foreach ($data as $i => $val) {
        if ($counts[$val] === 1) return $i;
    }
    return -1;
}


function atbE($_0) {
    $ab_ins = xScraper::xPath($_0, "//strong[contains(text(),',')]");
    #logx('ok', $ab_ins[0]);
    if (empty($ab_ins)) return null;
    
    $_ask = array_map('trim', explode(',', $ab_ins[0]));
    
    $_ab = '/data-token=\\\\"(?<token>[^"\\\\]+)\\\\".*?>(?<emoji>.*?)<\/a>/u';
    $ab_rel = rScraper::jPath($_0, $_ab);
    
    if (empty($ab_rel['token'])) return null;

    $ab_t = [];
    foreach ($ab_rel['token'] as $idx => $_rel) {
        $ab_e = $ab_rel['emoji'][$idx];
        $ab_t[$ab_e] = $_rel;
    }

    $db_e = LIBDIR.'/atb_e.json';
    $db = file_exists($db_e) ? json_decode(_get($db_e), true) : [];

    $solution = [];
    $log_map = []; 
    $_tokens = $ab_t;

    foreach ($_ask as $e_nam) {
        $e_nam = strtolower($e_nam);
        $ab_e = array_search($e_nam, $db);
        
        if ($ab_e !== false && isset($ab_t[$ab_e])) {
            $solution[$e_nam] = $ab_t[$ab_e];
            $log_map[] = "$e_nam:$ab_e";
            unset($_tokens[$ab_e]);
        } else {
            $solution[$e_nam] = null;
        }
    }

    foreach ($solution as $e_nam => $token) {
        if ($token === null) {
            if (count($_tokens) === 1) {
                $last_token = array_shift($_tokens);
                $solution[$e_nam] = $last_token;
                
                $emoji_sisa = array_search($last_token, $ab_t);
                $log_map[] = "$e_nam:$emoji_sisa(E)"; 
            }
        }
    }

    if (in_array(null, $solution) || count($solution) !== count($_ask)) {
        logx("err", "Antibot Unknown");
        return null;
    }

    #logx("info", "ATB: [" . implode(', ', $log_map) . "]");

    return implode(' ', array_values($solution));
}

function aHash($input) {
    $data = (is_string($input) && file_exists($input)) 
        ? _get($input) 
        : $input;

    $im = imagecreatefromstring($data);
    $w = 16; $h = 16;
    $thumb = imagecreatetruecolor($w, $h);
    imagecopyresampled($thumb, $im, 0,0,0,0, $w,$h, imagesx($im), imagesy($im));
    $pixels = [];
    $sum = 0;
    for ($y=0; $y<$h; $y++) {
        for ($x=0; $x<$w; $x++) {
            $rgb = imagecolorat($thumb, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $gray = 0.299*$r + 0.587*$g + 0.114*$b;
            $pixels[] = $gray;
            $sum += $gray;
        }
    }
    $avg = $sum / count($pixels);
    $hash = '';
    foreach ($pixels as $p) {
        $hash .= ($p >= $avg) ? '1' : '0';
    }
    #imagedestroy($thumb);
    #imagedestroy($im);
    return $hash;
}

function dHash($input) {
    $data = (is_string($input) && file_exists($input)) 
        ? _get($input) 
        : $input;

    $im = imagecreatefromstring($data);
    $w = 16; $h = 16;
    $thumb = imagecreatetruecolor($w, $h);
    imagecopyresampled($thumb, $im, 0,0,0,0, $w,$h, imagesx($im), imagesy($im));
    $hash = '';
    for ($y=0; $y<$h; $y++) {
        for ($x=0; $x<$w-1; $x++) {
            $rgb1 = imagecolorat($thumb, $x, $y);
            $rgb2 = imagecolorat($thumb, $x+1, $y);
            $g1 = (($rgb1>>16)&0xFF + ($rgb1>>8)&0xFF + ($rgb1&0xFF))/3;
            $g2 = (($rgb2>>16)&0xFF + ($rgb2>>8)&0xFF + ($rgb2&0xFF))/3;
            $hash .= ($g1 > $g2) ? '1' : '0';
        }
    }
    #imagedestroy($thumb);
    #imagedestroy($im);
    return $hash;
}

function hamming($_1, $_2) {
    $dist = 0;
    for ($i=0; $i<strlen($_1); $i++) {
        if ($_1[$i] !== $_2[$i]) $dist++;
    }
    return $dist;
}



function imgGD($input) {
    $data = (is_string($input) && file_exists($input)) 
        ? _get($input) 
        : $input;
    $im = imagecreatefromstring($data);
    $w = 16; $h = 16;
    $thumb = imagecreatetruecolor($w, $h);
    imagecopyresampled($thumb, $im, 0,0,0,0, $w,$h, imagesx($im), imagesy($im));
    $data = [];
    for ($y=0; $y<$h; $y++) {
        for ($x=0; $x<$w; $x++) {
            $rgb = imagecolorat($thumb, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $gray = ($r+$g+$b)/3;
            $data[] = $gray;
        }
    }
    ##imagedestroy($thumb);
    ##imagedestroy($im);
    return $data;
}

function imgDiff($_a, $_b) {
    $diff = 0;
    for ($i=0; $i<count($_a); $i++) {
        $diff += abs($_a[$i]-$_b[$i]);
    }
    return $diff;
}