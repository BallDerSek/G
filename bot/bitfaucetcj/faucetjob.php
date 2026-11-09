<?php
#_die();
class bitfaucetcj {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    // ĐÃ THAY ĐỔI HOST MỚI
    private string $host = 'https://faucet.cryptojobss.com';
    private string $r = '/';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private array $headersCF = [];
    private array $sleep_coins = []; 
    
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
        while (true) {
            $ret = 0;
            $logged_in = false;
            $dash = null;
            
            // 1. KIỂM TRA ĐĂNG NHẬP
            while (!$logged_in) {
                $ret++;
                $dash = Net::C("{$this->host}/dashboard/", 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                
                // Lỗi mạng
                if (empty($dash) || $dash === 99) {
                    Logger::X('err', "Proxy connection failed. Retrying...");
                    _sle(5);
                    if ($ret > 10) die("Proxy quá kém, dừng tool!");
                    continue;
                }
                
                // Đã đăng nhập thành công
                if (strpos($dash, 'My Dashboard') !== false || strpos($dash, 'logout') !== false) {
                    if ($ret == 1) {
                        logx('Info', "Session OK, already logged in", false); 
                        _sle(2); _clr();
                    }
                    $logged_in = true;
                    break;
                }
                
                // Bị văng ra trang Đăng nhập
                if (strpos($dash, 'Start Claiming') !== false || strpos($dash, 'FaucetPay Email') !== false || strpos($dash, 'id="loginForm"') !== false) {
                    Logger::X('err', "Session expired. Logging in...", false); 
                    _sle(3); _clr();
                    
                    // SỬA LỖI TẠI ĐÂY: Gửi kèm trường 'submit'
                    $po = [
                        'email' => $this->mail,
                        'submit' => '' 
                    ];
                    
                    Net::C($this->host, 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                    _sle(3); 
                    
                    // Nếu thử login quá 3 lần mà vẫn thất bại, lưu HTML để debug
                    if ($ret >= 3) {
                        _put('debug_login_failed.html', $dash);
                    }
                } 
                // Bị Cloudflare hoặc lỗi HTML lạ
                else {
                    Logger::X('err', "Cloudflare or Unknown Page. Retrying...");
                    _put('debug_dash.html', $dash); 
                    _sle(5);
                    if ($ret > 15) die("Bị chặn IP hoặc Cloudflare, dừng tool!");
                }
            }
            
            // 2. DANH SÁCH COIN CẦN CLAIM
            $coins = [
                'eth', 'doge', 'ltc', 'bch', 'dash', 'dgb', 'trx', 'usdt'
            ]; 
            
            // XÁO TRỘN NGẪU NHIÊN THỨ TỰ COIN
            shuffle($coins);
            
            foreach ($coins as $coin) {
                if (isset($this->sleep_coins[$coin]) && time() < $this->sleep_coins[$coin]) {
                    $mins = ceil(($this->sleep_coins[$coin] - time()) / 60);
                    $this->logger('err', 'Sleep', "{$coin} skipped (sleeping for {$mins} more mins)");
                    continue; 
                }
                
                $claim_url = "{$this->host}/extra/{$coin}";
                
                print(FGd['CYN']." ".ITAL."processing {$coin} ");
                Logger::X('err', $coin);
                
                $this->claim_coin($claim_url, $coin);
                
                // NGẪU NHIÊN THỜI GIAN CHỜ TỪ 3 ĐẾN 8 GIÂY
                _sle(rand(3, 8)); 
            }
            
            styler("Waiting for next round", fn() => _sle(300));
        }
    }
    
    private function claim_coin($claim_url, $coin_name) {
        $ret99 = 0;
        while (true) {
            $ret99++;
            if ($ret99 >= 5) return; 
            
            $gen_payload = ['action' => 'generate'];
            $req = json_decode(Net::X("{$this->host}/captcha/cap_da.php", 'POST', $gen_payload, Inf::$cookie, $this->headersCF, $claim_url, Inf::$uagent) ?: '', 1);
            
            if (empty($req) || !isset($req['captcha_id']) || empty($req['image'])) {
                _sle(2); continue;
            }
            
            $captcha_id = $req['captcha_id'];
            $json_string = json_encode($req);
            $base64_payload = base64_encode($json_string);
            
            $solution = Solve::img($this->api, $this->host, 'bfcj_click', $base64_payload);
            
            if (isset($solution['trouble']) || empty($solution)) {
                $err_msg = $solution['trouble'] ?? 'Empty Response';
                Logger::X('err', "Xevil failed: " . $err_msg);
                _sle(3); continue;
            }
            
            $clicks = [];
            $sol_str = (string)$solution; 
            
            if (is_array($solution)) {
                $clicks = $solution;
            } else {
                $decoded = json_decode($sol_str, true);
                if (is_array($decoded) && isset($decoded[0]['x'])) {
                    $clicks = $decoded;
                } 
                elseif (preg_match_all('/x=(\d+)\s*,\s*y=(\d+)/i', $sol_str, $matches)) {
                    for ($i = 0; $i < count($matches[1]); $i++) {
                        $clicks[] = ['x' => (int)$matches[1][$i], 'y' => (int)$matches[2][$i]];
                    }
                } 
                elseif (preg_match_all('/(\d+)\s*,\s*(\d+)/', $sol_str, $matches)) {
                    for ($i = 0; $i < count($matches[1]); $i++) {
                        $clicks[] = ['x' => (int)$matches[1][$i], 'y' => (int)$matches[2][$i]];
                    }
                }
            }
            
            if (empty($clicks)) {
                Logger::X('err', "Parse Error: " . $sol_str);
                _sle(3); continue;
            }
            
            $verify_payload = [
                'action' => 'verify',
                'captcha_id' => $captcha_id,
                'clicks' => json_encode($clicks) 
            ];
            
            $verify = json_decode(Net::X("{$this->host}/captcha/cap_da.php", 'POST', $verify_payload, Inf::$cookie, $this->headersCF, $claim_url, Inf::$uagent) ?: '', 1);
            
            if (!isset($verify['success']) || !$verify['success']) {
                Logger::X('err', "Verify failed: " . ($verify['message'] ?? 'Unknown'));
                _sle(2); continue;
            }
            
            Logger::X('ok', "Captcha OK, claiming...");
            $claim_payload = ['faucet_submit' => ''];
            
            $claim_res = Net::C($claim_url, 'POST', $claim_payload, Inf::$cookie, $this->headersCF, $claim_url, Inf::$uagent);
            
            if ($claim_res && $claim_res !== 99) {
                _put('debug_claim.html', $claim_res); 
                
                $amount = "?";
                $symbol = strtoupper($coin_name);
                
                if (preg_match('/id="claimAmountDisplay">([^<]+)<\/span>/i', $claim_res, $matches)) {
                    $amount = trim($matches[1]);
                }
                if (preg_match('/class="claim-coin-badge">([^<]+)<\/span>/i', $claim_res, $matches)) {
                    $symbol = trim($matches[1]);
                }
                
                $today = "?";
                $available = "?";
                
                if (preg_match('/id="todayClaimCount"[^>]*>(\d+)</i', $claim_res, $match_today)) {
                    $today = $match_today[1];
                }
                if (preg_match('/id="availableClaimCount"[^>]*>(\d+)</i', $claim_res, $match_avail)) {
                    $available = $match_avail[1];
                }
                
                $claim_info = " [Today: {$today}, Left: {$available}]";
                
                if ($amount !== "?" && $amount !== "0" && $amount !== "0.00000000") {
                    $this->logger('ok', 'Claim', "Success! +{$amount} {$symbol}{$claim_info}");
                    
                    if ($available !== "?" && (int)$available <= 0) {
                        $this->logger('ok', 'System', "Đã đạt giới hạn faucet hàng ngày (Left: 0). TỰ ĐỘNG DỪNG SCRIPT!");
                        die();
                    }
                    return; 
                }
                
                $plain_text = strip_tags($claim_res);
                
                if (preg_match('/(insufficient|not enough|low balance)/i', $plain_text)) {
                    $this->logger('err', 'Claim', "Failed {$coin_name}: Insufficient balance. Sleeping for 2 hours.");
                    $this->sleep_coins[$coin_name] = time() + (2 * 3600); 
                    return; 
                }

                if (preg_match('/(daily limit|limit reached|no claims)/i', $plain_text)) {
                    $this->logger('err', 'Claim', "Failed {$coin_name}: Daily Limit Reached.");
                    $this->logger('ok', 'System', "Đã đạt giới hạn faucet hàng ngày. TỰ ĐỘNG DỪNG SCRIPT!");
                    die(); 
                }
    
                $this->logger('ok', 'Claim', "Processed {$coin_name}{$claim_info}");
                return; 
            }
            
            _sle(3);
        }
    }
}

(new bitfaucetcj())->exec();