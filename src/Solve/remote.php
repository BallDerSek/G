<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#Direct API Request 

final class Api { #contractor
    public const PX_AUTH = 'gamamoch-rotate:playernoob@p.webshare.io:80';
    public const PX_TYPE = 'socks5';
    public const PROXY = self::PX_TYPE . '://' . self::PX_AUTH;

    public const KEY = [
        'tertuyul' => ['ep' => 'http://tertuyul.my.id', 'cls' => tertuyul::class],
        'solverify' => ['ep' => 'https://solverify.net', 'cls' => solverify::class],
        'xevil' => ['ep' => 'Xevil_check_bot.t.me',  'cls' => xevil::class],
        'skibidixxx' => ['ep' => 'https://waryono.my.id', 'cls' => skibidixxx::class],
        'capsolver' => ['ep' => 'https://capsolver.com', 'cls' => capsolver::class],
        'multibot' => ['ep' => 'http://multibot.in', 'cls' => multibot::class],
        'gmxch' => ['ep' => 'https://gmxch-to.hf.space', 'cls' => gmxch::class],
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
            'cft' => [
                'k'=>'siteKey','url'=>'domain','api'=>'cloudflare', 'defaults' => ['method' => 'turnstile']
                ],
            'hc' => [
                'k'=>'siteKey','url'=>'domain','api'=>'hcaptcha'
                ],
            'rc2' => [
                'k'=>'siteKey','url'=>'domain','api'=>'recaptcha', 'defaults' => ['method' => 'v2']
                ],
            'rc3' => [
                'k'=>'siteKey','url'=>'domain','api'=>'recaptcha', 'defaults' => ['method' => 'v3']
                ],
            'pcc' => [
                'k'=>'cpobj','url'=>'domain','api'=>'pc_coinclix'
                ],
            'icc' => [
                'k'=>'cpobj','url'=>'domain','api'=>'ic_coinclix'
                ],
        ],

