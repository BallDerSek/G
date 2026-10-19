<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://nyxtap.com';
    private string $r = '';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    
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
        
        $habis = [];
        $curr = '';
        $skipped = [];
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}", $this->headersCF, 'Create a free FaucetPay wallet');
                if ($l['ok']) {
                    $dash = $l['html'];
                    logx('Info', "logged in", false); 
                    _sle(3); _clr();
                    #var_dump($dash); die;
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                
                $_0 = Net::X($this->host.'/login', 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['return' => 'dashboard', 'email' => $this->mail];
                        #$cap = $this->_cp($_0);
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                    #_put('ve.html', $ve); 
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash);
            
            $_fa = Scraper::_xP($dash, '//a[@class="faucet-card"]/@href');
            #print_r($_fa);
            
            if ($this->claim) {
                
               foreach ($_fa as $fa) {
                    
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::C($fa, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, d: true);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        
                        $fau = $this->checkCF($this->headersCF, $fa, $fau);
                        #_put('fau.html', $fau);
                        
                        $po = null;
                        if (!empty($fau) && $fau !== 99) {
                            
                            $frmC = [
                                'csrf' => Scraper::_var($fau, 'csrf'),
                                'pnxc' => stripslashes(Scraper::_var($fau, 'api')?: ''),
                                'coin' => Scraper::_var($fau, 'coin'),
                            ];
                            
                            if (in_array(null, $frmC, true)) {
                                
                                $this->logger('err', "unknown error", 'undetected some parameter');
                                continue;
                                
                            }
                            
                            $po = $this->nyxCap($frmC, $fa);
                            #var_dump($po); die;
                        }
                        
                        if (!empty($po)) {
                            
                            $cla = json_decode(($po)?: '', 1);
                            
                            if (!empty($cla)) {
                                
                                $stt = $cla['success'] ? 'ok' : 'err';
                                
                                $msg = (($cla['data']['coin_amount'] ?? '').' '.($cla['data']['coin'] ?? '').' '.($cla['message'] ?? '')) ?? 'unknown response';
                                
                                $this->logger($stt, 'fct', $msg);
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg)) $habis[$fa] = true;
                                
                                if (preg_match('/blacklist|flagged|banned|nti fraud/i', $msg)) die;
                                
                            }
                            
                            
                        }
                        
                        styler("waiting for next claim", fn() => _sle(110));
                        break;
                    }
               }
            }
            
        }
        
        
        
        
        
        
        
    }
    
    private function nyxSolve($data) {
        static $db = null;
        $prompt = $data['challenge']['prompt'] ?? '';
        #var_dump($prompt);
        if (!empty($prompt)) {
            if ($db === null) $db = json_decode(_get(LIBDIR . '/nyx.json'), true);
            
            $ename = null;
            foreach ($db['map'] as $emoji => $name) {
                if (mb_strpos($prompt, $emoji) !== false) {
                    $ename = $name;
                    break;
                }
            }
            #var_dump($ename);
            
            if ($ename && isset($db['hashes'][$ename])) {
                $bestId = null;
                $bestScore = PHP_INT_MAX;
                $bestImageB64 = null;
                
                foreach ($data['challenge']['tiles'] as $tile) {
                    $id = $tile['id'];
                    $b64 = $tile['image'];
                    
                    if (($pos = strpos($b64, ',')) !== false) $cleanB64 = substr($b64, $pos + 1);
                    else $cleanB64 = $b64;
                    
                    $binary = base64_decode($cleanB64);
                    if ($binary === false) continue;
            
                    $pHash = SolveUtils::pHash($binary);
                    $aHash = SolveUtils::aHash($binary);
                    $dHash = SolveUtils::dHash($binary);
            
                    if (!$pHash || !$aHash || !$dHash) continue;
                    
                    foreach ($db['hashes'][$ename] as $key => $hashes) {
                        $score = SolveUtils::hamming($pHash, $hashes['p']) + SolveUtils::hamming($aHash, $hashes['a']) + SolveUtils::hamming($dHash, $hashes['d']);
                        
                        if ($score < $bestScore) {
                            $bestScore = $score;
                            $bestId = $id;
                            $bestImageB64 = $cleanB64;
                        }
                    }
                }
                
                
                if ($bestId !== null && $bestImageB64 !== null) {
                    
                    $imageData = base64_decode($bestImageB64);
                    #if ($imageData !== false) _put("{$ename}.png", $imageData);
                    return $bestId;
                }
                
            }
        }
        
        return null;
    }
    
    private function extractDynamicSecretKey(string $html): ?string {
        if (preg_match_all('/[a-zA-Z0-9_]+\s*=\s*\[(?:\s*\[\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\]\s*,?)+\s*\]/', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $arrayStr = $match[0];
                $pairs = [];
                
                if (preg_match_all('/\[\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\]/', $arrayStr, $triplets, PREG_SET_ORDER)) {
                    foreach ($triplets as $t) {
                        $idx = (int)$t[1];
                        $val = (((int)$t[2]) ^ ((int)$t[3])) & 0xFF;
                        $pairs[$idx] = chr($val);
                    }
                    
                    if (count($pairs) === 16) {
                        ksort($pairs);
                        $key = implode('', $pairs);
                        if (preg_match('/^[0-9a-f]{16}$/i', $key)) {
                            return $key;
                        }
                    }
                }
            }
        }
        return null;
    }

    private function fnv1a_32(string $data, int $seed): string {
        $h = $seed;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $h ^= ord($data[$i]);
            $h = ($h * 16777619) & 0xFFFFFFFF;
        }
        return sprintf("%08x", $h);
    }

    private function generateAntibotSig(string $secretKey, string $sigNonce, int $eventMask = 2): string {
        $payloadStr = $secretKey . "|" . $sigNonce . "|" . $eventMask;
        $hash1 = $this->fnv1a_32($payloadStr, 2166136261);
        $hash2 = $this->fnv1a_32($payloadStr, 84696351);
        return $hash1 . $hash2;
    }

    private function nyxCap($param, $reff) {
        _sle(3);
        
        $_00 = json_decode(Net::X(
            $param['pnxc'].'claim', 'POST',
            array_merge($param, ['website' => '']),
            Inf::$cookie, $this->headersCF, $reff, Inf::$uagent
        )?: '', 1)['data'] ?? null;
        
        $form = null;
        $htmlContent = '';
        
        if (!empty($_00)) {
            if (($_00['redirect'] ?? false) && $_00['solve_url']) {
                $htmlContent = Net::C($_00['solve_url'], 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                #_put('plyn.html', $htmlContent); die;
                
                $TIER = Scraper::_var($htmlContent, 'TIER');
                if ($TIER != 'standard') $this->logger('err', "captcha changed", "USED $TIER", true);
                
                if (!empty($htmlContent) && $htmlContent !== 99) {
                    $form = [
                        'csrf_token' => Scraper::_var($htmlContent, 'CSRF'),
                        'base' => stripslashes(Scraper::_var($htmlContent, 'BASE') ?: ''),
                    ];
                }
            } else {
                $this->logger('err', "error", 'unknown format or response changed');
                return null;
            }
        }
        
        $ans = null;
        $sigNonce = null;
        $secretKey = null;
        
        if ($form) {
            $secretKey = $this->extractDynamicSecretKey($htmlContent);
            
            if (!$secretKey) {
                $this->logger('err', "error", 'Failed to extract valid dynamic secret key from HTML');
                return null;
            }

            preg_match('/var\s+HOLD_NONCE\s*=\s*"([a-f0-9]+)";/i', $htmlContent, $holdNonceMatch);
            $holdNonce = $holdNonceMatch[1] ?? null;

            if ($holdNonce) {
                $holdSig = $this->generateAntibotSig($secretKey, $holdNonce, 1);
                _sle(5);
                
                $holdResp = json_decode(Net::X(
                    $form['base'].'api/captcha', 'POST',
                    array_merge($form, [
                        'action' => 'hold',
                        'antibot_sig' => $holdSig
                    ]),
                    Inf::$cookie, $this->headersCF, $param['pnxc'], Inf::$uagent
                )?: '', 1);
                
                if (!($holdResp['success'] ?? false)) {
                    $this->logger('err', "error", 'Hold step failed: ' . ($holdResp['message'] ?? 'unknown'));
                    return null;
                }
            }
            
            $_get = json_decode(Net::X(
                $form['base'].'api/captcha', 'POST',
                array_merge($form, ['action' => 'challenge']),
                Inf::$cookie, $this->headersCF, $param['pnxc'], Inf::$uagent
            )?: '', 1);
            
            if (($_get['success'] ?? false) && !empty($_get['data'])) {
                $sigNonce = $_get['data']['challenge']['k']?? '';
                
                $ans = $this->nyxSolve($_get['data']);
            } else {
                $this->logger('err', "error", 'Challenge failed: ' . ($_get['message'] ?? 'unknown'));
                return null;
            }
        }
        
        $ccver = null;
        if ($ans && $sigNonce !== null && $secretKey !== null) {
            $antibot_sig = $this->generateAntibotSig($secretKey, $sigNonce, 3);
            
            $realistic_ms = (string)(int)(microtime(true) * 1000);

            $_ver = json_decode(Net::X(
                $form['base'].'api/captcha', 'POST',
                array_merge($form, [
                    'action' => 'verify', 
                    'antibot_order[]' => (string)$ans,
                    'antibot_click_ms[]' => $realistic_ms,
                    'antibot_sig' => $antibot_sig
                ]),
                Inf::$cookie, $this->headersCF, $param['pnxc'], Inf::$uagent
            )?: '', 1);
            
            #var_dump("Verify Response:", $_ver); #die; 
            
            if (($_ver['success'] ?? false) && !empty($_ver['data']['next_url'])) {
                $rdr = stripslashes($_ver['data']['next_url'] ?: '');
                $ccver = Net::C($rdr, 'GET', null, Inf::$cookie, $this->headersCF, $param['pnxc'], Inf::$uagent);
            } else {
                $this->logger('err', "error", 'Verify failed: ' . ($_ver['message'] ?? 'unknown'));
            }
        }
        
        if (!empty($ccver) && $ccver !== 99) {
            #_put('ccver.html', $ccver);
            
            $dataGoArr = Scraper::_xP($ccver, "//a[@data-go]/@data-go");
            $rdr = $dataGoArr[0] ?? '';
            
            $query = parse_url($rdr, PHP_URL_QUERY);
            $_p0 = [];
            if ($query) parse_str(html_entity_decode($query), $_p0);
            
            $_p1 = $param;
            
            $payload = [
                'csrf' => (Scraper::_var($ccver, 'csrf') ?? $_p1['csrf']),
                'sub_id' => (Scraper::_var($ccver, 'subId') ?? $_p0['sub_id']),
                'token' => (Scraper::_var($ccver, 'rtToken') ?? $_p0['token']),
                'status' => 'success',
            ];
            
            if (in_array(null, $payload, true)) {
                $this->logger('err', "error", 'Final payload missing required fields');
                return null;
            }
            
            $_01 = Net::X($_p1['pnxc'].'captcha-claim', 'POST', $payload, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
            
            if (!empty($_01) && $_01 !== 99) {
                return $_01;
            }
        }
        
        return null;
    }


})->exec();



