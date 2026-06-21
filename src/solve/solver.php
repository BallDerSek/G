<?php

class Solve {
    public static $ua;
    public static $ck;
    public static $ip;
    public static $in;
    public static $context;
    
    public static function exec($html, $host, ?Provider $api, $pa = null, $force = false) {
        
        #return [];
        
        
        self::$context = inf::$context;
        
        $solution = [];
        $_cap = Capt::cha($html);
        
        #var_dump($_cap); die;
        
        $_fields = null; 
        $_select = '';
        $captchaFields = [];

        if (is_array($pa)) {
            $_option = null;
            $_foundField = null;
            foreach ($pa as $key => $val) {
                if (str_contains(strtolower($key), 'captcha')) {
                    
                    $captchaFields[] = $key;
                    if (is_array($val)) {
                        $_option = $val;
                    } else {
                        $_foundField = $key;
                    }
                }
            }
            $_fields = $_foundField ?? $_fields;
            if ($_option === null && $_foundField !== null) {
                $_option = [$pa[$_foundField]];
            }
            if (!empty($_option)) {
                $pref = ['shield', 'rot', 'smart', 'turnstile', 'hcaptcha', 'recaptcha'];
                foreach ($pref as $p) {
                    foreach ($_option as $opt) {
                        if (str_contains(str_replace(['-', '_'], '', strtolower($opt)), $p)) {
                            $_select = $opt;
                            break 2;
                        }
                    }
                }
                if (!$_select) $_select = $_option[0];
            }

        } else {
            $_select = (string)$pa;
            $_fields = 'captcha';
            $captchaFields[] = $_fields;
        }

        if ($_fields && $_select) {
            $solution[$_fields] = $_select;
            
            if (is_array($pa)) {
                foreach ($pa as $key => $val) {
                    if (str_contains(strtolower($key), 'captcha')) {
                        if ($key === $_fields) {
                            $solution[$key] = $_select;
                        } else {
                            $solution[$key] = $_select;
                        }
                    }
                }
            }
        }

        if (!empty($_cap['antibot'])) {
            $atbData = $_cap['antibot']['data'] ?? [];
            $resAtb = locally::ATB($_cap['antibot']['type'], $api, $html, $force, $atbData);
            if ($resAtb === 77) return ['trouble' => 'reload'];
            if ($resAtb) $solution['antibotlinks'] = $resAtb;
        }
        
        if ($_select) {
            $_checks = str_replace(['-', '_'], '', strtolower($_select));
            
            switch ($_checks) {
                case 'shield':
                    if (isset($pa['shield_answer'])) {
                        $resShi = sCaptcha::shield($html);
                        if ($resShi) $solution = array_merge($solution, $resShi);
                    }
                    break;
                
                case 'rotcaptcha':
                case 'rot':
                    if (isset($pa['rot_captcha_val'])) {
                        $resRot = sCaptcha::rotate($html);
                        if ($resRot) $solution = array_merge($solution, $resRot);
                    }
                    break;
                
                case 'smartcaptcha':
                case 'smart':
                    if (isset($pa['smart_token'])) {
                        $resSmt = locally::smartFP($html);
                        if ($resSmt) $solution['smart_token'] = $resSmt;
                    }
                    break;
            }
        }
        
        
        if (isset($_cap['ic_fw'])) {
            $data = [
                'token' => $_cap['ic_fw']['keys'],
                'endpoint' => $_cap['ic_fw']['url'],
            ];
            
            $ic = null; 
            $attempt = 0;
            while (!$ic && $attempt < 3) {
                if ($attempt > 0) _sle(1); // Jeda sebelum retry
                $ic = locally::iCaptcha($host, $data, self::$context);
                if ($ic === 99) return ['trouble' => 'proxy'];
                $attempt++;
            }
            
            if ($ic) {
                $solution = array_merge($solution, $ic);
                
                $found = null;
                foreach ($pa as $key => $val) {
                    if (stripos($val, 'icaptcha') !== false || stripos($key, 'icon') !== false) {
                        $found = $key;
                        break;
                    }
                }
                
                if ($found) {
                    $solution[$found] = $pa[$found];
                } elseif (!empty($_fields)) {
                    $solution[$_fields] = 'icaptcha';
                }
            } else {
                return ['trouble' => 'reload'];
            }
        }
        
        elseif (isset($_cap['ucaptcha'])) {
            $utype = $_cap['ucaptcha']['mods'];
            $ucap_res = null;
            $attempt = 0;
            while (!$ucap_res && $attempt < 3) {
                if ($attempt > 0) _sle(1); // Jeda sebelum retry
                $ucap_res = self::ucap($_cap['ucaptcha'], $host, $html);
                if (!is_array($ucap_res)) $attempt++;
            }
            
            if (is_array($ucap_res)) {
                $solution = array_merge($solution, $ucap_res);
            } else {
                return ['trouble' => 'reload'];
            }
        }
        
        elseif (isset($_cap['rss'])) {
            $rss_res = self::rss($_cap['rss'], $api, $host, $html);
            if (is_array($rss_res)) {
                $solution = array_merge($solution, $rss_res);
                $found = null;
                foreach ($pa as $key => $val) {
                    if (stripos($val, 'rscaptcha') !== false) {
                        $found = $key;
                        break;
                    }
                }
                
                if ($found) $solution[$found] = $pa[$found];
                elseif (!empty($_fields)) {
                    $solution[$_fields] = 'rscaptcha';
                }
            } else {
                return ['trouble' => 'reload'];
            }
        }
        
        
        $ignoreFields = array_merge(['antibotlinks'], $captchaFields);
        $mainSolved = count(array_diff(array_keys($solution), $ignoreFields)) > 0;

        if ($api && !$mainSolved) {
            $priority = [];
            $lowType = str_replace(['-', '_'], '', strtolower($_select));

            if (str_contains($lowType, 'turnstile')) {
                $priority = ['cft'];
            } elseif (str_contains($lowType, 'hcaptcha') || str_contains($lowType, 'hc')) {
                $priority = ['hc'];
            } elseif (str_contains($lowType, 'recaptcha')) {
                $priority = ['rc3', 'rc2'];
            } else {
                $priority = ['cft', 'rc3', 'rc2', 'hc'];
            }

            foreach ($priority as $t) {
                if (!isset($_cap[$t])) continue;
                
                $_ty = $_cap[$t]['type'] ?? $t; 
                $_ke = $_cap[$t]['keys'] ?? null;
                $_ex = array_filter($_cap[$t]['extra'] ?? [], fn($v) => !is_null($v));

                if (!$_ke) continue;
                
                $token = self::tkn($api, $host, $_ke, $_ty, $_ex);
                if ($token === 471) continue; 
                if ($token === 404) return ['trouble' => 'reload']; 
                
                if (is_string($token) && !empty($token)) {
                    $solution = array_merge($solution, [
                        'g-recaptcha-response'    => $token,
                        'cf-turnstile-response'   => $token,
                        'h-captcha-response'      => $token,
                        'hcaptcha-response'       => $token,
                        'g-recaptcha-response-v3' => $token
                    ]);
                    break; 
                }
            }
        } elseif (!$api) die(Logger::X('err', 'undefined provider'));

        if (empty($solution) && empty($_cap)) {
            logx('info', 'no captcha detected');
            return ['nocaptcha' => true];
        }

        return !empty($solution) ? $solution : [];
    }

