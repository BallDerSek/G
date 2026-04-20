<?php
die;
if (!defined('ROOT')) { die; }
$api = onKeys();
$cookieFile = getCookie();
$userAgent = getUagent();






tes:
$links = Net::C("$host/links", 'GET', null, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
#_put('0.html', $links);

$bct_sl = xScraper::xPath($links, "//*[starts-with(@id, 'bct-')]//a/@href");
#print_r($bct_sl);

$bitco_get = Net::C($bct_sl[0], 'GET', null, $cookieFile, [], "$host/faucet", "$host/links");
#_put('sl.html', $bitco_get);

$bct_u = rScraper::jPath($bitco_get, "/window\.location\.href\s*=\s*['\"]([^'\"]+)['\"]/");

if (!empty($bct_u[1][0])) {
    $bct_go = $bct_u[1][0];
    logx('info', $bct_go);
    
    #goto tes;
    $bitco_go = Net::C($bct_go, 'GET', null, $cookieFile, [], "$host/links");
    #_put('sl.html', $bitco_go);
}


$cap_u = xScraper::xPath($bitco_go, "//script[contains(@src,'captcha2/')]/@src");
logx('info', $cap_u[0]);

$bct_h = parse_url($bct_go)['host'];
$cap_u = "https://$bct_h{$cap_u[0]}";
logx('ok', $cap_u);

$cap_js = Net::C($cap_u, 'GET', null, $cookieFile, [], $bct_go, $userAgent);
_put('cap.js', $cap_js);

$rng = new Random\Randomizer();

$cc_p = [
    't' => (int)(microtime(true) * 1000),
    'r' => $rng->getFloat(0, 1)
];
$cap_pa = json_decode(Net::X($cap_u, 'POST', $cc_p, $cookieFile, [], $bct_go, $userAgent, true) ?: '', true);

if (!empty($cap_pa['options']) && !empty($cap_pa['pixel'])) {

    $cap_dir = __DIR__ . '/bct';
    if (!is_dir($cap_dir)) {
        mkdir($cap_dir, 0777, true);
    }

    _put($cap_dir . '/cap.json', json_encode($cap_pa, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    render_rgba(
        $cap_pa['pixel'],
        200,
        100,
        $cap_dir . '/main.png'
    );

    foreach ($cap_pa['options'] as $i => $opt) {
        render_rgba(
            $opt['pixels'],
            $opt['width'],
            $opt['height'],
            $cap_dir . "/opt_$i.png"
        );
    }
}








die;
tess:

$cap_js = _get('cap.js');












function render_rgba($b64, $w, $h, $file) {
    $raw = base64_decode($b64);

    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $i = 0;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {

            $r = ord($raw[$i++] ?? "\x00");
            $g = ord($raw[$i++] ?? "\x00");
            $b = ord($raw[$i++] ?? "\x00");
            $a = ord($raw[$i++] ?? "\x00");

            $alpha = 127 - intval($a / 255 * 127);

            $color = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
            imagesetpixel($img, $x, $y, $color);
        }
    }

    imagepng($img, $file);
    #imagedestroy($img);
}