<?php

return (new class {
    
    private $api;
    private $banner;
    
    public function __construct() {
        $this->api = onKeys();
        $b = $this->banner = Banner::getInstance();
    }
    
    public function exec() {
        
        while (true) {
            $apiName = $this->api ? get_class($this->api) : 'none';
            $this->banner->show();
            $ddd = 0;
            
            while (true) {
                $ddd++;
                $this->banner->task1('info', "provider: ".$apiName);
                $this->banner->task2('info', "input shortlink or (change api)");
                
                if ($ddd >= 10) break;
                $url = getenv('login') ?: _rl('shortlink: ');
                
                if (!empty($url)) {
                    if ($url === 'change api') {
                        $this->api = onKeys();
                        break;
                    }
                    
                    $this->banner->task1('warn', "processing");
                    $this->banner->task2('info', "");
                    $bakk = Shortlinks::exec($this->api, $url);
                    
                    if ($bakk) {
                        $this->banner->task2('ok', "done");
                        Logger::X('ok', "result: ".$bakk, true, true);
                    } else $this->banner->task2('err', "fail");
                    
                } else {
                    $this->banner->task1('warn', "jangan kosong");
                    $this->banner->task2('err', "fail");
                }
                
                _sle(2);
            }
            
        }
        
    }
    
    
    
})->exec();













die;
tes:
$cookieFile = config::cookie();
$userAgent = config::uagent();
$url = $ez4s;
$_0 = Net::C($url, 'GET', null, $cookieFile, [], '', $userAgent);
_put('0.html', $_0);

$res = Scraper::_pP($_0, 'window.location.href');
$url = $res[0] ?? '';
if (!empty($url)) {
    $_1 = Net::X($url, 'GET', null, $cookieFile, [], $url, $userAgent);
    _put('1.html', $_1);
}


preg_match('/url=([^"\'>\s]+)/i', $_1, $url);
if ($url[1]) {
    $url = trim($url[1]);
    $_2 = Net::X($url, 'GET', null, $cookieFile, [], $url, $userAgent);
    _put('2.html', $_2);
}







