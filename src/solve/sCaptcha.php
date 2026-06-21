<?php


class sCaptcha {
    
    public static function rotate($html) {
        $solution = ['rot_captcha_val' => 0];
        if (!getDeps('gd@php')) die(Logger::X('err', 'gd@php is missing'));
        
        $_targetText = Scraper::_xP($html, "//div[@id='rc-title']//strong");
        $targetStr = isset($_targetText[0]) ? strtoupper($_targetText[0]) : 'UP';
        $targetDegrees = 270; 
        
        if (strpos($targetStr, 'DOWN') !== false)  $targetDegrees = 90;
        if (strpos($targetStr, 'RIGHT') !== false) $targetDegrees = 0;
        if (strpos($targetStr, 'LEFT') !== false)  $targetDegrees = 180;
        
        $_b = Scraper::find($html, 'rc-img', 'img', 'src', 'id')[0];
        $b64 = substr($_b, strrpos($_b, ',') + 1);
        
        $_img = base64_decode($b64);
        if (!$_img) return $solution;
        
        #$img = imagecreatefromstring($_img);
        $img = SolveUtils::createImg($_img);
        $W = imagesx($img);
        $H = imagesy($img);
        $_slc = max(5, (int)round(max($W, $H) * 0.08));
        
        $_brdr = [];
        for ($x = 0; $x < $W; $x++) {
            $_brdr[] = imagecolorat($img, $x, 0);
            $_brdr[] = imagecolorat($img, $x, $H - 1);
        }
        for ($y = 1; $y < $H - 1; $y++) {
            $_brdr[] = imagecolorat($img, 0, $y);
            $_brdr[] = imagecolorat($img, $W - 1, $y);
        }
        $counts = array_count_values($_brdr);
        arsort($counts);
        $bgRaw = key($counts);
        $bgR = ($bgRaw >> 16) & 0xFF;
        $bgG = ($bgRaw >> 8)  & 0xFF;
        $bgB = $bgRaw & 0xFF;
        
        $_bnr = [];
        for ($y = 0; $y < $H; $y++) {
            for ($x = 0; $x < $W; $x++) {
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8)  & 0xFF;
                $b =  $c        & 0xFF;
                $_dst = sqrt(($r-$bgR)**2 + ($g-$bgG)**2 + ($b-$bgB)**2);
                $_bnr[$y][$x] = ($_dst > 50) ? 1 : 0;
            }
        }
        
