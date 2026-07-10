<?php

class Solve {

    private static function findField(array $pa, $needle) {
        foreach ($pa as $k => $v) {
            if (is_string($v) && stripos($v, $needle) !== false) return $k;
        }
        return null;
    }

    private static function init($context = null) {
        return $context ?? (inf::$context ?? []);
    }
    
    public static function exec($html, $host, ?Provider $api, $pa = null, $force = false, $context = null) {
        
            $ctx = self::init($context);
        
            $solution = [];
            $_cap = Capt::cha($html);
            
            #var_dump($_cap); die;
            
            $_fields = null;
            $_select = '';
            $hardSolved = false;
        
            if (is_array($pa)) {
        
                $_option = null;
                $_foundField = null;
        
                foreach ($pa as $key => $val) {
                    if (str_contains(strtolower($key), 'captcha')) {
        
                        if (is_array($val)) {
                            $_option = $val;
                        } else {
                            $_foundField = $key;
                        }
                    }
                }
        
                $_fields = $_foundField;
        
                if ($_option === null && $_foundField) {
                    $_option = [$pa[$_foundField]];
                }
        
                if (!empty($_option)) {
        
                    $pref = ['turnstile','hcaptcha','recaptcha','shield','rot','smart'];
        
                    foreach ($pref as $p) {
                        foreach ($_option as $opt) {
        
                            if (str_contains(
                                strtolower(str_replace(['-','_'], '', $opt)),
                                $p
                            )) {
                                $_select = $opt;
                                break 2;
                            }
                        }
                    }
        
                    $_select = $_select ?: (is_array($_option) ? $_option[0] : $_option);
                }
        
            } else {
                $_select = (string)$pa;
                $_fields = 'captcha';
            }
        
            if ($_fields && $_select) $solution[$_fields] = $_select;
            
            if ($_select) {
                $_checks = str_replace(['-', '_'], '', strtolower($_select));
            
                switch ($_checks) {
                    case 'shield':
                        if (isset($pa['shield_answer'])) {
                            $res = sCaptcha::shield($html);
                            if ($res) {
                                $solution = array_merge($solution, $res);
                                $hardSolved = true;
                            }
                        }
                        break;
            
                    case 'rot':
                    case 'rotcaptcha':
                        if (isset($pa['rot_captcha_val'])) {
                            $res = sCaptcha::rotate($html);
                            if ($res) {
                                $solution = array_merge($solution, $res);
                                $hardSolved = true;
                            }
                        }
                        break;
            
                    case 'smart':
                    case 'smartcaptcha':
                        if (isset($pa['smart_token'])) {
                            $res = locally::smartFP($html);
                            if ($res) {
                                $solution['smart_token'] = $res;
                                $hardSolved = true;
                            }
                        }
                        break;
                }
            }
            
            if (!empty($_cap['antibot'])) {
        
                $resAtb = AntibotLinks::exec(
                    $_cap['antibot']['type'],
                    $api,
                    $_cap['antibot']['data'] ?? [],
                    $force
                );
        
                if ($resAtb === 77) return ['trouble' => 'reload'];
                if ($resAtb) $solution['antibotlinks'] = $resAtb;
            }
        
            if (isset($_cap['ucaptcha'])) {
        
                $ucap_ctx = array_merge($ctx ?? [], [
                    'host' => $host,
                    'html' => $html
                ]);
        
                $ucap_res = Retry::untilArray(
                    fn() => (new uCaptcha($ucap_ctx))->exec($_cap['ucaptcha']),
                    3, 1
                );
        
                if (!$ucap_res) return ['trouble' => 'reload'];
        
                $solution += $ucap_res;
                $hardSolved = true;
            }
        
            elseif (isset($_cap['rss'])) {
        
                $ctx2 = array_merge($ctx ?? [], [
                    'host' => $host,
                    'html' => $html
                ]);
        
                $rss_res = Retry::until(
                    fn() => (new rsCaptcha($ctx2))->exec($_cap['rss'], $api, $html),
                    3, 1
                );
        
                if (!$rss_res || !is_array($rss_res)) {
                    return ['trouble' => 'reload'];
                }
        
                $solution += $rss_res;
                $hardSolved = true;
        
                $found = self::findField($pa ?? [], 'rscaptcha');
                if ($found) {
                    $solution[$found] = $pa[$found];
                } elseif ($_fields) {
                    $solution[$_fields] = 'rscaptcha';
                }
            }
        
            elseif (isset($_cap['ic_fw'])) {
        
                $data = [
                    'token' => $_cap['ic_fw']['keys'],
                    'endpoint' => $_cap['ic_fw']['url'],
                ];
        
                $ic = Retry::until(
                    fn() => locally::iCaptcha($host, $data, $ctx),
                    3, 1
                );
        
                if (is_array($ic) && isset($ic['__proxy__'])) {
                    return ['trouble' => 'proxy'];
                }
        
                if (!$ic || !is_array($ic)) {
                    return ['trouble' => 'reload'];
                }
        
                $solution += $ic;
                $hardSolved = true;
            }
        
            if ($api && !$hardSolved) {
            
                $priority = ['cft','rc3','rc2','hc'];
            
                foreach ($priority as $t) {
            
                    if (!isset($_cap[$t])) {
                        continue;
                    }
            
                    $_ke = $_cap[$t]['keys'] ?? null;
                    $_ty = $_cap[$t]['type'] ?? $t;
                    $_ex = array_filter($_cap[$t]['extra'] ?? [], fn($v) => $v !== null);
            
                    if (!$_ke) continue;
            
                    $token = self::tkn(
                        $api,
                        $host,
                        $_ke,
                        $_ty,
                        $_ex,
                        $ctx
                    );
            
                    if (isset($token['fail'])) {
            
                        if ($token['fail'] === 471) continue;
            
                        if ($token['fail'] === 404) return ['trouble' => 'reload'];
            
                        continue;
                    }
            
                    if (!empty($token['done'])) {
            
                        $solution += [
                            'g-recaptcha-response' => $token['done'],
                            'cf-turnstile-response' => $token['done'],
                            'h-captcha-response' => $token['done'],
                        ];
            
                        break;
                    }
                }
            }
        
            if (!$api) die(Logger::X('err', 'undefined provider'));
            
            if (empty($solution) && empty($_cap)) return ['nocaptcha' => true];
        
            return $solution;
        }
    
