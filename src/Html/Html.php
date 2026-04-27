<?php
/** @class Scraper 
 * @method dom
     * @param string|DOMXPath $html
     * @return DOMXPath
 * @method payload
     * @param string $html
     * @return array
 * @method _xP
     * @param string|DOMXPath $html
     * @param string $query
     * @return array
 * @method find
     * @param string|DOMXPath $html
     * @param string $name
     * @param string $tag
     * @param string|null $attr
     * @param string $key
     * @return array|null
 * @method xlit
     * @param string $s
     * @return string
 * @method _pP
     * @param string $html
     * @param string $targets
     * @return array
 * @method _jP
     * @param string $code
     * @param string $pattern
     * @return array|null
 * @method build
     * @param string $html
     * @param string $js
     * @param array|string $tokenData
     * @return array
 */
class Scraper {

    public static function dom($html): DOMXPath {
        if ($html instanceof DOMXPath) return $html;
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        return new DOMXPath($dom);
    }

    # PAYLOAD
    public static function payload($html): array {
        $xp = self::dom($html);
        $forms = $xp->query("//form");
        $out = [];

        foreach ($forms as $form) {
            $entry = [
                'url'     => $form->getAttribute('action'),
                'method'  => strtoupper($form->getAttribute('method') ?: 'GET'),
                'payload' => []
            ];

            foreach ($xp->query(".//input[@name]", $form) as $input) {
                $name  = $input->getAttribute('name');
                $type  = strtolower($input->getAttribute('type') ?: 'text');
                $value = $input->getAttribute('value');

                if (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
                    continue;
                }
                $entry['payload'][$name] = $value;
            }

            foreach ($xp->query(".//select[@name]", $form) as $select) {
                $name = $select->getAttribute('name');
                $opt = $xp->query(".//option[@selected]", $select)->item(0)
                    ?? $xp->query(".//option", $select)->item(0);
                
                if (!isset($entry['payload'][$name])) {
                    $entry['payload'][$name] = $opt ? $opt->getAttribute('value') : '';
                }
            }

            foreach ($xp->query(".//textarea[@name]", $form) as $ta) {
                $entry['payload'][$ta->getAttribute('name')] = trim($ta->textContent);
            }

            $out[] = $entry;
        }
        return $out;
    }

    # DOMXp BASED
    public static function _xP($html, $query): array {
        $xpath = self::dom($html);
        $nodes = $xpath->query($query);
        $out = [];
        if (!$nodes) return [];

        foreach ($nodes as $node) {
            if ($node instanceof DOMAttr) {
                $out[] = $node->value;
            } else {
                $out[] = trim($node->textContent);
            }
        }
        return $out;
    }

    public static function find(string|DOMXPath $html, $name, $tag='input', $attr='value', $key='name'): ?array {
        foreach ([$tag, $attr, $key] as $id) {
            if ($id !== null && !preg_match('/^[a-z][\w-]*$/i', $id)) return null;
        }

        $q = $attr
            ? "//{$tag}[@{$key}=" . self::xlit($name) . "]/@{$attr}"
            : "//{$tag}[@{$key}=" . self::xlit($name) . "]";

        return self::_xP($html, $q) ?: null;
    }

    private static function xlit($s) {
        if (!str_contains($s, "'")) return "'$s'";
        if (!str_contains($s, '"')) return "\"$s\"";
        
        $at = array_map(fn($p) => match ($p) {
            "'" => "\"'\"",
            '"' => "'\"'",
            default => "'$p'", 
        }, array_filter(preg_split('/([\'"])/', $s, -1, PREG_SPLIT_DELIM_CAPTURE), fn($p) => $p !== ''));

        return 'concat(' . implode(',', $at) . ')';
    }

    # REGexp BASED
    public static function _pP($html, $targets): array {
        $t = preg_quote($targets, '/');
        $pattern = "/{$t}\s*=\s*[\"']([^\"']+)[\"']/";
        preg_match_all($pattern, $html, $m);
        return $m[1] ?? [];
        #return $m ?? [];
    }

    public static function _jP($code, $pattern): ?array {
        preg_match_all($pattern, $code, $match);
        return $match;
    }
    
    # PROBLEMATIC PAYLOAD 
    public static function build($html, $js, $tokenData) {
        $jsContent = is_file($js) ? _get($js) : $js;
        $response = is_array($tokenData) ? $tokenData : json_decode($tokenData, true);

        $pattern = '/getElementById\s*\(\s*["\']([^"\']+)["\']\s*\)\s*\.value\s*=\s*(?:e\.token|response\.([a-zA-Z0-9_]+))/';
        $mappedInputs = [];
        
        if (preg_match_all($pattern, $jsContent, $matches)) {
            foreach ($matches[1] as $idx => $inputName) {
                $jsonKey = $matches[2][$idx] ?? null;

                if ($jsonKey && isset($response[$jsonKey])) {
                    $mappedInputs[$inputName] = $response[$jsonKey];
                } elseif (is_string($tokenData)) {
                    $mappedInputs[$inputName] = $tokenData;
                }
            }
        }

        preg_match('/document\.querySelector\("([^"]+)"\)\.innerHTML\s*=\s*`/', $jsContent, $cMatch);
        $captchaTag = $cMatch[1] ?? null;

        $xp = self::dom($html);
        $forms = $xp->query('//form');
        $payload = [];
/*
$inputs = $xpath->query('.//input', $form);
foreach ($inputs as $input) {
    $name = $input->getAttribute('name');
    $id   = $input->getAttribute('id');
    $type = $input->getAttribute('type');

    if ($name && $type !== 'submit') {
        if ($id && isset($mappedInputs[$id])) {
            $payload[$name] = $mappedInputs[$id];
            unset($mappedInputs[$id]);
        } else {
            $payload[$name] = $input->getAttribute('value');
        }
    }
}
*/
        foreach ($forms as $form) {
            $isTarget = true;
            if ($captchaTag) {
                $isTarget = $xp->query(".//*[contains(@id, '$captchaTag')] | .//$captchaTag", $form)->length > 0;
            }

            if ($isTarget) {
                foreach ($xp->query('.//input[@name]', $form) as $input) {
                    if ($input->getAttribute('type') !== 'submit') {
                        $payload[$input->getAttribute('name')] = $input->getAttribute('value');
                    }
                }
                
                foreach ($mappedInputs as $name => $value) {
                    $payload[$name] = $value;
                }
                break; 
            }
        }

        foreach ($mappedInputs as $name => $value) {
            if (!isset($payload[$name])) $payload[$name] = $value;
        }

        return $payload;
    }
    
}