    public static function tkn($api, $host, $key, $type, array $data = []) {
        $solver = config::getKeys($api, $type); 
        
        #print(DIMM.BOLD.ITAL.FGo['MAG']."solving  ".RSET);
        $t = null;
        
        $Params = array_merge($data, ['userAgent' => self::$context['uagent'] ?? '']);
        for ($retry = 0; $retry < 2; $retry++) {
            $t = $solver->token($key, $host, $type, $Params);
            
            if ($t === 777) {
                if (!isset(Api::TKN[get_class($api)][$type])) return 471; 
                logx('ok', "Switching to ".get_class($api));
                $t = $api->token($key, $host, $type, $Params);
                
                if ($t === 71) return 471;
                if (in_array($t, [77, false], true)) return 404;
                if ($t) break;
            }
            
            if ($t === 71) return 471;
            if ($t === 77) return 404;
            if ($t) break; 
            
            _sle(1);
        }
        
        if ($t === false) return 404;
        
        if ($api instanceof Provider) $api->getInfo();
        return $t;
    }

    public static function img($api, $host, $type, $img) {
        $solver = config::getKeys($api, $type, 'b64');
        
        #print(DIMM.BOLD.ITAL.FGo['MAG']."solving  ".RSET);
        $res = null;
        
        for ($retry = 0; $retry < 2; $retry++) {
            $res = isset(Api::B64[get_class($solver)][$type]) ? $solver->base64($img, $type) : 777;
            
            if ($res === 777) {
                if (!isset(Api::B64[get_class($api)][$type])) return ['trouble' => 'reload'];
                
                logx('ok', "Switching to " . get_class($api));
                $res = $api->base64($img, $type);
                
                if ($res === 71) return ['trouble' => 'reload'];
                if ($res && $res !== 777) {
                    if ($api instanceof Provider) $api->getInfo();
                    break;
                }
            }
            
            if (in_array($res, [71, 77], true)) {
                if ($res === 71) logx('err', 'unsupported provider');
                return ['trouble' => 'reload'];
            }
            
            if ($res && $res !== 777) break;
            _sle(1);
        }
        
        if ($res && $res !== 777) return $res; 
        
        return ['trouble' => 'reload'];
    }

    private static function rss($rss, $api, $host, $html) {
        
        $utils = ['host' => $host, 'html' => $html];
        $ctx = array_merge(self::$context, $utils);
        return (new rsCaptcha($ctx))->exec($rss, $api, $html);
    }

    private static function ucap($ucap, $host, $html) {
        $utils = ['host' => $host, 'html' => $html];
        $ctx = array_merge(self::$context, $utils);
        
        #var_dump($ctx); die;
        return (new uCaptcha($ctx))->exec($ucap);
    }

}
