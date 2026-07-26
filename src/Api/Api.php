<?php

final class Api {
    public const PX_AUTH = '';
    public const PX_TYPE = '';
    public const PROXY = self::PX_TYPE . '://' . self::PX_AUTH;

    public const KEY = [
        'tertuyul' => ['ep' => 'http://tertuyul.my.id', 'cls' => tertuyul::class],
        'solverify' => ['ep' => 'https://solverify.net', 'cls' => solverify::class],
        'xevil' => ['ep' => 'Xevil_check_bot.t.me',  'cls' => xevil::class],
        'skibidixxx' => ['ep' => 'https://waryono.my.id', 'cls' => skibidixxx::class],
        'capsolver' => ['ep' => 'https://capsolver.com', 'cls' => capsolver::class],
        'multibot' => ['ep' => 'http://multibot.in', 'cls' => multibot::class],
        'gmxch' => ['ep' => 'gamamoch', 'cls' => gmxch::class],
        'glitch' => ['ep' => 'https://buxads.com/api-token', 'cls' => glitch::class], 
    ];

    public static function use($API, $KEY): Provider {
        $a = trim($API);
        $k   = strtolower($a);
        $cfg = self::KEY[$a] ?? self::KEY[$k] ?? null;
        
        if (!$cfg) {
            foreach (self::KEY as $r) {
                if (($r['ep'] ?? null) === $a) { $cfg = $r; break; }
            }
        }
        if (!$cfg) {
            throw new Exception("invalid $API");
        }
        $cls = $cfg['cls'];
        return new $cls($KEY);
    }

    public const TKN = [

        solverify::class => [
            'cft' => [
                'k'=>'websiteKey','url'=>'websiteURL','api'=>'turnstile'
                ],
        ],

        gmxch::class => [
            'shortlink' => true,
            'cft' => [
                'k'=>'siteKey','url'=>'domain','api'=>'cloudflare', 'defaults' => ['method' => 'turnstile'], 'map' => ['cdata' => 'cData']
                ],

            'cf' => [
                'k'=>'siteKey','url'=>'domain','api'=>'popularcaptcha', 'defaults' => ['method' => 'turnstile']
                ],
            'hc' => [
                'k'=>'siteKey','url'=>'domain','api'=>'popularcaptcha', 'defaults' => ['method' => 'hcaptcha']
                ],
            /*
            'rc2' => [
                'k'=>'siteKey','url'=>'domain','api'=>'popularcaptcha', 'defaults' => ['method' => 'recaptcha2']
                ],
            'rc3' => [
                'k'=>'siteKey','url'=>'domain','api'=>'popularcaptcha', 'defaults' => ['method' => 'recaptcha3']
                ],
            */

            'pcc' => [
                'k'=>'cpobj','url'=>'domain','api'=>'coinclix', 'defaults' => ['method' => 'pc']
                ],
            'icc' => [
                'k'=>'cpobj','url'=>'domain','api'=>'coinclix', 'defaults' => ['method' => 'ic']
                ],
        ],

        tertuyul::class => [
            'shortlink' => true,
            '_proxy_format' => 'split',
            'cft' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'turnstile'
                ],
            'hc'  => [
                'k' => 'sitekey','url' =>'pageurl','api' => 'hcaptcha'
                ],
            'rc2' => [
                'k' => 'googlekey','url' => 'pageurl','api' => 'userrecaptcha'
                ],
            'rc3' => [
                'k' => 'googlekey','url' => 'pageurl','api' => 'userrecaptcha','need' => ['action'],
                'defaults' => ['version' => 'v3']
                ],
        ],

