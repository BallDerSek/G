<?php

return (new class {
    
    use Base;
    
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://tronblow.site';
    private string $r = '/?ref=gamamoch%40gmail.com';
    
    private bool $claim = true;
    
    public function __construct() {
        $this->acc = Config::credential(['ua' => fn() => Config::uagent()], 0, ['PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        $this->_init();
        
        Proxy::load();
        Check::Geo();
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', "auto multi+batch");
        $b->task2('info', "total: ".count($this->ctx)." email");
        
    }
    
    public function exec() {
        $claimed = false;
        
        while (true) {
            $calls = [];
            $keys = [];
            #var_dump($this->ctx);
            
            if (!$this->claim) {
                Proxy::load();
                Check::Geo();
            }
            
            
            if (!empty($this->ctx)) {
                if ($claimed) styler("waiting for next claim", fn() => _sle(80));
                
                foreach ($this->ctx as $id => $ak) {
                    $keys[] = [
                        'idx' => $id,
                        'coo' => $ak['path']
                    ];
                    $calls[] = [
                        $this->host.$this->r, 'GET',
                        null, $ak['path'], [],
                        $this->host.$this->r, $this->acc['ua'],
                        null, false, $this->acc['PROXY']
                    ];
                    
                }
                
            }
            
            if (!empty($calls)) {
                #var_dump($keys, $calls);
                $_0 = styler("Preparing " . count($calls) . " payloads", function() use ($calls) {
                    return Mux::C(...$calls);
                });
                
            } else {
                styler("waiting for stable connection", fn() => _sle(60));
                continue;
            }
            
            $postCalls = [];
            $postKeys = [];
            
            foreach ($_0 as $j => $html) {
                
                if (!empty($html) && $html !== 99) {
                    $info = $keys[$j];
                    $f = Scraper::payload($html)[0] ?? null;
                    #var_dump($f); die;
                
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        if (isset($pa['math_answer'])) {
                            $pa['math_answer'] = SolveUtils::math($pa['math_q1'], $pa['math_q2'], $pa['math_op']);
                            $pa['email'] = $this->ctx[$info['idx']]['mail'];
                        }
                        $postKeys[] = $info;
                        $postCalls[] = [
                            $this->host, 'POST', $pa,
                            $info['coo'], [],
                            $this->host.$this->r, $this->acc['ua'],
                            null, false, $this->acc['PROXY']
                        ];
                    } else @unlink($info['coo']);
                    
                }
                
            }
            
            if (!empty($postCalls)) {
                
                $_1 = styler("Claiming " . count($postCalls) . " sites", function() use ($postCalls) {
                    return Mux::C(...$postCalls);
                });
                
                $limit = [];
                
                foreach ($_1 as $k => $res) {
                    $info = $postKeys[$k];
                    $idx = $info['idx'];
                    
                    if (!empty($res) && $res !== 99) {
                        
                        $mf = Scraper::_xP($res, "//div[contains(@class,'alert-success')] | //div[contains(@class,'alert-error')]")[0] ?? null;
                        
                        if (!empty($mf)) {
                            $claimed = true;
                            $msg = strtolower(trim(strip_tags($mf)));
                            
                            $this->logger(
                                'info', 
                                "tronblow", 
                                "[$idx] $msg", 
                                0, 
                                $this->ctx[$idx]['mail']
                            );
                            
                            if ((stripos($msg, 'mit reached') !== false) || stripos($msg, 'aily limit') !== false) $limit[] = $idx;
                            
                        }
                        
                    }
                    
                    @unlink($info['coo']);
                }
                
                if (!empty($limit)) {
                    foreach (array_unique($limit) as $lmt) unset($this->ctx[$lmt]);
                    $this->ctx = array_values($this->ctx);
                }
                
            } else Logger::X('warn', "No forms found.");
            
            if (empty($this->ctx)) {
                
                var_dump(count($this->ctx));
                $this->_init();
                var_dump(count($this->ctx));
                styler("waiting for next claim", fn() => _sle(100));
                $claimed = false;
                
            }
            
        }
        
    }
    
    private function _init() {
        $mailPath = LIBDIR . '/email.txt';
        if (!is_file($mailPath)) die(Logger::X('err', 'email.txt not found, create & fill with ur email list. (perline format) and save to lib folder'));
        
        $emails = file($mailPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($emails as $mail) {
            $this->ctx[] = [
                'mail' => $mail,
                'path' => Config::cookie($mail),
            ];
        }
        
        
        
    }
    
})->exec();