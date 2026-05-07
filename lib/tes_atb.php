<?php


fungsi1: {
    private $LEET_CODE_TO_NUM = [
        'zoo'=>'200','ozo'=>'020','ooz'=>'002',
        'soo'=>'500','oso'=>'050','oos'=>'005',
        'lol'=>'101','sos'=>'505','zoz'=>'202','lll'=>'111',
    ];

    private $OCR_LEET_CODE_FIXES = [
        '|0|'=>'lol','|o|'=>'lol','1o1'=>'lol','l0l'=>'lol',
        '0z0'=>'ozo','0zo'=>'ozo','oz0'=>'ozo',
        '0oz'=>'ooz','00z'=>'ooz','o0z'=>'ooz','Q0z'=>'ooz','qoz'=>'ooz',
        '5o5'=>'sos','s0s'=>'sos',
        'soi'=>'sos','sol'=>'sos','s0i'=>'sos','s0l'=>'sos',
        'zoi'=>'zoo','z0i'=>'zoo',
        '5oo'=>'soo','s00'=>'soo','so0'=>'soo','5o0'=>'soo',
        '0s0'=>'oso','os0'=>'oso','0so'=>'oso',
        '0os'=>'oos','00s'=>'oos','o0s'=>'oos',
        'zo2'=>'zoz','2oz'=>'zoz','z0z'=>'zoz','2o2'=>'zoz',
        'z00'=>'zoo','zo0'=>'zoo',
        '|l|'=>'lll','1l1'=>'lll','|||'=>'lll',
        'oo5'=>'oos','0o5'=>'oos','o05'=>'oos',
        'ooo'=>'oos', 'loi'=>'lol', 'soi'=>'sos', // dll
    ];




private function ocrImageBase64(string $base64, bool $strictLeet = false): string {
        $prompt = $strictLeet
            ? 'Read ONLY the characters in the image exactly as shown. This is leet/obfuscated text. Do NOT convert to plain words. Output exactly what you see character by character. Valid examples: "3l3ph@nt", "c0w", "m0us3", "31eph@nt", "l!on", "t!g3r". Output a single token only.'
            : 'Read ONLY the characters in the image exactly as shown. Do NOT interpret as words. Output exactly what you see. Valid examples: "200", "101", "c@t", "3+2", "VII", "IV". Output a single token only.';
        $resp = $this->curl('https://ocrollama.my.id/api/generate', json_encode([
            'model'  => 'glm-ocr',
            'prompt' => $prompt,
            'images' => [$base64],
            'stream' => false,
        ]), ['Content-Type: application/json']);
        $data = json_decode($resp, true);
        return trim($data['response'] ?? '');
    }

    // ─── FIX v3.3: Fuzzy Leet Code Matcher ───────────────────────────────
    // Menangani kasus GLM hallucinate 1 karakter ekstra
    // Contoh: "sooo" (GLM baca lebih) → coba "soo" → "500" ✅
    //         "oooos" (GLM baca lebih) → coba dari belakang "oos" → "005" ✅
    private function findClosestLeetCode(string $lo): ?string {
        // 1. Exact match
        if (isset($this->LEET_CODE_TO_NUM[$lo])) return $this->LEET_CODE_TO_NUM[$lo];

        // 2. OCR fix table dulu
        if (isset($this->OCR_LEET_CODE_FIXES[$lo])) {
            $fixed = $this->OCR_LEET_CODE_FIXES[$lo];
            if (isset($this->LEET_CODE_TO_NUM[$fixed])) return $this->LEET_CODE_TO_NUM[$fixed];
        }

        // 3. Fuzzy: hanya aktif kalau string > 3 char dan semua char adalah leet valid (s/o/z/l)
        //    Hindari false positive pada kata biasa seperti "seven", "lion", dll
        if (strlen($lo) > 3 && preg_match('/^[sozl]+$/', $lo)) {
            // Coba potong dari belakang (GLM nambah ekstra di akhir)
            $fromFront = substr($lo, 0, 3);
            if (isset($this->LEET_CODE_TO_NUM[$fromFront])) {
                $this->log("Fuzzy leet (trim belakang): \"$lo\" → \"$fromFront\" = {$this->LEET_CODE_TO_NUM[$fromFront]}");
                return $this->LEET_CODE_TO_NUM[$fromFront];
            }

            // Coba potong dari depan (GLM nambah ekstra di awal)
            $fromBack = substr($lo, -3);
            if (isset($this->LEET_CODE_TO_NUM[$fromBack])) {
                $this->log("Fuzzy leet (trim depan): \"$lo\" → \"$fromBack\" = {$this->LEET_CODE_TO_NUM[$fromBack]}");
                return $this->LEET_CODE_TO_NUM[$fromBack];
            }

            // Coba semua sliding window 3-char (GLM insert di tengah)
            for ($i = 0; $i <= strlen($lo) - 3; $i++) {
                $window = substr($lo, $i, 3);
                if (isset($this->LEET_CODE_TO_NUM[$window])) {
                    $this->log("Fuzzy leet (window[$i]): \"$lo\" → \"$window\" = {$this->LEET_CODE_TO_NUM[$window]}");
                    return $this->LEET_CODE_TO_NUM[$window];
                }
            }
        }

        return null;
    }

    // ─── Normalise AntiBot value ──────────────────────────────────────────
    private function normaliseAntiBotValue(string $raw): string {
        $s  = trim($raw);
        // FIX: buang karakter punctuation di awal/akhir (titik koma, titik, koma, dll)
        $s  = trim($s, ',;.:?');
        $lo = strtolower($s);

        if ($s === '') return '';

        // Fix OCR misread umum (termasuk Roman numeral yg sering salah baca)
        $ocrFix = [
            // Angka teks
            'fen'    => 'ten',  'len'    => 'ten',
            'tern'   => 'ten',  'tеn'    => 'ten',
            'sjx'    => '6',    'slx'    => '6',
            'bix'    => '6',    'sіx'    => '6',
            '/'    => '1',
            'vim'    => '8',
            'IH'    => '9',    'ih'    => '9',
            // Roman: I sering dibaca l (huruf L kecil), V sering dibaca U/v
            'iu'     => 'iv',   'lv'     => 'iv',   'lu'     => 'iv',
            'iiv'    => 'iv',
            'vl'     => 'vi',   'vli'    => 'vii',
            'vlll'   => 'viii', 'vlii'   => 'viii', 'vlil'   => 'viii',
            'lll'    => 'iii',  'll'     => 'ii',
            'lx'     => 'ix',   'lxi'    => 'xi',
            'xl'     => 'xi',
            'iix'    => 'ix',
            // Pipe chars / vertical bars sebagai OCR misread Roman numeral
            // OCR sering baca I sebagai | (pipe)
            '|'      => 'i',
            '||'     => 'ii',
            '|||'    => 'iii',
            '||||'   => 'iii', // bisa III atau IV; default iii karena lebih umum
            '|||||'  => 'v',
            // H → II=2 (H terlihat seperti dua garis vertikal)
            'h'      => 'ii',
            'ooo' => '005',   // langsung ke angka jika dibutuhkan
            'loi' => '101',
            // U → V (OCR misread V sebagai U)
            'ui'     => 'vi',   // UI → VI=6
            'uii'    => 'vii',  // UII → VII=7
            'uiii'   => 'viii', // UIII → VIII=8
            'uiiii'  => 'ix',   // UIIII → IX? (jarang)
            // MI → III=3 (M terlihat seperti beberapa I yang menyatu)
            'mi'     => 'iii',  // MI → III=3
            'mii'    => 'iii',  // MII → III=3 (OCR tambah ekstra)
            // W → bisa jadi VV atau M, tapi paling umum diabaikan
            'wi'     => 'vi',   // WI → VI=6
            'wii'    => 'vii',  // WII → VII=7
            // Leet animal words: OCR sering salah baca 1→i padahal harusnya 1→l
            // (1!on, 1!0n = lion; t!g3r bisa terbaca t!93r, dll)
            '1!on'   => 'lion', '1!0n'  => 'lion', 'l!on'  => 'lion',
            '1ion'   => 'lion', 'li0n'  => 'lion', '1i0n'  => 'lion',
            // Lion: GLM baca 'l' sebagai '1', jadi l1on → 110n
'110n'      => 'lion',
'11on'      => 'lion',
'1l0n'      => 'lion',

// Elephant: GLM baca 'l'→'i' dan 't'→'f', '3'→'s','e','8' dll
// 3l3ph@nt → berbagai variasi OCR misread
'3ieph@nf'  => 'elephant',
'3l3ph@nf'  => 'elephant',
'3i3ph@nt'  => 'elephant',
'3ieph@nt'  => 'elephant',
'3l3ph@nt'  => 'elephant',
'sieph@nt'  => 'elephant',
'31eph@nt'  => 'elephant',
'3leph@nt'  => 'elephant',
'31eph@nf'  => 'elephant',
'3leph@nf'  => 'elephant',
'8leph@nt'  => 'elephant',
'8l3ph@nt'  => 'elephant',
'8ieph@nt'  => 'elephant',
'el3ph@nt'  => 'elephant',
'eleph@nt'  => 'elephant',
'3i3ph@nf'  => 'elephant',
'eieph@nt'  => 'elephant',
'3eph@nt'   => 'elephant',
'3iph@nt'   => 'elephant',
// Cow variasi
'c0w'       => 'cow',
'c@w'       => 'cow',
'cow'       => 'cow',
// Mouse variasi tambahan
'm0us3'     => 'mouse',
'm0use'     => 'mouse',
'mqus3'     => 'mouse',
'mquse'     => 'mouse',
'mous3'     => 'mouse',
'm@use'     => 'mouse',
'm@us3'     => 'mouse',
            't!93r'  => 'tiger','t!g3r' => 'tiger','tig3r' => 'tiger',
            'm0nk3y' => 'monkey','m0nkey'=> 'monkey',
            'b3ar'   => 'bear', 'b34r'  => 'bear',
            'w01f'   => 'wolf', 'w0lf'  => 'wolf',
            'f0x'    => 'fox',
            'd0g'    => 'dog',
            'c@t'    => 'cat',   'c4t'   => 'cat',   'c@ft'   => 'cat',
            'm0us3'  => 'mouse', 'm0use' => 'mouse','mQus3'  => 'mouse', 'mqus3' => 'mouse','mQuse'  => 'mouse', 'mquse' => 'mouse',
            'sn4il'  => 'snail', 'sna1l' => 'snail',
            'h3n'    => 'hen',
            'wh4le'  => 'whale', 'wh@le' => 'whale',
            'sh4rk'  => 'shark',
            'fr0g'   => 'frog',  'fr@g'  => 'frog',
            'duc k'  => 'duck',
            'g0at'   => 'goat',
            'b3ar'   => 'bear',  'b34r'  => 'bear',
            'r4bbit' => 'rabbit','r@bbit'=> 'rabbit',
            'sn4il'  => 'snail','sna1l' => 'snail',
        ];
        if (isset($ocrFix[$lo])) { $s = $ocrFix[$lo]; $lo = strtolower($s); }

        // ✅ FIX v3.3: Fuzzy leet code (handle GLM hallucinate ekstra char)
        $leetResult = $this->findClosestLeetCode($lo);
        if ($leetResult !== null) return $leetResult;

        // Roman numeral
        $roman = ['i'=>1,'ii'=>2,'iii'=>3,'iv'=>4,'v'=>5,'vi'=>6,'vii'=>7,'viii'=>8,'ix'=>9,'x'=>10,'xi'=>11,'xii'=>12];
        if (isset($roman[$lo])) return (string)$roman[$lo];

        // Unicode Roman
        $unicodeRoman = ['Ⅰ'=>1,'Ⅱ'=>2,'Ⅲ'=>3,'Ⅳ'=>4,'Ⅴ'=>5,'Ⅵ'=>6,'Ⅶ'=>7,'Ⅷ'=>8,'Ⅸ'=>9,'Ⅹ'=>10];
        if (isset($unicodeRoman[$s])) return (string)$unicodeRoman[$s];

        // Word number
        $words = [
            'one'=>1,'two'=>2,'three'=>3,'four'=>4,'five'=>5,'six'=>6,'seven'=>7,
            'eight'=>8,'nine'=>9,'ten'=>10,'eleven'=>11,'twelve'=>12,
        ];
        if (isset($words[$lo])) return (string)$words[$lo];

        // Math expression
        if (preg_match('/^(\d+)([+\-*×])(\d+)$/', $lo, $m)) {
            $a = (int)$m[1]; $b = (int)$m[3];
            $op = $m[2];
            if ($op === '+') return (string)($a + $b);
            if ($op === '-') return (string)($a - $b);
            return (string)($a * $b);
        }

        // Plain number
        if (is_numeric($lo)) return $lo;

        // Single char confusions
        $single = ['s'=>'5','b'=>'6','g'=>'9','q'=>'9','z'=>'2'];
        if (isset($single[$lo])) return $single[$lo];

        // Leet speak → plain word (d0g→dog, t!g3r→tiger, m0nk3y→monkey)
        $leetMap = ['0'=>'o','@'=>'a','3'=>'e','1'=>'i','!'=>'i','4'=>'a','$'=>'s','5'=>'s','7'=>'t','8'=>'b','9'=>'g'];
        $plain = strtr($lo, $leetMap);

        // FIX: Jika plain tidak valid, coba juga '1'→'l' (karena banyak leet pakai 1=l bukan 1=i)
        $leetMapL = ['0'=>'o','@'=>'a','3'=>'e','1'=>'l','!'=>'l','4'=>'a','$'=>'s','5'=>'s','7'=>'t','8'=>'b','9'=>'g'];
        $plainL = strtr($lo, $leetMapL);
        $knownWords = [
            // Hewan darat
            'dog','cat','fox','tiger','monkey','wolf','bear','lion','fish','bird',
            'frog','duck','cow','pig','owl','ant','bee','bat','rat','elk','horse',
            'deer','goat','lamb','crab','snail','whale','dolphin','apple','mango',
            'grape','lemon','peach','plum','cherry','melon','banana','orange',
            'rabbit','turtle','parrot','penguin','chicken','gorilla','buffalo',
            // Tambahan yang sering muncul di antibot
            'mouse','hamster','squirrel','hedgehog','koala','panda','giraffe',
            'zebra','hippo','rhino','camel','sheep','donkey','rooster','hen',
            'shrimp','lobster','shark','octopus','jellyfish','dragon','dinosaur',
            'flamingo','eagle','hawk','peacock','swan','crow','bee','wasp',
            'spider','scorpion','snake','lizard','crocodile','elephant',
            'kangaroo','wallaby','bison','moose','reindeer','alpaca','llama',
            'corgi','beagle','poodle','husky','shiba','golden',
        ];
        if (in_array($plain, $knownWords)) return $plain;
        if (in_array($plainL, $knownWords)) return $plainL;

        return $lo;
    }

    // ─── Parse AntiBot instruction ────────────────────────────────────────
    private function parseAntiBotInstruction(string $text): array {
        $tokens = preg_split('/[\s,،.;:]+/', $text);  // FIX: tambah ; sebagai delimiter
        $result = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') continue;
            $result[] = $this->normaliseAntiBotValue($token);
        }
        // Hapus duplikat berurutan
        $clean = [];
        foreach ($result as $v) {
            if (empty($clean) || end($clean) !== $v) $clean[] = $v;
        }
        return $clean;
    }

    // ─── Solve AntiBot ────────────────────────────────────────────────────
    private function solveAntiBot(string $faucetHtml): string {
        // 1. OCR instruction image
        if (!preg_match('#<img[^>]*src="data:image/png;base64,([^"]+)"[^>]*>#', $faucetHtml, $m)) {
            throw new \RuntimeException('Instruction image not found');
        }
        $instrB64  = $m[1];
        $instrText = '';
        for ($try = 0; $try < 5; $try++) {
            $instrText = $this->ocrImageBase64($instrB64);
            if (preg_match('/[0-9a-zA-Z]/', $instrText)) break;
            $this->log('OCR instruction tidak valid, retry...');
            sleep(1);
        }
        $this->log('AntiBot instruction raw: ' . $instrText);
        $order = $this->parseAntiBotInstruction($instrText);
        $this->log('Parsed order: ' . implode(', ', $order));

        if (empty($order)) {
            throw new \RuntimeException('Parsed order kosong');
        }

        // 2. Ambil ablinks
        if (!preg_match('/var\s+ablinks\s*=\s*(\[[^\]]*?\])/s', $faucetHtml, $m)) {
            file_put_contents($this->tempDir . '/faucet_debug.html', $faucetHtml);
            throw new \RuntimeException('ablinks not found');
        }
        $rawJson = str_replace(["\r", "\n", "\t", "'"], ['', '', '', '"'], trim($m[1]));
        $ablinks = json_decode($rawJson, true);
        if (!is_array($ablinks)) {
            preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $rawJson, $matches);
            $ablinks = $matches[1] ?? [];
        }
        $this->log('Extracted ' . count($ablinks) . ' antibot links');

        // 3. OCR semua button terlebih dahulu
        $buttons = [];
        foreach ($ablinks as $link) {
            if (preg_match('/rel="(\d+)"/', $link, $rm) && preg_match('#src="data:image/png;base64,([^"]+)"#', $link, $im)) {
                $raw = $this->ocrImageBase64($im[1]);
                $val = $this->normaliseAntiBotValue($raw);
                // Jika val masih terlihat seperti leet (ada @, angka-huruf), coba strict prompt
                if ($val === strtolower($raw) && preg_match('/[@!0-9]/', $raw)) {
                    $raw2 = $this->ocrImageBase64($im[1], true);
                    $val2 = $this->normaliseAntiBotValue($raw2);
                    if ($val2 !== $val && $val2 !== strtolower($raw2)) {
                        $this->log("  [OCR strict] rel={$rm[1]} raw2=\"$raw2\" norm2=\"$val2\" (sebelumnya: \"$val\")");
                        $raw = $raw2; $val = $val2;
                    }
                }
                $buttons[] = ['rel' => $rm[1], 'val' => $val, 'raw' => $raw];
                $this->log("  Button rel={$rm[1]} raw=\"$raw\" norm=\"$val\"");
            }
        }

        if (empty($buttons)) {
            throw new \RuntimeException('Tidak ada tombol antibot yang bisa di-OCR');
        }

        // 4. Pre-check semua match sebelum submit
        $this->log('Checking all matches before proceeding...');
        $tempUsed   = [];
        $allMatched = true;

        foreach ($order as $expected) {
            $matched = false;
            foreach ($buttons as $btn) {
                if (!in_array($btn['rel'], $tempUsed) && $btn['val'] === $expected) {
                    $tempUsed[] = $btn['rel'];
                    $matched    = true;
                    $this->log("  ✓ Pre-check: \"$expected\" → rel={$btn['rel']}");
                    break;
                }
            }
            if (!$matched) {
                $this->log("  ✗ Pre-check GAGAL: \"$expected\" tidak ditemukan di buttons");
                $allMatched = false;
            }
        }

        // 5. Jika gagal: coba re-normalise instruction dengan lookup ke nilai button
        if (!$allMatched) {
            $this->log('  [Fallback] Coba fuzzy matching antara order dan button values...');
            $availableVals = array_column($buttons, 'val');
            $this->log('  Available button vals: ' . implode(', ', $availableVals));

            // Strategi A: Levenshtein fuzzy match
            // Untuk setiap expected yang gagal, cari button dengan jarak Levenshtein terkecil
            $fuzzyOrder  = [];
            $fuzzyUsed   = [];
            $fuzzyOk     = true;

            foreach ($order as $expected) {
                // Cek exact match dulu
                $found = false;
                foreach ($buttons as $btn) {
                    if (!in_array($btn['rel'], $fuzzyUsed) && $btn['val'] === $expected) {
                        $fuzzyOrder[] = $btn['rel'];
                        $fuzzyUsed[]  = $btn['rel'];
                        $found = true;
                        break;
                    }
                }
                if ($found) continue;

                // Fuzzy: cari yang paling mirip (Levenshtein ≤ 2 atau substring)
                $bestRel   = null;
                $bestDist  = PHP_INT_MAX;
                foreach ($buttons as $btn) {
                    if (in_array($btn['rel'], $fuzzyUsed)) continue;
                    $dist = levenshtein($expected, $btn['val']);
                    // Juga coba: apakah expected adalah substring dari raw btn, atau sebaliknya
                    $rawLo = strtolower($btn['raw']);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $bestRel  = $btn['rel'];
                    }
                    // Exact raw (before normalise) check
                    if (strtolower($btn['raw']) === strtolower($expected) || $rawLo === $expected) {
                        $bestDist = 0;
                        $bestRel  = $btn['rel'];
                        break;
                    }
                }

                if ($bestRel !== null && $bestDist <= 2) {
                    $this->log("  [Fuzzy] \"$expected\" → rel=$bestRel (dist=$bestDist)");
                    $fuzzyOrder[] = $bestRel;
                    $fuzzyUsed[]  = $bestRel;
                } else {
                    // Strategi B: coba re-OCR instruction dan cocokkan ke raw button values
                    // Cek apakah expected cocok dengan raw OCR button (sebelum normalisasi)
                    $rawMatch = false;
                    foreach ($buttons as $btn) {
                        if (in_array($btn['rel'], $fuzzyUsed)) continue;
                        $rawNorm = $this->normaliseAntiBotValue($btn['raw']);
                        if ($rawNorm === $expected || strtolower($btn['raw']) === $expected) {
                            $this->log("  [RawFallback] \"$expected\" → rel={$btn['rel']} via raw=\"{$btn['raw']}\"");
                            $fuzzyOrder[] = $btn['rel'];
                            $fuzzyUsed[]  = $btn['rel'];
                            $rawMatch = true;
                            break;
                        }
                    }
                    if (!$rawMatch) {
                        $fuzzyOk = false;
                        $this->log("  [Fallback] Gagal: \"$expected\" tidak cocok satupun (best dist=$bestDist)");
                    }
                }
            }

            if ($fuzzyOk && count($fuzzyOrder) === count($order)) {
                $antibotlinks = implode(' ', $fuzzyOrder);
                $this->log('✓ AntiBot links final (fuzzy): ' . $antibotlinks);
                return $antibotlinks;
            }

            // Strategi C: posisi-based (angka = posisi button 1-based)
            $positionMatch = true;
            $posSequence   = [];
            foreach ($order as $expected) {
                $intVal = (int)$expected;
                if ($intVal >= 1 && $intVal <= count($buttons)) {
                    $posSequence[] = $buttons[$intVal - 1]['rel'];
                } else {
                    $positionMatch = false;
                    break;
                }
            }

            if ($positionMatch && count($posSequence) === count($order)) {
                $this->log('  [Fallback] Posisi-based match berhasil: ' . implode(', ', $posSequence));
                $antibotlinks = implode(' ', $posSequence);
                $this->log('✓ AntiBot links final (position): ' . $antibotlinks);
                return $antibotlinks;
            }

            $this->log('✗ Tidak semua button cocok → retry halaman baru');
            throw new \RuntimeException('AntiBot match incomplete - akan retry halaman baru');
        }

        // 6. Semua cocok → susun urutan final dan submit
        $this->log('✓ Semua match OK → menyusun urutan klik...');
        $used     = [];
        $sequence = [];
        foreach ($order as $expected) {
            foreach ($buttons as $btn) {
                if (!in_array($btn['rel'], $used) && $btn['val'] === $expected) {
                    $used[]     = $btn['rel'];
                    $sequence[] = $btn['rel'];
                    $this->log("  Klik \"$expected\" → rel={$btn['rel']}");
                    break;
                }
            }
        }

        $antibotlinks = implode(' ', $sequence);
        $this->log('✓ AntiBot links final: ' . $antibotlinks);
        return $antibotlinks;
    }
}