#rsshort.com
function rsShort($url, $final, $cookieLinks, $userAgent, $solver) {

    $_0 = Net::C($url, 'GET', null, $cookieLinks, [], $userAgent, '', true); _sle(1);
    #file_put_contents('0.html', $_0['body']);
    
        $html = $_0['body'];
    $token = x($html, 'rscaptcha_token');
    if (!empty($token)) {
        Logger::X('err', "0 level ");
        file_put_contents('0.html', $html);
        xtractJs($html); 
    } else { 
        Logger::X('err', "1 level");
        startstepRs:
        $attempts = 0;
        $js = null;
        while ($attempts < 5 && !$js) {
            $m = Scraper::_xP($html, "//script/text()");
            if (!empty($m)) {
                foreach ($m as $code) {
                    $code = trim($code);
                    if (strlen($code) > 5000 && stripos($code, '_0x') && stripos($code, 'decodeURIcomponent') !== false) {
                        $js = $code; break;
                    }
                }
            }
            if (!$js) {
                $attempts++; _sle(2); 
                $html = Net::C($url, 'GET', null, $cookieLinks, [], $userAgent, true);
            }
        }
        if ($js) {
            file_put_contents('rs.js', $js);
            try {
                dumpJsFlex('rs.js', 'tes.html',true); $wait = 0;
                while (!file_exists('0.html') || filesize('0.html') < 20) {
                    usleep(200000); $wait++; 
                    if ($wait > 20) break;
                }
            if (file_exists('0.html')) {
                $dumpContent = _get('0.html');
                xtractJs($dumpContent);
            }
            } catch (Throwable $e) {
                Logger::X('err', "{$e->getMessage()}");
            }
        } else {
            Logger::X('err', "Error Html"); 
            goto startstepRs; 
        }
    }
    die;
    while (true) {

        $sync = "synchrony 1.js 2>&1";$output = shell_exec($sync); _sle(1);
        $cleanHtml = stripslashes(_get('0.html'));
        if (!preg_match('/<button[^>]*>\s*(Step\s?\d+\/\d+)\s*<\/button>/i', $html, $m)) {
            
            preg_match('/<button[^>]*>\s*(Step\s?\d+\/\d+)\s*<\/button>/i', $cleanHtml, $m);
        }
        Logger::X('info', "\r".$m[1]."\t", false, true);
            
            $token_data = Scraper::_pP($cleanHtml, 'rscaptcha_token" value');
            $token = $token_data[1][0] ?? null;
            
            $img_data = Scraper::_pP($cleanHtml, 'rscaptcha_img" src');
            $img = $img_data[1][0] ?? null;
            
            $aesFile = file_exists('2.js') ? '2.js' : '1.cleaned.js';
            
            
            $aes = ['rskp2305' => '', 'uf' => '', 'iv' => '', 'ciphertext' => '', 'titikNol' => 0];
            if (file_exists($aesFile)) {$aes = rsAescipher($aesFile);}
            
            
            
            $rscaptcha_response = null;
            if ($img) {
                file_put_contents('captcha.png', _get($img));
                $coords = $solver->base64('captcha.png', 'rs_upside');
                if ($coords) {
                    preg_match_all('/\d+/', $coords, $matches);
                    if (count($matches[0]) >= 2) {
                        $x = $matches[0][0]; $y = $matches[0][1];
                        
                        $rscaptcha_response = rsResponse('1.cleaned.js', $x, $y, $aes['titikNol'], $userAgent);
                    }
                }
            } else { _sle(1); }
            
            $postData = [
                'rskp2305'   => $aes['rskp2305'] ?? '',
                'uage'       => $userAgent,
                'uf'         => $aes['uf'] ?? '',
                'iv'         => $aes['iv'] ?? '',
                'ciphertext' => $aes['ciphertext'] ?? '',
                'utt'        => TIMEZONE(),
                'ls'         => LANGUAGE()
                ];
            
            if ($token) $postData['rscaptcha_token'] = $token;
            if ($rscaptcha_response) $postData['rscaptcha_response'] = $rscaptcha_response;
            $articleUrl = lastLocation($_0['headers']);
            #Logger::X('info', $articleUrl);
            $rsCookies = [
                'uf'  => $aes['uf'],
                'utt' => TIMEZONE(),
                'ls'  => LANGUAGE()
                ];
            
            $h = headers($articleUrl, $articleUrl, '', $rsCookies);
            @unlink('1.cleaned.js'); @unlink('2.js'); @unlink('tes.html'); @unlink('captcha.png'); @unlink('1.js');

            styler("building", function() {_sle(20);});

            $_0 = Net::C($articleUrl, 'POST',$postData, $cookieLinks, $h, '', $userAgent, true);
            _sle(1); #file_put_contents('tes.html', $_0['body']);

$finalLink = lastLocation($_0['headers'], $final);
if ($finalLink) { Logger::X('ok', $finalLink); return $finalLink; }

            $html = $_0['body'];
            $token = x($html, 'rscaptcha_token');
            if (!empty($token)) {
                file_put_contents('tes.html', $html);
                xtractJs($html); 
            } else { 
                nextstepRs:
                $attempts = 0;
                $js = null;
                while ($attempts < 5 && !$js) {
                     $m = Scraper::_jP($html, '#<script\b[^>]*>(.*?)</script>#is');
                    if (!empty($m[1])) {
                        foreach ($m[1] as $code) {
                            $code = trim($code);
                            if (strlen($code) > 5000 && stripos($code, '_0x') && stripos($code, 'decodeURIcomponent') !== false) {
                                $js = $code; break;
                            }
                        }
                    }
                    if (!$js) {
                        $attempts++; _sle(2);
                        $html = Net::C($articleUrl, 'GET', null, $cookieLinks, $h, $articleUrl, $userAgent);
                    }
                }
                if ($js) {
                    file_put_contents('rs.js', $js);
                    try {
                        dumpJsFlex('rs.js', 'tes.html',true);
                        $wait = 0;
                        while (!file_exists('tes.html') || filesize('tes.html') < 20) {
                            usleep(200000);
                            $wait++;
                            if ($wait > 20) break;
                        }
                        if (file_exists('tes.html')) {
                            $dumpContent = _get('tes.html');
                            xtractJs($dumpContent);
                        }
                    } catch (Throwable $e) {
                        Logger::X('err', "{$e->getMessage()}"); 
                    }
                } else { Logger::X('err', "Error Html"); goto nextstepRs; }
            } 
            

    }
}

