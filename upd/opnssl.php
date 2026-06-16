<?php

define('RUNNER', '11.8.0'); 

$name = preg_replace('/[^a-zA-Z0-9_]/', '', trim(readline('name: '))); 



$useV2 = (random_int(0, 1) === 1);
echo $useV2 ? "mode: v2\n" : "mode: v1\n";

if ($useV2) v2($name);
else v1($name);







function v1 ($name) {
    global $argv;
file_put_contents("$name.txt", base64_encode(openssl_encrypt("DATASET[meta:[name:$name;ver:".trim(readline('ver: ')).";min_launcher:". RUNNER ."],content:[###".base64_encode(file_get_contents($argv[1]))."###]]", 'AES-128-CBC', str_pad($name, 16, '0'), OPENSSL_RAW_DATA, substr(hash('sha256', base64_encode($name), true), 0, 16))));
echo "written: $name.txt\n";
}







function v2 ($name) {
    global $argv;
$key = str_pad($name, 16, '0');
$iv  = substr(hash('sha256', base64_encode($name), true), 0, 16);

$ct = openssl_encrypt("DATASET[meta:[name:$name;ver:".trim(readline('ver: ')).";min_launcher:".RUNNER."],content:[###".base64_encode(file_get_contents($argv[1]))."###]]", 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);



file_put_contents("$name.txt", base64_encode(json_encode(['v'   => 2, 'alg' => 'AES-128-CBC+HMAC-SHA256', 'ct'  => base64_encode($ct), 'mac' => base64_encode(hash_hmac('sha256', $iv . $ct, $key, true)),], JSON_UNESCAPED_SLASHES)));
echo "written: $name.txt\n";
}






function v3($name) {
    global $argv;

    $ver = trim(readline('ver: '));

    $metaArr = [ 'name' => $name, 'ver'  => $ver, 'min_launcher' => RUNNER, ];
    $meta = json_encode($metaArr, JSON_UNESCAPED_SLASHES);

    $content = base64_encode(file_get_contents($argv[1]));

    $salt = random_bytes(16);
    $iter = 200000;

    $master = RUNNER . '|' . $name;

    $key = hash_pbkdf2('sha256', $master, $salt, $iter, 32, true);

    $nonce = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt(
        $content,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        $meta,
        16
    );
    if ($ct === false) throw new RuntimeException('openssl_encrypt failed');

    $payload = [
        'v'     => 3,
        'alg'   => 'AES-256-GCM+PBKDF2-SHA256',
        'iter'  => $iter,
        'salt'  => base64_encode($salt),
        'nonce' => base64_encode($nonce),
        'tag'   => base64_encode($tag),
        'meta'  => $meta,
        'ct'    => base64_encode($ct),
    ];

    file_put_contents("$name.txt", base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), LOCK_EX);
    @chmod("$name.txt", 0600);
    echo "written: $name.txt\n";
} 




function v4($name) {
    global $argv;

    $ver = trim(readline('ver: '));

    $metaArr = [
        'name' => $name,
        'ver'  => $ver,
        'min_launcher' => RUNNER,
        'comp' => 'deflate',
        'lvl'  => 6,
    ];
    $meta = json_encode($metaArr, JSON_UNESCAPED_SLASHES);

    // raw bytes file
    $raw = file_get_contents($argv[1]);
    if ($raw === false) throw new RuntimeException('read file failed');

    // compress BEFORE encrypt
    $lvl = 6;
    $z = gzdeflate($raw, $lvl);
    if ($z === false) throw new RuntimeException('compress failed');

    // KDF (konsisten dengan pilihan kamu)
    $salt = random_bytes(16);
    $iter = 200000;
    $master = RUNNER . '|' . $name;

    $key = hash_pbkdf2('sha256', $master, $salt, $iter, 32, true);

    // AES-GCM
    $nonce = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt(
        $z,                 // bytes compressed
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        $meta,              // AAD (meta)
        16
    );
    if ($ct === false) throw new RuntimeException('openssl_encrypt failed');

    $payload = [
        'v'     => 4,
        'alg'   => 'AES-256-GCM+PBKDF2-SHA256',
        'iter'  => $iter,
        'salt'  => base64_encode($salt),
        'nonce' => base64_encode($nonce),
        'tag'   => base64_encode($tag),
        'meta'  => $meta,
        'ct'    => base64_encode($ct),
    ];

    file_put_contents("$name.txt", base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), LOCK_EX);
    @chmod("$name.txt", 0600);
    echo "written: $name.txt\n";
}