<?php

class Capt {

    private static function _sitekey($type, $source) {

        $patterns = [
            'cft' => [
                '/data-sitekey=["\']?(0x[a-zA-Z0-9_-]+)/',
                '/turnstileSiteKey\s*[=:]\s*["\']?(0x[a-zA-Z0-9_-]+)/',
                '/sitekey\s*[=:]\s*["\']?(0x[a-zA-Z0-9_-]+)/i',
                '/turnstile\.render\s*$[^)]*sitekey\s*[=:]\s*["\']?(0x[a-zA-Z0-9_-]+)/',
                '/["\']sitekey["\']\s*:\s*["\']?(0x[a-zA-Z0-9_-]+)/',
            ],
            'hc' => [
                '/data-sitekey=["\']([a-zA-Z0-9_-]{20,})["\']/',
                '/hcaptchaSiteKey\s*[=:]\s*["\']?([a-zA-Z0-9_-]{20,})/',
                '/site-key=["\']([a-zA-Z0-9_-]{20,})/',
                '/["\']sitekey["\']\s*:\s*["\']([a-zA-Z0-9_-]{20,})/',
            ],
            'rc2' => [
                '/data-sitekey=["\']([a-zA-Z0-9_-]{20,})["\']/',
                '/recaptchaSiteKey\s*[=:]\s*["\']?([a-zA-Z0-9_-]{20,})/',
                '/["\']sitekey["\']\s*:\s*["\']([a-zA-Z0-9_-]{20,})/',
            ],
            'rc3' => [
                '/render=([a-zA-Z0-9_-]{20,})/',
                '/recaptchaSiteKey\s*[=:]\s*["\']?([a-zA-Z0-9_-]{20,})/',
            ],
        ];

        foreach ($patterns[$type] ?? [] as $rx) {
            if (preg_match($rx, $source, $m)) return $m[1];
        }

        return null;
    }

