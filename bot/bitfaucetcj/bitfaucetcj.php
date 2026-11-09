<?php
#_die();
class bitfaucetcj {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    // Cập nhật lại host mới
    private string $host = 'https://bitfaucet.cryptojobss.com';
    private string $r = '/'; // Trang chủ để login
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private array $headersCF = [];
    
    // Mảng lưu thời gian sleep của từng coin
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
    login:
        Proxy::load();
        Check::Geo();
    
        while (true) {
            $ret = 0;
            $logged_in = false;
            $dash = null;
            
            // 1. KIỂM TRA ĐĂNG NHẬP
            do {
                $ret++;
                $dash = Net::C("{$this->host}/dashboard/", 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                
                if (!empty($dash) && $dash !== 99 && strpos($dash, 'FaucetPay Email') === false) {
                    logx('Info', "Logged in", false); 
                    _sle(3); _clr();
                    $logged_in = true;
                    break;
                }
                
                if ($ret >= 5) {
                    _put('debug_dash.html', $dash); 
                    $this->logger('err', "can't login", 'CHECK file debug_dash.html', true);
                }
                
                Logger::X('err', "Logging in...", false); 
                _sle(3); _clr();
                
                $po = ['email' => $this->mail];
                Net::C($this->host, 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                
            } while (!$logged_in);
            
            // 2. DANH SÁCH COIN CẦN CLAIM
            $coins = ['eth', 'ltc', 'doge', 'bch', 'dash', 'dgb', 'trx', 'usdt']; 
            
            foreach ($coins as $coin) {
                // KIỂM TRA XEM COIN NÀY CÓ ĐANG TRONG THỜI GIAN SLEEP KHÔNG
                if (isset($this->sleep_coins[$coin]) && time() < $this->sleep_coins[$coin]) {
                    $mins = ceil(($this->sleep_coins[$coin] - time()) / 60);
                    $this->logger('err', 'Sleep', "{$coin} skipped (sleeping for {$mins} more mins)");
                    continue; // Bỏ qua coin này, chạy sang coin tiếp theo
                }
                
                $claim_url = "{$this->host}/extra/{$coin}";
                
                print(FGd['CYN']." ".ITAL."processing {$coin} ");
                Logger::X('err', $coin);
                
                $this->claim_coin($claim_url, $coin);
                
                _sle(5); 
            }
            
            // Chờ thời gian faucet tiếp theo (ví dụ 5 phút)
            styler("Waiting for next round", fn() => _sle(300));
        }
    }
    
    private function claim_coin($claim_url, $coin_name) {
        $ret99 = 0;
        while (true) {
            $ret99++;
            if ($ret99 >= 5) return; // Thử 5 lần thất bại thì bỏ qua coin này
            
            // --- BƯỚC 1: LẤY CAPTCHA TỪ SERVER ---
            $gen_payload = ['action' => 'generate'];
            $req = json_decode(Net::X("{$this->host}/captcha/cap_da.php", 'POST', $gen_payload, Inf::$cookie, $this->headersCF, $claim_url, Inf::$uagent) ?: '', 1);
            
            if (empty($req) || !isset($req['captcha_id']) || empty($req['image'])) {
                _sle(2); continue;
            }
            
            $captcha_id = $req['captcha_id'];
            
            // Encode toàn bộ JSON thành base64 để gửi cho Xevil
            $json_string = json_encode($req);
            $base64_payload = base64_encode($json_string);
            
            // --- BƯỚC 2: GỬI CHO XEVIL GIẢI ---
            $solution = Solve::img($this->api, $this->host, 'bfcj_click', $base64_payload);
            
            if (isset($solution['trouble']) || empty($solution)) {
                $err_msg = $solution['trouble'] ?? 'Empty Response';
                Logger::X('err', "Xevil failed: " . $err_msg);
                _sle(3); continue;
            }
            
            // --- BƯỚC 3: XỬ LÝ KẾT QUẢ TỪ XEVIL ---
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
            
            // --- BƯỚC 4: GỬI KẾT QUẢ LÊN SERVER ĐỂ VERIFY ---
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
            
            // --- BƯỚC 5: CLAIM VÀ ĐỌC KẾT QUẢ ---
            Logger::X('ok', "Captcha OK, claiming...");
            $claim_payload = ['faucet_submit' => ''];
            
            $claim_res = Net::C($claim_url, 'POST', $claim_payload, Inf::$cookie, $this->headersCF, $claim_url, Inf::$uagent);
            
            if ($claim_res && $claim_res !== 99) {
                // Lưu lại mã HTML để kiểm tra nếu cần
                _put('debug_claim.html', $claim_res); 
                
                // 1. TÌM SỐ TIỀN VÀ TÊN COIN TỪ HTML
                $amount = "?";
                $symbol = strtoupper($coin_name);
                
                if (preg_match('/id="claimAmountDisplay">([^<]+)<\/span>/i', $claim_res, $matches)) {
                    $amount = trim($matches[1]);
                }
                if (preg_match('/class="claim-coin-badge">([^<]+)<\/span>/i', $claim_res, $matches)) {
                    $symbol = trim($matches[1]);
                }
                
                // 2. TÌM SỐ LẦN CLAIM HÔM NAY VÀ SỐ LẦN CÒN LẠI
                $today = "?";
                $available = "?";
                
                if (preg_match('/id="todayClaimCount"[^>]*>(\d+)</i', $claim_res, $match_today)) {
                    $today = $match_today[1];
                }
                if (preg_match('/id="availableClaimCount"[^>]*>(\d+)</i', $claim_res, $match_avail)) {
                    $available = $match_avail[1];
                }
                
                $claim_info = " [Today: {$today}, Left: {$available}]";
                
                // 3. IN RA KẾT QUẢ THÀNH CÔNG
                if ($amount !== "?" && $amount !== "0" && $amount !== "0.00000000") {
                    $this->logger('ok', 'Claim', "Success! +{$amount} {$symbol}{$claim_info}");
                    
                    // KIỂM TRA ĐIỀU KIỆN DỪNG TOOL: Nếu số lần còn lại của hệ thống = 0
                    if ($available !== "?" && (int)$available <= 0) {
                        $this->logger('ok', 'System', "Đã đạt giới hạn faucet hàng ngày (Left: 0). TỰ ĐỘNG DỪNG SCRIPT!");
                        die();
                    }
                    
                    return; // Thành công thì thoát hàm, sang coin tiếp theo
                }
                
                // 4. KIỂM TRA LỖI TỪ SERVER
                $plain_text = strip_tags($claim_res);
                
                // 4.1. Lỗi hết số dư của một coin cụ thể -> SLEEP 2 GIỜ
                if (preg_match('/(insufficient|not enough|low balance)/i', $plain_text)) {
                    $this->logger('err', 'Claim', "Failed {$coin_name}: Insufficient balance. Sleeping for 2 hours.");
                    $this->sleep_coins[$coin_name] = time() + (2 * 3600); // Lưu thời gian thức dậy (hiện tại + 7200 giây)
                    return; // Bỏ qua coin này, chạy sang coin khác
                }

                // 4.2. Lỗi đạt giới hạn faucet hàng ngày -> DỪNG TOOL
                if (preg_match('/(daily limit|limit reached|no claims)/i', $plain_text)) {
                    $this->logger('err', 'Claim', "Failed {$coin_name}: Daily Limit Reached.");
                    $this->logger('ok', 'System', "Đã đạt giới hạn faucet hàng ngày. TỰ ĐỘNG DỪNG SCRIPT!");
                    die(); // Dừng toàn bộ tool
                }
    
                // 5. Trường hợp không khớp gì cả
                $this->logger('ok', 'Claim', "Processed {$coin_name}{$claim_info}");
                return; 
            }
            
            _sle(3);
        }
    }
}

(new bitfaucetcj())->exec();