fungsi2: {
$LEET_MAP = [
    '@' => 'a', '4' => 'a',
    '1' => 'i', '!' => 'i',
    'I' => 'l',   // I kapital → l kecil
    'L' => 'l',   // L kapital → l kecil
    '3' => 'e',
    'v' => 'u', 'V' => 'u',
    '5' => 's', '$' => 's',
    '0' => 'o',
    '7' => 't', '+' => 't',
    '6' => 'g',
    '2' => 'z',
    '8' => 'b',
    '9' => 'q',
];

// ===== EKSPRESI MATEMATIKA → angka =====
// Sub image bisa berisi ekspresi: "1+2"=3, "2*4"=6, "2+8"=10
// Main image bisa berisi hasil: "3, 10, 8"
$MATH_EXPR_MAP = [
    // Penjumlahan
    '1+1' => '2', '1+2' => '3', '1+3' => '4', '1+4' => '5',
    '1+5' => '6', '1+6' => '7', '1+7' => '8', '1+8' => '9',
    '2+1' => '3', '2+2' => '4', '2+3' => '5', '2+4' => '6',
    '2+5' => '7', '2+6' => '8', '2+7' => '9', '2+8' => '10',
    '3+1' => '4', '3+2' => '5', '3+3' => '6', '3+4' => '7',
    '3+5' => '8', '3+6' => '9', '3+7' => '10',
    '4+1' => '5', '4+2' => '6', '4+3' => '7', '4+4' => '8',
    '4+5' => '9', '4+6' => '10',
    '5+1' => '6', '5+2' => '7', '5+3' => '8', '5+4' => '9',
    '5+5' => '10', '5+6' => '11',
    '6+1' => '7', '6+2' => '8', '6+3' => '9', '6+4' => '10',
    '7+1' => '8', '7+2' => '9', '7+3' => '10',
    '8+1' => '9', '8+2' => '10',
    '9+1' => '10',
    // Pengurangan
    '2-1' => '1', '3-1' => '2', '3-2' => '1', '4-1' => '3',
    '4-2' => '2', '5-1' => '4', '5-2' => '3', '6-1' => '5',
    '6-2' => '4', '7-1' => '6', '7-2' => '5', '8-1' => '7',
    '8-2' => '6', '9-1' => '8', '9-2' => '7', '10-1' => '9',
    // Perkalian (* atau x)
    '1*2' => '2', '1*3' => '3', '1*4' => '4', '1*5' => '5',
    '2*2' => '4', '2*3' => '6', '2*4' => '8', '2*5' => '10',
    '3*2' => '6', '3*3' => '9', '3*4' => '12',
    '4*2' => '8', '4*3' => '12',
    '1x2' => '2', '1x3' => '3', '1x4' => '4', '1x5' => '5',
    '2x2' => '4', '2x3' => '6', '2x4' => '8', '2x5' => '10',
    '3x2' => '6', '3x3' => '9', '3x4' => '12',
    '4x2' => '8', '4x3' => '12',
    // Pola dengan digit leet (misal "2*4" bisa terbaca "244" atau "342")
    '141' => '2', '1*1' => '1',
    '242' => '4', '2*2' => '4',
    '342' => '5', '3*2' => '6',
    '244' => '6', '2*4' => '8',
    '344' => '7', '3*4' => '12',
    '444' => '8', '4*4' => '16',
    '148' => '9', '1*8' => '8',
];

// ===== ANGKA ROMAWI → angka =====
// Sub image bisa berisi angka romawi: II=2, IV=4, VII=7
$ROMAN_MAP = [
    'i'    => '1',  'ii'   => '2',  'iii'  => '3',
    'iv'   => '4',  'v'    => '5',  'vi'   => '6',
    'vii'  => '7',  'viii' => '8',  'ix'   => '9',
    'x'    => '10', 'xi'   => '11', 'xii'  => '12',
    // Kapital juga
    'I'    => '1',  'II'   => '2',  'III'  => '3',
    'IV'   => '4',  'V'    => '5',  'VI'   => '6',
    'VII'  => '7',  'VIII' => '8',  'IX'   => '9',
    'X'    => '10', 'XI'   => '11', 'XII'  => '12',
];
// Reverse: angka → romawi (untuk match main → sub)
$ROMAN_REVERSE = [
    '1'  => 'i',   '2'  => 'ii',  '3'  => 'iii',
    '4'  => 'iv',  '5'  => 'v',   '6'  => 'vi',
    '7'  => 'vii', '8'  => 'viii','9'  => 'ix',
    '10' => 'x',   '11' => 'xi',  '12' => 'xii',
];

// ===== LEET NUMBERS (pola 3 karakter) → angka =====
// Sub/main bisa berisi pola seperti: zoo=200, lol=101, sos=505
$LEET_NUM_MAP = [
    'zoo' => '200', 'ozo' => '020', 'ooz' => '002',
    'soo' => '500', 'oso' => '050', 'oos' => '005',
    'lol' => '101', 'sos' => '505', 'zoz' => '202',
    'lll' => '111', 'zzz' => '222', 'sss' => '555',
    'loz' => '102', 'zol' => '201', 'los' => '105',
    'sol' => '501', 'zos' => '205', 'soz' => '502',
];
// Reverse: angka → leet pattern
$LEET_NUM_REVERSE = array_flip($LEET_NUM_MAP);

// ===== LEET ANIMALS / KATA UMUM → kata asli =====
$LEET_WORD_MAP = [
    // Animals
    'c@t'      => 'cat',   'c4t'    => 'cat',
    'd0g'      => 'dog',   'dog'    => 'dog',
    '1!0n'     => 'lion',  'li0n'   => 'lion',  'l10n' => 'lion',
    't!g3r'    => 'tiger', 'tig3r'  => 'tiger', 't1ger' => 'tiger',
    'm0nk3y'   => 'monkey','m0nkey' => 'monkey',
    '31eph@nt' => 'elephant',
    'c0w'      => 'cow',
    'f0x'      => 'fox',
    'm0us3'    => 'mouse', 'm0use'  => 'mouse',
    '@nt'      => 'ant',
    // Tambahan umum
    'b1rd'     => 'bird',  'b!rd'   => 'bird',
    'f!5h'     => 'fish',  'f15h'   => 'fish',
    'fr0g'     => 'frog',
    'b3@r'     => 'bear',  'b34r'   => 'bear',
    'w0lf'     => 'wolf',
    'h0rs3'    => 'horse', 'h0rse'  => 'horse',
    '5h33p'    => 'sheep', 'sh33p'  => 'sheep',
    'd33r'     => 'deer',
    'r@bb1t'   => 'rabbit','r4bb1t' => 'rabbit',
];

$DIGIT_WORD_MAP = [
    '0' => 'zero',  '1' => 'one',   '2' => 'two',   '3' => 'three',
    '4' => 'four',  '5' => 'five',  '6' => 'six',   '7' => 'seven',
    '8' => 'eight', '9' => 'nine',  '10' => 'ten',  '11' => 'eleven',
];

$NUMBER_MAP = [
    'zero' => '0', 'one' => '1', 'two' => '2', 'three' => '3',
    'four' => '4', 'five' => '5', 'six' => '6', 'seven' => '7',
    'eight' => '8', 'nine' => '9', 'ten' => '10', 'eleven' => '11',
];
$NUMBER_REVERSE = array_flip($NUMBER_MAP);

function leetDecode(string $word): string {
    global $LEET_MAP;
    $word   = trim($word);
    $result = '';
    for ($i = 0; $i < strlen($word); $i++) {
        $c = $word[$i];
        $result .= $LEET_MAP[$c] ?? strtolower($c);
    }
    return preg_replace('/[^a-z]/', '', $result);
}

function evalMathExpr(string $expr): ?string {
    global $MATH_EXPR_MAP;
    $expr = strtolower(trim($expr));
    if (isset($MATH_EXPR_MAP[$expr])) return $MATH_EXPR_MAP[$expr];
    $expr2 = str_replace('x', '*', $expr);
    if (preg_match('/^(\d+)\s*([\+\-\*\/])\s*(\d+)$/', $expr2, $m)) {
        $a = (int)$m[1]; $op = $m[2]; $b = (int)$m[3];
        switch ($op) {
            case '+': return (string)($a + $b);
            case '-': return (string)($a - $b);
            case '*': return (string)($a * $b);
            case '/': return $b != 0 ? (string)intdiv($a, $b) : null;
        }
    }
    return null;
}

function decodeRoman(string $text): ?string {
    global $ROMAN_MAP;
    $text = trim($text);
    if (isset($ROMAN_MAP[$text])) return $ROMAN_MAP[$text];
    $lower = strtolower($text);
    if (isset($ROMAN_MAP[$lower])) return $ROMAN_MAP[$lower];
    return null;
}

function isRoman(string $text): bool {
    return (bool)preg_match('/^[IVXLCDMivxlcdm]+$/', trim($text));
}

function isMathExpr(string $text): bool {
    return (bool)preg_match('/^\d+\s*[\+\-\*\/x]\s*\d+$/', trim($text));
}

function decodeLeetNum(string $text): ?string {
    global $LEET_NUM_MAP;
    $lower = strtolower(trim($text));
    return $LEET_NUM_MAP[$lower] ?? null;
}

function decodeLeetWord(string $text): ?string {
    global $LEET_WORD_MAP;
    $lower = strtolower(trim($text));
    return $LEET_WORD_MAP[$lower] ?? null;
}
function normalizeToken(string $raw): string {
    $raw   = trim($raw);
    $lower = strtolower($raw);
    if (!$raw) return '';
    if (isMathExpr($raw)) {
        $r = evalMathExpr($raw);
        if ($r !== null) return $r;
    }
    if (isRoman($raw)) {
        $r = decodeRoman($raw);
        if ($r !== null) return $r;
    }
    $r = decodeLeetNum($lower);
    if ($r !== null) return $r;
    $r = decodeLeetWord($lower);
    if ($r !== null) return $r;
    $decoded = leetDecode($raw);
    if (preg_match('/([aeiou])ii$/', $decoded)) {
        $decoded = substr($decoded, 0, -1) . 'l';
    }

    return $decoded ?: $lower;
}

function leetDecodeWords(string $text): array {
    $text  = preg_replace('/[\x80-\xFF]/', '', $text);
    $text  = trim($text);
    $parts = preg_split('/[\s,]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $tokens = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if (!$p) continue;
        $norm = normalizeToken($p);
        if ($norm !== '' && strlen($norm) >= 1) $tokens[] = $norm;
    }
    return $tokens;
}

function parseSubWord(string $ocrText): string {
    $text = preg_replace('/[\x80-\xFF]/', '', $ocrText);
    $text = trim($text);
    if (!$text) return '';
    preg_match('/[a-zA-Z0-9@!$\+\-\*\/x]+/', $text, $m);
    $token = $m[0] ?? $text;
    return normalizeToken($token);
}


function otsuThreshold(array $hist, int $total): int {
    $sum = 0;
    for ($i = 0; $i < 256; $i++) $sum += $i * $hist[$i];
    $sumB = $wB = $max = 0; $thr = 128;
    for ($t = 0; $t < 256; $t++) {
        $wB += $hist[$t];
        if (!$wB) continue;
        $wF = $total - $wB;
        if (!$wF) break;
        $sumB += $t * $hist[$t];
        $mB = $sumB / $wB;
        $mF = ($sum - $sumB) / $wF;
        $between = $wB * $wF * ($mB - $mF) ** 2;
        if ($between > $max) { $max = $between; $thr = $t; }
    }
    return $thr;
}

function preprocessImage(string $base64): string {
    if (!extension_loaded('gd')) return $base64;

    $src = @imagecreatefromstring(base64_decode($base64));
    if (!$src) return $base64;
    $w = imagesx($src); $h = imagesy($src);
    $s  = 2; // 2x cukup untuk glm-ocr
    $dst = imagecreatetruecolor($w*$s, $h*$s);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w*$s, $h*$s, $w, $h);
    imagedestroy($src);
    $W = $w*$s; $H = $h*$s;
    $rowAvg = [];
    for ($y = 0; $y < $H; $y++) {
        $sum = 0;
        for ($x = 0; $x < $W; $x++) {
            $rgb = imagecolorat($dst, $x, $y);
            $sum += (int)(0.299*(($rgb>>16)&0xFF) + 0.587*(($rgb>>8)&0xFF) + 0.114*($rgb&0xFF));
        }
        $rowAvg[$y] = $W > 0 ? $sum / $W : 128;
    }
    $globalAvg = array_sum($rowAvg) / count($rowAvg);

    $gray = imagecreatetruecolor($W, $H);
    imagefill($gray, 0, 0, imagecolorallocate($gray, 255, 255, 255));

    for ($y = 0; $y < $H; $y++) {
        $bg = max(1, $rowAvg[$y]);
        for ($x = 0; $x < $W; $x++) {
            $rgb = imagecolorat($dst, $x, $y);
            $lum = (int)(0.299*(($rgb>>16)&0xFF) + 0.587*(($rgb>>8)&0xFF) + 0.114*($rgb&0xFF));
            $n   = (int)min(255, ($lum / $bg) * $globalAvg);
            imagesetpixel($gray, $x, $y, imagecolorallocate($gray, $n, $n, $n));
        }
    }
    imagedestroy($dst);
    imagefilter($gray, IMG_FILTER_GAUSSIAN_BLUR);
    imagefilter($gray, IMG_FILTER_BRIGHTNESS, 10);
    imagefilter($gray, IMG_FILTER_CONTRAST, -40);
    $hist = array_fill(0, 256, 0);
    for ($x = 0; $x < $W; $x++) for ($y = 0; $y < $H; $y++) {
        $hist[imagecolorat($gray, $x, $y) & 0xFF]++;
    }
    $thr   = otsuThreshold($hist, $W * $H);
    $black = imagecolorallocate($gray, 0, 0, 0);
    $white = imagecolorallocate($gray, 255, 255, 255);
    for ($x = 0; $x < $W; $x++) for ($y = 0; $y < $H; $y++) {
        imagesetpixel($gray, $x, $y, (imagecolorat($gray,$x,$y)&0xFF) < $thr ? $black : $white);
    }

    ob_start(); imagepng($gray); $png = ob_get_clean();
    imagedestroy($gray);
    return base64_encode($png);
}

