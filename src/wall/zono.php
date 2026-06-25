<?php


function zono($url, $tmr , $api, $host, $mail) {
    
    var_dump($url);
    
    $ck = config::cookie($host);
    $ua = config::uagent('mobile');
    $context = ['cookie' => $ck, 'uagent' => $ua];
    
    
    $zono_h = "https://offerzono.com";
    
    $zn_get = Net::C($url, 'GET', null, $ck, [], $host, $ua);
    _put('znget.html', $zn_get);
    $f = Scraper::payload($zn_get);
    print_r($f);
    
    
    
    
    
}


function offerzono($html, $type = 'SL') {
    $dom = Scraper::dom($html);
    
    if ($type == 'SL') {
        // SL nanti
        return [];
        
    } else {
        $result = ['ptcs' => [], 'prom' => []];
        
        // SURF ADS
        $cards = $dom->query("//div[@x-show=\"activeTab === 'tabSptcads'\"]//div[contains(@class, 'links_card')]");
        foreach ($cards as $card) {
            $btn = $dom->query(".//button[contains(@class, 'view_ads_click')]", $card);
            if ($btn->length === 0) continue;
            
            $button = $btn->item(0);
            $titleNode = $dom->query(".//h1", $card);
            $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';
            
            $result['ptcs'][] = [
                'data' => [
                    'id' => (int)$button->getAttribute('data-adid'),
                    'timer' => (int)$button->getAttribute('data-adtime'),
                    'url' => $button->getAttribute('value')
                ],
                'info' => [
                    'title' => $title,
                    'reward' => trim($button->textContent)
                ]
            ];
        }
        
        // WINDOW ADS
        $cards = $dom->query("//div[@x-show=\"activeTab === 'tabWptcads'\"]//div[contains(@class, 'links_card')]");
        foreach ($cards as $card) {
            $btn = $dom->query(".//button[@value]", $card);
            if ($btn->length === 0) continue;
            
            $button = $btn->item(0);
            $titleNode = $dom->query(".//h1", $card);
            $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';
            
            $result['prom'][] = [
                'url' => $button->getAttribute('value'),
                'info' => [
                    'title' => $title,
                    'reward' => trim($button->textContent)
                ]
            ];
        }
        
        $result['ptcs_'] = count($result['ptcs']);
        $result['prom_'] = count($result['prom']);
        
        return $result;
    }
}