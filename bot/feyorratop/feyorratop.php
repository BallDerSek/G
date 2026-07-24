<?php

return (new class {
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://feyorra.top';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $limit = false;
    private bool $claim = true;
    private bool $SLDONE = false;
    private bool $ADDONE = false;
    private bool $BCDONE = false;
    private array $skipped = [];
    private array $headersCF = [];
    private bool $can_withdraw = true;
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential([], false, ['mail', 'pass', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['mail'];
        $this->pass = $this->acc['pass'];
        
        Inf::setup(
            Config::uagent('mobile'), 
            Config::cookie($this->mail),
            $this->ip,
            false, 
            $this->mail
        );
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', $this->mail);
        $b->task2('ok', "site: " . $this->host);
    }

    public function exec() {
        $skipped = [];
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0; 
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", [], '/register');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    Logger::X('Info', "logged in", false); 
                    _sle(3); _clr();
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                $_0 = Net::C("{$this->host}/login", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, false, false, $this->ip);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    #var_dump($f); die;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['email' => $this->mail, 'password' => $this->pass];
                        
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                    
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, [], $this->host.'/login', Inf::$uagent, ip: $this->ip);
                    
                    $msg_d = Scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]")[0] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'nvalid Captcha')) continue;
                        $this->logger('err', '', $msg_d, 1);
                        
                    }
                    
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); die;
            
            if ($dash && str_contains($dash, 'confirm your email')) $this->can_withdraw = false;
            
            $_bal = Scraper::_xP($dash, "//div[contains(@class, 'topStat_card')]//p[contains(text(), 'Coins')]/text()")[0] ?? '';
            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                $bal = ((int)$_bal);
                
                if ($this->can_withdraw && ($bal >= 2000)) {
                    $po = null;
                    $jjn = [];
                    $wd = Net::C("{$this->host}/withdraw", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    $jjn = $this->_wd($wd);
                    
                    if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                        $pa = $jjn['payload'];
                        $cap = Solve::exec($dash, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) $this->can_withdraw = false;
                        
                        $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                        if (empty($pa[$walletKey])) $pa[$walletKey] = $this->mail;
                        
                        $po = array_merge($pa, $cap);
                        
                        $this->logger('', "", "tes ilmu: ".$jjn['info']['coin']);
                        $wdd = Net::C($jjn['url'], 'POST', $po, Inf::$cookie, [], "{$this->host}/withdraw", Inf::$uagent, ip: $this->ip);
                        $mwd = Scraper::_jP($wdd, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                        if (isset($mwd[2][0])) {
                            $msg = $mwd[2][0];
                            $this->logger('ok', 'withdraw', $msg);
                        }
                        
                    } else Logger::X('err', 'gak bisa wd kayaknya');
                    
                }
                
            }
            
            $setF = 0;
            if (!$this->limit && $this->claim) {
                $ret99 = 0; 
                while (true) {
                    $ret99++;
                    $fau = Net::C("{$this->host}/faucet", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    #_put('fau.html', $fau); 
                    if ($fau === 99) {
                        if ($ret99 >= 5) goto login;
                        _sle(40);
                        continue;
                    }
                    
                    $po = null;
                    if (!empty($fau) && $fau !== 99) {
                        $ret99 = 0;
                        $f = Scraper::payload($fau)[0] ?? [];
                        #var_dump($f);
                        
                        if (!empty($f['payload'])) {
                            $pa = $f['payload'];
                            $_ca = $pa['captcha'] ?? '';
                            
                            if  (($_ca === 'faucetcaptcha')) {
                                $fcc = FaucetCaptcha::exec($this->host, $this->host.'/faucet', $this->mail);
                                #var_dump($fcc); die;
                                if ($fcc === 44) break;
                                
                                if (isset($fcc['trouble'])) continue;
                            }
                            
                            if (($_ca === 'hcaptcha')) {
            /* comment ini kalo mau lanjut solve*/
                                $this->claim = false; break;
                            }
                            
                            $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                            
                            if (isset($cap['trouble'])) continue;
                            
                            $po = array_merge($pa, $cap, $fcc ?? []);
                            
                            if (stripos($fau, 'rite what you see') !== false) {
                                
                                $t_text = null;
                                $_cu = null;
                                foreach (Scraper::_pP($fau, 'src') as $_u) {
                                    if (str_contains($_u, '/images/captcha')) {
                                        $_cu = trim($_u);
                                        break;
                                    }
                                }
                                
                                if ($_cu) {
                                    $img = Net::C($_cu, 'GET', null, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent);
                                    
                                } else {
                                    $_cuu = Scraper::_jP($fau, '/src="data:image\/png;base64,([^"]+)"/i')[1][0] ?? null;
                                    if (!empty($_cuu)) {
                                        #var_dump($_cuu);
                                        $img = base64_decode($_cuu);
                                    }
                                    
                                }
                                if (!empty($img) && ($img !== 99)) $t_text = _text($img, $this->host, $this->mail);
                                
                                if (!$t_text) continue;
                                
                                if ($t_text !== null) {
                                    $xp = Scraper::dom($fau);
                                    $nodes = $xp->query("//input[@pattern='[0-9]*'] | //input[@inputmode='numeric']");
                                    
                                    $_Tfield = null;
                                    if ($nodes->length > 0) $_Tfield = $nodes->item(0)->getAttribute('name');
                                    
                                    if (!empty($_Tfield)) $po[$_Tfield] = $t_text;
                                }
                            }
                            
                            
                        } else {
                            if (str_contains($fau, '/register')) continue 2;
                            
                            /*
                            if (!$this->SLDONE || !$this->ADDONE) {
                                $setF = microtime(true);
                                break;
                            }
                            */
                            
                            styler('Waiting for faucet', fn() => _sle(30));
                            continue;
                        }
                        
                    }
                    
                    if (!empty($po)) {
                        #print_r($po); die;
                        $cla = Net::C($f['url'], 'POST', $po, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent, ip: $this->ip);
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        $mf = Scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                        if (!empty($mf[2][0])) {
                            $msg = $mf[2][0];
                            
                            $this->logger('ok', 'fct', $msg);
                            if (stripos($msg, 'has been added')) {
                                $setF = microtime(true);
                                break;
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
            $ads = Net::C("{$this->host}/ptc", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                #print_r($ptcList); die;
                
                if ($ptcNumb <= 1) {
                    $this->ADDONE = true;
                } else {
                    if (!empty($ptcList['local'])) {
                        foreach ($ptcList['local'] as $ptc) {
                            break;
                            [$ad_u, $ad_t] = $ptc;
                            $cla = null;
                            $view = null;
                            
                            $view = Net::C($ad_u, 'GET', null, Inf::$cookie, [], "{$this->host}/ptc", Inf::$uagent, ip: $this->ip);
                            #_put('view.html', $view);
                            
                            $po = null;
                            if (!empty($view) && $view !== 99) {
                                
                                styler("waiting for ads: $ad_t", fn() => _sle($ad_t));
                                
                                $f = Scraper::payload($view)[0] ?? [];
                                
                                if (!empty($f)) {
                                    $paa = $f['payload'];
                                    $_caa = $paa['captcha'] ?? '';
                                    
                                    if (($_caa === 'hcaptcha')) {
                                        break;
                                        
                                    }
                                    if  (($_caa === 'faucetcaptcha')) {
                                        $fcc = FaucetCaptcha::exec($this->host, $ad_u, $this->mail);
                                        if ($fcc === 44) {
                                            $this->ADDONE = true; break;
                                        }
                                        if (isset($fcc['trouble'])) continue;
                                    }
                                    $cap = Solve::exec($view, $ad_u, $this->api, $paa);
                                    
                                    if (isset($cap['trouble'])) continue;
                                    
                                    $po = array_merge($paa, $cap, $fcc ?? []);
                                    
                                }
                                
                            }
                            
                            if (!empty($po)) {
                                $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, [], $ad_u, Inf::$uagent, ip: $this->ip);
                                
                                $ma = Scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s")[2][0] ?? null;
                                
                                if (!empty($ma)) $this->logger('info', 'ptc', $ma);
                                /*
                                var_dump($po, $f, $_ca); 
                                _put('view.html', $view);
                                _put('cla.html', $cla);
                                _rl('cek:'); 
                                */
                                $endF = microtime(true);
                                if ($setF > 0 && $this->claim) {
                                    $balik = $endF - $setF;
                                    if ($balik >= 2 * 60) continue 2;
                                }
                                
                            }
                            
                        }
                        
                    }
                    
                    if (!empty($ptcList['bctt'])) {
                        #print_r($ptcList['bctt']); die;
                        foreach ($ptcList['bctt'] as $ptc) {
                            [$ad_u, $ad_t] = $ptc;
                            $bctt = new Bctt($this->host, $this->api, $this->mail);
                            $ch = $bctt->exec($ad_u, $ad_t);
                            if ($ch === 99) goto login;
                            if ($ch === 'forbidden') break;
                            $endF = microtime(true);
                            if ($setF > 0 && $this->claim) {
                                $balik = $endF - $setF;
                                if ($balik >= 2 * 60) continue 2;
                            }
                            
                        }
                    }
                    
                }
                
                
                
            }
            
            if (!$this->SLDONE) {
                $ret99 = 0;
                $up = ['earnow','shortano', 'shortino', 'fc-lc'];
                
                do {
                    $ret99++;
                    $sho = Net::C("{$this->host}/links", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    if ($sho === 99) {
                        if ($ret99 >= 5) goto login;
                        continue;
                    }
                    $ret99 = 0;
                    
                    $short = Shortlinks::extract($sho);
                    if (empty($short)) $this->SLDONE = true;
                    #print_r($short); die;
                    
                    $f = Scraper::payload($sho)[0] ?? [];
                    
                    if (!empty($f)) {
                        $po = $f['payload'];
                        
                        if (str_contains($sho, 'Write what you see in the picture')) {
                            $t_text = null;
                            $_cu = null;
                            foreach (Scraper::_pP($sho, 'src') as $_u) {
                                if (str_contains($_u, '/images/captcha')) {
                                    $_cu = trim($_u);
                                    break;
                                }
                            }
                            if ($_cu) {
                                $img = Net::C($_cu, 'GET', null, Inf::$cookie, [], "{$this->host}/links", Inf::$uagent);
                                $t_text = _text($img, $this->host, $this->mail);
                            }
                            if ($t_text) {
                                foreach ($po as $key => $val) {
                                    if ($val === '' || $val === null) {
                                        $po[$key] = $t_text;
                                    }
                                }
                            }
                        }
                    } 
                    
                    $can_process = false; 
                    foreach ($short as $links => [$idd, $lmt]) {
                        if (!Shortlinks::limit($lmt) || isset($skipped[$idd])) continue;
                        $can_process = true;
                        
                        $ud = $this->host.'/links/go/'.$idd;
                        $loc = $this->parseShortL($ud, "{$this->host}/links", $po);
                        
                        if (!$loc) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        $loc_u = parse_url($loc)['host'];
                        $is_bl = false;
                        foreach ($up as $blacklisted) {
                            if (str_contains($loc_u, $blacklisted)) {
                                Logger::X('warn', "Domain $blacklisted Skipping..");
                                $skipped[$idd] = true;
                                $is_bl = true;
                                break; 
                            }
                        }
                        if ($is_bl) continue;
                        
                        $start = microtime(true);
                        $bakk = Shortlinks::exec($this->api, $loc);
                        $wait = 130 - (int)(microtime(true) - $start);
                        
                        if (!$bakk) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                        
                        $retVer = 0;
                        while ($retVer <= 3) {
                            $retVer++;
                            $ver = Net::C($bakk, 'GET', null, Inf::$cookie, [], $loc, Inf::$uagent);
                            
                            if (!empty($ver) && $ver !== 99) {
                                $msh = Scraper::_jP($ver, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                                
                                if (!empty($msh[2][0])) {
                                    
                                    $msg = $msh[2][0];
                                    $this->logger('ok', 'sho', $msg);
                                    
                                }
                                
                                break 3;
                                
                            }
                        }
                        
                    }
                    
                    if (!$can_process) {
                        $this->logger('err', 'sho', 'SL habis atau sisa blacklist');
                        $this->SLDONE = true;
                    }
                    
                } while (!$this->SLDONE);
                
            }
            
            $off_B = Net::C("{$this->host}/offerwall/bitcotasks", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
            $bctt_u = Scraper::_jP($off_B, '/<iframe[^>]*src=["\']([^"\']*bitcotask[^"\']*)["\'][^>]*>/i')[1][0] ?? null;
            
            if (!empty($bctt_u)) {
                $bctt = new Bctt($this->host, $this->api, $this->mail);
                $bcttwl = $bctt->wall($bctt_u);
                if (($bcttwl === 'claim') && $this->claim) $bcttwl->cleanup();
                if (($bcttwl === 'habis')) $this->BCDONE = true;
                
            }
            
            if ($this->SLDONE && $this->ADDONE && !$this->claim && $this->BCDONE) styler('cooldown', fn() => _sle(600));
            
        }
        
    }
    
    private function parseShortL($ud, $sl, $po = null) {
        
        $getLok = 0;
        while ($getLok <= 3) {
            $getLok++;
            $lok = Net::X($ud, 'POST', $po, Inf::$cookie, $this->headersCF, $sl, Inf::$uagent);
            if (!empty($lok) && $lok !== 99) {
                return Scraper::_pP($lok, 'location.href')[0] ?? null;
                
            }
        }
        
        return null;
        
    }
    
    private function parsePtcAds($html) {
        $host = $this->host;
        if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $xp = Scraper::dom($html);
        if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
        $baseUrl = (parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host);
        $baseUrl = rtrim($baseUrl, '/');
        
        foreach ($xp->query("//div[contains(@class,'ptc_cards')]") as $card) {
            $btn = $xp->query(".//button/@onclick", $card);
            if ($btn->length === 0) continue;
            
            preg_match("/(?:window\.location\s*=\s*|window\.open\s*\(\s*|location\.href=')'([^']+)'/", $btn->item(0)->value, $m);
            if (empty($m[1])) continue;
            
            $url = $m[1];
            
            if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
                $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
            } elseif (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            }
            
            $timer = 5;
            $spans = $xp->query(".//span", $card);
            foreach ($spans as $span) {
                $text = trim($span->textContent);
                if (preg_match('/(\d+)\s*s/', $text, $tm)) {
                    $timer = (int)$tm[1];
                    break;
                }
            }
            
            $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
            
            if ($uHost === $host) $result['local'][] = [$url, $timer];
            elseif (strpos($url, 'bitcotasks.com') !== false) $result['bctt'][] = [$url, $timer];
            else $result['external'][] = [$url, $timer];
        }
        
        $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
        
        return $result;
    }
    
    private function _wd($html) {
        $res = Scraper::payload($html)[0] ?? null;
        if (!$res) return false;
    
        $names  = Scraper::_xP($html, "//input[@name='method']/@data-coincode");
        $values = Scraper::_xP($html, "//input[@name='method']/@value");
        $stocks = Scraper::_xP($html, "//div[contains(@class, 'col-2') and contains(text(), '%')]");
    
        foreach ($names as $i => $name) {
            if (stripos($name, 'btc') !== false || stripos($name, 'bitcoin') !== false) continue;
            
            $stokValue = (int) ($stocks[$i] ?? 0);
            
            if ($stokValue > 20) {
                $res['payload']['method'] = $values[$i];
                
                $res['info'] = [
                    'coin'  => $name,
                    'stock' => $stokValue . '%'
                ];
                return $res;
            }
        }
        return false;
    }
    
})->exec();


function pre($in_put, $threshold = 128) {

    $put_in = dirname($in_put) . DIRECTORY_SEPARATOR . 'pre_' . basename($in_put);

    $img = @imagecreatefromstring(_get($in_put));
    if (!$img) return 300;

    $width  = imagesx($img);
    $height = imagesy($img);

    $scale = 3;
    $newWidth  = $width * $scale;
    $newHeight = $height * $scale;
    $clean = imagecreatetruecolor($newWidth, $newHeight);
    
    imagecopyresampled($clean, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    imagefilter($clean, IMG_FILTER_GRAYSCALE);
    
    imagefilter($clean, IMG_FILTER_CONTRAST, 40); 

    for ($y = 0; $y < $newHeight; $y++) {
        for ($x = 0; $x < $newWidth; $x++) {
            $rgb = imagecolorat($clean, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            if ($r < $threshold) {
                $color = imagecolorallocate($clean, 0, 0, 0);
            } else {
                $color = imagecolorallocate($clean, 255, 255, 255);
            }
            imagesetpixel($clean, $x, $y, $color);
        }
    }

    $topLeft = imagecolorat($clean, 0, 0);
    if (($topLeft & 0xFF) < 128) imagefilter($clean, IMG_FILTER_NEGATE);

    imagepng($clean, $put_in);
    @imagedestroy($img);

    return $put_in;
}

function _text($imgData = null, $host = '', $mail = null) {
    if (!getDeps('gd@php')) die(Logger::X('err', 'gd@php missing'));
    return styler("solving ocr", function() use ($imgData, $host, $mail) {
        if (empty($imgData)) return null;
    
        $tmpDir = _lib('ocr', $host, $mail); 
        $originalImg = $tmpDir . '/raw.png';
    
        _put($originalImg, $imgData);
        
        $t_vote = [];
        $_th = [80, 90, 100, 110, 120, 140, 160];
        $_psms = [6, 8, 11];
    
        try {
            foreach ($_th as $th) {
                $preFile = pre($originalImg, $th, 3); 
                
                if ($preFile === 300) return null;
                
                if (!$preFile || !file_exists($preFile)) continue;
    
                foreach ($_psms as $psm) {
                    $output = [];
                    $cmd = "tesseract " . escapeshellarg($preFile) . " stdout --psm $psm -c tessedit_char_whitelist=0123456789 2>/dev/null";
                    @exec($cmd, $output);
                    
                    $resText = trim(implode('', $output));
                    
                    if (ctype_digit($resText) && strlen($resText) === 4) {
                        $t_vote[] = $resText;
                    }
                }
                if (file_exists($preFile)) @unlink($preFile);
            }
        } finally {
            if (file_exists($originalImg)) @unlink($originalImg);
            if (is_dir($tmpDir)) @rmdir($tmpDir);
        }
    
        if (!empty($t_vote)) {
            $counts = array_count_values($t_vote);
            arsort($counts); 
            $t_text = (string)key($counts); 
            #Logger::X('ok', "OCR: $t_text (" . reset($counts) . "/" . count($t_vote) . ")");
            return $t_text;
        }
    
        return null;
    });

}

class FaucetCaptcha {
    private static $dataCAP;
    
    private static $host;
    private static $reff;
    private static $mail;
    
    public static function exec($host, $reff, $mail) {
        
        self::$host = $host;
        self::$reff = $reff;
        self::$mail = $mail;
        self::$dataCAP = null;
        
        return styler("faucetcaptcha", function() use ($host, $reff) {
            _sle(5);
            $data_fc = json_decode(Net::C("$host//api/api.php?action=challenge", 'GET', null, Inf::$cookie, [], "$reff", Inf::$uagent)?: '', 1);
            #var_dump($data_fc); #die;
            if (!empty($data_fc) && isset($data_fc['dom'])) {
                $dataCAP = null;
                _sle(5);
                $id = $data_fc['challenge_id'];
                $dt = $data_fc['dom'];
                
                $setP = microtime(true);
                
                $fp_token = $dt['headerFpToken'];
                $fp_cnfig = json_decode($dt['configJson'], 1);
                $fp_scttr = $dt['scatterHtml'];
                
                $ids = $fp_cnfig['scatterIds'];
                $enc = $fp_cnfig['enc'];
        
                $ikm = hash('sha256', implode('|', $ids), true);
                $key = hash_hkdf('sha256', $ikm, 32, 'aes-gcm-key', 'fc-config-v2');
                $config = json_decode(
                    openssl_decrypt(
                        substr(base64_decode($enc), 12, -16),
                        'aes-256-gcm',
                        $key,
                        OPENSSL_RAW_DATA,
                        substr(base64_decode($enc), 0, 12),
                        substr(base64_decode($enc), -16))?: ''
                , 1);
                
                if (!empty($config)) {
                    $hsh_sc = self::_schh();
                    $pub_key = self::_sct($fp_scttr, $ids);
                    
                    $pow_mod = self::_dec($config['powVariantToken'], $config['nonce'], $config['challenge']);
                    $pow_res = self::_pow($config['challenge'], $config['difficulty'], $pow_mod);
                    
                    if (!$pub_key || !$pow_res) return ['trouble' => 1];
                    
                    self::$dataCAP = [
                        
                        'cid' => $id,
                        'tok' => $fp_token,
                        
                        'hsh' => $hsh_sc,
                        'key' => $pub_key,
                        'pow' => [
                            'mod' => $pow_mod,
                            'res' => $pow_res,
                        ],
                        
                        'end' => (int)((microtime(true) - $setP) * 1000)
                        
                    ];
                    
                    return self::token($config);
                }
                
            }
            
            return ['trouble' => 1];
        });
    
    }
    
    public static function token($config) {
        
        $sign_res = self::_sign($config['signEndpoint']);
        if (!empty($sign_res)) {
            $solution = self::_verf($config, $sign_res);
            if (!$solution || $solution === 44) return 44;
            $fc_fi = $config['payloadField'] ?? 'f_' . substr(md5(self::$dataCAP['cid']), 0, 12);
            
            return [
                "captcha" => "faucetcaptcha",
                "$fc_fi" => $solution['sol'],
                'fc_token' => $solution['tkn'],
                'fc_challenge_id' => self::$dataCAP['cid']
            ];
            
        }
        return ['trouble' => 1];
        
    }

    private static function _dec($token, $nonce, $task) {
        $ikm = hash('sha256', $nonce . '|' . $task, true);
        $key = hash_hkdf('sha256', $ikm, 32, 'variant-enc', 'fc-pow-v1');
        
        $b64 = str_replace(['-', '_'], ['+', '/'], $token);
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        $pt = openssl_decrypt(
            substr(base64_decode($b64), 12, -16),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            substr(base64_decode($b64), 0, 12),
            substr(base64_decode($b64), -16)
        );
        
        $valid = ['sha256-prefix', 'sha256-suffix', 'double-hash', 'interleaved', 'xor-target', 'hmac-prefix'];
        
        if ($pt && in_array($pt, $valid)) return $pt;
        
        return 'sha256-prefix';
    }

    private static function _sct($html, $ids) {
        
        $p1 = null;
        $com = Scraper::_jP($html, '/<!--[^>]*' . preg_quote($ids[0], '/') . ':([A-Za-z0-9+\/=]+):[^>]*-->/');
        if (!empty($com[1][0])) $p1 = $com[1][0];
        
        $p2 = null;
        $found = Scraper::find($html, $ids[1], 'span', 'data-' . $ids[1], 'id');
        if ($found) $p2 = $found[0];
        
        $p3 = null;
        $found = Scraper::find($html, $ids[2], 'input', 'value', 'id');
        if ($found) {
            $decoded = base64_decode($found[0]);
            if ($decoded !== false) $p3 = $decoded;
        }
        
        $p4 = null;
        $sty = Scraper::_jP($html, '/--d\s*:\s*["\']?([^;"\'"]+)["\']?/');
        if (!empty($sty[1][0])) $p4 = trim($sty[1][0]);
        
        if (!$p1 || !$p2 || !$p3 || !$p4) return null;
        
        return hash('sha256', "{$p1}{$p2}{$p3}{$p4}");
    }

    private static function _pow($task, $difficulty, $variant) {
        $target = str_repeat('0', $difficulty);
        $nonce = 0;
        
        while ($nonce < 1000000) {
            $nstr = base_convert($nonce, 10, 36);
            
            $hash = match($variant) {
                'sha256-prefix' => hash('sha256', $task.$nstr),
                'sha256-suffix' => hash('sha256', $task.$nstr),
                'double-hash' => hash('sha256', hash('sha256', $task.$nstr)),
                'interleaved' => hash('sha256', $nstr.substr($task, 0, intdiv(strlen($task), 2)).$nstr . substr($task, intdiv(strlen($task), 2))),
                'xor-target' => hash('sha256', $task.$nstr),
                'hmac-prefix' => hash_hmac('sha256', $nstr, $task),
                default => hash('sha256', $task.$nstr),
            };
            
            $match = match($variant) {
                'sha256-suffix' => str_ends_with($hash, $target),
                default => str_starts_with($hash, $target),
            };
            
            if ($match) {
                return [
                    'nonce' => $nstr,
                    'hash' => $hash,
                ];
            }
            
            $nonce++;
            
        }
        
        return null;
    }

    private static function _schh() {
        return hash(
            'sha256',
            Net::S(self::$host.'/api/captcha.js')
        ) ?? 'f2b1f584738b12d25c2ba882e833a3cab4229ba066a21ac75a345ef17f9e8017';
    }

    private static function _sign($sgn_u) {
        $ua = Inf::$uagent;
        $base = self::$mail.$ua;
        $isMobile = (stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false);
        
        $_env = [
            'webdriver' => false,
            'outerSize' => false,
            'noLanguages' => false,
            'lowConcurrency' => false,
            'lowColorDepth' => false,
            'noFocusOnClick' => false,
            'fakeGPU' => false,
            'permissionAnomaly' => false,
            'highPerfResolution' => false,
            'automationLeaks' => false,
            'missingChrome' => false,
            'hiddenAtLoad' => false,
            'isTouchDevice' => $isMobile,
        ];
        
        $onCnv = substr(base64_encode(md5($base.'canvas') . md5($base.'canvas')), 0, 48);
        $onDlt = $isMobile ? 0 : (abs(crc32($base.'mouse')) % 200) + 50;
        $onBox = (abs(crc32($base.'time')) % 5000) + 5000;
        
        $_bhv = [
            'mouseDelta' => $onDlt,
            'keystrokeCount' => 0,
            'keystrokeVarMs' => 0,
            'timeToCheckbox' => $onBox,
            'scrollEvents' => 0,
            'focusBlurCount' => 3,
            'canvasFp' => $onCnv,
            'pathSamples' => 2,
            'curvatureVar' => -1,
            'accelVar' => -1,
            'hiddenCount' => 4,
            'resizeCount' => 0,
            'hiddenAtLoad' => false,
            'perfDrift' => 0.0003,
            'touchCount' => $isMobile ? 12 : 0,
            'movesBeforeClick' => 0,
        ];
        
        $body = [
            'challenge_id' => self::$dataCAP['cid'],
            'envFacts' => $_env,
            'bhv' => $_bhv,
        ];
        
        $sign = json_decode(
            Net::X($sgn_u, 'POST', $body, Inf::$cookie,
            ['X-FC-Sign: 1'],
            self::$reff, Inf::$uagent, json: true)?: ''
        , 1)['sig'] ?? null;
        
        if (!empty($sign)) {
            return [
                'sig' => $sign,
                'envFacts' => $_env,
                'bhv' => $_bhv,
            ];
        }
        
        return null;
    }

    private static function _verf($config, $sign_res) {
        
        $pow_res = self::$dataCAP['pow']['res'];
        
        $ver_u = str_replace('action=sign', 'action=verify', $config['signEndpoint']);
        
        $payloadData = [
            'nonce' => $config['nonce'],
            'powNonce' => $pow_res['nonce'],
            'solveMs' => self::$dataCAP['end'],
            'envFacts' => $sign_res['envFacts'],
            'pubKeyHash' => self::$dataCAP['key'],
            'ecSig' => $config['ecSig'] ?? '',
            'bhv' => $sign_res['bhv'],
            'telemetrySig' => $sign_res['sig'],
            'scriptHash' => self::$dataCAP['hsh'],
            'headerFpToken' => self::$dataCAP['tok'],
        ];
        
        $payload = base64_encode(json_encode($payloadData));
        
        $body = [
            'challenge_id' => self::$dataCAP['cid'],
            'payload' => $payload,
            'honeypots' => new stdClass(),
        ];
        
        $sol = json_decode(
            Net::X($ver_u, 'POST', $body, Inf::$cookie,
            ['X-FC-Sign: 1'],
            self::$reff, Inf::$uagent, json: true)?: ''
        , 1);
        #var_dump($sol); die;
        
        if (!empty($sol['success']) || !empty($sol['token'])) {
            return ['tkn' => $sol['token'], 'sol' => $payload];
        }
        if (!empty($sol['message']) && str_contains($sol['message'], 'verification failed')) return 44;
        return null;
        
    }
    
}