    public static function cha($html) {
        if (empty($html)) return null;
        
        $xp = Scraper::dom($html);
        $sc = Scraper::_sC($html);
        $found = [];
        
        $allJs = implode('', array_merge($sc['external'], $sc['inline']));

        $has_turnstile = str_contains($allJs, 'challenges.cloudflare.com/turnstile') || str_contains($allJs, 'turnstileSiteKey') || str_contains($html, 'data-captcha="turnstile"');

        $has_hcaptcha = str_contains($allJs, 'hcaptcha.com/1/api.js') || str_contains($allJs, 'hcaptchaSiteKey');

        $has_recaptcha = str_contains($allJs, 'google.com/recaptcha/api.js') || str_contains($allJs, 'recaptchaSiteKey');
        
        // uCaptcha
        $is_anticap = 
            str_contains($html, 'anti-captcha') ||
            str_contains($html, 'anti_captcha') ||
            str_contains($html, 'data-id="anticap-box"') ||
            str_contains($html, 'data-id="anticap-root"') ||
            str_contains($html, 'anti_captcha_selected_icon') ||
            str_contains($allJs, 'anticap') ||
            (str_contains($allJs, 'ApiKey') && str_contains($allJs, 'SecretKey'));
            
        if ($is_anticap) {
            $ac_api    = Scraper::_jP($html, '/const\s+ApiKey\s*=\s*["\']([^"\']+)["\']/');
            $ac_secret = Scraper::_jP($html, '/const\s+SecretKey\s*=\s*["\']([^"\']+)["\']/');
            $ac_appUrl = Scraper::_jP($html, '/const\s+appUrl\s*=\s*["\']([^"\']+)["\']/');
            $icons     = Scraper::_xP($xp, "//div[@data-id='anticap-grid']//img/@src") ?: [];
            
            $key = $ac_api[1][0] ?? null;
            $sec = $ac_secret[1][0] ?? null;
            $app = $ac_appUrl[1][0] ?? null;
            
            $js_urls = [];
            if ($key === null || $sec === null) {
                foreach ($sc['external'] as $src) {
                    if (str_contains($src, 'anticap') || str_contains($src, 'app.js')) {
                        $js_urls[] = $src;
                    }
                }
                foreach ($sc['inline'] as $content) {
                    if (str_contains($content, 'ApiKey') || str_contains($content, 'SecretKey')) {
                        if (preg_match('/ApiKey\s*=\s*["\']([^"\']+)["\']/', $content, $m)) $key = $m[1];
                        if (preg_match('/SecretKey\s*=\s*["\']([^"\']+)["\']/', $content, $m)) $sec = $m[1];
                    }
                }
            }
            
            $found['ucaptcha'] = [
                'mods' => 'anti_captcha',
                'type'  => 'match',
                'keys'  => $key,
                'extra' => [
                    'sec' => $sec,
                    'app' => $app,
                    'icn' => !empty($icons) ? $icons : null,
                    'js' => !empty($js_urls) ? $js_urls : null,
                ]
            ];
        }
        
        $is_ucaptcha = 
            str_contains($html, 'upside-captcha-widget') ||
            str_contains($html, 'data-ucaptcha="widget"') ||
            str_contains($html, 'upside_captcha') ||
            str_contains($html, 'upside/css/captcha.css') ||
            str_contains($allJs, 'UCaptchaPos') ||
            (str_contains($allJs, 'flipped') && str_contains($allJs, 'ucaptcha'));
        
        if ($is_ucaptcha) {
            $js_urls = [];
            foreach ($sc['external'] as $src) {
                if (str_contains($src, 'source/app.js') || str_contains($src, 'captcha.js')) {
                    $js_urls[] = $src;
                }
            }
            
            $found['ucaptcha'] = [
                'mods' => 'upside_captcha',
                'type' => 'upside',
                'keys' => null,
                'extra' => [
                    'js' => !empty($js_urls) ? $js_urls : null,
                ]
            ];
        }
        
        // hCaptcha
        if ($has_hcaptcha) {
            $hc = Scraper::find($xp, 'h-captcha', '*', 'data-sitekey')
               ?? Scraper::_xP($xp, "//div[contains(@class,'h-captcha')]/@data-sitekey")
               ?? Scraper::_xP($xp, "//h-captcha/@site-key");

            $key = (!empty($hc) && isset($hc[0])) ? $hc[0] : null;

            if (!$key) $key = self::_sitekey('hc', $allJs);
            if (!$key) $key = self::_sitekey('hc', $html);

            if ($key) {
                $found['hc'] = [
                    'type'  => 'hc',
                    'keys'  => $key,
                    'extra' => ['invisible' => str_contains($html, 'data-size="invisible"')]
                ];
            }
        }
        
        // adsLab
        $is_alcaptcha = 
            str_contains($html, 'adslab-captcha') ||
            str_contains($html, 'adslab_pro') ||
            str_contains($html, 'alcaptcha/widget.js') ||
            (str_contains($allJs, 'alcaptcha') && str_contains($allJs, 'adslab.me'));
            
        if ($is_alcaptcha) {
            $alckey = Scraper::find($xp, 'adslab', '*', 'data-sitekey')
               ?? Scraper::_xP($xp, "//div[contains(@class,'adslab-captcha')]/@data-sitekey")
               ?? Scraper::_xP($xp, "//adslab-captcha/@site-key");
               
            $alcsid = Scraper::_xP($xp, "//div[contains(@class,'adslab-captcha')]/@data-sub-id")
               ?? Scraper::find($xp, 'adslab', '*', 'data-sub_id')
               ?? Scraper::_xP($xp, "//div[contains(@class,'adslab-captcha')]/@data-subid")
               ?? Scraper::_xP($xp, "//adslab-captcha/@sub-id");
           
            $js_urls = [];
            foreach ($sc['external'] as $src) {
                if (str_contains($src, 'widget.js') && str_contains($src, 'adslab.me')) {
                    $js_urls[] = $src;
                }
            }
            
            $found['adslab'] = [
                'keys' => (!empty($alckey) && isset($alckey[0])) ? $alckey[0] : null,
                'sid' => (!empty($alcsid) && isset($alcsid[0])) ? $alcsid[0] : null,
                'version' => null,
                'extra' => [
                    'js' => !empty($js_urls) ? $js_urls[0] : null,
                ]
            ];
            
        }
        
        // turnstile
        if ($has_turnstile) {
            $cft = array_filter(array_merge(
                Scraper::_xP($xp, "//div[contains(@class,'cf-turnstile')]/@data-sitekey") ?: [],
                Scraper::_xP($xp, "//*[@data-sitekey][contains(@id,'cf-turnstile')]/@data-sitekey") ?: [],
                Scraper::_xP($xp, "//div[contains(@class,'g-recaptcha')]/@data-sitekey") ?: []
            ));

            $key = !empty($cft) ? array_values(array_unique($cft))[0] : null;

            if (!$key) $key = self::_sitekey('cft', $allJs);
            if (!$key) $key = self::_sitekey('cft', $html);

            if ($key) {
                $mCda = Scraper::_jP($html, "/cdata\s*:\s*['\"]([^'\"]+)['\"]/");
                $found['cft'] = [
                    'type'  => 'cft',
                    'keys'  => $key,
                    'extra' => ['cdata' => $mCda[1][0] ?? null]
                ];
            }
        }
        
        // reCaptcha2
        if ($has_recaptcha) {
            $v2 = Scraper::find($xp, 'g-recaptcha', 'div', 'data-sitekey')
               ?? Scraper::find($xp, 'sitekey', '*', 'data-sitekey');

            $key = (!empty($v2) && isset($v2[0])) ? $v2[0] : null;

            if (!$key) $key = self::_sitekey('rc2', $allJs) ?? self::_sitekey('rc2', $html);

            if ($key) {
                $found['rc2'] = [
                    'type' => 'rc2',
                    'keys' => $key,
                    'extra' => [
                        'invisible' => str_contains($html, 'data-size="invisible"'),
                        'data-s'    => Scraper::find($xp, 'data-s', '*', 'data-s')[0] ?? null
                    ]
                ];
            }
        }
        
        // reCaptcha3
        $v3_raw = [];
        foreach ($sc['external'] as $src) {
            if (preg_match('/render=([^&]+)/', $src, $m)) {
                if (strlen($m[1]) > 20 && $m[1] !== 'explicit') {
                    $v3_raw[] = $m[1];
                }
            }
        }
        
        if (preg_match_all('/grecaptcha\.execute$\s*[\'"]([^\'"]+)/', $html, $m)) {
            $v3_raw = array_merge($v3_raw, $m[1]);
        }
        
        $v3_keys = array_values(array_unique($v3_raw));
        if (!empty($v3_keys)) {
            $mAct = Scraper::_jP($html, "/action\s*[:=]\s*['\"]((?!http)[^'\"]+)['\"]/");
            $found['rc3'] = [
                'type' => 'rc3',
                'keys' => $v3_keys[0],
                'extra' => ['action' => $mAct[1][0] ?? 'homepage']
            ];
        }
        
        // iCaptcha
        if (str_contains($html, 'iconcaptcha-widget') || str_contains($html, '_iconcaptcha-token')) {
            $endpoint = null;
            if (preg_match("~IconCaptcha\.init.*?endpoint\s*:\s*['\"]([^'\"]+)['\"]~is", $html, $m)) $endpoint = $m[1];
            
            $found['ic_fw'] = [
                'keys' => Scraper::find($html, '_iconcaptcha-token')[0] ?? null,
                'url' => $endpoint 
            ];
        }
        
        // rsCaptcha
        foreach ($sc['external'] as $src) {
            if (preg_match('/rscaptcha\.com.*\?(.*)$/', $src, $m)) {
                parse_str($m[1] ?? '', $params);
                if (!empty($params['public_key'])) {
                    $found['rss'] = [
                        'type' => 'rsc_' . preg_replace('/^v/', '', $params['version'] ?? '1'),
                        'keys' => $params['public_key'],
                        'extra' => $params
                    ];
                    break;
                }
            }
        }
        
        if (stripos($html, 'rscaptcha_token')) {
            $rs_token = Scraper::find($html, 'rscaptcha_token')[0] ?? null;
            $rs_image = Scraper::_xP($xp, "//img[@id='rscaptcha_img']/@src")[0] ?? null;
            $js_content = Scraper::_xP($xp, "//div[@id='rscap_js']/script/text()")[0] ?? null;
            
            if ($rs_token && $rs_image) {
                $_t = stripos($html, 'the least amount of times') ? 'icon' : 'upside';
                $found['rss'] = [
                    'type' => "rs_{$_t}",
                    'keys' => $rs_image,
                    'extra' => [
                        'token' => $rs_token,
                        'js' => $js_content
                    ]
                ];
            }
        }
        
        // antiBotLinks
        if (str_contains($html, 'antibotlinks_reset')) {
        
            $rxToken = "/data-token=\\\\*\"(?<token>[^\"\\\$$+?)\\\\*\"/";
            $is_emoji = Scraper::_jP($html, $rxToken);
        
            if (!empty($is_emoji['token'][0])) {
                $ab_ins = Scraper::_xP($xp, "//strong[contains(text(),',')]");
                $_ask = !empty($ab_ins) ? array_map('trim', explode(',', $ab_ins[0])) : [];
        
                $_ab = "/data-token=\\\\*\"(?<token>[^\"\\\$$+?)\\\\*\".*?>(?<emoji>.*?)<\/a>/su";
                $ab_rel = Scraper::_jP($html, $_ab);
        
                $ab_t = [];
                if (!empty($ab_rel['token'])) {
                    foreach ($ab_rel['token'] as $idx => $_rel) {
                        $ab_e = $ab_rel['emoji'][$idx] ?? null;
                        if ($ab_e !== null) $ab_t[$ab_e] = $_rel;
                    }
                }
                $found['antibot'] = [
                    'type' => 'emoji',
                    'data' => [
                        'main' => $_ask,
                        'rels' => $ab_t
                    ]
                ];
            } else {
                $images = self::extractAtbImages($xp, $html);
                if (!empty($images['main'])) {
                    $found['antibot'] = [
                        'type' => 'image',
                        'data' => $images
                    ];
                }
            }
        }
        
        return !empty($found) ? $found : null;
    }
    
