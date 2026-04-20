<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#EXTRACT
class sScraper {
    
    private static function dom(string $html): DOMXPath {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        return new DOMXPath($dom);
    }

    private static function norm(string $s): string {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5)));
    }

    private static function _getNodes(DOMXPath $xp, DOMNode $node): string {
        return self::norm((string)$xp->evaluate("normalize-space(string(.))", $node));
    }

    private static function _getLimit($txt) {
        return preg_match('~(\d+)\s*/\s*(\d+)~', $txt, $m) ? ($m[1].'/'.$m[2]) : null;
    }

    private static function _getCont(DOMNode $node): DOMNode {
        $cur = $node;
        for ($i=0; $i<8 && $cur && $cur->parentNode; $i++) {
            if (in_array($cur->nodeName, ['div','section','article','li'], true)) return $cur;
            $cur = $cur->parentNode;
        }
        return $node;
    }

    private static function _badName($s) {
        $s = self::norm($s);
        if ($s === '')
        return true;
        if (mb_strlen($s) > 60)
        return true;
        if (preg_match('~^\d+\s*/\s*\d+$~', $s)) 
        return true;
        if (preg_match('~^\d+(\.\d+)?\s*[A-Z]{2,6}$~', $s))
        return true;
        if (preg_match('~^https?://~i', $s))
        return true;
        if (preg_match('~\b(claim|login|submit|continue|next)\b~i', $s))
        return true;
        
        return false;
    }

    private static function _getName(DOMXPath $xp, DOMNode $container) {
    $heads = $xp->query(".//h1|.//h2|.//h3|.//h4|.//h5|.//h6", $container);
    foreach ($heads as $h) {
        $t = self::_getNodes($xp, $h);
        if (!self::_badName($t) && mb_strlen($t) >= 2 && mb_strlen($t) <= 40) {
            return $t;
        }
    }

    $nodes = $xp->query(".//a|.//span|.//strong|.//b|.//label", $container);

    $checked = 0;
    foreach ($nodes as $n) {
        if (++$checked > 30) break;
        $t = self::_getNodes($xp, $n);
        $t = self::norm($t);
        if (self::_badName($t))
        continue;
        if (mb_strlen($t) < 2 || mb_strlen($t) > 40) 
        continue;
        if (preg_match('~\d~', $t))
        continue;
        return $t;
    }

    $all = self::_getNodes($xp, $container);
    $all = self::norm($all);

    if (preg_match('~^([^\d]{2,40})~u', $all, $m)) {
        $cand = self::norm($m[1]);
        if (!self::_badName($cand)) return $cand;
    }
    return "";
}

    public static function extract($html): array {
        $xp = self::dom($html);

        $nodes = $xp->query(
            "//*[@data-link-id 
               or @href[contains(.,'/go/')] 
               or @href[contains(.,'/validate/')] 
               or @value[contains(.,'/go/')] 
               or @value[contains(.,'/validate/')] 
               or @data-href[contains(.,'/go/')] 
               or @data-href[contains(.,'/validate/')] 
               or @data-url[contains(.,'/go/')] 
               or @data-url[contains(.,'/validate/')]]"
        );
        
        $seen = [];
        $out  = [];

        foreach ($nodes as $n) {
            $url = null;
            foreach (['href','value','data-href','data-url', 'data-link-id'] as $attr) {
                $a = $n->attributes?->getNamedItem($attr);
                if (!$a) continue;
                if ($attr === 'data-link-id') {
                    $id  = (int)$a->nodeValue;
                    $url = $a->nodeValue;
                    break;
                }
                if ($a && preg_match('~/go/(\d+)(?:/|$|\?)~', $a->nodeValue, $m)) {
                    $url = $a->nodeValue;
                    $id  = (int)$m[1];
                    break;
                }
                if ($a && preg_match('~/validate/(\d+)(?:/|$|\?)~', $a->nodeValue, $m)) {
                    $url = $a->nodeValue;
                    $id  = (int)$m[1];
                    break;
                }
            }
            if (!isset($id)) continue;
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $container = self::_getCont($n);

            $containerText = self::_getNodes($xp, $container);
            $limit = self::_getLimit($containerText);
            $name  = self::_getName($xp, $container);
            $key = $name !== '' ? $name : ('go_'.$id);
            $key = strtolower($key);
            $out[$key] = [$id, $limit ?? ''];
            unset($id);
        }

        return $out;
    }
} 

function limit($id) {
    list($current, $max) = explode('/', $id);
    $current = (int)$current;
    $max = (int)$max;

    return $current > 0;
} 