function preprocessSubImage(string $base64): string {
    if (!extension_loaded('gd')) return $base64;
    $src = @imagecreatefromstring(base64_decode($base64));
    if (!$src) return $base64;
    $w = imagesx($src); $h = imagesy($src);
    $s = 2; // scale 2x — cukup, tidak perlu 4x
    $dst = imagecreatetruecolor($w*$s, $h*$s);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w*$s, $h*$s, $w, $h);
    imagedestroy($src);
    imagefilter($dst, IMG_FILTER_CONTRAST, -45);
    imagefilter($dst, IMG_FILTER_BRIGHTNESS, 10);
    $W = $w*$s; $H = $h*$s;
    $hist = array_fill(0, 256, 0);
    for ($x = 0; $x < $W; $x++) for ($y = 0; $y < $H; $y++) {
        $hist[imagecolorat($dst, $x, $y) & 0xFF]++;
    }
    $thr   = otsuThreshold($hist, $W * $H);
    $black = imagecolorallocate($dst, 0, 0, 0);
    $white = imagecolorallocate($dst, 255, 255, 255);
    for ($x = 0; $x < $W; $x++) for ($y = 0; $y < $H; $y++) {
        imagesetpixel($dst, $x, $y, (imagecolorat($dst,$x,$y)&0xFF) < $thr ? $black : $white);
    }
    ob_start(); imagepng($dst); $png = ob_get_clean();
    imagedestroy($dst);
    return base64_encode($png);
}

