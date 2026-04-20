<?php
if (!defined('ROOT')) { die; }
#$api = onKeys();

$acc = config::credential([], true);
$mail = $acc['mail'];
$pass = $acc['pass'];

$cookieFile = config::cookie($mail);
$userAgent = config::uagent();



$host = 'https://tfaucet.com';
$domain = parse_url($host, PHP_URL_HOST);

banner(); 
login:

$_0 = Net::C()