<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

loader(__DIR__); 

class capt {
    public static function cha($html): ?array {
        $xp = xScraper::dom($html);

        #recaptcha2
        $v2 = xScraper::xPath($xp, "//div[contains(@class,'g-recaptcha')]/@data-sitekey");
        if (!empty($v2)) {
            #compat
            foreach ($xp->query("//script[@src]") as $script) {
                $src = $script->getAttribute('src');
                if (strpos($src, 'challenges.cloudflare.com/turnstile') !== false) {
                    return ['type' => 'cft', 'keys' => $v2];
                }
            }
            return ['type' => 'rc2', 'keys' => $v2];
        }

        #recaptcha3
        $v3 = [];
        foreach ($xp->query("//script[@src]") as $script) {
            $src = $script->getAttribute('src');
            if (preg_match('/recaptcha\/api\.js\?render=([^&]+)/', $src, $m)) {
                $v3[] = $m[1];
            }
        }
        if (!empty($v3) || preg_match('/grecaptcha\.execute/', $html)) {
            return ['type' => 'rc3', 'keys' => $v3];
        }

        #turnstile (native)
        $turnstile = xScraper::xPath($xp, "//div[contains(@class,'cf-turnstile')]/@data-sitekey");
        foreach ($xp->query("//script[@src]") as $script) {
            $src = $script->getAttribute('src');
            if (preg_match('/challenges\.cloudflare\.com\/turnstile.*sitekey=([^&]+)/', $src, $m)) {
                $turnstile[] = $m[1];
            }
        }
        if (!empty($turnstile)) {
            return ['type' => 'cft', 'keys' => $turnstile];
        }

        #hcaptcha
        $hcaptcha = [];
        $hcaptcha = array_merge($hcaptcha, xScraper::xPath($xp, "//h-captcha/@site-key"));
        $hcaptcha = array_merge($hcaptcha, xScraper::xPath($xp, "//div[contains(@class,'h-captcha')]/@data-sitekey"));
        if (!empty($hcaptcha)) {
            return ['type' => 'hc', 'keys' => $hcaptcha];
        }

        #RsCaptcha
        $rsc = [];
        foreach ($xp->query("//script[@src]") as $script) {
            $src = $script->getAttribute('src');
            if (preg_match('/rscaptcha\.com.*\?(.*)$/', $src, $m)) {
                parse_str($m[1], $params);
                if (!empty($params['app_id']) && !empty($params['public_key']) && !empty($params['version'])) {
                    $ver = preg_replace('/^v/', '', $params['version']);
                    $rsc[] = [
                        'version'    => $ver,
                        'app_id'     => $params['app_id'],
                        'public_key' => $params['public_key']
                    ];
                }
            }
        }
        if (!empty($rsc)) {
            return ['type' => 'rsc_'.$rsc[0]['version'], 'keys' => $rsc];
        }

        return null; // tidak terdeteksi
    }
    
    public static function chaaa($html): ?array {
    $xp = xScraper::dom($html);

    # cache script src
    $scripts = [];
    foreach ($xp->query("//script[@src]") as $script) {
        $scripts[] = $script->getAttribute('src');
    }

    $found = [];

    #recaptcha2
    $v2 = xScraper::xPath($xp,"//div[contains(@class,'g-recaptcha')]/@data-sitekey");
    if (!empty($v2)) {
        $keys = array_values(array_unique($v2));

        $type = 'rc2';
        foreach ($scripts as $src) {
            if (strpos($src,'challenges.cloudflare.com/turnstile') !== false) {
                $type = 'cft'; // compat mode
                break;
            }
        }

        $found[] = ['type'=>$type,'key'=>$keys[0],'keys'=>$keys];
    }

    #recaptcha3
    $v3 = [];
    foreach ($scripts as $src) {
        if (preg_match('/recaptcha\/api\.js\?render=([^&]+)/',$src,$m)) {
            $v3[] = $m[1];
        }
    }

    if (preg_match_all('/grecaptcha\.execute\(\s*[\'"]([^\'"]+)/',$html,$m)) {
        $v3 = array_merge($v3,$m[1]);
    }

    if (!empty($v3)) {
        $keys = array_values(array_unique($v3));
        $found[] = ['type'=>'rc3','key'=>$keys[0],'keys'=>$keys];
    }

    #turnstile
    $turnstile = [];
    $turnstile = array_merge($turnstile,
        xScraper::xPath($xp,"//div[contains(@class,'cf-turnstile')]/@data-sitekey"),
        xScraper::xPath($xp,"//div[@data-sitekey]/@data-sitekey")
    );

    foreach ($scripts as $src) {
        if (preg_match('/turnstile.*sitekey=([^&]+)/',$src,$m)) {
            $turnstile[] = $m[1];
        }
    }

    if (!empty($turnstile)) {
        $keys = array_values(array_unique($turnstile));
        $found[] = ['type'=>'cft','key'=>$keys[0],'keys'=>$keys];
    }

    #hcaptcha
    $hcaptcha = [];
    $hcaptcha = array_merge($hcaptcha,
        xScraper::xPath($xp,"//h-captcha/@site-key"),
        xScraper::xPath($xp,"//div[contains(@class,'h-captcha')]/@data-sitekey")
    );

    if (!empty($hcaptcha)) {
        $keys = array_values(array_unique($hcaptcha));
        $found[] = ['type'=>'hc','key'=>$keys[0],'keys'=>$keys];
    }

    #RsCaptcha
    $rsc = [];
    foreach ($scripts as $src) {
        if (preg_match('/rscaptcha\.com.*\?(.*)$/',$src,$m)) {
            parse_str($m[1],$params);
            if (!empty($params['app_id']) && !empty($params['public_key']) && !empty($params['version'])) {
                $ver = preg_replace('/^v/','',$params['version']);
                $rsc[] = [
                    'version'=>$ver,
                    'app_id'=>$params['app_id'],
                    'public_key'=>$params['public_key']
                ];
            }
        }
    }

    if (!empty($rsc)) {
        $found[] = [
            'type'=>'rsc_'.$rsc[0]['version'],
            'key'=>$rsc[0]['public_key'],
            'keys'=>$rsc
        ];
    }

    return !empty($found) ? $found : null;
}
    
}