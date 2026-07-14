<?php

class Shortlinks {
    
    public static function extract($html): array {
        
        return (new class($html) {

            public array $data = [];

            public function __construct($html) {
                $xp    = $this->dom($html);
                $nodes = $xp->query(
                    "//*[@data-slid] | //*[@data-link-id 
                        or @href[contains(.,'/go/')] 
                        or @href[contains(.,'/validate/')] 
                        or @href[contains(.,'/pre_verify/')] 
                        or @value[contains(.,'/go/')] 
                        or @value[contains(.,'/validate/')] 
                        or @value[contains(.,'/pre_verify/')] 
                        or @data-href[contains(.,'/go/')] 
                        or @data-href[contains(.,'/validate/')] 
                        or @data-url[contains(.,'/go/')] 
                        or @data-url[contains(.,'/validate/')]]"
                );

                $seen = [];
                $out  = [];

                foreach ($nodes as $n) {
                    $url = null;
                    $id  = null;

                    foreach (['data-slid', 'href', 'value', 'data-href', 'data-url', 'data-link-id'] as $attr) {

                        $a = $n->attributes?->getNamedItem($attr);
                        if (!$a) continue;

                        if ($attr === 'data-slid') {
                            $id  = (int) $a->nodeValue;
                            $url = "/shortlink/load/{$id}";
                            break;
                        }

                        if ($attr === 'data-link-id') {
                            $id  = (int) $a->nodeValue;
                            $url = $a->nodeValue;
                            break;
                        }

                        if (preg_match('~/(go|validate|pre_verify)/(\d+)(?:/|$|\?)~', $a->nodeValue, $m)) {
                            $url = $a->nodeValue;
                            $id  = (int) $m[2];
                            break;
                        }
                    }

                    if (!isset($id))       continue;
                    if (isset($seen[$id])) continue;
                    $seen[$id] = true;

                    $container = $this->_getCont($n, $xp);
                    $containerText = $this->_getNodes($xp, $container);
                    $limit = $this->_getLimit($containerText);
                    $name = $this->_getName($xp, $container);

                    $key = $name !== '' ? $name : "go_{$id}";
                    $key = strtolower($key);

                    $out[$key] = [$id, $limit ?? ''];
                    unset($id);
                }

                $this->data = $out;
            }

            private function dom($html): DOMXPath {
                if (!$html) return [];
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
                return new DOMXPath($dom);
            }

            private function norm($s): string {
                return trim(preg_replace('/\s+/', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5)));
            }

            private function _getNodes(DOMXPath $xp, DOMNode $node): string {
                return $this->norm((string) $xp->evaluate("normalize-space(string(.))", $node));
            }

            private function _getLimit(string $txt): ?string {
                return preg_match('~(\d+)\s*/\s*(\d+)~', $txt, $m) ? "{$m[1]}/{$m[2]}" : null;
            }

            private function _getCont(DOMNode $node, DOMXPath $xp): DOMNode {
                $cur = $node;
                for ($i = 0; $i < 8 && $cur && $cur->parentNode; $i++) {
                    if (in_array($cur->nodeName, ['div', 'section', 'article', 'li', 'td'], true)) {
                        $txt = $this->_getNodes($xp, $cur);
                        if (mb_strlen($txt) > 20 || preg_match('~\d+/\d+~', $txt))  return $cur;
                    }
                    $cur = $cur->parentNode;
                }
                return $node->parentNode ?? $node;
            }

            private function _badName($s): bool {
                $s = $this->norm($s);
                if ($s === '') return true;
                if (mb_strlen($s) > 60) return true;
                if (preg_match('~^\d+\s*/\s*\d+$~', $s)) return true;
                if (preg_match('~^\d+(\.\d+)?\s*[A-Z]{2,6}$~', $s)) return true;
                if (preg_match('~^https?://~i', $s)) return true;
                if (preg_match('~\b(claim|login|submit|continue|next)\b~i', $s)) return true;
                return false;
            }

            private function _getName(DOMXPath $xp, DOMNode $container): string {
                $heads = $xp->query(".//h1|.//h2|.//h3|.//h4|.//h5|.//h6", $container);
                foreach ($heads as $h) {
                    $t = $this->_getNodes($xp, $h);
                    if (!$this->_badName($t) && mb_strlen($t) >= 2 && mb_strlen($t) <= 40) {
                        return $t;
                    }
                }

                $nodes   = $xp->query(".//a|.//span|.//strong|.//b|.//label", $container);
                $checked = 0;
                foreach ($nodes as $n) {
                    if (++$checked > 30) break;
                    $t = $this->norm($this->_getNodes($xp, $n));
                    if ($this->_badName($t))                      continue;
                    if (mb_strlen($t) < 2 || mb_strlen($t) > 40) continue;
                    if (preg_match('~\d~', $t))                   continue;
                    return $t;
                }

                $all = $this->norm($this->_getNodes($xp, $container));
                if (preg_match('~^([^\d]{2,40})~u', $all, $m)) {
                    $cand = $this->norm($m[1]);
                    if (!$this->_badName($cand)) return $cand;
                }

                return "";
            }
        })->data;
        
    }

    public static function limit($id) {
        $parts = explode('/', $id);
        if (count($parts) < 2) return (int) $id > 0;
        return (int) $parts[0] > 0;
    }

    public static function exec(?Provider $api = null, $url = '', $noapi = false) {
        if ($noapi) $api = null;
    
        try {
            $f_url = (new Links($url))->exec($api);
    
            if ($f_url && is_string($f_url)) {
                Logger::X('ok', " SL Direct passed", true, true);
                return $f_url;
            }
        } catch (Throwable $e) {
            Logger::X('err', " SL Direct failed: " . $e->getMessage());
        }
    
        if (!$api) return false;
    
        $solver = Config::getKeys($api, 'shortlink', 'tkn');
    
        if (stripos($url, 'coinclix')) return false;
        if (!$solver || !method_exists($solver, 'shortLink')) return false;
    
        $res = $solver->shortLink($url);
    
        if (isset($res['fail']) && $solver !== $api) $res = method_exists($api, 'shortLink') ? $api->shortLink($url) : ['fail' => 1];
    
        if (isset($res['done'])) return $res['done'];
    
        return false;
    }

}
