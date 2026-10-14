<?php

return (new class {
    
    use Base, WorkDir;
    
    private $api;
    private $banner;
    private array $ctx = [];
    private string $jsonPath;
    
    private string $host = 'https://mix-crypto.com';
    private string $r = '/?r=gamamoch@gmail.com';
    private array $curr = ['/litecoin'];
    private string $ip = '';
    
    private int $size = 20;
    
    public function __construct() {
        $this->api = onKeys();
        $this->jsonPath = __DIR__ . '/mail.json';
        
        $this->_init();
        
        Proxy::load();
        Check::Geo();
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', "claiming multi-account");
        $b->task2('info', "total: ".count($this->ctx)." email");
    }
    
    public function exec() {
        $activeAccounts = $this->ctx;
        
        while (!empty($activeAccounts)) {
            Logger::X('info', "Active accounts remaining: " . count($activeAccounts), 1, 1);
            
            $batches = array_chunk($activeAccounts, $this->size, true);
            
            foreach ($batches as $batch) {
                foreach ($this->curr as $coin) {
                    $prep_queue = [];
                    
                    foreach ($batch as $idx => $account) {
                        $mail = $account['mail'];
                        $proxy = $account['proxy'] ?? null;
                        $_ua = Config::uagent('mobile');
                        $_ck = Config::cookie($mail);
                        $faucetUrl = $this->host . $coin . $this->r;
                        
                        $prep_queue[$mail] = [
                            $faucetUrl, 'GET', null, $_ck, [], 
                            $this->host, $_ua, $this->ip, 
                            false, $proxy, $idx, $coin
                        ];
                    }
                    
                    $pages = styler("Fetching pages for " . count($prep_queue) . " accounts", function() use ($prep_queue) {
                        return Mux::K($prep_queue);
                    });
                    
                    $sharedCap = null;
                    $multi_calls = [];
                    $accountsToRemove = [];
                    
                    foreach ($pages as $mail => $html) {
                        $data = $prep_queue[$mail] ?? null;
                        if (!$data) continue;
                        
                        $idx = $data[10];
                        $coinName = $data[11];
                        $proxy = $data[9];
                        $_ua = $data[6];
                        $_ck = $data[3];
                        $faucetUrl = $data[0];
                        
                        if (empty($html) || $html === 99) {
                            $this->logger('warn', 'mix-crypto', 'Empty page response', 0, $mail);
                            continue;
                        }
                        
                        $forms = Scraper::payload($html)[0] ?? null;
                        if (empty($forms)) {
                            $this->logger('warn', 'mix-crypto', 'Form not found', 0, $mail);
                            continue;
                        }
                        
                        $pa = $forms['payload'];
                        $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                        $pa[$walletKey] = $mail;
                        
                        if ($sharedCap === null) {
                            #$sharedCap = $this->_cp($html);
                            $sharedCap = Solve::exec($html, $this->host, $this->api, $pa);
                        }
                        
                        if (empty($sharedCap) || isset($sharedCap['trouble'])) {
                            $this->logger('warn', 'mix-crypto', 'Captcha trouble', 0, $mail);
                            continue;
                        }
                        
                        $po = array_merge($pa, $sharedCap);
                        $postUrl = (strpos($forms['url'], 'http') === 0) ? $forms['url'] : $this->host . $forms['url'];
                        
                        $multi_calls[$mail] = [
                            $postUrl, 'POST', $po, $_ck, [], 
                            $faucetUrl, $_ua, $this->ip, 
                            false, $proxy, $idx, $coinName
                        ];
                    }
                    
                    if (!empty($multi_calls)) {
                        $results = styler("Claiming for " . count($multi_calls) . " accounts", function() use ($multi_calls) {
                            return Mux::K($multi_calls);
                        });
                        
                        foreach ($results as $mail => $cla) {
                            $data = $multi_calls[$mail] ?? null;
                            if (!$data) continue;
                            
                            $idx = $data[10];
                            $coinName = $data[11];
                            
                            if (empty($cla)) {
                                $this->logger('warn', 'mix-crypto', 'Empty response on blast', 0, $mail);
                                continue;
                            }
                            
                            $_suc = Scraper::_xP($cla, "//div[contains(@class, 'alert')]");
                            if (isset($_suc[0])) {
                                $msg = trim(str_replace('×', '', $_suc[0]));
                                $lowMsg = strtolower($msg);
                                $stt = (stripos($lowMsg, 'sent') !== false) ? 'ok' : 'err';
                                
                                $this->logger($stt, 'mix-crypto', "$msg | $coinName", 0, $mail);
                                
                                if (stripos($lowMsg, 'blacklisted') !== false || 
                                    stripos($lowMsg, 'blocked') !== false || 
                                    stripos($lowMsg, 'access denied') !== false ||
                                    stripos($lowMsg, 'claim limit') !== false || 
                                    stripos($lowMsg, 'sufficient') !== false || 
                                    stripos($lowMsg, 'safety') !== false) {
                                    
                                    $accountsToRemove[$idx] = $mail;
                                }
                            } else {
                                $this->logger('err', 'mix-crypto', "No alert found in response", 0, $mail);
                            }
                        }
                    }
                    
                    foreach ($accountsToRemove as $idx => $mail) {
                        $this->logger('info', str_pad('REMOVED', 24), 'Account blocked or limited, skipping', 0, $mail);
                        unset($activeAccounts[$idx]);
                    }
                    
                    $activeAccounts = array_values($activeAccounts);
                }
                
                if (!empty($activeAccounts)) {
                    styler("waiting for next round", fn() => _sle(30));
                }
            }
        }
        
        Logger::X('info', 'All accounts reached limit, blocked, or finished.', 1, 1);
    }
    
    private function _init() {
        $accounts = [];
        if (file_exists($this->jsonPath)) {
            $content = _get($this->jsonPath);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) $accounts = $decoded;
        }

        $count = count($accounts);
        Logger::X('info', "Detected {$count} account(s) in mail.json", 1, 1);
        
        if ($count == 0) {
            Logger::X('err', "No accounts found. Initializing new list.", 1, 1);
            $this->_addAccounts($accounts);
        } elseif ($count > 0) {
            Logger::X('warn', "\n[C]ontinue, [E]dit (Add/Update), [R]eset (Clear all)", 1, 1);
        }
        $action = strtoupper(trim(_rl("Choose action: ")));

        if ($action === 'R') {
            $accounts = [];
            $this->_addAccounts($accounts);
        } elseif ($action === 'E' || $count === 0) {
            $this->_addAccounts($accounts);
        }

        foreach ($accounts as $acc) {
            if (!empty($acc['mail'])) {
                $this->ctx[] = [
                    'mail' => $acc['mail'],
                    'proxy' => $acc['proxy'] ?? null
                ];
            }
        }

        if (empty($this->ctx)) {
            die(Logger::X('err', 'No valid accounts loaded. Exiting.'));
        }
    }
    
    private function _addAccounts(array &$accounts) {
        
        Logger::X('info', "Enter details (type 'done' as email to finish):", 1, 1);
        while (true) {
            $mail = trim(_rl("Email: "));
            if (strtolower($mail) === 'done') break;
            if (empty($mail)) continue;

            $proxy = trim(_rl("Proxy (e.g., http://user:pass@ip:port, or 'none'): "));
            
            $proxyVal = (strtolower($proxy) === 'none' || empty($proxy)) ? null : $proxy;

            $found = false;
            foreach ($accounts as &$acc) {
                if ($acc['mail'] === $mail) {
                    $acc['proxy'] = $proxyVal;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $accounts[] = ['mail' => $mail, 'proxy' => $proxyVal];
            }
            
            _put($this->jsonPath, json_encode($accounts, JSON_PRETTY_PRINT));
            Logger::X('ok', "Saved", 1, 1);
        }
    }
    
})->exec();