<?php

/** @class Capt 
 * @type cha
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

        if ($has_hcaptcha) {
            $hc = Scraper::find($xp, 'h-captcha', '*', 'data-sitekey') 
               ?? Scraper::_xP($xp, "//div[contains(@class,'h-captcha')]/@data-sitekey")
               ?? Scraper::_xP($xp, "//h-captcha/@site-key");
            
            if (!empty($hc) && isset($hc[0])) {
                $found['hc'] = [
                    'type' => 'hc',
                    'keys'   => $hc[0],
                    'extra'  => ['invisible' => str_contains($html, 'data-size="invisible"')]
                ];
            }
        }
        
        if ($has_turnstile) {
            $cft = array_merge(
                Scraper::_xP($xp, "//div[contains(@class,'cf-turnstile')]/@data-sitekey") ?: [],
                Scraper::_xP($xp, "//div[contains(@class,'g-recaptcha')]/@data-sitekey") ?: []
            );
            if (!empty($cft)) {
                $mCda = Scraper::_jP($html, "/cdata\s*:\s*['\"]([^'\"]+)['\"]/");
                $found['cft'] = [
                    'type' => 'cft',
                    'keys'   => array_values(array_unique($cft))[0],
                    'extra'  => [
                        'cdata' => $mCda[1][0] ?? null,
                    ]
                ];
            }
        }

        if ($has_recaptcha && !isset($found['cft'])) {
            $v2 = Scraper::find($xp, 'g-recaptcha', 'div', 'data-sitekey') 
               ?? Scraper::find($xp, 'sitekey', '*', 'data-sitekey');

            if (!empty($v2) && isset($v2[0])) {
                $found['rc2'] = [
                    'type' => 'rc2',
                    'keys'   => $v2[0],
                    'extra'  => [
                        'invisible' => str_contains($html, 'data-size="invisible"'),
                        'data-s'    => Scraper::find($xp, 'data-s', '*', 'data-s')[0] ?? null
                    ]
                ];
            }
        }

        $v3 = [];
        foreach ($scripts as $src) {
            if (preg_match('/render=([^&]+)/', $src, $m)) $v3[] = $m[1];
        }
        if (preg_match_all('/grecaptcha\.execute\(\s*[\'"]([^\'"]+)/', $html, $m)) {
            $v3 = array_merge($v3, $m[1]);
        }

        if (!empty($v3)) {
            $v3_keys = array_values(array_unique($v3));
            $mAct = Scraper::_jP($html, "/action\s*[:=]\s*['\"]([^'\"]+)['\"]/");
            $found['rc3'] = [
                'type' => 'rc3',
                'keys'   => $v3_keys[0],
                'extra'  => ['action' => $mAct[1][0] ?? 'homepage']
            ];
        }

        foreach ($scripts as $src) {
            if (preg_match('/rscaptcha\.com.*\?(.*)$/', $src, $m)) {
                $queryStr = $m[1] ?? null;
                if ($queryStr) {
                    parse_str($queryStr, $params);
                    if (!empty($params['public_key'])) {
                        $found['rss'] = [
                            'type' => 'rsc_' . preg_replace('/^v/', '', $params['version'] ?? '1'),
                            'keys'   => $params['public_key'],
                            'extra'  => $params
                        ];
                        break;
                    }
                }
            } 
        }
        if (str_contains($html, 'rscaptcha_token')) {
            $rs_token = Scraper::find($html, 'rscaptcha_token')[0] ?? null;
            $rs_image = scraper::_xP($html, "//img[@id='rscaptcha_img']/@src")[0];
            #var_dump($rs_token);
            if ($rs_token && $rs_image) {
                $_i = 'the least amount of times';
                $_u = 'select the upside-down';
                $_t = str_contains($html, $_i) ? 'icon' : 'upside';
                
                $found['rss'] = [
                    'type' => "rs_{$_t}",
                    'keys' => $rs_image,
                ];
            }
        }

        if (str_contains($html, 'antibotlinks_reset')) {
            $is_emoji = Scraper::_jP($html, '/data-token=\\\\"(?<token>[^"\\\\]+)\\\\"/');
            $found['antibot'] = ['type' => !empty($is_emoji[1][0]) ? 'emoji' : 'image'];
        }

        return !empty($found) ? $found : null;
    }
}




    
/** @class ATBtest
 * @type b64
     * @param string $uri
     * @return string|null
 * @type mainATB
     * @param string $html
     * @return string|null
 * @type relsATB
     * @param string $html
     * @return array
 * @type get
     * @param string $html
     * @return array
 */
final class ATBtest {

    private static function b64($uri): ?string {
        if (!preg_match('~^data:image/[a-z0-9.+-]+;base64,([a-z0-9+/=\s]+)$~i', $uri, $m)) {
            return null;
        }
        return preg_replace('~\s+~', '', $m[1]);
    }

    private static function mainATB($html): ?string {
        $xp = Scraper::dom($html);

        $a = $xp->query("//input[@id='antibotlinks' or @name='antibotlinks']")->item(0)
          ?: $xp->query("//*[contains(concat(' ',normalize-space(@class),' '),' antibotlinks ')]")->item(0);

        if ($a) {
            for ($up=0; $up<=6; $up++) {
                $ctx = $a;
                for ($i=0; $i<$up && $ctx?->parentNode; $i++) $ctx = $ctx->parentNode;
                $n = $ctx ? $xp->query(".//img[starts-with(@src,'data:image')]/@src", $ctx)->item(0) : null;
                if ($n) return $n->nodeValue;
            }

            // Fallback: Preceding/Following sibling
            $p = (string)$xp->evaluate("string((preceding::img[starts-with(@src,'data:image')][1]/@src))", $a);
            if ($p !== '') return $p;

            $f = (string)$xp->evaluate("string((following::img[starts-with(@src,'data:image')][1]/@src))", $a);
            if ($f !== '') return $f;
        }

        if (($pos = stripos($html, 'antibotlinks')) !== false) {
            $chunk = substr($html, max(0, $pos-8000), 16000);
            if (preg_match('~src\s*=\s*(["\'])(data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)\1~i', $chunk, $m)) {
                return $m[2];
            }
        }
        return null;
    }

    private static function relsATB($html): array {
        $out = [];
        $rx = [
            '~\brel\s*=\s*["\'](\d+)["\'][\s\S]{0,8000}?\bsrc\s*=\s*["\'](data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)["\']~i',
            '~\brel\s*=\s*\\\\?"(\d+)\\\\?"[\s\S]{0,8000}?\bsrc\s*=\s*\\\\?"(data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)\\\\?"~i',
        ];

        foreach ($rx as $re) {
            if (preg_match_all($re, $html, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    $out[$m[1]] ??= $m[2];
                }
            }
        }
        return $out;
    }

    public static function get($html): array {
        $ret = ['main' => null, 'rels' => []];
        
        if (($u = self::mainATB($html))) {
            $ret['main'] = self::b64($u);
        }

        foreach (self::relsATB($html) as $rel => $u) {
            if ($b = self::b64($u)) {
                $ret['rels'][$rel] = $b;
            }
        }
        return $ret;
    }
    
}
