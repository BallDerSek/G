<?php

final class AntibotLinks {

    public static function exec($type, $api, $data = [], $force = false) {
        if (!$type) return null;

        return match ($type) {
            'image' => self::solveImage($api, $force, $data),
            'emoji' => self::solveEmoji($data),
            default => null,
        };
    }

    private static function solveEmoji($data) {
        $_ask = $data['main'] ?? [];
        $ab_t = $data['rels'] ?? [];

        if (empty($_ask) || empty($ab_t)) return 77;

        $db_file = LIBDIR . '/atb_e.json';
        $db = file_exists($db_file) ? json_decode(_get($db_file), true) : [];

        if (!is_array($db)) $db = [];

        $solution = [];
        $_tokens = $ab_t;

        foreach ($_ask as $e_nam) {
            $e_nam = strtolower($e_nam);
            $ab_e = array_search($e_nam, $db);

            if ($ab_e !== false && isset($ab_t[$ab_e])) {
                $solution[$e_nam] = $ab_t[$ab_e];
                unset($_tokens[$ab_e]);
            } else {
                $solution[$e_nam] = null;
            }
        }

        foreach ($solution as $e_nam => $token) {
            if ($token === null && count($_tokens) === 1) {
                $solution[$e_nam] = array_shift($_tokens);
            }
        }

        if (in_array(null, $solution, true)) return 77;

        return implode(' ', $solution);
    }

    private static function solveImage($api, $force, $data) {
    
        if (!isset(Api::B64[get_class($api)]['antibot'])) {
            Logger::X('err', 'provider not support atb');
            return 77;
        }
    
        if (empty($data['main'])) {
            return 77;
        }
    
        if ($force) {
            $atb = $api->atb($data);
        } else {
            $solver = Config::getKeys($api, 'antibot', 'b64');
            $atb = $solver->atb($data);
        }
    
        if (isset($atb['fail'])) {
            return 77;
        }
    
        return $atb['done'];
    }
    
}