        tertuyul::class => [
            '_proxy_format' => 'split',
            'cft' => [
                'k' => 'sitekey','url' => 'pageurl','api' => 'turnstile'
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
                'k' => 'sitekey','url' => 'pageurl','api' => 'turnstile'
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
            'cft' => [
                'k' => 'sitekey', 'url' => 'domain', 'api' => 'turnstile'
                ],
            'rc2' => [
                'k' => 'sitekey', 'url' => 'domain', 'api' => 'recapv2'
                ],
            'rc3' => [
                'k' => 'sitekey', 'url' => 'domain', 'api' => 'recapv3'
                ],
        ],

        capsolver::class => [
            'cft' => [
                'k'=>'websiteKey','url'=>'websiteURL','api'=>'turnstile'
                ],
            'rc2' => [
                'k'=>'websiteKey','url'=>'websiteURL','api'=>'recaptcha2',
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
            'ocr' => ['t' => 'universal', 'field' => 'body'],
            'least' => ['t' => 'iconfinder', 'field' => 'body'],
            'rs_upside' => ['t' => 'upside', 'field' => 'body'],
            'upside' => ['t' => 'upside', 'field' => 'body'],
            'vie_upside' => ['t' => 'upside', 'field' => 'body'],
            'fa_icon' => ['t' => 'hunter', 'field' => 'body'],
            'icon_up' => ['t' => 'iconflip', 'field' => 'body'],
            'rs_icon' => ['t' => 'rscaptcha', 'field' => 'body'],
            'rs_slide' => ['t' => 'sliders', 'field' => 'body'],
        ],

        xevil::class => [
            'ocr' => ['t' => 'base64', 'field' => 'body'],
            'upside' => ['t' => 'viefaucet', 'field' => 'body'],
            'rs_upside' => ['t' => 'viefaucet', 'field' => 'body'],
            'vie_upside' => ['t' => 'viefaucet', 'field' => 'body'],
            'icon_up' => ['t' => 'iconupfinder', 'field' => 'body'],
            'rs_icon' => ['t' => 'rscaptcha', 'field' => 'body'],
        ],

        multibot::class => [
            'ocr' => ['t' => 'universal', 'field' => 'body'],
            'least' => ['t' => 'iconfinder', 'field' => 'body'],
            'rs_upside' => ['t' => 'upside', 'field' => 'body'],
            'upside' => ['t' => 'upside', 'field' => 'body'],
            'vie_upside' => ['t' => 'upside', 'field' => 'body'],
            'fa_icon' => ['t' => 'cls',  'field' => 'body', 'extra' => ['type' => 'Bitcotasks']],
            'icon_up' => ['t' => 'rscaptcha', 'field' => 'body'],
            'rs_icon' => ['t' => 'rscaptcha', 'field' => 'body'],
        ],

        skibidixxx::class => [
            'fa_icon' => ['t' => 'bitcocaptcha', 'field' => 'base64_str'],
            'fa3_icon' => ['t' => 'bitcocaptcha_v2', 'field' => 'base64_str'],
            'earnow' => ['t' => 'shortearnow', 'field' => 'base64_str'],
            'rs_upside' => ['t' => 'upsidedown_2', 'field' => 'base64_str'],
            'rs_icon' => ['t' => 'rsicon', 'field' => 'base64_str'],
            'ocr' => ['t' => 'image-to-text','field' => 'base64_str'],
            'least' => ['t' => 'least-icons', 'field' => 'base64_str'],
            'upside' => ['t' => 'upsidedown_1', 'field' => 'base64_str'],
            'vie_upside' => ['t' => 'upsidedown_3', 'field' => 'base64_str'],
        ],

        capsolver::class => [
            'ocr' => ['t' => 'ImageToTextTask', 'field' => 'body'],
        ],

        gmxch::class => [
            'fa3_icon' => ['t' => 'fa_3', 'field' => 'image'],
        ],

        solverify::class => [
            'ocr' => ['t' => 'ocr', 'field' => 'body'],
        ],
    ];
    
    public static function cfgB64($c, $t, $b64): array {
        $cfg = self::B64[$c][$t] ?? null;

        if (!is_array($cfg) || empty($cfg['t']) || empty($cfg['field'])) {
            throw new Exception("invalid method, change providers");
        }
        $extra = $cfg['extra'] ?? [];
        if ($extra && !is_array($extra)) $extra = [];

        return [$cfg['t'], [$cfg['field'] => $b64] + $extra];
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
            'interstitial' => [
                'api' => 'cloudflare','url' => 'domain', 'defaults' => ['method' => 'interstitial']
            ],
            'fingerprint' => [
                'api'  => 'fingerprint','url'  => 'domain','need' => ['userAgent'],
            ],
        ],
        
        tertuyul::class => [
            'interstitial' => [
                'api' => 'cloudflare','url' => 'domain', 'need' => ['proxy'], 
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
        
    ];
    
    public const ERR = [

        'fail' => [ 
            'ERROR_CAPTCHA_UNSOLVABLE',
            "ERROR_CAPTCHA_SOLVE_FAILED",
            'ERROR_TASK_FAILED',
            'ERROR_CAPTCHA_TIMEOUT',
            'ERROR_TIMEOUT',
            'ERROR_TASK_TIMEOUT',
            'WRONG_RESULT',
            'totally failed',
            'Timeout Error'
            ],

        'ret' => [ 
            'ERROR_UNKNOWN_STATUS',
            'ERROR_NO_NODES_AVAILABLE',
            'CAPCHA_NOT_READY',
            'CAPTCHA_NOT_READY',
            'ERROR_REQUEST_COOLDOWN',
            'ERROR_NO_SLOT_CONNECTION',
            'ERROR_RATE_LIMIT',
            'Task not found',
            'APP_11',
            'APP_14',
        ],

        'con' => [
            'ERROR_INTERNAL_SERVER',
            'ERROR_INTERNAL',
            'ERROR_BANNED',
            'ERROR_TASK_CREATION_FAILED',
            'INTENAL_SERVER_ERROR',
            'INTERNAL_SERVER_ERROR',
            'ERROR_PROXY_CONNECTION_FAILED',
            'ERROR_CAPTCHA_SERVER_OFFLINE',
            'ERROR_SERVICE_UNAVALIABLE',
            'ERROR_SERVICE_UNAVAILABLE',
            'ERROR_PROXY_BANNED',
            'APP_9',
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
            'APP_10',
            'APP_17',
            'APP_18',
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
            'Invalid Key',
            'missing Key',
            'APP_15',
            'APP_16',
            'APP_12',
        ],
    ];
    
    public static function errType($msgOrCode) {
        $code = trim(strtok($msgOrCode, " \t-")); 
        foreach (self::ERR as $type => $codes) {
            if (in_array($code, $codes, true)) return $type;
        }
        return false;
    }
    
} 

abstract class Provider {
    protected $apiKey;
    #protected $baseUrl;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    final protected function run($method, array $params) {
        return $this->call($method, $params);
    }

    final protected function call($method, array $params) {
        for ($i = 0; $i < 3; $i++) {
            try {
                return styler(static::class . "=>$method", function() use ($method, $params) {
                    $id = $this->get_api($method, $params);
                    return $this->res_api($id);
                });
            } catch (Throwable $e) {
                $code = $e->getMessage();
                $type = Api::errType($code);
                #var_dump($code);
                logx('err', $code);
                
                if (in_array($type, ['ret','con','fail'], true)) {
                    _sle(3); continue;
                }
                break;
            }
        }
        return false;
    }

    public function token($siteKey, $siteUrl, $type, array $extraParams = []) {
        try {
            [$method, $params] = Api::cfgTkn(static::class, $type, $siteKey, $siteUrl, $extraParams);
        } catch (Throwable $e) {
            logx('warn', $e->getMessage());
            return null;
        }
        return $this->run($method, $params);
    }

    public function base64($img, $type = 'ocr') {
        $raw = is_file($img) ? _get($img) : $img;
        $b64 = base64_encode($raw);
        try {
            [$m, $params] = Api::cfgB64(static::class, $type, $b64);
        } catch (Throwable $e) {
            logx('warn', $e->getMessage());
            return null;
        }
        return $this->run($m, $params);
    }

    public function access($siteUrl, $type, array $extraParams = []) {
        $cfg = Api::ACC[static::class][$type] ?? null;
        if (!$cfg) { logx('warn', "invalid method, change providers", true, true); return null; }

        foreach (($cfg['need'] ?? []) as $k) {
            if (!array_key_exists($k, $extraParams)) {
                logx('warn', "missing arg: $k");
                return null;
            }
        }

        $params = array_merge(
            [$cfg['url'] => $siteUrl],
            ($cfg['defaults'] ?? []),
            $extraParams
        );

        return $this->run($cfg['api'], $params);
    }
    
    public function atb(array $data) {
        $mode = defined('static::ATB_MODE') ? static::ATB_MODE : 'num';
        
        $pa = [];
        if (!empty($data['main'])) {
            $pa['main'] = $data['main'];
        }
        
        $map = [];
        if ($mode === 'rel') {
            foreach ($data['rels'] as $rel => $b64) {
                $pa[$rel] = $b64;
            }
        } else {
            $i = 1;
            foreach ($data['rels'] as $rel => $b64) {
                $pa[$i] = $b64;
                $map[$i] = $rel;
                $i++;
            }
        }
        #print_r($pa);
        
        $res = $this->run('antibot', $pa);
        logx('info', static::class.': '.$res ?: 'false');
        if (!$res) return false;
        $in = explode(',', $res);
        $links = '';
        if ($mode === 'rel') {
            $rels = array_keys($data['rels']);
            foreach ($in as $idx) {
                $idx = (int) trim($idx);
                if (isset($rels[$idx])) {
                    $links .= ' '.$rels[$idx];
                }
            }
        } else {
            foreach ($in as $idx) {
                $idx = (int) trim($idx);
                $idx++;
                if (isset($map[$idx])) {
                    $links .= ' '.$map[$idx];
                }
            }
        }
        #return trim($links);
        return $links;
    }

    abstract protected function get_api($method, array $params);
    
    abstract protected function res_api($jobId);
}