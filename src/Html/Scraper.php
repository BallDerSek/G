<?php

class Scraper {

    private static array $cache = [];

    private static function _init(string $html): string {
        return md5($html);
    }

    public static function dom($html): ?DOMXPath {
        if (!$html) return null;
        if ($html instanceof DOMXPath) return $html;

        $key = self::_init($html);

        // Hit cache → return langsung, tanpa parse ulang
        if (isset(self::$cache[$key]['xp'])) {
            return self::$cache[$key]['xp'];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        if (stripos($html, '<meta charset') === false && stripos($html, '<head>') !== false) {
            $html = str_ireplace('<head>', '<head><meta charset="UTF-8">', $html);
        } elseif (stripos($html, '<meta charset') === false) {
            $html = '<meta charset="UTF-8">' . $html;
        }

        $dom->loadHTML(
            $html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET
        );

        libxml_clear_errors();

        $xp = new DOMXPath($dom);

        // Simpan ke cache
        self::$cache[$key]['xp'] = $xp;

        return $xp;
    }

    public static function clearCache(): void {
        self::$cache = [];
    }

    public static function title($html) {
        $t = self::_xP($html, "//title/text()");
        return trim($t[0] ?? '');
    }

    # PAYLOAD
    public static function payload($html, $id = null): array {
        $xp = self::dom($html);   // ← cache hit kalau HTML sama
        if (!$xp) return [];
        $query = $id ? "//form[@id=" . self::xlit($id) . "]" : "//form";
        $forms = $xp->query($query);
        $out = [];

        foreach ($forms as $form) {
            $entry = [
                'id' => $form->getAttribute('id') ?: null,
                'url' => $form->getAttribute('action'),
                'method' => strtoupper($form->getAttribute('method') ?: 'GET'),
                'payload' => []
            ];

            $addPayload = function($name, $value) use (&$entry) {
                if (isset($entry['payload'][$name])) {
                    if (!is_array($entry['payload'][$name])) {
                        $entry['payload'][$name] = [$entry['payload'][$name]];
                    }
                    $entry['payload'][$name][] = $value;
                } else {
                    $entry['payload'][$name] = $value;
                }
            };

            foreach ($xp->query(".//input[@name]", $form) as $input) {
                $name = $input->getAttribute('name');
                $type = strtolower($input->getAttribute('type') ?: 'text');
                $val  = $input->getAttribute('value');
                $addPayload($name, $val);
            }

            foreach ($xp->query(".//select[@name]", $form) as $select) {
                $name = $select->getAttribute('name');
                $options = $xp->query(".//option", $select);
                foreach ($options as $opt) {
                    $addPayload($name, $opt->getAttribute('value'));
                }
                if (!isset($entry['payload'][$name])) {
                    $entry['payload'][$name] = '';
                }
            }

            foreach ($xp->query(".//textarea[@name]", $form) as $ta) {
                $addPayload($ta->getAttribute('name'), trim($ta->textContent));
            }

            $out[] = $entry;
        }
        return $out;
    }

    # DOMXp BASED
    public static function _xP($html, $query, $context = null): array {

        if ($html instanceof DOMXPath) $xpath = $html;
        else $xpath = self::dom($html);    // ← cache hit

        if (!$xpath) return [];

        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);

        $out = [];
        if (!$nodes) return [];

        foreach ($nodes as $node) {
            if ($node instanceof DOMAttr) $out[] = $node->value;
            else $out[] = trim($node->textContent);
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
        if (!$html) return [];
        $t = preg_quote($targets, '/');
        $pattern = "/{$t}\s*=\s*[\"']([^\"']+)[\"']/";
        preg_match_all($pattern, $html, $m);
        return $m[1] ?? [];
    }

    public static function _jP($html, $pattern): array {
        if (!$html) return [];
        $result = @preg_match_all($pattern, $html, $match);
        if ($result === false) return [];
        return $match;
    }

    public static function _var($html, $name) {
        preg_match('/' . preg_quote($name, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]/', $html, $m);
        return $m[1] ?? null;
    }

    # PROBLEMATIC PAYLOAD
    public static function build($html, $js, $tokenData) {
        $jsContent = is_file($js) ? _get($js) : $js;
        $response = is_array($tokenData) ? $tokenData : json_decode($tokenData, true);

        $pattern = '/getElementById\s*$\s*["\']([^"\']+)["\']\s*$\s*\.value\s*=\s*(?:e\.token|response\.([a-zA-Z0-9_]+))/';
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

        preg_match('/document\.querySelector$"([^"]+)"$\.innerHTML\s*=\s*`/', $jsContent, $cMatch);
        $captchaTag = $cMatch[1] ?? null;

        $xp = self::dom($html);
        if (!$xp) return [];
        $forms = $xp->query('//form');
        $payload = [];

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

    public static function _sC($html): array {

        if (is_string($html)) {
            $key = self::_init($html);
            if (isset(self::$cache[$key]['scripts'])) {
                return self::$cache[$key]['scripts'];
            }
        }

        $xp = self::dom($html);

        if (!$xp) return ['external' => [], 'inline' => []];

        $external = self::_xP($xp, "//script[@src]/@src") ?: [];
        $inlineNodes = $xp->query("//script[not(@src)]");

        $inline = [];
        foreach ($inlineNodes as $node) {
            $content = trim($node->textContent);
            if (!empty($content)) $inline[] = $content;
        }

        $result = [
            'external' => $external,
            'inline' => $inline
        ];

        if (is_string($html)) {
            $key = self::_init($html);
            self::$cache[$key]['scripts'] = $result;
        }

        return $result;
    }

    public static function _get($html, $key) {
        $scripts = self::_sC($html);
        foreach ($scripts['external'] as $src) {
            if (str_contains($src, $key)) return $src;
        }

        foreach ($scripts['inline'] as $content) {
            if (str_contains($content, $key)) return $content;
        }

        return null;
    }

}