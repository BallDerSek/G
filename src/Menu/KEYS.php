<?php

class KEYS {

    private static $file = LIBDIR . '/apikey.json';

    private static $defaultEndpoints = [
        'https://solverify.net' => '',
        'http://tertuyul.my.id' => '',
        'Xevil_check_bot.t.me' => '',
        'https://waryono.my.id' => '',
        'http://multibot.in' => '',
        'https://capsolver.com' => '',
        'https://buxads.com/api-token' => '',
    ];

    public static function sync() {
        $data = is_file(self::$file) ? json_decode(_get(self::$file), 1) : [];

        $data = (is_array($data) && !empty($data)) ? $data : self::$defaultEndpoints;

        foreach ($data as $ep => $val) $GLOBALS['_CTX']['apikey'][$ep] = $val;
    }

    public static function run() {
        self::sync();

        if (!hasTty() || getenv('CI') === '1') return self::CI_env();

        return self::CLI_env();
    }

    private static function CLI_env() {

        $providers = array_merge(['no apikey'], array_keys(self::$defaultEndpoints), ['update keys']);

        $idx = pickIndex($providers, function($providers, $idx) {

            Logger::X('', "SELECT PROVIDER\n");

            foreach ($providers as $i => $url) {

                $val = $GLOBALS['_CTX']['apikey'][$url] ?? '';

                $status = empty($val) ? FGo["RED"] . '[NO]' : FGo["GRN"] . '[ON]';

                echo $status.RSET.($i === $idx ? FGo["BLU"]." => " : "    ").$url.RSET."\n";
            }
        });

        $endpoint = $providers[$idx];
        
        if ($endpoint === 'update keys') {
            self::newKeys();
            return self::CLI_env();
        }
        
        if ($endpoint === 'no apikey') { 
            _cle();
            return null;
        }

        if (empty($GLOBALS['_CTX']['apikey'][$endpoint])) {

            $apiKey = self::_ask($endpoint);

            if ($apiKey === '') {
                Logger::X('err', "rejected");
                return self::CLI_env();
            }
            
            _cle();
            return Api::use($endpoint, $apiKey);
        }
        
        _cle();
        return Api::use($endpoint, $GLOBALS['_CTX']['apikey'][$endpoint]);
    }

    private static function CI_env() {

        $endpoint = self::maps((string)getenv('API'));
        $apiKey = trim((string)getenv('KEY'));

        if ($endpoint === '' || $apiKey === '') die(Logger::X('err', 'API/KEY required'));

        $solver = Api::use($endpoint, $apiKey);

        if (!self::viewKeys($solver)) die(Logger::X('err', 'API/KEY rejected'));

        return $solver;
    }

    private static function _ask($endpoint) {

        echo "\n".BOLD.$endpoint.RSET."\n";

        $apiKey = trim(_rl("  apikeys: "));
        if ($apiKey === '') return '';

        $solver = Api::use($endpoint, $apiKey);

        if (!self::viewKeys($solver)) {
            Logger::X('err', "rejected");
            _sle(1);
            return '';
        }

        $data = $GLOBALS['_CTX']['apikey'];
        $data[$endpoint] = $apiKey;

        _put(self::$file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $GLOBALS['_CTX']['apikey'][$endpoint] = $apiKey;

        Logger::X('ok', "SAVED");

        return $apiKey;
    }

    private static function viewKeys($solver) {
        try {
            return styler("CHECK " . get_class($solver), function () use ($solver) {
                _sle(2);
                ob_start();
                try {
                    $ok = $solver->getInfo();
                } finally {
                    ob_end_clean();
                }
                return (bool)$ok;
            });
        } catch (Throwable $e) {
            Logger::X('err', $e->getMessage());
            return false;
        }
    }

    public static function newKeys() {

        $providers = array_keys(self::$defaultEndpoints);

        $idx = pickIndex($providers, function($providers, $idx) {

            Logger::X('warn', "UPDATE KEY\n", true, true);

            foreach ($providers as $i => $url) {

                $val = $GLOBALS['_CTX']['apikey'][$url] ?? '';

                $status = empty($val) ? FGo["RED"].'[NO]' : FGo["GRN"].'[ON]';

                echo $status.RSET.($i === $idx ? FGo["BLU"]." => " : "    ").$url.RSET."\n";
            }
        });

        self::_ask($providers[$idx]);
    }

    public static function maps($v) {
        $v = trim($v);
        $cfg = Api::KEY[$v] ?? Api::KEY[strtolower($v)] ?? null;
        return $cfg['ep'] ?? $v;
    }

}