        xevil::class => [
            '_proxy_format' => 'split',
            'cft' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'turnstile'
                ],
            'hc'  => [
                'k' => 'sitekey','url' =>'pageurl','api' => 'hcaptcha'
                ],
            'rc2' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'userrecaptcha'
                ],
            'rc3' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'userrecaptcha','need' => ['action'], 
                'defaults' => ['version' => 'v3']
                ],
        ],

        multibot::class => [
            '_proxy_format' => 'uri',
            'cft' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'turnstile', 'map' => ['cdata' => 'data']
                ],
            'hc'  => [
                'k' => 'sitekey','url' =>'pageurl','api' => 'hcaptcha',
                'map' => [
                    'invisible' => 'isInvisible'
                ]
                ],
            'rc2' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'userrecaptcha',
                'defaults' => ['version' => 'v2'],
                'map' => [
                    'invisible' => 'isInvisible'
                ]
                ],
            'rc3' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'userrecaptcha','need' => ['action'], 
                'defaults' => ['version' => 'v3']
                ],
        ],

        skibidixxx::class => [
            'shortlink' => true,
            'cft' => [
                'k' => 'sitekey', 'url' => 'domain', 'api' => 'turnstile'
                ],
            /*
            */
            'hc' => [
                'k' => 'sitekey', 'url' => 'domain', 'api' => 'hcaptcha'
                ],
            'rc2' => [
                'k' => 'sitekey', 'url' => 'domain', 'api' => 'userrecaptcha',
                'defaults' => ['version' => '2']
                ],
            'rc3' => [
                'k' => 'sitekey', 'url' => 'domain', 'api' => 'userrecaptcha',
                'defaults' => ['version' => '3']
                ],
        ],

        glitch::class => [
            'shortlink' => true,
            'cft' => [
                'k' => 'siteKey', 'url' => 'domain', 'api' => 'turnstile'
                ],
            'hc' => [
                'k' => 'siteKey', 'url' => 'domain', 'api' => 'hcaptcha'
                ],
            'rc2' => [
                'k' => 'siteKey', 'url' => 'domain', 'api' => 'recaptchav2'
                ],
            'rc3' => [
                'k' => 'siteKey', 'url' => 'domain', 'api' => 'recaptchav3'
                ],
        ],

        capsolver::class => [
            'cft' => [
                'k'=>'websiteKey','url'=>'websiteURL','api'=>'turnstile'
                ],
            'rc2' => [
                'k'=>'websiteKey','url'=>'websiteURL','api'=>'recaptchav2',
                ],
            'rc3' => [
                'k'=>'websiteKey','url'=>'websiteURL','api'=>'recaptcha3', 'need'=>['action'], 
                ],
        ],
    ];
    
    public static function cfgTkn($c, $t, $siteKey, $siteUrl, array $extra = []): array {
        
        $cfg = self::TKN[$c][$t] ?? null;
        
        if (!is_array($cfg) || empty($cfg['api']) || empty($cfg['k']) || empty($cfg['url'])) {
            throw new Exception("invalid method, change providers");
        }
        foreach (($cfg['need'] ?? []) as $k) {
            if (!array_key_exists($k, $extra)) {
                throw new Exception("missing arg: $k");
            }
        }
        
        $params = array_merge([
            $cfg['k']   => $siteKey,
            $cfg['url'] => $siteUrl
        ], ($cfg['defaults'] ?? []), $extra);
        
        $providerCfg = self::TKN[$c];
        $fmt = $providerCfg['_proxy_format'] ?? null;
        if (isset($params['proxy']) && ($params['proxy'] === 'empty' || $params['proxy'] === '')) {
            unset($params['proxy'], $params['proxytype']);
        }
        
        $px = $params['proxy'] ?? ($fmt && !isset($params['proxy']) ? self::PROXY : null);
        if ($px && $fmt) {
            $u = parse_url($px);
            if ($u && !empty($u['host']) && !empty($u['port'])) {
                $scheme = strtolower($u['scheme'] ?? self::PX_TYPE ?? 'http');
                $auth = !empty($u['user']) ? "{$u['user']}:".($u['pass'] ?? '')."@{$u['host']}:{$u['port']}" : "{$u['host']}:{$u['port']}";
                if ($fmt === 'uri') {
                    unset($params['proxytype']);
                    $params['proxy'] = "{$scheme}://{$auth}";
                }
                if ($fmt === 'split') {
                    $params['proxy'] = $auth;
                    $params['proxytype'] = $scheme;
                }
            }
        }
        
        if (!empty($cfg['map'])) {
            foreach ($cfg['map'] as $from => $to) {
                if (array_key_exists($from, $params)) {
                    $params[$to] = $params[$from];
                    unset($params[$from]);
                }
            }
        }
        return [$cfg['api'], $params];
    }

    public const B64 = [
    
        tertuyul::class => [
            'bitcotask' => true,
            'antibot' => true,
    
            'ocr' => ['api' => 'universal', 'img' => 'body'],
            'least' => ['api' => 'iconfinder', 'img' => 'body'],
            'rs_upside' => ['api' => 'upside', 'img' => 'body'],
            'upside' => ['api' => 'upside', 'img' => 'body'],
            'vie_upside' => ['api' => 'upside', 'img' => 'body'],
            'fa_icon' => ['api' => 'hunter', 'img' => 'body'],
            'icon_up' => ['api' => 'iconflip', 'img' => 'body'],
            'rs_icon' => ['api' => 'rscaptcha', 'img' => 'body'],
        ],
    
        glitch::class => [
            'antibot' => true,
            'ocr' => ['api' => 'textcaptcha', 'img' => 'image_base64'],
            'rs_upside' => ['api' => 'rsv2', 'img' => 'image_base64'],
            'rs_icon' => ['api' => 'rsv2', 'img' => 'image_base64'],
        ],
    
        xevil::class => [
            'antibot' => true,
            'ocr' => ['api' => 'base64', 'img' => 'body'],
            'upside' => ['api' => 'viefaucet', 'img' => 'body'],
            'rs_upside'  => ['api' => 'viefaucet', 'img' => 'body'],
            'vie_upside' => ['api' => 'viefaucet', 'img' => 'body'],
            'icon_up' => ['api' => 'iconupfinder', 'img' => 'body'],
            'rs_icon' => ['api' => 'rscaptcha', 'img' => 'body'],
        ],
    
        multibot::class => [
            'antibot' => true,
            'ocr' => ['api' => 'universal', 'img' => 'body'],
            'least' => ['api' => 'iconfinder', 'img' => 'body'],
            'rs_upside'  => ['api' => 'upside', 'img' => 'body'],
            'upside' => ['api' => 'upside', 'img' => 'body'],
            'vie_upside' => ['api' => 'upside', 'img' => 'body'],
            'icon_up' => ['api' => 'rscaptcha', 'img' => 'body'],
            'rs_icon' => ['api' => 'rscaptcha', 'img' => 'body'],
        ],
    
        skibidixxx::class => [
            'antibot' => true,
            'fa_icon' => ['api' => 'bitcocaptcha', 'img' => 'base64_str'],
            'rs_upside'  => ['api' => 'upsidedown_2', 'img' => 'base64_str'],
            'rs_icon' => ['api' => 'rsicon', 'img' => 'base64_str'],
            'ocr' => ['api' => 'image-to-text','img' => 'base64_str'],
            'least' => ['api' => 'least-icons',  'img' => 'base64_str'],
            'upside' => ['api' => 'upsidedown_3', 'img' => 'base64_str'],
            'vie_upside' => ['api' => 'upsidedown_3', 'img' => 'base64_str'],
        ],
    
        capsolver::class => [
            'ocr' => [
                'api' => 'ImageToTextTask',
                'img' => 'body',
            ],
        ],
    
        gmxch::class => [
            'bitcotask' => true,
            'antibot' => true,
            'zercaptcha' => true,
            
            'fa_icon' => [
                'api' => 'visual',
                'img' => 'main',
                'defaults' => [
                    'method' => 'SL-iconcaptcha',
                ],
                'map' => [
                    'opt' => 'options',
                ],
            ],
            
            'onlyfans' => [
                'api' => 'visual',
                'img' => 'main',
                'defaults' => [
                    'method' => 'onlyfans',
                ],
                
            ],
            
            'upside' => [
                'api' => 'visual',
                'img' => 'main',
                'defaults' => [
                    'method' => 'upside',
                ],
                
            ],
            
        ],
    
        solverify::class => [
            'ocr' => [
                'api' => 'ocr',
                'img' => 'body',
            ],
        ],
    
    ];
    
    public static function cfgB64($c, $t, $b64, array $extra = []): array {
    
        $cfg = self::B64[$c][$t] ?? null;
    
        if (!$cfg && in_array($t, ['math', '4num'])) {
            $cfg = self::B64[$c]['ocr'] ?? null;
        }
    
        if (!is_array($cfg) || empty($cfg['api']) || empty($cfg['img'])) {
            throw new Exception("invalid method, change providers");
        }
    
        foreach (($cfg['need'] ?? []) as $k) {
            if (!array_key_exists($k, $extra)) {
                throw new Exception("missing arg: $k");
            }
        }
    
        $params = array_merge([$cfg['img'] => $b64], $cfg['defaults'] ?? [], $extra);
    
        if (!empty($cfg['map'])) {
            foreach ($cfg['map'] as $from => $to) {
                if (array_key_exists($from, $params)) {
                    $params[$to] = $params[$from];
                    unset($params[$from]);
                }
            }
        }
    
        return [$cfg['api'], $params];
    }

    public const ACC = [
        
        solverify::class => [
            'interstitial' => [
                'api' => 'interstitial','url' => 'websiteURL', 'need' => ['proxy'], 
                
            ],
            'perimeterx' => [
                'api'  => 'perimeterx','url'  => 'websiteURL','need' => ['websiteKey'],
            ],
            'datadome' => [
                'api'  => 'datadome','url'  => 'websiteURL','need' => ['captchaUrl'],
            ],
        ],
        
        gmxch::class => [
            '_proxy_format' => 'uri',
            'interstitial' => [
                'api' => 'cloudflare','url' => 'domain', 'defaults' => ['method' => 'interstitial']
            ],
            'fingerprint' => [
                'api'  => 'fingerprint','url' => 'domain','need' => ['userAgent'],
            ],
            /*
            'akamai_a' => [
                'api'  => 'akamai','url' => 'domain','defaults' => ['method' => 'abck']
            ],
            'akamai_s' => [
                'api'  => 'akamai','url' => 'domain','defaults' => ['method' => 'sbsd']
            ],
            'akamai_c' => [
                'api'  => 'akamai','url' => 'domain','defaults' => ['method' => 'censorship']
            ],
            */
        ],
        
        tertuyul::class => [
            '_proxy_format' => 'split',
            'interstitial' => [
                'api' => 'cloudflare','url' => 'pageurl', 'need' => ['proxy'], 
            ],
        ],
        
        skibidixxx::class => [
            '_proxy_format' => 'uri',
            'interstitiall' => [
                'api' => 'cf-uam','url' => 'domain', 'need' => ['proxy'], 
            ],
        ],
        
        glitch::class => [
            '_proxy_format' => 'object',
            'interstitial' => [
                'api' => 'iuam','url' => 'domain', 'need' => ['proxy'], 
            ],
        ],
        
        capsolver::class => [
            'interstitial' => [
                'api' => 'AntiCloudflareTask','url' => 'domain', 'need' => ['proxy'], 
            ],
            'datadome' => [
                'api'  => 'DatadomeSliderTask','url'  => 'websiteURL','need' => ['captchaUrl'],
            ],
        ],

        xevil::class => [
            '_proxy_format' => 'split',
            'interstitial' => [
                'api' => 'turnstile','url' => 'pageurl',
                'defaults' => ['sitekey' => 'jschallenge']
            ],
        ],
        
        multibot::class => [
            '_proxy_format' => 'split',
            'interstitial' => [
                'api' => 'turnstile','url' => 'pageurl',
                'defaults' => ['cf_clearance' => 1],
               # 'need' => ['body'] 
            ],
        ],
        
    ];
    
    public static function cfgAcc($c, $t, $siteUrl, array $extra = []): array {
        
        $cfg = self::ACC[$c][$t] ?? null;
        if (!$cfg) throw new Exception("invalid method, change providers");

        $params = array_merge([$cfg['url'] => $siteUrl], ($cfg['defaults'] ?? []), $extra);

        $fmt = self::ACC[$c]['_proxy_format'] ?? null;

        if (isset($params['proxy'])) {
            if ($params['proxy'] === 'empty' || $params['proxy'] === '') {
                unset($params['proxy'], $params['proxytype']);
            } elseif ($fmt) {
                $px = $params['proxy'];
                $u = parse_url($px);
                
                if ($u && !empty($u['host'])) {
                    $host = $u['host'];
                    $port = $u['port'] ?? 80;
                    $user = $u['user'] ?? '';
                    $pass = $u['pass'] ?? '';
                    $scheme = strtolower($u['scheme'] ?? self::PX_TYPE ?? 'http');

                    if ($fmt === 'uri') {
                        unset($params['proxytype']);
                        $auth = $user ? "{$user}:{$pass}@" : "";
                        $params['proxy'] = "{$scheme}://{$auth}{$host}:{$port}";
                    } elseif ($fmt === 'split') {
                        if (strpos(strtolower($c), 'xevil') !== false) {
                            $params['proxytype'] = $scheme;
                            $params['proxy'] = $user ? "{$host}:{$port}:{$user}:{$pass}" : "{$host}:{$port}";
                        } else {
                            unset($params['proxytype']); 
                            $auth = $user ? "{$user}:{$pass}@" : "";
                            $params['proxy'] = "{$auth}{$host}:{$port}";
                        }
                    } elseif ($fmt === 'object') {
                        $params['proxy'] = [
                            'hostname' => $host,
                            'port' => (int)$port,
                            'scheme'   => $scheme,
                            'username' => $user,
                            'password' => $pass
                        ];
                        unset($params['proxytype']);
                    }
                }
            }
        }

        if (!empty($cfg['map'])) {
            foreach ($cfg['map'] as $from => $to) {
                if (array_key_exists($from, $params)) {
                    $params[$to] = $params[$from];
                    unset($params[$from]);
                }
            }
        }
        
        #print_r($params);
        return [$cfg['api'], $params];
        
    }
    
    public const ERR = [

        'fail' => [ 
            'ERROR_CAPTCHA_UNSOLVABLE',
            "ERROR_CAPTCHA_SOLVE_FAILED",
            'ERROR_TASK_FAILED',
            'ERROR_CAPTCHA_TIMEOUT',
            'ERROR_TIMEOUT',
            'TIMEOUT after',
            'ERROR_TASK_TIMEOUT',
            'WRONG_RESULT',
            'TOTALLY_FAILED',
            'ERROR_NO_NODES_AVAILABLE',
            'ERROR_NO_SLOT_CONNECTION',
            'ERROR_NO_SLOT_AVAILABLE',
            'ERROR_INVALID_RESPONSE1',
            'ERROR_SOLVER_RESPONSE',
            'TASK_NOT_FOUND',
            'Job not found',
            'ERR_EMPTY_RESPONSE',
            'failed to respond',
            'INVALID_PARAMETERS',
            'SERVICE_BUSY',
            ],

        'ret' => [ 
            'ERROR_UNKNOWN_STATUS',
            'ERROR_REQUEST_COOLDOWN',
            'CAPTCHA_NOT_READY',
            'CAPCHA_NOT_READY',
            'ERROR_RATE_LIMIT',
            'ERROR_TOO_MANY_REQUESTS',
            'ERROR_INTERNAL_SERVER',
            'processing',
            'pending',
        ],

        'con' => [
            'ERROR_INTERNAL',
            'ERROR_BANNED',
            'ERROR_TASK_CREATION_FAILED',
            "ERROR_SOLVER_RESPONSE",
            'INTENAL_SERVER_ERROR',
            'INTERNAL_SERVER_ERROR',
            'ERROR_PROXY_CONNECTION_FAILED',
            'ERROR_CAPTCHA_SERVER_OFFLINE',
            'ERROR_SERVICE_UNAVALIABLE',
            'ERROR_SERVICE_UNAVAILABLE',
            'ERROR_PROXY_BANNED',
            'INTERNAL_SERVER_ERROR',
            'INTERNAL_SOLVER_ERROR',
            'External solver request failed',
            'returned HTTP 502',
        ],

        'err' => [
            'ERROR_IP_BANNED',
            'ERROR_TASK_TYPE_NOT_FOUND',
            'ERROR_PENDING_LIMIT_EXCEEDED',
            'ERROR_THREAD_LIMIT_EXCEEDED',
            'ERROR_TASK_NOT_FOUND',
            'WRONG_CAPTCHA_ID',
            'ERROR_WRONG_PAGEURL',
            'ERROR_SITEKEY_IS_INCORRECT',
            'SITEKEY_IS_INCORRECT',
            'ERROR_SITEKEY',
            'ERROR_INVALID_IMAGE',
            'ERROR_WRONG_DATA',
            'ERROR_INVALID_METHOD',
            'ERROR_METHOD_DOES_NOT_EXIST',
            'WRONG_METHOD',
            'ERROR_BAD_DATA',
            'ERROR_WRONG_ID_FORMAT',
            'ERROR_WRONG_CAPTCHA_ID',
            'ERROR_EMPTY_ACTION',
            'ERROR_INVALID_TASK_DATA',
            'ERROR_BAD_REQUEST',
            'ERROR_TASKID_INVALID',
            'ERROR_TASK_NOT_SUPPORTED',
            'ERROR_UNKNOWN_QUESTION',
            'ERROR_PARSE_IMAGE_FAIL',
            'WRONG_REQUESTS_LINK',
            'WRONG_LOAD_PAGEURL',
            'WRONG_COUNT_IMG',
            'TURNSTILE_NOT_FOUND',
            'HCAPTCHA_NOT_FOUND',
            'Missing domain',
            'Missing siteKey',
            'invalid mode',
            'Missing mode or action',
            'MISSING_FIELDS',
            'INVALID_TYPE',
            'INVALID_METHOD',
            'INVALID_PROXY',
            'INVALID_URL',
        ],

        'key' => [
            'ERROR_BILLING_EXPIRED',
            'ERROR_USER_NOT_FOUND',
            'ERROR_INVALID_KEY',
            'ERROR_KEY_EXPIRED',
            'ERROR_INSUFFICIENT_BALANCE',
            'ERROR_WRONG_USER_KEY',
            'ERROR_KEY_DOES_NOT_EXIST',
            'ERROR_ZERO_BALANCE',
            'ERROR_KEY_TEMP_BLOCKED',
            'ERROR_SETTLEMENT_FAILED',
            'ERROR_KEY_DENIED_ACCESS',
            'Insufficient token balance',
            'Invalid API key',
            'missing Key',
            'UNAUTHORIZED',
            'INVALID_KEY',
        ],

    ];

    public static function errType($msgOrCode) {
        $msg = trim($msgOrCode);
        if (empty($msg) || $msg === 'unknown') return 'ret';
        foreach (self::ERR as $type => $codes) {
            foreach ($codes as $c) {
                if (stripos($msg, $c) !== false) return $type;
            }
        }
        return false;
    }
    
} 