    public static function tkn($api, $host, $key, $type, array $data = [], $ctx = []) {
    
        $solver = config::getKeys($api, $type);
    
        $Params = array_merge($data, ['userAgent' => $ctx['uagent'] ?? '']);
    
        $t = Retry::until(function() use ($solver, $api, $key, $host, $type, $Params) {
    
            $t = $solver->token($key, $host, $type, $Params);
            
            #var_dump($t); die;
            
            if (isset($t['fail']) && $t['fail'] === 777) {
    
                if (!isset(Api::TKN[get_class($api)][$type])) {
                    return ['fail' => 471];
                }
    
                $t = $api->token($key, $host, $type, $Params);
    
                if (isset($t['fail'])) {
    
                    if ($t['fail'] === 71) return ['fail' => 471];
    
                    return ['fail' => 404];
                }
    
                return $t;
            }
    
            if (isset($t['fail'])) {
    
                if ($t['fail'] === 71) {
                    return ['fail' => 471];
                }
    
                return ['fail' => 404];
            }
    
            return $t;
    
        }, 2, 1);
    
        if ($t === false) {
            return ['fail' => 404];
        }
        
        #var_dump($t);
        
        return $t;
    }
    
    public static function img($api, $host, $type, $img, array $extra = []) {
    
        $solver = config::getKeys($api, $type, 'b64');
    
        $res = Retry::until(function() use ($solver, $api, $img, $type, $extra) {
    
            $res = isset(Api::B64[get_class($solver)][$type])
                ? $solver->base64($img, $type, $extra)
                : ['fail' => 777];
    
            if (isset($res['fail']) && $res['fail'] === 777) {
    
                if (!isset(Api::B64[get_class($api)][$type])) {
                    return ['trouble' => 'reload'];
                }
    
                $res = $api->base64($img, $type, $extra);
    
                if (isset($res['fail']) && $res['fail'] === 71) {
                    return ['trouble' => 'reload'];
                }
            }
    
            if (isset($res['fail'])) {
                return ['trouble' => 'reload'];
            }
    
            return $res['done'];
    
        }, 2, 1);
    
        if ($res === false) {
            return ['trouble' => 'reload'];
        }
    
        return $res;
    }

}
