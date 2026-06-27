<?php

final class Retry {

    public static function until(callable $fn, $max = 3, $sle = 1) {

        $res = null;
        for ($attempt = 0; $attempt < $max; $attempt++) {
            if ($attempt > 0) _sle($sle);
            $res = $fn($attempt);
            if ($res) return $res;
        }
        return $res;
    }

    public static function untilArray(callable $fn, $max = 3, $sle = 1) {

        $res = null;

        for ($attempt = 0; $attempt < $max; $attempt++) {
            if ($attempt > 0) _sle($sle);
            $res = $fn($attempt);
            if ($res) return $res;

        }

        return $res;
    }

    public static function untilStrict(callable $fn, $max = 3, $sle = 1,array $failValues = [false, 77, 404, 471, null, '']) {

        $res = null;
        for ($attempt = 0; $attempt < $max; $attempt++) {
            if ($attempt > 0) _sle($sle);
            $res = $fn($attempt);
            if (!in_array($res, $failValues, true)) return $res;
        }

        return $res;
    }

    public static function untilWhen(callable $fn, callable $ok, $max = 3, $sle = 1) {
        $res = null;
        for ($attempt = 0; $attempt < $max; $attempt++) {
            if ($attempt > 0) _sle($sle);
            $res = $fn($attempt);
            if ($ok($res)) return $res;
        }

        return $res;
    }
}
