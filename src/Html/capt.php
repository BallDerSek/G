<?php
/** @class Capt 
 * @method cha
 * @param string $html
 * @return array|null
 */
class Capt {
    
    public static function cha($html): ?array {
        if (empty($html)) return null;

        $xp = Scraper::dom($html);
        $found = [];

        $scripts = Scraper::_xP($xp, "//script[@src]/@src") ?: [];
        $scriptsStr = implode(' ', $scripts); 
        
        $has_turnstile = str_contains($scriptsStr, 'challenges.cloudflare.com/turnstile');
        $has_hcaptcha  = str_contains($scriptsStr, 'hcaptcha.com/1/api.js'); 
        $has_recaptcha = str_contains($scriptsStr, 'google.com/recaptcha/api.js');

        // --- 1. HCAPTCHA ---
        if ($has_hcaptcha) {
            $hc = Scraper::find($xp, 'h-captcha', '*', 'data-sitekey') 
               ?? Scraper::_xP($xp, "//div[contains(@class,'h-captcha')]/@data-sitekey")
               ?? Scraper::_xP($xp, "//h-captcha/@site-key");
            
            if (!empty($hc) && isset($hc[0])) {
                $found['hc'] = [
                    'type' => 'hc',
                    'keys' => $hc[0],
                    'extra' => ['invisible' => str_contains($html, 'data-size="invisible"')]
                ];
            } else {
                if (preg_match('/["\']sitekey["\']\s*:\s*["\']([a-zA-Z0-9_-]+)["\']/i', $html, $matches)) {
                    $found['hc'] = [
                        'type' => 'hc',
                        'keys' => $matches[1],
                        'extra' => ['invisible' => str_contains($html, 'data-size="invisible"')]
                    ];
                }
            }
        }
        
        // --- 2. TURNSTILE ---
        if ($has_turnstile) {
            $cft = array_filter(array_merge(
                Scraper::_xP($xp, "//div[contains(@class,'cf-turnstile')]/@data-sitekey") ?: [],
                Scraper::_xP($xp, "//*[@data-sitekey][contains(@id,'cf-turnstile')]/@data-sitekey") ?: [],
                Scraper::_xP($xp, "//div[contains(@class,'g-recaptcha')]/@data-sitekey") ?: []
            ));

            if (!empty($cft)) {
                $keys = array_values(array_unique($cft));
                $mCda = Scraper::_jP($html, "/cdata\s*:\s*['\"]([^'\"]+)['\"]/");
                $found['cft'] = [
                    'type' => 'cft',
                    'keys' => $keys[0],
                    'extra' => ['cdata' => $mCda[1][0] ?? null]
                ];
            } else {
                if (preg_match('/data-sitekey=["\'](0x[a-zA-Z0-9_-]+)["\']/', $html, $matches) || 
                    preg_match('/sitekey\s*:\s*["\'](0x[a-zA-Z0-9_-]+)["\']/', $html, $matches)) {
                    $found['cft'] = [
                        'type' => 'cft',
                        'keys' => $matches[1],
                    ];
                }
            }
        }

        // --- 3. RECAPTCHA V2 ---
        if ($has_recaptcha) {
            $v2 = Scraper::find($xp, 'g-recaptcha', 'div', 'data-sitekey') 
               ?? Scraper::find($xp, 'sitekey', '*', 'data-sitekey');

            if (!empty($v2) && isset($v2[0])) {
                $found['rc2'] = [
                    'type' => 'rc2',
                    'keys' => $v2[0],
                    'extra' => [
                        'invisible' => str_contains($html, 'data-size="invisible"'),
                        'data-s' => Scraper::find($xp, 'data-s', '*', 'data-s')[0] ?? null
                    ]
                ];
            }
        }

        // --- 4. RECAPTCHA V3 ---
        $v3_raw = [];
        foreach ($scripts as $src) {
            if (preg_match('/render=([^&]+)/', $src, $m)) {
                if (strlen($m[1]) > 20 && $m[1] !== 'explicit') {
                    $v3_raw[] = $m[1];
                }
            }
        }
        if (preg_match_all('/grecaptcha\.execute\(\s*[\'"]([^\'"]+)/', $html, $m)) {
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
        
        // --- 5. ICONCAPTCHA ---
        if (str_contains($html, 'iconcaptcha-widget') || str_contains($html, '_iconcaptcha-token')) {
            $found['ic_fw'] = [
                'keys' => Scraper::find($html, '_iconcaptcha-token')[0] ?? null
            ];
        }

        // --- 6. RSCAPTCHA ---
        foreach ($scripts as $src) {
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
                    'type'  => "rs_{$_t}",
                    'keys'  => $rs_image,
                    'extra' => [
                        'token' => $rs_token,
                        'js' => $js_content 
                    ]
                ];
            }
        }
        
        // --- 7. ANTIBOTLINKS ---
        if (str_contains($html, 'antibotlinks_reset')) {
            $is_emoji = Scraper::_jP($html, '/data-token=\\\\*"(?<token>[^"\\\\]+)\\\\*"/');
            
            if (!empty($is_emoji[1][0])) {
                $ab_ins = Scraper::_xP($html, "//strong[contains(text(),',')]");
                $_ask = !empty($ab_ins) ? array_map('trim', explode(',', $ab_ins[0])) : [];
                $_ab = '/data-token=\\\\*"(?<token>[^"\\\\]+)\\\\*".*?>(?<emoji>.*?)<\/a>/u';
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

    
    private static function extractAtbImages($xp, $html): array {
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

    private static function cleanB64($uri): ?string {
        if (!preg_match('~^data:image/[a-z0-9.+-]+;base64,([a-z0-9+/=\s]+)$~i', $uri, $m)) {
            return null;
        }
        return preg_replace('~\s+~', '', $m[1]);
    }
}