function ollamaIsRunning(): bool {
    $ch = curl_init(OLLAMA_HOST . '/api/tags');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 3]);
    $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
    return !$e && $r !== false;
}

function ollamaOCR(string $base64, string $prompt = 'Text Recognition:', int $timeout = OLLAMA_TIMEOUT): ?string {
    $payload = json_encode([
        'model'   => OLLAMA_MODEL,
        'prompt'  => $prompt,
        'images'  => [$base64],
        'stream'  => false,
        'options' => ['num_ctx' => 16384, 'temperature' => 0],
    ]);
    $ch = curl_init(OLLAMA_HOST . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err || !$response) return null;
    $data = json_decode($response, true);
    return isset($data['response']) ? trim($data['response']) : null;
}


function ollamaOCRBatch(array $base64List): ?array {
    $count = count($base64List);
    if ($count === 0) return [];
    $prompt = "You are an OCR engine. You will see $count small images. "
            . "Each image has ONE word in leet-speak. "
            . "Leet rules: @ or 4=a, 1 or !=i, 3=e, 0=o, 5=s, 7=t, 6=g, 8=b, v=u, I(capital)=l. "
            . "Output ONLY $count comma-separated words in order. Example: cat,bus,ship";
    $payload = json_encode([
        'model'   => OLLAMA_MODEL,
        'prompt'  => $prompt,
        'images'  => $base64List,
        'stream'  => false,
        'options' => ['num_ctx' => 16384, 'temperature' => 0],
    ]);
    $ch = curl_init(OLLAMA_HOST . '/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => OLLAMA_TIMEOUT_SUB,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err || !$response) return null;
    $data = json_decode($response, true);
    if (!isset($data['response'])) return null;
    $parts = preg_split('/[\s,;|]+/', trim($data['response']), -1, PREG_SPLIT_NO_EMPTY);
    return array_values($parts);
}

function wordSim(string $a, string $b): float {
    if ($a === $b) return 1.0;
    if (!$a || !$b) return 0.0;
    if (str_contains($b, $a) || str_contains($a, $b)) return 0.88;
    if (str_ends_with($b, $a) || str_ends_with($a, $b)) return 0.82;
    if (str_starts_with($b, $a) || str_starts_with($a, $b)) return 0.80;

    similar_text($a, $b, $pct);
    $maxLen = max(strlen($a), strlen($b));
    $lev    = $maxLen > 0 ? 1 - levenshtein($a, $b) / $maxLen : 0;
    $score  = max($pct / 100, $lev);


    if (min(strlen($a), strlen($b)) <= 4 && levenshtein($a, $b) <= 1)
        $score = max($score, 0.75);


    $bArr = str_split($b);
    $matched = 0;
    foreach (str_split($a) as $ch) {
        $pos = array_search($ch, $bArr);
        if ($pos !== false) { $matched++; unset($bArr[$pos]); }
    }
    if ($matched === strlen($a)) $score = max($score, 0.72);

    return $score;
}

function solveAntibotOllama(string $mainBase64, array $subBase64List, array $relValues): ?string {

    echo ts() . COLOR_CYAN . " [Antibot] Preprocessing + OCR main...\n";
    $mainOcr = ollamaOCR(preprocessImage($mainBase64), 'Text Recognition:');

    if ($mainOcr === null) { echo ts() . COLOR_RED . " [Antibot] OCR main gagal.\n"; return null; }

    echo ts() . COLOR_YELLOW . " [Antibot] Main OCR   : '$mainOcr'\n";
    $mainWords = leetDecodeWords($mainOcr);
    echo ts() . COLOR_CYAN   . " [Antibot] Main words : " . implode(', ', $mainWords) . "\n";

    if (empty($mainWords)) { echo ts() . COLOR_RED . " [Antibot] Tidak ada kata.\n"; return null; }


    $subWords = [];
    foreach ($subBase64List as $i => $b64) {
        echo ts() . COLOR_CYAN . " [Antibot] OCR sub[$i]...\n";
        $ocrRaw  = ollamaOCR($b64, 'Text Recognition:', OLLAMA_TIMEOUT_SUB);
        // Ambil kata pertama saja dari response
        $first   = $ocrRaw ? (preg_split('/[\s,]+/', trim($ocrRaw))[0] ?? $ocrRaw) : '';
        $decoded = parseSubWord($first);
        // Jika decoded hanya 1 char, cek apakah raw adalah digit tunggal → number word
        if (strlen($decoded) < 2 && strlen(trim($first)) === 1) {
            global $DIGIT_WORD_MAP;
            $rawChar = trim($first);
            if (isset($DIGIT_WORD_MAP[$rawChar])) {
                $decoded = $DIGIT_WORD_MAP[$rawChar]; // '8'→'eight'
                echo ts() . COLOR_YELLOW . " [Antibot] Sub[$i] digit='$rawChar' → '$decoded'\n";
            }
        }

        if (strlen($decoded) < 2 && $ocrRaw !== null) {
            echo ts() . COLOR_YELLOW . " [Antibot] Sub[$i] retry (raw='$first')...\n";
            $ocrRaw2 = ollamaOCR(preprocessSubImage($b64), 'Text Recognition:', OLLAMA_TIMEOUT_SUB);
            if ($ocrRaw2) {
                $first2   = preg_split('/[\s,]+/', trim($ocrRaw2))[0] ?? $ocrRaw2;
                $decoded2 = parseSubWord($first2);
                if (strlen($decoded2) > strlen($decoded)) {
                    $first   = $first2;
                    $decoded = $decoded2;
                }
            }
        }
        $subWords[$i] = $decoded;
        echo ts() . COLOR_YELLOW . " [Antibot] Sub[$i] raw:'$first' → '$decoded'\n";
    }

    $clickOrder = []; $usedIdx = []; $failedTargets = [];

    foreach ($mainWords as $target) {
        $bestIdx = null; $bestScore = 0.4;  // threshold dari dataset.php

        foreach ($subWords as $i => $word) {
            if (in_array($i, $usedIdx) || !strlen($word)) continue;
            $s = wordSim($target, $word);
            if ($s > $bestScore) { $bestScore = $s; $bestIdx = $i; }
        }

        if ($bestIdx === null) {
            $tShort = substr($target, 1);
            foreach ($subWords as $i => $word) {
                if (in_array($i, $usedIdx) || !strlen($word)) continue;
                $s = max(wordSim($tShort, $word), wordSim($target, substr($word, 1)));
                if ($s > $bestScore) { $bestScore = $s; $bestIdx = $i; }
            }
        }
        if ($bestIdx === null && strlen($target) >= 3) {
            $tHead = substr($target, 0, 3);
            foreach ($subWords as $i => $word) {
                if (in_array($i, $usedIdx) || !strlen($word)) continue;
                $s = wordSim($tHead, substr($word, 0, 3));
                if ($s > 0.55 && $s > $bestScore) { $bestScore = $s; $bestIdx = $i; }
            }
        }
        if ($bestIdx === null && strlen($target) <= 3) {
            foreach ($subWords as $i => $word) {
                if (in_array($i, $usedIdx) || !strlen($word)) continue;
                if ($target[0] === $word[0]) { $bestIdx = $i; $bestScore = 0.45; break; }
            }
        }

        if ($bestIdx !== null) {
            $clickOrder[] = $bestIdx; $usedIdx[] = $bestIdx;
            echo ts() . COLOR_GREEN . " [Antibot] '$target' → sub[$bestIdx] rel={$relValues[$bestIdx]} (score=" . round($bestScore,2) . ")\n";
        } else {
            // Simpan dulu target yang gagal, jangan langsung return null
            $failedTargets[] = $target;
            echo ts() . COLOR_YELLOW . " [Antibot] Belum cocok untuk '$target', coba reverse...\n";
        }
    }

    // Sub yang sudah punya pasangan jelas = skip. Sub orphan → cocokkan ke failed target.
    if (!empty($failedTargets)) {
        // Hitung sub mana yang paling "yakin" cocok ke SALAH SATU main word (termasuk yang gagal)
        $orphanSubs = [];
        foreach ($subWords as $i => $word) {
            if (in_array($i, $usedIdx) || !strlen($word)) continue;
            $orphanSubs[$i] = $word;
        }
        foreach ($failedTargets as $target) {
            $bestIdx = null; $bestScore = 0.0;
            // Coba cocokkan sub orphan ke failed target (toleransi lebih longgar)
            foreach ($orphanSubs as $i => $word) {
                // Cek apakah sub word ini lebih mirip target ini vs main words lain
                $sTarget = wordSim($target, $word);
                $maxOther = 0.0;
                foreach ($mainWords as $mw) {
                    if ($mw === $target) continue;
                    $maxOther = max($maxOther, wordSim($mw, $word));
                }
                // Sub ini lebih cocok ke target ini dibanding main words lain → pasangkan
                if ($sTarget >= $maxOther && $sTarget > $bestScore) {
                    $bestScore = $sTarget; $bestIdx = $i;
                }
            }
            if ($bestIdx !== null) {
                $clickOrder[] = $bestIdx; $usedIdx[] = $bestIdx;
                unset($orphanSubs[$bestIdx]);
                echo ts() . COLOR_GREEN . " [Antibot] '$target' → sub[$bestIdx] rel={$relValues[$bestIdx]} via reverse (score=" . round($bestScore,2) . ")\n";
            } else {
                // Last resort: ambil sub orphan pertama yang tersisa
                if (!empty($orphanSubs)) {
                    reset($orphanSubs);
                    $lastIdx = key($orphanSubs);
                    $clickOrder[] = $lastIdx; $usedIdx[] = $lastIdx;
                    unset($orphanSubs[$lastIdx]);
                    echo ts() . COLOR_YELLOW . " [Antibot] '$target' → sub[$lastIdx] last resort\n";
                } else {
                    echo ts() . COLOR_RED . " [Antibot] Tidak cocok untuk '$target' — ulangi.\n";
                    return null;
                }
            }
        }
    }

    if (empty($clickOrder)) return null;

    $parts = [];
    foreach ($clickOrder as $idx) {
        if (isset($relValues[$idx])) $parts[] = $relValues[$idx];
    }

    return empty($parts) ? null : ' ' . implode(' ', $parts);
}
    
}