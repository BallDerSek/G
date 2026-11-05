<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://satoshifaucet.io';
    private string $r = '';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    private bool $atbforce = false;
    private int $atbfail = 0;
    
    // Mảng lưu trữ thời gian bị phạt của từng đồng coin
    private array $cooldowns = [];
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], false, ['login', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['login'];
        
        Inf::setup(
            $this->acc['ua'],
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
        $curr = ''; 
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", $this->headersCF, '/auth/login');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    logx('Info', "logged in", false); 
                    _sle(3); _clr();
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                
                $_0 = Net::X($this->host.$this->r, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $this->mail];
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                    }
                }
                
                if ($po) {
                    $ve = Net::X($this->host.'/auth/login', 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent, ip: $this->ip);
                }
                
            } while (empty($dash));
            
            $_fa = Scraper::_xP($dash, "//div[normalize-space()='Faucets']/ancestor::li//div[@class='sub-menu-two']/a/@href");
            if (empty($curr)) shuffle($_fa);
            
            if ($this->claim) {
                foreach ($_fa as $fa) {
                    
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    // KIỂM TRA XEM ĐỒNG COIN NÀY CÓ ĐANG BỊ PHẠT KHÔNG
                    if (isset($this->cooldowns[$_c]) && time() < $this->cooldowns[$_c]) {
                        $timeLeft = $this->cooldowns[$_c] - time();
                        $this->logger('warn', $_c, "Đang bị phạt, chờ {$timeLeft}s...");
                        continue; // Bỏ qua, nhảy sang coin tiếp theo
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::C($fa, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, d: true, ip: $this->ip);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        
                        $fau = $this->checkCF($this->headersCF, $fa, $fau);
                        
                        if ($ban = $this->isBan($fau)) {
                            if (!$this->SLDONE) {
                                $curr = $_c;
                                break;
                            }
                            styler("waiting for unlocked {$ban['tmr']}", fn() => _sle($ban['sleep']));
                            continue;
                        }
                        
                        $po = null;
                        $this->atbforce = $this->atbfail >= 3;
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau, 'fauform')[0] ?? null;
                            
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                $_ca = $pa['captcha'] ?? '';
                                
                                if  (($_ca === 'advanced_captcha') && isset($pa['fc_token'])) {
                                    $fcc = $this->_fc($fau,$fa);
                                    $pa['fc_token'] = $fcc;
                                }
                                
                                $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $this->mail];
                                
                                // KIỂM TRA CAPTCHA ICONORDER
                                if (strpos($fau, 'iconorder') !== false || $_ca === 'iconorder' || strpos($fau, 'Tap the objects') !== false) {
                                    $capResult = $this->solveIconOrder($fau, $fa);
                                    
                                    // NẾU BỊ PHẠT (SERVER TRẢ VỀ THỜI GIAN CHỜ)
                                    if ($capResult && isset($capResult['wait'])) {
                                        $waitTime = $capResult['wait'];
                                        $this->cooldowns[$_c] = time() + $waitTime;
                                        $this->logger('warn', $_c, "Bị phạt Captcha, chờ {$waitTime}s");
                                        break; // Nhảy sang coin tiếp theo
                                    }

                                    if ($capResult && isset($capResult['pass'])) {
                                        $pa['sfz_pass'] = $capResult['pass']; 
                                        if (!empty($capResult['csrf'])) {
                                            $pa['csrf_token_name'] = $capResult['csrf'];
                                        }
                                        $po = array_merge($pa, $cre); 
                                    } else {
                                        _sle(2);
                                        continue; 
                                    }
                                } else {
                                    $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                                    if (isset($cap['trouble'])) continue;
                                    $po = array_merge($pa, $cap, $cre);
                                }
                                
                            } else {
                                if (stripos($fau, 'rate limit') !== false) break;
                                
                                if (str_contains($fau, 'limit for this coin reached')) {
                                    // Cho nghỉ 1 tiếng
                                    $this->cooldowns[$_c] = time() + 1000;
                                    break;
                                }
                            }
                        }
                        
                        if (!empty($po)) {
                            _sle(2);
                            $po['sfz_hp'] = ''; 
                            
                            $claimHeaders = array_merge($this->headersCF, [
                                'Origin: ' . $this->host,
                                'Referer: ' . $fa,
                                'Upgrade-Insecure-Requests: 1'
                            ]);

                            $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $claimHeaders, $fa, Inf::$uagent, ip: $this->ip);
                            
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            _put('claim_debug.html', $cla);
                            $this->checkATB($this->atbfail, $cla);
                            
                            if (stripos($cla, 'rate limited') !== false) {
                                $this->cooldowns[$_c] = time() + 60; // Nghỉ 60s nếu bị rate limit
                                break;
                            }
                            
                            $isSuccess = false;
                            $msg = '';
                            
                            // Bắt thông báo Swal
                            if (preg_match("/Swal\.fire\s*\(\s*\{.*?html\s*:\s*'([^']+)'/s", $cla, $m) || 
                                preg_match("/Swal\.fire\s*\(\s*\{.*?text\s*:\s*'([^']+)'/s", $cla, $m)) {
                                $msg = strip_tags($m[1]);
                            }

                            if (!empty($msg)) {
                                if (preg_match('/sufficient|could not be processed|faucet limit|limit reached/i', $msg)) {
                                    // Thay vì bỏ qua vĩnh viễn, ta cho coin này nghỉ 1 tiếng (3600 giây)
                                    $this->cooldowns[$_c] = time() + 3600; 
                                    $this->logger('err', 'fct', "Limit reached -> Nghỉ 1 tiếng");
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned|cheat/i', $msg)) {
                                    $this->logger('err', 'fct', $msg);
                                    die;
                                }
                                
                                if (preg_match('/went wron|cation failed|invalid captcha/i', $msg)) {
                                    $this->logger('err', 'fct', $msg);
                                    continue 3;
                                }
                                
                                if (preg_match('/sent|success|claimed|received/i', $msg)) {
                                    $this->logger('ok', 'fct', $msg);
                                    $isSuccess = true;
                                }
                            } else {
                                // Nếu không thấy Swal, kiểm tra thô trong HTML
                                if (stripos($cla, 'has been sent') !== false || stripos($cla, 'success') !== false) {
                                    $this->logger('ok', 'fct', 'Reward Claimed! (No Swal found)');
                                    $isSuccess = true;
                                } else {
                                    // KHÔNG DÙNG stripos limit Ở ĐÂY NỮA ĐỂ TRÁNH BẮT NHẦM
                                    $this->logger('err', 'fct', 'Claim failed: Unknown response (Check claim_debug.html)');
                                }
                            }

                            if ($isSuccess) {
                                $this->atbforce = false;
                                $this->atbfail = 0;
                                // Nghỉ 5 giây trước khi sang coin tiếp theo
                                styler("Claim success! Moving to next...", fn() => _sle(40));
                                break; 
                            }
                            
                            styler("waiting for next claim", fn() => _sle(rand(40, 50)));
                        }
                    }
                }
            }
            
            // Nghỉ 10s sau khi quét hết 1 vòng tất cả các coin
            styler("All coins processed. Waiting 10s...", fn() => _sle(10));
        }
    }
    
    // =================================================================
    // HÀM XỬ LÝ CAPTCHA ICONORDER
    // =================================================================
    private function solveIconOrder($html, $fa) {
        $csrf = Scraper::_xP($html, '//form[@id="fauform"]//input[@name="csrf_token_name"]/@value')[0] ?? '';
        if (empty($csrf)) {
            Logger::X('warn', "Không lấy được CSRF token");
            return null;
        }

        $actionUrl = Scraper::_xP($html, '//form[@id="fauform"]/@action')[0] ?? '';
        $faucet = basename(parse_url($actionUrl, PHP_URL_PATH)) ?: 'trump';

        $ajaxHeaders = array_merge($this->headersCF, [
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json, text/javascript, */*; q=0.01'
        ]);

        $challengeUrl = "{$this->host}/cosmos/challenge/faucet_{$faucet}?_=" . round(microtime(true) * 1000);
        $chRes = Net::C($challengeUrl, 'GET', null, Inf::$cookie, $ajaxHeaders, $fa, Inf::$uagent, ip: $this->ip);
        
        $chData = json_decode($chRes, true);
        
        // NẾU SERVER YÊU CẦU CHỜ (BỊ PHẠT)
        if (isset($chData['wait'])) {
            return ['wait' => (int)$chData['wait']];
        }

        if (empty($chData['img']) || empty($chData['token'])) {
            Logger::X('warn', "Không tải được ảnh Captcha");
            return null;
        }

        $imgB64 = $chData['img'];
        if (strpos($imgB64, 'base64,') !== false) {
            $imgB64 = explode('base64,', $imgB64, 2)[1];
        }

        $capToken = $chData['token'];

        if (is_string($this->api)) $provider = Api::use('tertuyul', $this->api);
        elseif (is_object($this->api)) $provider = $this->api;
        else return null;

        $result = $provider->base64($imgB64, 'iconorder');
        if (isset($result['fail']) || empty($result['done'])) {
            Logger::X('err', "Lỗi giải captcha iconorder: " . ($result['fail'] ?? 'Empty result'));
            return null;
        }

        $rawPoints = $result['done']; 
        $ptsArray = [];
        $parts = explode(';', $rawPoints);
        foreach ($parts as $part) {
            if (preg_match('/x\s*=\s*(\d+)\s*,\s*y\s*=\s*(\d+)/i', trim($part), $m)) {
                $ptsArray[] = $m[1] . ',' . $m[2];
            }
        }

        if (empty($ptsArray)) {
            Logger::X('err', "Không thể parse tọa độ");
            return null;
        }

        $formattedPoints = implode(';', $ptsArray);

        $checkUrl = "{$this->host}/cosmos/check/faucet_{$faucet}";
        $postData = http_build_query([
            'csrf_token_name' => $csrf,
            'token'           => $capToken,
            'hp'              => '', 
            'pts'             => $formattedPoints 
        ]);
        
        $ckHeaders = array_merge($ajaxHeaders, ['Content-Type: application/x-www-form-urlencoded']);
        $ckRes = Net::C($checkUrl, 'POST', $postData, Inf::$cookie, $ckHeaders, $fa, Inf::$uagent, ip: $this->ip);
        
        $ckData = json_decode($ckRes, true);
        
        if (!empty($ckData['pass'])) {
            Logger::X('info', "Lấy Pass Token thành công!", 1, 1);
            return [
                'pass' => $ckData['pass'],
                'csrf' => $ckData['csrf'] ?? null
            ];
        } else {
            Logger::X('err', "Server từ chối tọa độ: " . $ckRes);
            return null;
        }
    }

    private function parseShortL($ud, $sl) { /* ... Giữ nguyên ... */ }
    private function _fc($html, $reff) { /* ... Giữ nguyên ... */ }
    
})->exec();

function cnvs($_e, $_u) {
    return base_convert(abs(crc32("$_e|$_u")), 10, 36);
}
