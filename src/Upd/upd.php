<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

//BOT parsing 
function parsePkg($s): array {
    $s = trim($s);
    if (!preg_match('/^DATASET\s*\[\s*meta:\[(.*?)\]\s*,\s*content:\[(.*?)\]\s*\]\s*$/s', $s, $m)) {
        throw new Exception("invalid DATASET");
    }
    $metaRaw = trim($m[1]); $contentRaw = trim($m[2]);

    // parse meta
    $meta = [];
    foreach (preg_split('/\s*;\s*/', $metaRaw, -1, PREG_SPLIT_NO_EMPTY) as $pair) {
        if (strpos($pair, ':') === false) continue;
        [$k, $v] = array_map('trim', explode(':', $pair, 2));
        $meta[strtolower($k)] = $v;
    } 
    
    if (empty($meta['name'])) {
        throw new Exception("missing name");
    }

    if (!preg_match('/###(.*?)###/s', $contentRaw, $cm)) {
        throw new Exception("missing content");
    }

    $b64 = preg_replace('/\s+/', '', $cm[1]);
    $content = base64_decode($b64, true);
    if ($content === false) {
        throw new Exception("invalid content");
    }
    return ['meta' => $meta, 'content' => $content];
}
 
function getBot($pkgFile): void {
styler("getting bot", function() { _sle(1); });
    $path = UPDDIR."/$pkgFile";
    
    $nameNoExt = pathinfo($pkgFile, PATHINFO_FILENAME);

    try {
        $j = json_decode(base64_decode(trim(_get($path)), true), true);
        
        if (is_array($j) && ($j['v']) === 2) {
            if (!hash_equals(base64_decode($j['mac'], true), hash_hmac('sha256', substr(hash('sha256', base64_encode($nameNoExt), true), 0, 16) . base64_decode($j['ct'], true), str_pad($nameNoExt, 16, '0'), true))) throw new Exception("txt corrupt"); 
            $ds = parsePkg(openssl_decrypt(base64_decode($j['ct'], true), 'AES-128-CBC', str_pad($nameNoExt, 16, '0'), OPENSSL_RAW_DATA, substr(hash('sha256', base64_encode($nameNoExt), true), 0, 16))); 
        } else {
            $ds = parsePkg(openssl_decrypt(base64_decode(trim(_get($path)), true), 'AES-128-CBC', str_pad($nameNoExt, 16, '0'), OPENSSL_RAW_DATA, substr(hash('sha256', base64_encode($nameNoExt), true), 0, 16)));
            if ($ds === false) throw new Exception("decrypt error");
        }
    } catch (Throwable $e) {
        logx('err', "failed: {$e->getMessage()}");
        return;
    }

    $m = $ds['meta']; $c = $ds['content']; 

    // version gate
    if (version_compare(RUNNER, trim($m['min_launcher']), '<')) { logx('err', "please update launcher"); return;}


    $botName = $m['name'];
    $botPath = BOTDIR . "/$botName";
    if (!is_dir($botPath)) {mkdir($botPath, 0777, true);}

    if (stripos(ltrim($c), '<?php') !== 0) {$c = "<?php\n".$c;}
    _put("$botPath/$botName.php", rtrim($c)."\n");
    logx('ok', "installed: {$botName}.php");
    @unlink($path);
    foreach (glob(UPDDIR."/*.php") as $file) {
        @unlink($file);
    }
} 

//TXT scanning 
function viewTxt(): array {
    $files = glob(rtrim(UPDDIR, '/')."/*.txt");
    if (!$files) return [];
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return array_map('basename', $files);

}