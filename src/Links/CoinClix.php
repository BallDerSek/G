<?php

class CoinClix {
    
    public static function _ccCode($html) {
        $nodes = Scraper::_xP($html, "//div[contains(@class,'accordion-body')]");
        foreach ($nodes as $txt) {
            if (preg_match('/enter\s+this\s+key\s*-\s*([A-Za-z0-9]{5})/i', $txt, $m)) {
                return $m;
            }
        }
        return null;
    }
    
    public static function _ccForm($api, $dom, $ver, $pis, $cnn, $bg, $cp) {
    
        $cpobj = $cp ? json_decode(html_entity_decode($cp), true) : null;
        
        switch (strtoupper($ver)) {
    
            case 'CC':
                $token = bin2hex(random_bytes(15));
                break;
    
            case 'CT':
                $token = Solve::tkn($api, $dom, '0x4AAAAAAB5TRnwvGvH5b2kw', 'cft', ['action' => 'linkSubmit'])['done'] ?? null;
                break;
    
            case 'HC':
                $token = Solve::tkn($api, $dom, '2a9619f4-43bc-4e64-afc8-7fbc48f2bf34', 'hc', ['invisible'=>1])['done'] ?? null;
                #$token = _rl('token:');
                break;
    
            case 'PC':
            case 'IC':
                $token = Solve::tkn($api, $dom, $cpobj, $ver.'c')['done'] ?? null;
                break;
    
            default:
                return null;
        }
    
        return self::_ccLoad($pis, $cnn, $token, $bg);
    }
    
    public static function _ccLoad($pis, $cnn, $response, $bg) {
        $rand = function($len){
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $out = '';
            for($i = 0; $i<$len; $i++){
                $out .= $chars[random_int(0,strlen($chars)-1)];
            }
            return $out;
        };
    
        $linkCont = random_int(12345,54321);
        $ttl = $rand(15);
        $t = time();
        $g = $t+587814;
        $v47 = $t-835069;
    
        $n = hash_hmac('sha256', "bEhInD".$pis."YoU", (string)$v47);
    
        $key = $cnn."<|>".(987656789-$linkCont)."lIl1l";
    
        $i = hash_hmac('sha256', '"' . $response . '"', $key);
    
        $payload = [
            'linkCont' => $linkCont,
            'response' => $response,
            'n' => $n,
            'i' => $i,
            'g' => $g,
            'ttl' => $ttl
        ];
    
        if ($bg !== null && $bg !== '') {
            $payload['bg'] = $bg;
        }
    
        return $payload;
    }

}