#crypto-radio.eu
function cRadio($url, $final, $solver) {
global $cookieLinks, $userAgent;
$host = parse_url($url, PHP_URL_HOST) ?? '';
$path = parse_url($url, PHP_URL_PATH) ?? '';

$_0 = Net::C($url, 'GET', null, $cookieLinks, [], '', $userAgent);
$nextUrl = Scraper::_pP($_0, "location.href")[1][0];
    
for ($i = 0; $i < 5; $i++) {
    $_0 = Net::C($nextUrl, 'GET', null, $cookieLinks, [], $host, $userAgent, true);
    $html = $_0['body'];
    #file_put_contents("tes.html", $html);
    $h1 = Scraper::_xP($html, "//h1")[0] ?? null; 
    if ($h1) { Logger::X('ok', $h1); break; }
    $loc = Scraper::_pP($html, "location.href")[1][0] ?? null;
    $content = Scraper::_pP($html, "content")[1][0] ?? null;
    if ($loc) { $nextUrl = $loc; } 
    elseif ($content) {
        $nextUrl = preg_replace('/^0;URL=/i', '', $content);
    } else { Logger::X('err', "failed redirect"); break; }
    _sle(1);
}

while(true) {
file_put_contents('start.html', $html);
$sl_u = Scraper::_xP($html, "//script[contains(@src,'sl/')]/@src")[0];
$pat = lastLocation($_0['headers']);

$jsUrl = parse_url($pat, PHP_URL_HOST);
$SLjs = Net::C("https://{$jsUrl}{$sl_u}", 'GET', null, $cookieLinks, [], $pat); _sle(1);

if (strlen($SLjs) > 1000) {
    file_put_contents("sl.js", $SLjs); _sle(1);
    $cc_t = Scraper::_jP($SLjs, '/\?([a-f0-9]{32})=true/')[0][0] ?? '';
    if (!$cc_t) { return false; } #net error
    $cc_p = [Scraper::_jP($SLjs, '/data:\s*\{\s*([a-zA-Z0-9_]+)\s*:\s*1\s*\}/')[1][0] => 1];
    $cc_u = Scraper::_jP($SLjs, '/src\s*=\s*[\'"`](\/cc\/[\w\d]+\.js\?onload=[\w\d]+&action=captcha)[\'"`]/')[1][0];
}


$cc_vr = json_decode(Net::X("https://$jsUrl$path$cc_t", 'POST', $cc_p, $cookieLinks, [], $jsUrl), true);_sle(1);



if (isset($cc_vr['status']) && $cc_vr['status'] === 200) {
    $CCjs = Net::C("https://{$jsUrl}$cc_u", 'GET', null, $cookieLinks, [], "https://$jsUrl");
    if (strlen($CCjs) > 100) {
        file_put_contents("cc.js", $CCjs);
        $img_u = Scraper::_pP($CCjs, "src")[1][0]; 
        $img = Net::C("https://$jsUrl$img_u", 'GET', null, $cookieLinks, [], $pat); 
        $icon = Scraper::_jP($CCjs, '/captchaData\s*=\s*\{"options":\s*(\[.*?\])\}/s')[1];
        #print_r($icon);
        $xhr = Scraper::_jP($CCjs, '/xhr\.open\("POST",\s*"([^"]+)"/')[1][0];
        $xhrs = Scraper::_jP($CCjs, '/xhr\.send\(\s*"([^"]+)"\s*\+\s*([a-zA-Z0-9_]+)\s*\)/');
        
        if (!isset($xhrs[1][0], $xhrs[2][0])) {
            Logger::X('err', "invalid XHR Format"); return;
        } parse_str($xhrs[1][0], $pa);
    } else { Logger::X('err', "failed fetching cc"); }
} else { Logger::X('err', ($cc_vr['message'] ?? 'Unknown error')); }

$icons = [];
if (!empty($icon[0])) { $icons = json_decode($icon[0], true); }

if (!$solver) {
    file_put_contents("captcha.png", $img);
    echo "\noptions\n";
    foreach ($icons as $i => $icon) { echo "  [$i] $icon\n"; }
    $inputName = trim(readline("check captcha.png: "));
    @unlink('captcha.png');
} else {
    $inputName = $solver->base64($img, 'fa_icon'); 
}

$indexIcon = false; $fullName = "";

foreach ($icons as $i => $name) {
    if (stripos($name, $inputName) !== false) {
        $indexIcon = $i; $fullName = $name;
        break;
    }
}

if ($indexIcon !== false) {
    #echo "[$indexIcon] $fullName\n";
    $indexKey = array_search('', $pa, true);
    if ($indexKey !== false) { 
        $pa[$indexKey] = (string)$indexIcon;
    } else { $pa['icon'] = (string)$indexIcon; }
} unset($pa['iconIndex']);

_sle(10);
print_r($pa);
$cc_res = Net::X("https://$jsUrl$xhr", 'POST', $pa, $cookieLinks, [], $pat, $userAgent); _sle(1);
file_put_contents('cc.json', $cc_res, JSON_PRETTY_PRINT);
$payload = submit($CCjs, $html, $cc_res);
print_r($payload);
$_0 = Net::C($pat, 'POST', $payload, $cookieLinks, [], $pat, $userAgent, true); #_cle();
$html = $_0['body'];


for ($i = 0; $i < 5; $i++) {
    $h1 = Scraper::_xP($html, "//h1")[0] ?? null;
    $con = Scraper::_pP($html, "content")[1][0] ?? null;
    $loc = Scraper::_pP($html, "location.href")[1][0] ?? null;
    $nextUrl = null;
    if ($loc) { $nextUrl = $loc; } 
    elseif ($con) { $nextUrl = preg_replace('/^0;URL=/i', '', $con); }

    if ($nextUrl && strpos($nextUrl, $final) !== false) {
        return $nextUrl; 
    }

    if ($h1 && strpos($h1, 'STEP') !== false) { Logger::X('ok', $h1); 
        break; }

    if (!$nextUrl) { Logger::X('err', "fail"); break; }

    $_0 = Net::C($nextUrl, 'GET', null, $cookieLinks, [], $host, $userAgent, true); 
    $html = $_0['body'];
    _sle(1);
    file_put_contents('log.html', $html);
}
}
}