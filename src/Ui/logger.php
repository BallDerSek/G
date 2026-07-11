<?php

final class Logger {
    
    /* pattern die log err 
    die(Logger::X('err', 'die1'));
    (Logger::X('err', 'die2') ?: die);
    die(Logger::X('err', 'die3'));
    */

    
    public static function X($i = '', $t = "\n", $n = true, $b = false) {
        
        $b = $b ? BOLD : '';
        if (!$n && !animate()) $n = true;
        $i = trim($i);
        
        switch (strtoupper(trim($i))) {
            case 'ERR':
            case 'ERROR':
            case 'DANGER':
                $p = BOLD.FGb['RED'];  break;
                
            case 'INFO':
            case 'GOOD':
                $p = $b.FGb['CYN']; break;
            
            case 'WARN':
            case 'WARNING':
                $p = $b.FGb['YLW']; break;
                
            case 'OK':
            case 'SUC':
            case 'GREAT':
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
        $formatted = ($t ? BOLD . FGb['WHT'] . '[' . date('H:i:s') . '] ' . RSET : '') . BOLD . RBW[array_rand(RBW)] . trim($m) . RSET;
    
        if (outTty()) {
            echo $formatted . ($n ? PHP_EOL : '');
            fflush(STDOUT);
        } else self::X('', $m, $n);
    }
    
    public static function M($mail, $mask = true) {
        
        $formatted = $mask ? maskEmail($mail) : $mail;
        
        #print(FGd['CYN'].maskEmail($mail).RSET." ");
        print(FGd['CYN'].$formatted.RSET." ");
        
    }
    
}