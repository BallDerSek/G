<?php

class Solve {

    public static function exec($html, $host, ?Provider $api, $pa = null, $force = false, $context = null) {

        $ctx = self::init($context);

        $solution = [];
        $_cap = Capt::cha($html);

        $_fields = null;
        $_select = '';
        $captchaFields = [];
        $hardSolved = false;

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

            $_fields = $_foundField ?? null;

            if ($_option === null && $_foundField !== null) {
                $_option = [$pa[$_foundField]];
            }

            if (!empty($_option)) {

                $pref = [
                    'turnstile',
                    'hcaptcha',
                    'recaptcha',
                    'shield',
                    'rot',
                    'smart'
                ];

                foreach ($pref as $p) {
                    foreach ($_option as $opt) {
                        if (str_contains(strtolower(str_replace(['-', '_'], '', $opt)), $p)) {
                            $_select = $opt;
                            break 2;
                        }
                    }
                }

                if (!$_select) {
                    $_select = is_array($_option) ? $_option[0] : $_option;
                }
            }

        } else {
            $_select = (string)$pa;
            $_fields = 'captcha';
            $captchaFields[] = $_fields;
        }

        if ($_fields && $_select) {
            $solution[$_fields] = $_select;
        }

        if (!empty($_cap['antibot'])) {

            $atbData = $_cap['antibot']['data'] ?? [];
            $resAtb = locally::ATB($_cap['antibot']['type'], $api, $html, $force, $atbData);

            if ($resAtb === 77) {
                return ['trouble' => 'reload'];
            }

            if ($resAtb) {
                $solution['antibotlinks'] = $resAtb;
            }
        }

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

                case 'rotcaptcha':
                case 'rot':
                    if (isset($pa['rot_captcha_val'])) {
                        $res = sCaptcha::rotate($html);
                        if ($res) {
                            $solution = array_merge($solution, $res);
                            $hardSolved = true;
                        }
                    }
                    break;

                case 'smartcaptcha':
                case 'smart':
                    if (isset($pa['smart_token'])) {
                        $res = locally::smartFP($html);
                        if ($res) $solution['smart_token'] = $res;
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
                if ($attempt > 0) _sle(1);

                $ic = locally::iCaptcha($host, $data, $ctx);

                if ($ic === 99) {
                    return ['trouble' => 'proxy'];
                }

                $attempt++;
            }

            if ($ic) {

                $solution = array_merge($solution, $ic);
                $hardSolved = true;

                $found = null;

                foreach ($pa as $key => $val) {
                    if (is_string($val) &&
                        (stripos($val, 'icaptcha') !== false || stripos($key, 'icon') !== false)) {
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

            $ucap_res = null;
            $attempt = 0;

            while (!$ucap_res && $attempt < 3) {

                if ($attempt > 0) _sle(1);

                $ucap_res = self::ucap($_cap['ucaptcha'], $host, $html, $ctx);

                if (!is_array($ucap_res)) $attempt++;
            }

            if (is_array($ucap_res)) {
                $hardSolved = true;
                $solution = array_merge($solution, $ucap_res);
            } else {
                return ['trouble' => 'reload'];
            }
        }

        elseif (isset($_cap['rss'])) {

            $rss_res = self::rss($_cap['rss'], $api, $host, $html, $ctx);

            if (is_array($rss_res)) {

                $solution = array_merge($solution, $rss_res);
                $hardSolved = true;

                $found = null;

                foreach ($pa as $key => $val) {
                    if (is_string($val) && stripos($val, 'rscaptcha') !== false) {
                        $found = $key;
                        break;
                    }
                }

                if ($found) {
                    $solution[$found] = $pa[$found];
                } elseif (!empty($_fields)) {
                    $solution[$_fields] = 'rscaptcha';
                }

            } else {
                return ['trouble' => 'reload'];
            }
        }

        if ($api && !$hardSolved) {

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

                $token = self::tkn($api, $host, $_ke, $_ty, $_ex, $ctx);

                if ($token === 471) continue;
                if ($token === 404) return ['trouble' => 'reload'];

                if (is_string($token) && $token !== '') {

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
        }

        elseif (!$api) {
            die(Logger::X('err', 'undefined provider'));
        }

        if (empty($solution) && empty($_cap)) {
            logx('info', 'no captcha detected');
            return ['nocaptcha' => true];
        }

        return $solution;
    }

    public static function tkn($api, $host, $key, $type, array $data = [], $ctx = []) {

        $solver = config::getKeys($api, $type);
        $t = null;

        $Params = array_merge($data, [
            'userAgent' => $ctx['uagent'] ?? ''
        ]);

        for ($retry = 0; $retry < 2; $retry++) {

            $t = $solver->token($key, $host, $type, $Params);

            if ($t === 777) {

                if (!isset(Api::TKN[get_class($api)][$type])) return 471;

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

        #if ($api instanceof Provider) $api->getInfo();

        return $t;
    }

    public static function img($api, $host, $type, $img) {

        $solver = config::getKeys($api, $type, 'b64');

        $res = null;

        for ($retry = 0; $retry < 2; $retry++) {

            $res = isset(Api::B64[get_class($solver)][$type])
                ? $solver->base64($img, $type)
                : 777;

            if ($res === 777) {

                if (!isset(Api::B64[get_class($api)][$type])) {
                    return ['trouble' => 'reload'];
                }

                $res = $api->base64($img, $type);

                if ($res === 71) return ['trouble' => 'reload'];

                if ($res && $res !== 777) {
                    #if ($api instanceof Provider) $api->getInfo();
                    break;
                }
            }

            if (in_array($res, [71, 77], true)) {
                return ['trouble' => 'reload'];
            }

            if ($res && $res !== 777) break;

            _sle(1);
        }

        return ($res && $res !== 777)
            ? $res
            : ['trouble' => 'reload'];
    }

    private static function rss($rss, $api, $host, $html, $ctx) {

        $utils = ['host' => $host, 'html' => $html];
        $ctx = array_merge($ctx ?? [], $utils);

        return (new rsCaptcha($ctx))->exec($rss, $api, $html);
    }

    private static function ucap($ucap, $host, $html, $ctx) {

        $utils = ['host' => $host, 'html' => $html];
        $ctx = array_merge($ctx ?? [], $utils);

        return (new uCaptcha($ctx))->exec($ucap);
    }

    private static function init($context = null) {
        return $context ?? (inf::$context ?? []);
    }

}
