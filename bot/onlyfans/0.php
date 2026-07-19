<?php

class onlyfans {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://onlyfaucet.com';
    private string $r = '/?r=125290';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    
    // Tambahkan property untuk menyimpan fingerprint
    private array $fingerprintData = [];
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], false, ['login', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['login'];
        
        // Generate fingerprint random sebelum setup
        $this->generateRandomFingerprint();
        
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
    
    /**
     * DJB2 hash function
     */
    private function djb2($str) {
        $hash = 5381;
        for ($i = (strlen($str) - 1); $i >= 0; $i--) {
            $hash = ((($hash * 33) & 0xFFFFFFFF) ^ ord($str[$i])) & 0xFFFFFFFF;
        }
        $sign = sprintf('%u', $hash & 0xFFFFFFFF);
        return base_convert($sign, 10, 16);
    }
    
    /**
     * Generate random fingerprint data
     */
    private function generateRandomFingerprint(): void {
        $isMobile = (rand(0, 1) === 1);
        
        // Random screen dimensions
        $screenWidths = [360, 375, 390, 393, 414, 428, 768, 820, 834, 1024, 1280, 1366, 1440, 1536, 1600, 1920];
        $screenHeights = [664, 667, 844, 852, 896, 926, 1024, 1180, 1194, 1366, 1440, 1600, 1080, 1200, 1050, 1080];
        
        $sw = $screenWidths[array_rand($screenWidths)];
        $sh = $screenHeights[array_rand($screenHeights)];
        
        if ($isMobile) {
            $sw = min($sw, 428);
            $sh = min($sh, 926);
        }
        
        // Random GL renderer
        $glRenderers = [
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3060, OpenGL 4.5)',
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3070, OpenGL 4.5)',
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3080, OpenGL 4.6)',
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3090, OpenGL 4.6)',
            'ANGLE (AMD, AMD Radeon RX 6800, OpenGL 4.6)',
            'ANGLE (AMD, AMD Radeon RX 6900 XT, OpenGL 4.6)',
            'ANGLE (Intel, Intel Iris Xe Graphics, OpenGL 4.6)',
            'ANGLE (ARM, Mali-G57, OpenGL ES 3.2)',
            'ANGLE (ARM, Mali-G78, OpenGL ES 3.2)',
            'ANGLE (Apple, Apple M1, OpenGL 4.1)',
            'ANGLE (Apple, Apple M2, OpenGL 4.1)'
        ];
        $selectedGl = $glRenderers[array_rand($glRenderers)];
        
        $this->fingerprintData = [
            'isMobile' => $isMobile,
            'screenWidth' => $sw,
            'screenHeight' => $sh,
            'innerWidth' => $isMobile ? $sw : $sw - rand(0, 100),
            'innerHeight' => $isMobile ? $sh - rand(50, 150) : $sh - rand(50, 200),
            'glRenderer' => $selectedGl,
            'hardwareHash' => $this->generateHardwareHash($isMobile, $sw, $sh, $selectedGl),
            'webdriver' => 0,
            'timezone' => $this->getRandomTimezone(),
            'languages' => $this->getRandomLanguages(),
            'platform' => $isMobile ? 'Linux armv8l' : 'Win32',
            'deviceMemory' => [4, 8, 16, 32][array_rand([4, 8, 16, 32])],
            'hardwareConcurrency' => [4, 6, 8, 10, 12, 16][array_rand([4, 6, 8, 10, 12, 16])]
        ];
        
        Logger::X('info', "Fingerprint generated: " . json_encode([
            'mobile' => $isMobile,
            'screen' => "{$sw}x{$sh}",
            'gl' => $this->fingerprintData['glRenderer']
        ]));
    }
    
    /**
     * Generate hardware hash
     */
    private function generateHardwareHash($isMobile, $sw, $sh, $gl): string {
        $hwDetails = [
            'gl' => $gl,
            'sw' => $sw,
            'sh' => $sh,
            'wd' => false,
            'chr' => true,
            'ua' => $this->acc['ua'] ?? Config::uagent('mobile')
        ];
        
        $jsonString = json_encode($hwDetails, JSON_UNESCAPED_SLASHES);
        return $this->djb2($jsonString);
    }
    
    /**
     * Get random timezone
     */
    private function getRandomTimezone(): string {
        $timezones = [
            'America/New_York', 'America/Los_Angeles', 'America/Chicago',
            'Europe/London', 'Europe/Paris', 'Europe/Berlin',
            'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Singapore',
            'Australia/Sydney', 'Australia/Melbourne'
        ];
        return $timezones[array_rand($timezones)];
    }
    
    /**
     * Get random languages
     */
    private function getRandomLanguages(): array {
        $langs = [
            ['en-US', 'en'],
            ['en-GB', 'en'],
            ['en-AU', 'en'],
            ['fr-FR', 'fr'],
            ['de-DE', 'de'],
            ['es-ES', 'es'],
            ['it-IT', 'it'],
            ['pt-PT', 'pt'],
            ['nl-NL', 'nl'],
            ['pl-PL', 'pl'],
            ['ru-RU', 'ru'],
            ['zh-CN', 'zh'],
            ['ja-JP', 'ja'],
            ['ko-KR', 'ko']
        ];
        return $langs[array_rand($langs)];
    }
    
    /**
     * Generate fingerprint payload for requests
     */
    private function getFingerprintPayload(): array {
        return [
            'fingerprint' => [
                'hardware_hash' => $this->fingerprintData['hardwareHash'] ?? '',
                'webdriver' => $this->fingerprintData['webdriver'] ?? 0,
                'screen_width' => $this->fingerprintData['screenWidth'] ?? 1920,
                'screen_height' => $this->fingerprintData['screenHeight'] ?? 1080,
                'inner_width' => $this->fingerprintData['innerWidth'] ?? 1920,
                'inner_height' => $this->fingerprintData['innerHeight'] ?? 1080,
                'timezone' => $this->fingerprintData['timezone'] ?? 'America/New_York',
                'languages' => $this->fingerprintData['languages'] ?? ['en-US', 'en'],
                'platform' => $this->fingerprintData['platform'] ?? 'Win32',
                'device_memory' => $this->fingerprintData['deviceMemory'] ?? 8,
                'hardware_concurrency' => $this->fingerprintData['hardwareConcurrency'] ?? 8,
                'gl_renderer' => $this->fingerprintData['glRenderer'] ?? '',
                'is_mobile' => $this->fingerprintData['isMobile'] ?? false
            ]
        ];
    }
    
    /**
     * onfFPS method
     */
    private function onfFPS($ua, array $mouse, int $waktu) {
        $isMobile = $this->fingerprintData['isMobile'] ?? false;
        $gl = $this->fingerprintData['glRenderer'] ?? 'ANGLE (NVIDIA, NVIDIA GeForce RTX 3060, OpenGL 4.5)';
        $sw = $this->fingerprintData['screenWidth'] ?? 1920;
        $sh = $this->fingerprintData['screenHeight'] ?? 1080;
        
        $raw = [
            'iw' => $this->fingerprintData['innerWidth'] ?? $sw,
            'ih' => $this->fingerprintData['innerHeight'] ?? $sh,
            'gl' => $gl,
            'sw' => $sw,
            'sh' => $sh,
            'wd' => false,
            'chr' => true,
            'ua' => $ua
        ];
    
        $hwDetails = [
            'gl' => $raw['gl'],
            'sw' => $raw['sw'],
            'sh' => $raw['sh'],
            'wd' => $raw['wd'],
            'chr' => $raw['chr'],
            'ua' => $raw['ua']
        ];
        
        $jsonString = json_encode($hwDetails, JSON_UNESCAPED_SLASHES); 
        $hardwareHash = $this->djb2($jsonString); 
    
        $payload = [
            'solve_time_ms' => $waktu,
            'hardware_hash' => $hardwareHash,
            'webdriver' => $this->fingerprintData['webdriver'] ?? 0,
            'mouse_data' => array_values($mouse),
            'raw' => $raw
        ];
    
        return base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Refresh fingerprint saat login
     */
    private function refreshFingerprintOnLogin(): void {
        Logger::X('info', "Regenerating fingerprint for new login session");
        $this->generateRandomFingerprint();
        
        if (!empty($this->headersCF)) {
            $this->headersCF['X-Fingerprint'] = $this->fingerprintData['hardwareHash'] ?? '';
            $this->headersCF['X-Screen-Info'] = "{$this->fingerprintData['screenWidth']}x{$this->fingerprintData['screenHeight']}";
        }
    }
    
    public function exec() {
        $habis = [];
        $curr = 'ltc';
        $skipped = [];
        
        login:
            // Refresh fingerprint setiap kali login ulang
            $this->refreshFingerprintOnLogin();
            
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}", $this->headersCF, '/auth/login');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    logx('Info', "logged in with fingerprint: " . substr($this->fingerprintData['hardwareHash'] ?? 'N/A', 0, 8), false); 
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
                        $cre = ['wallet' => $this->mail];
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                        // Tambahkan fingerprint data ke payload login
                        if (!empty($this->fingerprintData)) {
                            $po['fingerprint'] = json_encode($this->getFingerprintPayload());
                        }
                    }
                }
                
                if ($po) {
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                }
                
            } while (empty($dash));
            
            $_fa = Scraper::_xP($dash, "//ul[@id='faucet']//a/@href");
            if (empty($curr)) shuffle($_fa);
            if ($this->claim) {
                foreach ($_fa as $fa) {
                    
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue 2;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::X($fa, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, d: true);
                        
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
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau, 'fauform')[0] ?? null;
                            
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                
                                $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                                
                                if (isset($cap['nocaptcha']) && isset($pa['captcha_answer'])) $cap = $this->onfCap($fau, $this->host, $fa, $this->api);
                                
                                if (isset($cap['trouble'])) continue;
                                $po = array_merge($pa, $cap);
                                
                            } else {
                                if (str_contains($fau, '/auth/login')) continue 3;
                            }
                            
                        }
                        
                        if (!empty($po)) {
                            $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                            
                            $mf = Scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                            if (!empty($mf[2][0])) {
                                
                                $stt = $mf[1][0];
                                $msg = $mf[2][0];
                                $this->logger($stt, 'fct', $msg);
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                    $habis[$fa] = true;
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned/i', $msg)) {
                                    die;
                                }
                                
                                // INI YANG PENTING - KEMBALI KE LOGIKA ASLI
                                // Jika gagal verifikasi, lanjut ke login (continue 3)
                                if (preg_match('/went wron|cation failed/i', $msg)) {
                                    // Kembali ke login dengan continue 3
                                    continue 3;
                                }
                                
                                if (stripos($msg, 'Shortlink')) {
                                    if ($this->SLDONE) die;
                                    $curr = $_c;
                                    break 2;
                                }
                                
                            }
                            
                            styler("waiting for next claim", fn() => _sle(10));
                        }
                        
                    }
                    
                }
            }
            
            if (count($habis) === count($_fa)) $this->logger('ok', '', 'beres', 1);
            
            $_sl = Scraper::_xP($dash, "//ul[@id='links']//a/@href");
            foreach ($_sl as $sl) {
                $_c = basename($sl);
                if (!empty($curr) && !str_contains($_c, $curr)) continue;
                
                $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
                $ret99 = 0;
                do {
                    $ret99++;
                    $sho = null;
                    $sho = Net::X($sl, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent);
                    if ($sho === 99) {
                        if ($ret99 >= 5) goto login;
                        continue;
                    }
                    $ret99 = 0;
                    
                    $short = Shortlinks::extract($sho);
                    if (empty($short)) continue 3;
                    
                    $success_in_page = false;
                    $found_one = false;
                    
                    foreach ($short as $links => [$idd, $lmt]) {
                        if (!Shortlinks::limit($lmt) || isset($skipped[$idd])) continue;
                        
                        $found_one = true;
                        $loc = $this->parseShortL($idd, $sl);
                        
                        if (!$loc) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        $loc_u = parse_url($loc['url'])['host'] ?? '';
                        $is_bl = false;
                        foreach ($up as $blacklisted) {
                            if (str_contains($loc_u, $blacklisted)) {
                                logx('warn', "Domain $blacklisted Skipping..");
                                $skipped[$idd] = true;
                                $is_bl = true;
                                break; 
                            }
                        }
                        if ($is_bl) continue;
                        
                        $start = microtime(true);
                        $bakk = Shortlinks::exec($this->api, $loc['url']);
                        $wait = 130 - (int)(microtime(true) - $start);
                        
                        if (!$bakk) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                        
                        $retVer = 0;
                        while ($retVer <= 3) {
                            $retVer++;
                            $ver = Net::X($bakk, 'GET', null, Inf::$cookie, $this->headersCF, $loc['url'], Inf::$uagent);
                            
                            if (!empty($ver) && $ver !== 99) {
                                $po = null;
                                $f = Scraper::payload($ver, 'claimForm')[0] ?? null;
                                if (!empty($f)) {
                                    $pa = $f['payload'];
                                    
                                    $cap = Solve::exec($ver, $this->host, $this->api);
                                    $po = array_merge($pa, $cap);
                                    
                                }
                                
                                if (!empty($po)) {
                                    $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                                    
                                    $msh = Scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                                    
                                    if (!empty($msh[2][0])) {
                                        $stt = $msh[1][0];
                                        $msg = $msh[2][0];
                                        $this->logger($stt, 'sho', $msg);
                                        
                                        if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                            $sidx = array_search($sl, $_sl);
                                            if ($sidx !== false && isset($_sl[$sidx + 1])) $curr = basename($_sl[$sidx + 1]);
                                            else $curr = '';
                                        }
                                    }
                                    
                                    if (stripos($cla, 'has been sent')) $success_in_page = true;
                                }
                                
                                $success_in_page = true;
                            }
                        }
                    }
                    if (!$found_one) {
                        $this->logger('err', 'sho', 'SL habis atau sisa blacklist');
                        $this->SLDONE = true;
                        break; 
                    }
                    
                } while (!$success_in_page);
                
                if ($success_in_page || $curr === "") break; 
                
            }
            
        }
    }
    
    private function onfCap($html, $host, $reff) {
        $setCAP = microtime(true);
        $img = null;
        $x_cap = ['ins' => 'ASC', 'cnt' => 3];
    
        $req = Net::X($host.'/faucet/captcha_image?_t=' . (time() * 1000), 'GET', null, Inf::$cookie, [], $reff, Inf::$uagent, d: true);
        
        if (!empty($req) && $req !== 99) {
            $x_pow = [
                'salt' => $req['headers']['x-pow-salt'][0] ?? '',
                'diff' => (int)($req['headers']['x-pow-difficulty'][0] ?? 2)
            ];
            $x_cap = [
                'ins' => $req['headers']['x-captcha-instruction'][0] ?? 'ASC',
                'cnt' => (int)($req['headers']['x-captcha-target-count'][0] ?? 3)
            ];
            $img = $req['body'] ?? null;
        }
        
        if (!empty($img)) {
            if (!AUTH_KEY) $this->logger('err', "unauthorized apikey", 'contact owner', true);
            $solution = Solve::img($this->api, $reff, 'onlyfans', $img);
            
            if (isset($solution['trouble'])) return ['trouble' => 'reload'];
            if (count($solution) < $x_cap['cnt']) return ['trouble' => 'reload']; 
            
            usort($solution, function($a, $b) use ($x_cap) {
                return ($x_cap['ins'] === 'ASC') ? ($a['area'] <=> $b['area']) : ($b['area'] <=> $a['area']);
            });
            
            $clk = array_slice($solution, 0, $x_cap['cnt']);
            
            $mdt = [];
            $ANS = [];
            $setCLK = microtime(true);
            
            foreach ($clk as $index => $obj) {
                $delay = ($index === 0) ? mt_rand(800000, 1200000) : mt_rand(400000, 700000);
                usleep($delay);
                
                $current = (microtime(true) - $setCLK) * 1000;
                
                $x = (int)max(0, min(449, $obj['center'][0]));
                $y = (int)max(0, min(279, $obj['center'][1]));
                
                $ANS[] = "$x,$y";
                $mdt[] = [
                    'x' => $x,
                    'y' => $y,
                    't' => (int)$current
                ];
            }
            $x_ans = implode(';', $ANS);
            
            $waktu = (int)((microtime(true) - $setCAP) * 1000);
            $bfp = $this->onfFPS(Inf::$uagent, $mdt, $waktu);
            $powRes = SolveUtils::Pow($x_pow['salt'], $x_pow['diff']);
            
            return [
                'pow_nonce' => $powRes['nonce'] ?? 0,
                'captcha_answer' => implode(';', $ANS),
                'browser_fingerprint' => $bfp
            ];
        }
        return ['trouble' => 'reload'];
    }
    
    private function parseShortL($ud, $sl) {
        $curr = basename($sl);
        $bon = '----' . md5(mt_rand());
        $token = json_decode(Net::X("{$this->host}/links/get_csrf_token", 'GET', [], Inf::$cookie, [], $sl, Inf::$uagent, true)?: '', 1)['csrf_hash'] ?? null;
        
        if ($token) {
            $payload = [
                'link_id' => $ud,
                'cur' => strtoupper($curr),
                'csrf_token_name' => $token
            ];
            
            $short = json_decode(
                    Net::X("https://onlyfaucet.com/links/verify_go",
                           'POST',
                           SolveUtils::webkitID($payload, $bon),
                           Inf::$cookie, 
                           ["Content-Type: multipart/form-data; boundary=$bon"],
                           $sl,
                           Inf::$uagent)
                    ?: '', 1)['url'] ?? null;
            
            if ($short) return ['url' => $short, 'tkn' => $token];
            
        }
        
        return null;
            
    }
    
}

(new onlyfans())->exec();