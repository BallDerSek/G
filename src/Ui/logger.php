<?php


function logg($clock = true, $msg = '', $n = true, $check = false) {
    return Logger::G($clock, $msg, $n);
}

function logx($in = "", $msg = "\n", $n = true, $b = false) {
    return Logger::X($in, $msg, $n, $b);
}

function logm($mail) {
    return Logger::M($mail);
}


/*
function logg($clock = true, $msg = '', $n = true, $check = false) {

    $theme = [
        ['bg' => BG['WHT'], 'bgk' => 'WHT'],
        ['bg' => BG['YLW'], 'bgk' => 'YLW'],
        ['bg' => BG['CYN'], 'bgk' => 'CYN'],
        ['bg' => BG['GRN'], 'bgk' => 'GRN'],
        ['bg' => BG['RED'], 'bgk' => 'RED'],
        ['bg' => BG['BLU'], 'bgk' => 'BLU'],
        ['bg' => BG['MAG'], 'bgk' => 'MAG'],
        ['bg' => BG['BLK'], 'bgk' => 'BLK'],
    ];

    $pick = $theme[array_rand($theme)];

    $fg = FGo['BLK'];
    $fgk = 'BLK';

    if ($pick['bgk'] === 'BLK') {
        $fg = FGo['WHT'];
        $fgk = 'WHT';
    }

    $time = $clock
        ? BOLD.FGo['WHT']."[" . date('H:i:s') . "] ".RSET
        : "";

    if ($check) echo "BG={$pick['bgk']} FG={$fgk}\n";
    
    $formatted = $time . $pick['bg'] . $fg . BOLD . " " . trim($msg) . " " . RSET;

    if (outTty()) {
        echo $formatted . ($n ? PHP_EOL : "");
        fflush(STDOUT);
    } else logx('', $msg, $n);
    
}
*/
/*
function logx($in = "", $msg = "\n", $n = true, $b = false) {
    $b = $b ? BOLD : '';
    if (!$n && !animate()) $n = true;
    $i = trim($in);

    switch (strtoupper(trim($i))) {
        case 'ERR':
        case 'ERROR':
            $p = BOLD.FGb['RED'];  break;
            
        case 'INFO': $p = $b.FGb['CYN']; break;
        
        case 'WARN':
        case 'WARNING':
            $p = $b.FGb['YLW']; break;
            
        case 'OK':
        case 'SUC':
        case 'SUCCESS':
            $p = $b.FGb['GRN']; break;
            
        default: $p = $b.FGo['WHT']; break;
    }

    $out = $p.$msg.RSET.($n ? PHP_EOL : '');
    print($out);
    fflush(STDOUT);
}
*/
/*
function logm($mail) {
    print(FGd['CYN'].maskEmail($mail).RSET." ");
    
}
*/

final class Logger {
    
    public static function X($i = '', $t = "\n", $n = true, $b = false) {
        
        $b = $b ? BOLD : '';
        if (!$n && !animate()) $n = true;
        $i = trim($i);
        
        switch (strtoupper(trim($i))) {
            case 'ERR':
            case 'ERROR':
                $p = BOLD.FGb['RED'];  break;
                
            case 'INFO':
                $p = $b.FGb['CYN']; break;
            
            case 'WARN':
            case 'WARNING':
                $p = $b.FGb['YLW']; break;
                
            case 'OK':
            case 'SUC':
            case 'SUCCESS':
                $p = $b.FGb['GRN']; break;
            
            default: $p = $b.FGo['WHT']; break;
        }
        
        $out = $p.$t.RSET.($n ? PHP_EOL : '');
        print($out);
        fflush(STDOUT);
        
        return 0;
    }
    
    public static function G($t = true, $m = '', $n = true) {
        
        $theme = [
            ['bg' => BG['WHT'], 'bgk' => 'WHT'],
            ['bg' => BG['YLW'], 'bgk' => 'YLW'],
            ['bg' => BG['CYN'], 'bgk' => 'CYN'],
            ['bg' => BG['GRN'], 'bgk' => 'GRN'],
            ['bg' => BG['RED'], 'bgk' => 'RED'],
            ['bg' => BG['BLU'], 'bgk' => 'BLU'],
            ['bg' => BG['MAG'], 'bgk' => 'MAG'],
            ['bg' => BG['BLK'], 'bgk' => 'BLK'],
        ];
        
        $pick = $theme[array_rand($theme)];
        $fg = FGo['BLK'];
        $fgk = 'BLK';
        
        if ($pick['bgk'] === 'BLK') {
            $fg = FGo['WHT'];
            $fgk = 'WHT';
        }
        
        $time = $t ? BOLD . FGo['WHT'] . "[" . date('H:i:s') . "] " . RSET : "";
        
        $formatted = $time . $pick['bg'] . $fg . BOLD . " " . trim($m) . " " . RSET;
        
        if (outTty()) {
            echo $formatted . ($n ? PHP_EOL : "");
            fflush(STDOUT);
        } else logx('', $m, $n);
        
        #return;
        
    }
    
    public static function M($mail, $mask = true) {
        
        $formatted = $mask ? maskEmail($mail) : $mail;
        
        #print(FGd['CYN'].maskEmail($mail).RSET." ");
        print(FGd['CYN'].$formatted.RSET." ");
        
    }
    
}