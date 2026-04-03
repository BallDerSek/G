<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

function xlit($s) {
    if (!str_contains($s, "'")) return "'$s'";
    if (!str_contains($s, '"')) return "\"$s\"";
    
    $at = array_map(fn($p) => match ($p) {
        "'" => "\"'\"",
        '"' => "'\"'",
        default => "'$p'", 
    },
    array_filter(preg_split('/([\'"])/', $s, -1,PREG_SPLIT_DELIM_CAPTURE), 
    fn($p) => $p !== ''));

    return 'concat('.implode(',', $at).')';
}

function x(string|DOMXPath $html, $name, $tag='input', $attr='value', $key='name'): ?array {

    foreach ([$tag,$attr,$key] as $id)
        if ($id !== null && !preg_match('/^[a-z][\w-]*$/i',$id))
            return null;

    $q = $attr
        ? "//{$tag}[@{$key}=".xlit($name)."]/@{$attr}"
        : "//{$tag}[@{$key}=".xlit($name)."]";

    return xScraper::xPath($html,$q) ?: null;
}

class xScraper { #xdom based
    public static function dom($html): DOMXPath {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        return new DOMXPath($dom);
    }
    
    public static function payload($html): array {
        $xp = self::dom($html);
        $forms = $xp->query("//form");
        $out = [];
        foreach ($forms as $idx => $form) {
            $entry = [
                'url'     => $form->getAttribute('action'),
                'method'  => strtoupper($form->getAttribute('method') ?: 'GET'),
                'payload' => []
            ];
            // INPUT
            foreach ($xp->query(".//input[@name]", $form) as $input) {
                $name  = $input->getAttribute('name');
                $type  = strtolower($input->getAttribute('type') ?: 'text');
                $value = $input->getAttribute('value');

                if (in_array($type, ['checkbox', 'radio'], true)
                    && !$input->hasAttribute('checked')) {
                    continue;
                }
                $entry['payload'][$name] = $value !== '' ? $value : '';
            }

            // SELECT
            foreach ($xp->query(".//select[@name]", $form) as $select) {
                $name = $select->getAttribute('name');

                $opt = $xp->query(".//option[@selected]", $select)->item(0)
                    ?? $xp->query(".//option", $select)->item(0);
                
                if (!isset($entry['payload'][$name])) {
                    $entry['payload'][$name] = $opt ? $opt->getAttribute('value') : '';
                }

                #$entry['payload'][$name] = $opt ? $opt->getAttribute('value') : '';
            }

            // TEXTAREA
            foreach ($xp->query(".//textarea[@name]", $form) as $ta) {
                $entry['payload'][$ta->getAttribute('name')] =
                    trim($ta->textContent);
            }

            $out[] = $entry;
        }
        return $out;
    }
    
    public static function xPath ($html, $query):array {
        $xpath = $html instanceof DOMXPath ? $html : self::dom($html);
        $nodes = $xpath->query($query);
        $out = [];
        foreach ($nodes as $node) {
        // Attribute $node
            if ($node instanceof DOMAttr) { $out[] = $node->value; }
        // Element / Text node
            else { $out[] = trim($node->textContent); }
        } return $out;
    }
    
    
}

class rScraper { #regex based
    public static function pPath($html, $target): array {
        $t = preg_quote($target, '/');
        $pattern = "/{$t}\s*=\s*[\"']([^\"']+)[\"']/";
        preg_match_all($pattern, $html, $m);
        return $m;
    }
    public static function jPath($code, $pattern): ?array {
        preg_match_all($pattern, $code, $match);
        return $match;
    }
} 