    private static function extractAtbImages($xp, $html) {
        $ret = ['main' => null, 'rels' => []];
        
        $mainUrl = null;
        $a = $xp->query("//input[@id='antibotlinks' or @name='antibotlinks']")->item(0)
          ?: $xp->query("//*[contains(concat(' ',normalize-space(@class),' '),' antibotlinks ')]")->item(0);

        if ($a) {
            for ($up = 0; $up <= 6; $up++) {
                $ctx = $a;
                for ($i = 0; $i < $up && $ctx?->parentNode; $i++) $ctx = $ctx->parentNode;
                $n = $ctx ? $xp->query(".//img[starts-with(@src,'data:image')]/@src", $ctx)->item(0) : null;
                if ($n) {
                    $mainUrl = $n->nodeValue;
                    break;
                }
            }
            if (!$mainUrl) {
                $p = (string)$xp->evaluate("string((preceding::img[starts-with(@src,'data:image')][1]/@src))", $a);
                $mainUrl = ($p !== '') ? $p : (string)$xp->evaluate("string((following::img[starts-with(@src,'data:image')][1]/@src))", $a);
            }
        }

        if (!$mainUrl && ($pos = stripos($html, 'antibotlinks')) !== false) {
            $chunk = substr($html, max(0, $pos - 8000), 16000);
            if (preg_match('~src\s*=\s*(["\'])(data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)\1~i', $chunk, $m)) {
                $mainUrl = $m[2];
            }
        }

        if ($mainUrl) {
            $ret['main'] = self::cleanB64($mainUrl);
        }

        $rx = [
            '~\brel\s*=\s*["\'](\d+)["\'][\s\S]{0,8000}?\bsrc\s*=\s*["\'](data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)["\']~i',
            '~\brel\s*=\s*\\\\?"(\d+)\\\\?"[\s\S]{0,8000}?\bsrc\s*=\s*\\\\?"(data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)\\\\?"~i',
        ];

        foreach ($rx as $re) {
            if (preg_match_all($re, $html, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    if ($b64 = self::cleanB64($m[2])) {
                        $ret['rels'][$m[1]] ??= $b64;
                    }
                }
            }
        }

        return $ret;
    }

    private static function cleanB64($uri) {
        if (!preg_match('~^data:image/[a-z0-9.+-]+;base64,([a-z0-9+/=\s]+)$~i', $uri, $m)) {
            return null;
        }
        return preg_replace('~\s+~', '', $m[1]);
    }

}