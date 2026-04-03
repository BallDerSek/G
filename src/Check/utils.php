<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

loader(__DIR__); 

function IP() {
    return $GLOBALS['_CTX']['geo']['ip'];
}

function TIMEZONE() {
    return $GLOBALS['_CTX']['geo']['timezone'];
}
    
function COUNTRY() {
    return $GLOBALS['_CTX']['geo']['country'];
}
    
function COUNTRY_CODE() {
    return $GLOBALS['_CTX']['geo']['country_code'];
}
    
function LANGUAGE() {
    return $GLOBALS['_CTX']['geo']['language'];
}
    