<?php
if (!defined('ROOT')) {
    error_log("[earnsolana] ROOT chưa được define – file phải được include từ runner.");
    die;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '512M'); // Tăng bộ nhớ tránh OOM
error_reporting(E_ALL);

return (new class {
    private $api; 
    private $xevilObj; 
    private string $host = 'https://earnsolana.xyz';
    private array $ctx      = [];
    private array $accounts = [];
    private array $cookies  = [];
    private string $jsonPath;
    private string $withdrawnPath;

    private string $xevilKey = 'lE8rBTi0kppZV9cwczKOsu3xxm75ChGX';
    private string $xevilMethod = 'teaserfast'; 

    private array $allowedWithdrawMethods = [1, 2, 3, 4, 5, 6, 7, 8];
    private int $minWithdraw = 5000; 

    private int $maxClaimsPerAccount = 500; 

    private array $totalClaims = [];
    private int $sleepSeconds = 0; // Không nghỉ
    private array $bannedAccounts = [];

    // === CẤU HÌNH THỜI GIAN CHỜ (SLEEP 0) ===
    private int $loopWaitSeconds      = 10; // Nghỉ 0s giữa các vòng
    private int $sequentialSleepSecs  = 5; // Nghỉ 0s giữa các account

    public function __construct() {
        $this->_verifyDependencies();
        
        $this->api = onKeys(); 
        if ($this->api === null || $this->api === false) {
            throw new RuntimeException("onKeys() trả về rỗng – không thể vượt Cloudflare.");
        }

        $this->xevilObj = Api::use('xevil', $this->xevilKey);

        // Tự động nhận diện môi trường GitHub Actions
        $envMail = getenv('mail');
        if (!empty($envMail)) {
            $envProxy = getenv('PROXY') ?: null;
            $safeName = md5($envMail);
            $this->jsonPath      = __DIR__ . "/mail_{$safeName}.json";
            $this->withdrawnPath = __DIR__ . "/withdrawn_{$safeName}.json";
            
            $this->accounts = [
                ['mail' => $envMail, 'proxy' => $envProxy, 'cookie' => '']
            ];
            file_put_contents($this->jsonPath, json_encode($this->accounts, JSON_PRETTY_PRINT));
            
            if (getenv('KEY')) {
                $this->xevilKey = getenv('KEY');
            }
        } else {
            $this->jsonPath      = __DIR__ . '/mail.json';
            $this->withdrawnPath = __DIR__ . '/withdrawn.json';
            if (file_exists($this->jsonPath)) {
                $this->accounts = json_decode(file_get_contents($this->jsonPath), true) ?? [];
            }
        }

        $this->_init();

        if (!is_dir(__DIR__ . '/debug_html')) mkdir(__DIR__ . '/debug_html', 0777, true);

        $b = Banner::getInstance();
        $b->show();
        $b->task1('ok', "EarnSolana (Sequential Mode - Max Speed)");
        $b->task2('info', "total: ".count($this->ctx)." email");
        $b->task2('info', "Auto Withdraw: Random Coin (Except BTC) - Min: {$this->minWithdraw}");
        $b->task2('info', "Max Claims/Account: {$this->maxClaimsPerAccount}");
        $b->task2('info', "Speed: Sequential (Tuần tự) | Sleep: 0s");
        $b->task2('info', "Đã rút: " . count($this->_loadWithdrawnMap()) . "/" . count($this->ctx) . " tài khoản (từ withdrawn.json)");
    }

    private function _verifyDependencies(): void {
        $missing = [];
        foreach (['Banner','Proxy','Check','Config','Inf','Logger','Net','Scraper','Solve','SolveUtils','Api','Cloudflare'] as $cls) {
            if (!class_exists($cls)) $missing[] = "class {$cls}";
        }
        if ($missing) throw new RuntimeException("Thiếu dependency: ".implode(', ', $missing));
    }

    private function _init() {
        if (file_exists($this->jsonPath) && empty($this->accounts)) {
            $this->accounts = json_decode(file_get_contents($this->jsonPath), true) ?? [];
        }
        foreach ($this->accounts as $acc) {
            if (!empty($acc['mail'])) {
                $m = $acc['mail'];
                $this->ctx[]           = ['mail' => $m, 'proxy' => $acc['proxy'] ?? null];
                $this->totalClaims[$m] = 0;
                $this->cookies[$m]     = $acc['cookie'] ?? '';
            }
        }
        if (empty($this->ctx)) die("Không tìm thấy tài khoản trong mail.json\n");
    }

    // ================= WITHDRAWN LOGIC =================
    private function _loadWithdrawnMap(): array {
        if (!file_exists($this->withdrawnPath)) return [];
        $fp = fopen($this->withdrawnPath, 'r');
        if (!$fp) return [];
        $data = [];
        if (flock($fp, LOCK_SH)) {
            $jsonStr = stream_get_contents($fp);
            $data = json_decode($jsonStr, true) ?: [];
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $data;
    }

    private function markWithdrawn(string $login): void {
        $fp = fopen($this->withdrawnPath, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $jsonStr = stream_get_contents($fp);
            $data = json_decode($jsonStr, true) ?: [];
            $data[$login] = date('Y-m-d H:i:s');
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        if ($fp) fclose($fp);
    }

    private function isWithdrawn(string $login): bool {
        $data = $this->_loadWithdrawnMap();
        return isset($data[$login]);
    }

    private function allWithdrawn(): bool {
        $data = $this->_loadWithdrawnMap();
        if (empty($data)) return false;
        foreach ($this->ctx as $acc) {
            if (!isset($data[$acc['mail']])) return false;
        }
        return true;
    }
    // ===================================================

    private function _persistMailJson() {
        foreach ($this->accounts as $i => $acc) {
            $m = $acc['mail'];
            if (isset($this->cookies[$m])) $this->accounts[$i]['cookie'] = $this->cookies[$m];
        }
        @file_put_contents($this->jsonPath, json_encode($this->accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function loadCookieFromJson(string $login, string $cookieFile) {
        if (!empty($this->cookies[$login])) @file_put_contents($cookieFile, $this->cookies[$login]);
    }

    private function saveCookieToJson(string $login, string $cookieFile) {
        if (!file_exists($cookieFile) || filesize($cookieFile) === 0) return;
        $content = @file_get_contents($cookieFile);
        if ($content === false || $content === '') return;
        $this->cookies[$login] = $content;
        $this->_persistMailJson();
    }

    // ================= HÀM GỌI TRỰC TIẾP XEVIL CLOUD API =================
    private function solveTurnstileDirect($sitekey, $pageurl) {
        $inUrl = 'https://sctg.xyz/in.php?' . http_build_query([
            'key' => $this->xevilKey,
            'method' => 'turnstile',
            'pageurl' => $pageurl,
            'sitekey' => $sitekey
        ]);
        
        $ch = curl_init($inUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);

        if (strpos(trim($res), 'OK|') !== 0) {
            throw new Exception("Lỗi gửi task lên Xevil: " . $res);
        }
        
        $taskId = explode('|', trim($res), 2)[1];
        
        $pollUrl = 'https://sctg.xyz/res.php?' . http_build_query([
            'key' => $this->xevilKey,
            'id' => $taskId,
            'action' => 'get'
        ]);
        
        $startTime = time();
        while (time() - $startTime < 300) { // Timeout 300s
            sleep(5); // Poll mỗi 5s
            $ch = curl_init($pollUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $pollResult = trim(curl_exec($ch));
            
            if (strpos($pollResult, 'NOT_READY') === false && strpos($pollResult, 'PROCESSING') === false) {
                if (strpos($pollResult, 'OK|') === 0) {
                    return explode('|', $pollResult, 2)[1];
                } else {
                    throw new Exception("Xevil giải thất bại: " . $pollResult);
                }
            }
        }
        throw new Exception("Hết thời gian chờ Xevil giải Turnstile");
    }
    // ======================================================================

    public function exec() {
        $round = 0;
        while (true) {
            
            if ($this->allWithdrawn()) {
                $this->_allDoneAndDie();
            }
            
            $activeCtx = array_values(array_filter($this->ctx, fn($acc) => !$this->isWithdrawn($acc['mail'])));
            
            if (empty($activeCtx)) {
                $this->_allDoneAndDie();
            }

            $round++;
            Logger::X('ok', "BẮT ĐẦU VÒNG {$round} (Tuần tự - " . count($activeCtx) . " tài khoản).");

            foreach ($activeCtx as $account) {
                $login = $account['mail'];
                $proxy = $account['proxy'] ?? null;

                if ($this->isWithdrawn($login)) continue;

                if ($this->totalClaims[$login] >= $this->maxClaimsPerAccount) {
                    Logger::X('warn', "[*] Tài khoản {$login} đã đạt giới hạn {$this->maxClaimsPerAccount} lần claim. Loại bỏ.");
                    if (!in_array($login, $this->bannedAccounts, true)) {
                        $this->bannedAccounts[] = $login;
                    }
                    continue;
                }

                if (in_array($login, $this->bannedAccounts, true)) continue;

                echo "\n" . str_repeat("=", 60) . "\n";
                echo FGd['CYN'] . " [*] XỬ LÝ: {$login} (Đã claim: {$this->totalClaims[$login]}/{$this->maxClaimsPerAccount})\n";
                echo str_repeat("=", 60) . "\n";

                putenv("PROXY=" . ($proxy ?? ''));
                Proxy::load();
                Check::Geo();

                $cookieFile = Config::cookie(md5($login));
                $this->loadCookieFromJson($login, $cookieFile);

                $c         = Config::credential(['ua' => fn() => Config::uagent('mobile')]);
                $userAgent = $c['ua'];
                Inf::setup($userAgent, $cookieFile, null, false, $login);

                $headersCF  = [];
                $dash       = '';
                $ret        = 0;
                $retryLogin = false;

                // ================= QUY TRÌNH ĐĂNG NHẬP =================
                do {
                    $ret++;
                    $checkDash = Net::X($this->host . '/dashboard', 'GET', null, Inf::$cookie, $headersCF, '', Inf::$uagent, d: true);
                    
                    if (!empty($checkDash['body'])) {
                        $cfDash = checkCF($this->host . '/dashboard', $this->api, $checkDash);
                        if (!empty($cfDash['html'])) {
                            $headersCF = $cfDash['head'];
                            $checkDash['body'] = $cfDash['html'];
                        }
                        
                        if (stripos($checkDash['body'], 'logout') !== false || stripos($checkDash['body'], 'Dashboard') !== false) {
                            $dash = $checkDash['body'];
                            break;
                        }
                    }

                    if ($ret >= 3) {
                        @unlink($cookieFile);
                        $retryLogin = true;
                        break;
                    }

                    Logger::X('err', "Đang thực hiện đăng nhập...");
                    $headerGet = array_merge((array)$headersCF, ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8']);
                    $_0 = Net::X($this->host.'/', 'GET', null, Inf::$cookie, $headerGet, '', Inf::$uagent, d: true);

                    if ($_0 === 99 || empty($_0)) { _sle($this->sleepSeconds); continue; }

                    $cfRes = checkCF($this->host.'/', $this->api, $_0);
                    if (empty($cfRes['html'])) {
                        $retryLogin = true; break;
                    }
                    $_0 = $cfRes['html'];
                    if (!empty($cfRes['head'])) $headersCF = $cfRes['head'];

                    $csrfToken = ''; $deviceToken = '';
                    $domLogin = new DOMDocument();
                    @$domLogin->loadHTML($_0);
                    $xpathLogin = new DOMXPath($domLogin);

                    $csrfNodes = $xpathLogin->query('//input[@name="csrf_token_name"]');
                    if ($csrfNodes->length > 0) $csrfToken = $csrfNodes->item(0)->getAttribute('value');

                    $deviceNodes = $xpathLogin->query('//input[@name="device_token"]');
                    if ($deviceNodes->length > 0) $deviceToken = $deviceNodes->item(0)->getAttribute('value');

                    if (empty($deviceToken) && preg_match('/name="device_token"\s+value="([^"]+)"/i', $_0, $m)) {
                        $deviceToken = $m[1];
                    }

                    if (empty($csrfToken)) {
                        @unlink($cookieFile); $retryLogin = true; break;
                    }
                    if (empty($deviceToken)) $deviceToken = 'dev_' . bin2hex(random_bytes(8));

                    // ================= GIẢI CLOUDFLARE TURNSTILE =================
                    $turnstileToken = '';
                    $sitekey = '';
                    
                    $cfTurnstileNode = $xpathLogin->query('//div[contains(@class, "cf-turnstile")]')->item(0);
                    if ($cfTurnstileNode) {
                        $sitekey = $cfTurnstileNode->getAttribute('data-sitekey');
                    }
                    
                    if (empty($sitekey) && preg_match('/data-sitekey=["\']([^"\']+)["\']/i', $_0, $matches)) {
                        $sitekey = $matches[1];
                    }

                    if (!empty($sitekey)) {
                        Logger::X('info', "Phát hiện Cloudflare Turnstile. Đang gọi trực tiếp API sctg.xyz (Sitekey: {$sitekey})...");
                        
                        try {
                            $turnstileToken = $this->solveTurnstileDirect($sitekey, $this->host . '/');
                            Logger::X('ok', "Đã giải Turnstile thành công.");
                        } catch (Exception $e) {
                            Logger::X('err', "Lỗi giải Turnstile: " . $e->getMessage());
                            _sle(3);
                            continue;
                        }
                    }
                    // ==========================================================

                    $payloadLogin = [
                        'wallet'          => $login,
                        'csrf_token_name' => $csrfToken,
                        'device_token'    => $deviceToken,
                        'cf-turnstile-response' => $turnstileToken 
                    ];

                    $headersPost = array_merge((array)$headersCF, [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Origin: ' . $this->host,
                        'Upgrade-Insecure-Requests: 1'
                    ]);
                    
                    $loginResponse = Net::X($this->host . '/auth/login', 'POST', $payloadLogin, Inf::$cookie, $headersPost, $this->host.'/', Inf::$uagent, d: true);

                    if (in_array($loginResponse['http_code'] ?? 0, [303, 302, 200])) {
                        if (stripos($loginResponse['body'] ?? '', 'logout') !== false || stripos($loginResponse['body'] ?? '', 'Dashboard') !== false || ($loginResponse['http_code'] ?? 0) == 302 || ($loginResponse['http_code'] ?? 0) == 303) {
                            Logger::X('ok', "-> Đăng nhập thành công.");
                        } else {
                            Logger::X('warn', "-> Có thể đăng nhập thất bại (Bị chặn Turnstile hoặc sai mail).");
                            _sle(3);
                        }
                    } else {
                        _sle($this->sleepSeconds);
                    }
                } while (empty($dash));
                
                if ($retryLogin) { _sle($this->sleepSeconds); continue; }
                $this->saveCookieToJson($login, $cookieFile);

                // ================= CLAIM =================
                $fa = $this->host . '/faucet'; 
                $isClaimed = $this->doSingleClaim($fa, 'sol', $headersCF, $login, $cookieFile);
                
                if ($isClaimed) {
                    $this->totalClaims[$login]++;
                    Logger::X('info', "Đã Claim thành công ({$this->totalClaims[$login]}/{$this->maxClaimsPerAccount}). Chuyển ngay sang tài khoản tiếp theo...");
                    
                    // [AUTO WITHDRAW]
                    $this->doWithdraw($headersCF, $login, $cookieFile);
                } else {
                    Logger::X('warn', "Claim không thành công (chưa tới giờ hoặc lỗi). Chuyển ngay sang tài khoản tiếp theo...");
                }

                _sle($this->sequentialSleepSecs); // = 0s
            }

            // Loại bỏ tài khoản ban/giới hạn khỏi danh sách
            $this->ctx = array_values(array_filter($this->ctx, function ($acc) {
                return !in_array($acc['mail'], $this->bannedAccounts, true);
            }));

            if (empty($this->ctx)) {
                echo FGd['CYN'] . "\n[*] TẤT CẢ TÀI KHOẢN ĐÃ ĐẠT GIỚI HẠN HOẶC BỊ LỖI. DỪNG CHƯƠNG TRÌNH.\n";
                die(); 
            }

            if ($this->allWithdrawn()) {
                $this->_allDoneAndDie();
            }

            echo FGd['GRN'] . "\n[!] HOÀN THÀNH VÒNG {$round}. CHỜ 0s VÀ BẮT ĐẦU VÒNG MỚI...\n";
            _sle($this->loopWaitSeconds); // = 0s
        }
    }

    private function _allDoneAndDie(): void {
        $done  = count($this->_loadWithdrawnMap());
        $total = count($this->ctx);
        echo "\n" . str_repeat("=", 60) . "\n";
        echo FGd['GRN'] . " [***] TẤT CẢ {$done}/{$total} TÀI KHOẢN ĐÃ RÚT TIỀN THÀNH CÔNG!\n";
        echo FGd['GRN'] . " [***] SCRIPT KẾT THÚC.\n";
        echo str_repeat("=", 60) . "\n";
        @unlink($this->withdrawnPath);
        die(0);
    }

    // ================= HÀM RÚT TIỀN TỰ ĐỘNG =================
    private function doWithdraw(array &$headersCF, string $login, string $cookieFile): void {
        Logger::X('info', "Kiểm tra điều kiện rút tiền...");
        
        $withdrawUrl = $this->host . '/withdraw';
        $referer = $this->host . '/dashboard';
        
        $headers = array_merge((array)$headersCF, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Referer: ' . $referer,
        ]);

        $req = Net::X($withdrawUrl, 'GET', null, Inf::$cookie, $headers, $referer, Inf::$uagent, d: true);
        if ($req === 99 || empty($req['body'])) return;

        $html = $req['body'];
        $cfRes = checkCF($withdrawUrl, $this->api, $req, $headersCF);
        if (!empty($cfRes['html'])) {
            $headersCF = $cfRes['head'];
            $html      = $cfRes['html'];
        }

        $cleanBalance = 0;
        if (preg_match('/Available Balance[\s\S]*?([\d,]+)\s*(?:Coins|<)/is', $html, $m)) {
            $cleanBalance = (int) str_replace(',', '', trim($m[1]));
        } elseif (preg_match('/<div[^>]*class="[^"]*bg-green[^"]*"[^>]*>[\s\S]*?([\d,]+)\s*(?:Coins|<\/)/is', $html, $m)) {
            $cleanBalance = (int) str_replace(',', '', trim($m[1]));
        } else {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//*[contains(text(), "Coins")]') as $node) {
                if (preg_match('/([\d,]+)\s*Coins/i', $node->nodeValue, $m)) {
                    $cleanBalance = (int) str_replace(',', '', $m[1]);
                    break;
                }
            }
        }
        
        Logger::X('info', "Số dư khả dụng: {$cleanBalance} Coins");

        if ($cleanBalance < $this->minWithdraw) {
            Logger::X('info', "Chưa đủ mức tối thiểu ({$this->minWithdraw} Coins) để rút. Tiếp tục claim...");
            return;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $csrfNode = $xpath->query('//input[@name="csrf_token_name"]')->item(0);
        $csrfToken = $csrfNode ? $csrfNode->getAttribute('value') : '';

        if (empty($csrfToken)) {
            Logger::X('err', "Không tìm thấy CSRF Token trên trang Withdraw.");
            return;
        }

        $randomMethod = $this->allowedWithdrawMethods[array_rand($this->allowedWithdrawMethods)];
        Logger::X('warn', "Đã đủ điều kiện! Tiến hành gửi yêu cầu rút tiền (Random Method: {$randomMethod})...");

        $processUrl = $this->host . '/withdraw/process';
        $payload = [
            'csrf_token_name' => $csrfToken,
            'method'          => $randomMethod
        ];
        $queryString = http_build_query($payload);

        $headersProcess = array_merge((array)$headersCF, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Content-Type: application/x-www-form-urlencoded',
            'Origin: ' . $this->host,
            'Referer: ' . $withdrawUrl,
            'Upgrade-Insecure-Requests: 1'
        ]);

        $res = Net::X($processUrl, 'POST', $queryString, Inf::$cookie, $headersProcess, $withdrawUrl, Inf::$uagent, d: true);

        $httpCode = $res['http_code'] ?? 0;
        $responseBody = $res['body'] ?? '';
        
        $isSuccess = false;
        if (in_array($httpCode, [302, 303])) {
            $isSuccess = true;
        } elseif ($httpCode == 200 && (stripos($responseBody, 'success') !== false || stripos($responseBody, 'sent to your') !== false)) {
            $isSuccess = true;
        }

        if ($isSuccess) {
            Logger::M($login);
            Logger::X('ok', "wd ", false);
            Logger::G(false, "RÚT TIỀN THÀNH CÔNG! (Method: {$randomMethod}, HTTP {$httpCode})");
            $this->saveCookieToJson($login, $cookieFile);
            
            // Đánh dấu đã rút
            $this->markWithdrawn($login);
            Logger::X('ok', "-> Đã thêm {$login} vào withdrawn.json. Còn lại: " . (count($this->ctx) - count($this->_loadWithdrawnMap())) . " tài khoản.");
        } else {
            Logger::X('err', "Rút tiền thất bại. HTTP: {$httpCode}");
            @file_put_contents(__DIR__ . "/debug_withdraw_fail.html", $responseBody);
        }
    }
    // ==========================================================

    private function doSingleClaim(string $fa, string $coin, array &$headersCF, string $login, string $cookieFile): bool {
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            Logger::X('info', "--- Bắt đầu thử Claim (Lần $attempt/$maxRetries) ---");
            
            $referer = $this->host . '/dashboard';
            $headersFaucet = array_merge((array)$headersCF, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Referer: ' . $referer,
            ]);

            $req = Net::X($fa, 'GET', null, Inf::$cookie, $headersFaucet, $referer, Inf::$uagent, d: true);
            if ($req === 99 || empty($req['body'])) { _sle(1); continue; } // Nghỉ 1s nếu lỗi mạng để tránh treo

            $html = $req['body'];
            $cfRes = checkCF($fa, $this->api, $req, $headersCF);
            if (!empty($cfRes['html'])) {
                $headersCF = $cfRes['head'];
                $html      = $cfRes['html'];
            }
            
            if (stripos($html, 'name="wallet"') !== false) {
                Logger::X('err', "-> Bị đá ra trang chủ! Session không hợp lệ.");
                @unlink($cookieFile);
                return false;
            }

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $balanceNode = $xpath->query('//strong[@id="faucetBalance"]')->item(0);
            $currentBalance = $balanceNode ? trim($balanceNode->nodeValue) : 'Không rõ';
            Logger::X('info', "Số dư hiện tại (tại trang Faucet): " . $currentBalance);

            $csrfToken = ''; $claimToken = '';
            $csrfNodes = $xpath->query('//input[@name="csrf_token_name"]');
            if ($csrfNodes->length > 0) $csrfToken = $csrfNodes->item(0)->getAttribute('value');
            
            $claimNodes = $xpath->query('//input[@name="claim_token"]');
            if ($claimNodes->length > 0) $claimToken = $claimNodes->item(0)->getAttribute('value');

            if (empty($csrfToken) || empty($claimToken)) {
                Logger::X('err', "Không tìm thấy token trong trang Faucet.");
                _sle(1); continue;
            }

            Logger::X('info', "Thử gửi Claim (Không có Captcha)...");
            
            $payloadNoCap = [
                'csrf_token_name' => $csrfToken,
                'claim_token'     => $claimToken
            ];
            $queryNoCap = http_build_query($payloadNoCap);
            
            $headersSubmit = array_merge((array)$headersCF, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $this->host,
                'Referer: ' . $fa,
                'Upgrade-Insecure-Requests: 1'
            ]);

            $verifyUrl = $this->host . '/faucet/verify';
            $resNoCap = Net::X($verifyUrl, 'POST', $queryNoCap, Inf::$cookie, $headersSubmit, $fa, Inf::$uagent, d: true);
            
            $httpCode = $resNoCap['http_code'] ?? 0;
            $bodyNoCap = $resNoCap['body'] ?? '';

            if (in_array($httpCode, [302, 303]) || preg_match('/success|sent to your/i', $bodyNoCap)) {
                Logger::M($login);
                Logger::X('ok', "fct ", false);
                Logger::G(false, "Claim thành công (Không cần Captcha)!");
                $this->saveCookieToJson($login, $cookieFile);
                return true; 
            }

            if (stripos($bodyNoCap, 'Wait') !== false || stripos($bodyNoCap, 'Next claim') !== false || stripos($bodyNoCap, 'timer') !== false) {
                Logger::X('warn', "Chưa đến thời gian Claim tiếp theo. Bỏ qua tài khoản này.");
                return false; 
            }

            $needsCaptcha = (stripos($bodyNoCap, 'verification') !== false || stripos($bodyNoCap, 'captcha') !== false || stripos($bodyNoCap, 'Verify') !== false);

            if ($needsCaptcha) {
                Logger::X('warn', "Server yêu cầu Captcha! Tiến hành xử lý...");
                
                $reqReset = Net::X($fa, 'GET', null, Inf::$cookie, $headersFaucet, $referer, Inf::$uagent, d: true);
                $htmlReset = $reqReset['body'] ?? '';
                
                $domReset = new DOMDocument();
                @$domReset->loadHTML($htmlReset);
                $xpathReset = new DOMXPath($domReset);
                
                $csrfToken = $xpathReset->query('//input[@name="csrf_token_name"]')->item(0)->getAttribute('value');
                $claimToken = $xpathReset->query('//input[@name="claim_token"]')->item(0)->getAttribute('value');

                $captchaUrl = $this->host . '/faucet/captcha';
                $payloadCaptcha = ['csrf_token_name' => $csrfToken];
                
                $headersCaptcha = array_merge($headersFaucet, [
                    'X-Requested-With: XMLHttpRequest',
                    'Accept: application/json, text/javascript, */*; q=0.01',
                    'Origin: ' . $this->host,
                    'Referer: ' . $fa
                ]);

                $captchaRes = Net::X($captchaUrl, 'POST', $payloadCaptcha, Inf::$cookie, $headersCaptcha, $fa, Inf::$uagent, d: true);
                $bodyCaptcha = $captchaRes['body'] ?? '';
                
                $captchaData = json_decode($bodyCaptcha, true);

                if (empty($captchaData) || empty($captchaData['img']) || empty($captchaData['label'])) {
                    Logger::X('err', "Không lấy được dữ liệu Captcha (HTTP " . ($captchaRes['http_code'] ?? 0) . ").");
                    _sle(1);
                    continue;
                }

                $base64Img = preg_replace('#^data:image/\w+;base64,#i', '', $captchaData['img']);
                $taskLabel = $captchaData['label'];
                $captchaId = $captchaData['id'];
                $tokens = array_values($captchaData['tokens']); 
                $newCsrfToken = $captchaData['csrf_hash'] ?? $csrfToken;

                Logger::X('info', "Gửi Captcha sang Xevil. Câu hỏi: [{$taskLabel}]");
                
                try {
                    $solution = Solve::img($this->xevilObj, $this->host, $this->xevilMethod, $base64Img, [
                        'ins' => $taskLabel 
                    ]);
                } catch (Exception $e) {
                    Logger::X('err', "Lỗi giải Captcha: " . $e->getMessage());
                    _sle(1);
                    continue;
                }

                if (is_array($solution)) {
                    $errMsg = $solution['trouble'] ?? 'UNKNOWN_ERROR';
                    Logger::X('err', "Xevil giải thất bại: " . $errMsg);
                    _sle(1);
                    continue;
                }

                if (empty($solution) || !is_string($solution) || stripos($solution, 'ERROR') !== false) {
                    Logger::X('err', "Xevil không thể giải được: " . (is_string($solution) ? $solution : 'Empty'));
                    _sle(1);
                    continue;
                }

                $indices = preg_split('/[,;]/', $solution);
                Logger::X('ok', "Xevil giải xong. Vị trí chọn: $solution");

                $solvedTokens = [];
                foreach ($indices as $idx) {
                    $arrIdx = (int)trim($idx) - 1; 
                    if (isset($tokens[$arrIdx])) {
                        $solvedTokens[] = $tokens[$arrIdx];
                    }
                }

                if (empty($solvedTokens)) {
                    Logger::X('err', "Không map được vị trí Captcha sang token.");
                    _sle(1);
                    continue;
                }

                Logger::X('info', "Gửi Claim cùng Captcha Tokens...");
                _sle(0);

                $finalPayload = [
                    'csrf_token_name' => $newCsrfToken,
                    'claim_token'     => $claimToken,
                    'captcha_id'      => $captchaId,
                    'captcha_sel'     => $solvedTokens 
                ];
                $queryFinal = http_build_query($finalPayload);
                
                $finalRes = Net::X($verifyUrl, 'POST', $queryFinal, Inf::$cookie, $headersSubmit, $fa, Inf::$uagent, d: true);

                if (in_array($finalRes['http_code'] ?? 0, [302, 303]) || preg_match('/success|sent to your/i', $finalRes['body'] ?? '')) {
                    Logger::M($login);
                    Logger::X('ok', "fct ", false);
                    Logger::G(false, "Claim thành công (Đã giải Captcha)!");
                    $this->saveCookieToJson($login, $cookieFile);
                    return true;
                } else {
                    Logger::X('err', "Claim với Captcha thất bại. HTTP: " . ($finalRes['http_code'] ?? 'N/A'));
                    _sle(1);
                }
            } else {
                Logger::X('err', "Claim thất bại không rõ lý do. HTTP: " . $httpCode);
                _sle(1);
            }
        }
        
        Logger::X('err', "Đã thử $maxRetries lần nhưng đều thất bại. Chuyển sang tài khoản khác.");
        return false;
    }
})->exec();

/* ================= CÁC HÀM BỔ TRỢ ================= */
function checkCF($url, $api, $body = null, $headersCF = []) {
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    if (!$html || !$code) return [];

    $isCF = (stripos($html, 'Just a moment') !== false)
         || (stripos($html, 'Attention Required!') !== false)
         || (stripos($html, 'cf-browser-verification') !== false)
         || (stripos($html, 'cf_chl_opt') !== false);

    if (!$isCF) return ['html' => $html, 'head' => $headersCF];

    $currentProxy = getenv("PROXY");
    $cf = Cloudflare::exec($api, $url, Inf::$cookie, Inf::$uagent, ['html' => $html, 'proxy' => $currentProxy]);

    if ($cf) {
        [$newHeaders, $newUa] = $cf;
        $headersCF = array_merge((array)$headersCF, (array)$newHeaders);
        Inf::setup($newUa, Inf::$cookie);
        $headersCF['User-Agent'] = $newUa;

        for ($try = 1; $try <= 3; $try++) {
            _sle(2); // Vẫn giữ 2s cho Cloudflare để tránh bị block
            $fix = Net::X($url, 'GET', null, Inf::$cookie, $headersCF, $url, $newUa, d: true);
            if (!empty($fix) && isset($fix['http_code'])) {
                if ($fix['http_code'] === 200 && stripos($fix['body'], 'Just a moment') === false) {
                    Config::credential()['ua'] = $newUa;
                    return ['html' => $fix['body'], 'head' => $headersCF];
                }
            }
        }
    }
    return [];
}