        $_vst = [];
        $_bst = [];
        for ($sy = 0; $sy < $H; $sy++) {
            for ($sx = 0; $sx < $W; $sx++) {
                if (!($_bnr[$sy][$sx] ?? 0) || ($_vst[$sy][$sx] ?? false)) continue;
                $p_a = [];
                $q_a = [[$sx, $sy]];
                $_vst[$sy][$sx] = true;
                while (!empty($q_a)) {
                    [$cx2, $cy2] = array_pop($q_a);
                    $p_a[] = [$cx2, $cy2];
                    foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dx2,$dy2]) {
                        $nx2 = $cx2 + $dx2; $ny2 = $cy2 + $dy2;
                        if ($nx2 < 0 || $nx2 >= $W || $ny2 < 0 || $ny2 >= $H) continue;
                        if (!($_bnr[$ny2][$nx2] ?? 0) || ($_vst[$ny2][$nx2] ?? false)) continue;
                        $_vst[$ny2][$nx2] = true;
                        $q_a[] = [$nx2, $ny2];
                    }
                }
                if (count($p_a) > count($_bst)) $_bst = $p_a;
            }
        }
        
        $n = count($_bst);
        if ($n < 10) return $solution;
        
        $sumX = $sumY = 0.0;
        foreach ($_bst as [$px, $py]) {
            $sumX += $px; $sumY += $py; 
        }
        
        $cxC = $sumX / $n; $cyC = $sumY / $n;
        $mu20 = $mu02 = $mu11 = 0.0;
        
        foreach ($_bst as [$px, $py]) {
            $dx2 = $px - $cxC; $dy2 = $py - $cyC;
            $mu20 += $dx2 * $dx2; $mu02 += $dy2 * $dy2; $mu11 += $dx2 * $dy2;
        }
        
        $mu20 /= $n; $mu02 /= $n; $mu11 /= $n;
        $angle = 0.5 * atan2(2 * $mu11, $mu20 - $mu02);
        $cosA = cos($angle); $sinA  = sin($angle);
        
        $t_V = [];
        foreach ($_bst as [$px, $py]) {
            $t_V[] = ($px - $cxC) * $cosA + ($py - $cyC) * $sinA;
        }
        $tMin = min($t_V); $tMax = max($t_V);
        
        $avgDev = function($t_C) use ($_bst, $cxC, $cyC, $cosA, $sinA, $t_V, $_slc) {
            $sum = 0.0; $cnt = 0;
            foreach ($_bst as $i => [$px, $py]) {
                if (abs($t_V[$i] - $t_C) <= $_slc) {
                    $sum += abs(-($px - $cxC) * $sinA + ($py - $cyC) * $cosA);
                    $cnt++;
                }
            }
            return $cnt > 0 ? $sum / $cnt : INF;
        };
        
        $_minn = $avgDev($tMin);
        $_maxx = $avgDev($tMax);
        $v_A = ($_minn < $_maxx) ? 'min' : 'max';
        $cntPos = 0; $cntNeg = 0;
        
        foreach ($t_V as $t) {
            if ($t >= 0) $cntPos++; else $cntNeg++; 
        }
        
        $v_B = ($cntNeg < $cntPos) ? 'min' : 'max';
        $he_ = $v_A; 
        $he_T = ($he_ === 'min') ? $tMin : $tMax;
        $te_T = ($he_ === 'min') ? $tMax : $tMin;
        $vecDx = ($he_T - $te_T) * $cosA;
        $vecDy = ($he_T - $te_T) * $sinA;
        $arr_D = fmod(rad2deg(atan2($vecDy, $vecDx)) + 360, 360);
        $rot_V = (int) round(fmod($targetDegrees - $arr_D + 360, 360));
        return ['rot_captcha_val' => $rot_V];
    }
    
    public static function shield($fau) {
        $json = json_decode(Scraper::_jP($fau, '/var D=({.*?});/')[1][0] ?? '', true);
        if (!$json) return ['shield_answer' => ""];
        
        $grid = $json['grid'];
        #print_r($grid);
        $instruction = strtolower($json['instruction']);
        #logx('ok', " [ $instruction ]", true, true);
        
        $ans = [];
        if (str_contains($instruction, "belong") || str_contains($instruction, "different")) {
            $shapeCounts = array_count_values(array_column($grid, 'shape'));
            $colorCounts = array_count_values(array_column($grid, 'color'));
            foreach ($grid as $index => $item) {
                if ($shapeCounts[$item['shape']] === 1 || $colorCounts[$item['color']] === 1) {
                    $ans[] = $index;
                    break;
                }
            }
        } else {
            preg_match('/<b>(.*?)<\/b>/', $instruction, $match);
            $target = $match[1] ?? '';
            $colorMap = [
                'blue' => ['#3b82f6', '#2563eb', '#60a5fa', '#1d4ed8'],
                'red' => ['#ef4444', '#dc2626', '#f87171', '#b91c1c'],
                'green' => ['#22c55e', '#16a34a', '#4ade80', '#15803d'],
                'yellow' => ['#eab308', '#facc15', '#ca8a04'],
                'orange' => ['#f97316', '#ea580c', '#fb923c', '#f59e0b'],
                'pink' => ['#ec4899', '#db2777', '#f472b6'],
                'purple' => ['#a855f7', '#9333ea', '#c084fc'],
                'cyan' => ['#06b6d4', '#0891b2', '#22d3ee'],
                'gray' => ['#64748b', '#475569', '#94a3b8'],
                'indigo' => ['#6366f1', '#4f46e5', '#818cf8'],
                'sky' => ['#0ea5e9', '#0284c7', '#38bdf8']
            ];
            
            $shapes = ['circle', 'square', 'triangle', 'diamond', 'star', 'hexagon'];
            foreach ($grid as $index => $item) {
                $itemColor = strtolower($item['color']);
                $itemShape = strtolower($item['shape']);
                if (isset($colorMap[$target])) {
                    if (in_array($itemColor, $colorMap[$target])) {
                        $ans[] = $index;
                    }
                } elseif (in_array($target, $shapes)) {
                    if ($itemShape === $target) {
                        $ans[] = $index;
                    }
                }
            }
        }
        sort($ans);
        return ['shield_answer' => implode(',', $ans)];
    }
    
}