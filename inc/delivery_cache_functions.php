<?php
require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/functions.php';

function delivery_cache_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    foreach (['tbl_delivery_cache_locations', 'tbl_delivery_cache_desks'] as $table) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'wilaya_id'");
            if ($stmt->rowCount() === 0) {
                $after = $table === 'tbl_delivery_cache_locations' ? 'company_id' : 'company_id';
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `wilaya_id` INT NULL AFTER `$after`");
            }
        } catch (Throwable $e) {
            error_log('Delivery cache schema check failed for ' . $table . ': ' . $e->getMessage());
        }
    }
}

function delivery_cache_normalize_wilaya_key($name) {
    $name = trim((string)$name);
    if ($name === '') return '';
    $name = strtr($name, [
        'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
        'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
        'Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n','’'=>"'",'`'=>"'",'´'=>"'"
    ]);
    $name = strtolower($name);
    return preg_replace('/[^a-z0-9]+/', '', $name);
}

function delivery_cache_known_wilaya_id_map() {
    static $map = null;
    if ($map !== null) return $map;

    $known = [
        1 => 'Adrar', 2 => 'Chlef', 3 => 'Laghouat', 4 => 'Oum El Bouaghi', 5 => 'Batna',
        6 => 'Béjaïa', 7 => 'Biskra', 8 => 'Béchar', 9 => 'Blida', 10 => 'Bouira',
        11 => 'Tamanrasset', 12 => 'Tébessa', 13 => 'Tlemcen', 14 => 'Tiaret', 15 => 'Tizi Ouzou',
        16 => 'Alger', 17 => 'Djelfa', 18 => 'Jijel', 19 => 'Sétif', 20 => 'Saïda',
        21 => 'Skikda', 22 => 'Sidi Bel Abbès', 23 => 'Annaba', 24 => 'Guelma', 25 => 'Constantine',
        26 => 'Médéa', 27 => 'Mostaganem', 28 => "M'Sila", 29 => 'Mascara', 30 => 'Ouargla',
        31 => 'Oran', 32 => 'El Bayadh', 33 => 'Illizi', 34 => 'Bordj Bou Arreridj', 35 => 'Boumerdès',
        36 => 'El Tarf', 37 => 'Tindouf', 38 => 'Tissemsilt', 39 => 'El Oued', 40 => 'Khenchela',
        41 => 'Souk Ahras', 42 => 'Tipaza', 43 => 'Mila', 44 => 'Aïn Defla', 45 => 'Naâma',
        46 => 'Aïn Témouchent', 47 => 'Ghardaïa', 48 => 'Relizane', 49 => 'Timimoun',
        50 => 'Bordj Badji Mokhtar', 51 => 'Ouled Djellal', 52 => 'Beni Abbes', 53 => 'In Salah',
        54 => 'In Guezzam', 55 => 'Touggourt', 56 => 'Djanet', 57 => "El M'Ghair", 58 => 'El Meniaa',
    ];

    $map = [];
    foreach ($known as $id => $name) {
        $map[delivery_cache_normalize_wilaya_key($name)] = (int)$id;
    }
    return $map;
}

function delivery_cache_guess_wilaya_id($name) {
    $map = delivery_cache_known_wilaya_id_map();
    $key = delivery_cache_normalize_wilaya_key($name);
    return $map[$key] ?? 0;
}

function delivery_cache_wilaya_name($wilaya) {
    if (is_array($wilaya)) {
        return trim((string)($wilaya['name'] ?? $wilaya['wilaya_name'] ?? ''));
    }
    return trim((string)$wilaya);
}

function delivery_cache_arabic_wilaya_names() {
    return [
        1 => 'أدرار', 2 => 'الشلف', 3 => 'الأغواط', 4 => 'أم البواقي', 5 => 'باتنة',
        6 => 'بجاية', 7 => 'بسكرة', 8 => 'بشار', 9 => 'البليدة', 10 => 'البويرة',
        11 => 'تمنراست', 12 => 'تبسة', 13 => 'تلمسان', 14 => 'تيارت', 15 => 'تيزي وزو',
        16 => 'الجزائر', 17 => 'الجلفة', 18 => 'جيجل', 19 => 'سطيف', 20 => 'سعيدة',
        21 => 'سكيكدة', 22 => 'سيدي بلعباس', 23 => 'عنابة', 24 => 'قالمة', 25 => 'قسنطينة',
        26 => 'المدية', 27 => 'مستغانم', 28 => 'المسيلة', 29 => 'معسكر', 30 => 'ورقلة',
        31 => 'وهران', 32 => 'البيض', 33 => 'إليزي', 34 => 'برج بوعريريج', 35 => 'بومرداس',
        36 => 'الطارف', 37 => 'تندوف', 38 => 'تيسمسيلت', 39 => 'الوادي', 40 => 'خنشلة',
        41 => 'سوق أهراس', 42 => 'تيبازة', 43 => 'ميلة', 44 => 'عين الدفلى', 45 => 'النعامة',
        46 => 'عين تموشنت', 47 => 'غرداية', 48 => 'غليزان', 49 => 'تيميمون',
        50 => 'برج باجي مختار', 51 => 'أولاد جلال', 52 => 'بني عباس', 53 => 'عين صالح',
        54 => 'عين قزام', 55 => 'تقرت', 56 => 'جانت', 57 => 'المغير', 58 => 'المنيعة',
    ];
}

function delivery_cache_location_label($name, $arabic_name) {
    $name = trim((string)$name);
    $arabic_name = trim((string)$arabic_name);
    if ($arabic_name === '' || $arabic_name === $name) {
        return $name;
    }
    return $name . ' - ' . $arabic_name;
}

function delivery_cache_unicode_text($escaped) {
    $decoded = json_decode('"' . $escaped . '"');
    return is_string($decoded) ? $decoded : '';
}

function delivery_cache_phonetic_arabic_location_name($name) {
    $value = trim((string)$name);
    if ($value === '' || preg_match('/[\x{0600}-\x{06FF}]/u', $value)) {
        return '';
    }

    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && trim($ascii) !== '') {
            $value = $ascii;
        }
    }

    $words = preg_split('/[\s\'-]+/', strtolower($value));
    $dictionary = [
        'ain' => '\u0639\u064a\u0646',
        'el' => '\u0627\u0644',
        'al' => '\u0627\u0644',
        'ouled' => '\u0623\u0648\u0644\u0627\u062f',
        'oulad' => '\u0623\u0648\u0644\u0627\u062f',
        'beni' => '\u0628\u0646\u064a',
        'ben' => '\u0628\u0646',
        'sidi' => '\u0633\u064a\u062f\u064a',
        'bir' => '\u0628\u0626\u0631',
        'bordj' => '\u0628\u0631\u062c',
        'dar' => '\u062f\u0627\u0631',
        'hassi' => '\u062d\u0627\u0633\u064a',
        'ras' => '\u0631\u0627\u0633',
        'oued' => '\u0648\u0627\u062f',
        'wadi' => '\u0648\u0627\u062f\u064a',
    ];
    $letters = [
        'a' => '\u0627', 'b' => '\u0628', 'c' => '\u0643', 'd' => '\u062f',
        'e' => '\u064a', 'f' => '\u0641', 'g' => '\u0642', 'h' => '\u0647',
        'i' => '\u064a', 'j' => '\u062c', 'k' => '\u0643', 'l' => '\u0644',
        'm' => '\u0645', 'n' => '\u0646', 'o' => '\u0648', 'p' => '\u0628',
        'q' => '\u0642', 'r' => '\u0631', 's' => '\u0633', 't' => '\u062a',
        'u' => '\u0648', 'v' => '\u0641', 'w' => '\u0648', 'x' => '\u0643\u0633',
        'y' => '\u064a', 'z' => '\u0632',
    ];
    $digraphs = [
        'ch' => '\u0634', 'kh' => '\u062e', 'gh' => '\u063a', 'dj' => '\u062c',
        'ou' => '\u0648', 'oo' => '\u0648', 'ai' => '\u0627\u064a', 'ei' => '\u064a',
    ];

    $result = [];
    foreach ((array)$words as $word) {
        $word = preg_replace('/[^a-z]/', '', (string)$word);
        if ($word === '') {
            continue;
        }
        if (isset($dictionary[$word])) {
            $result[] = delivery_cache_unicode_text($dictionary[$word]);
            continue;
        }
        $out = '';
        for ($i = 0, $len = strlen($word); $i < $len; $i++) {
            $pair = $i + 1 < $len ? $word[$i] . $word[$i + 1] : '';
            if ($pair !== '' && isset($digraphs[$pair])) {
                $out .= delivery_cache_unicode_text($digraphs[$pair]);
                $i++;
                continue;
            }
            if (isset($letters[$word[$i]])) {
                $out .= delivery_cache_unicode_text($letters[$word[$i]]);
            }
        }
        if ($out !== '') {
            $result[] = $out;
        }
    }

    return trim(implode(' ', $result));
}

function delivery_cache_load_commune_arabic_map(PDO $pdo) {
    static $cached = null;
    if ($cached !== null) return $cached;

    // Curated Latin→Arabic commune labels, version-controlled data file.
    // Shape: [wilaya_id => [ normalized_latin_name => arabic_name ]].
    // The delivery VALUE stays the Latin name; this only sets the shown Arabic label.
    $map = [];
    $file = __DIR__ . '/commune_ar_map.php';
    if (is_file($file)) {
        $data = include $file;
        if (is_array($data)) {
            foreach ($data as $wid => $names) {
                if (is_array($names)) $map[(int)$wid] = $names;
            }
        }
    }
    return $cached = $map;
}

function delivery_cache_get_adapters() {
    return [
        'ecotrack' => [
            'name' => 'Ecotrack',
            'sync_function' => 'delivery_cache_sync_ecotrack',
            'is_configured' => function($pdo) {
                $settings = ecotrack_normalize_settings(front_get_settings($pdo));
                return ecotrack_is_configured($settings);
            }
        ]
        // You can add more adapters here like zrexpress in the future
    ];
}

function delivery_cache_sync_company(PDO $pdo, $company_code) {
    $adapters = delivery_cache_get_adapters();
    if (!isset($adapters[$company_code])) {
        return ['success' => false, 'message' => 'Adapter not found for ' . $company_code];
    }
    
    $adapter = $adapters[$company_code];
    if (!call_user_func($adapter['is_configured'], $pdo)) {
        return ['success' => false, 'message' => $adapter['name'] . ' is not configured.'];
    }

    $sync_func = $adapter['sync_function'];
    if (!function_exists($sync_func)) {
        return ['success' => false, 'message' => 'Sync function missing for ' . $adapter['name']];
    }

    return $sync_func($pdo, $company_code);
}

function delivery_cache_sync_all(PDO $pdo) {
    $results = [];
    $adapters = delivery_cache_get_adapters();
    foreach ($adapters as $code => $adapter) {
        $results[$code] = delivery_cache_sync_company($pdo, $code);
    }
    return $results;
}

function delivery_cache_sync_ecotrack(PDO $pdo, $company_code) {
    delivery_cache_ensure_schema($pdo);
    // Determine the company_id from tbl_delivery_company if it exists, or create one, or just map by code?
    // Let's ensure a company ID exists.
    $stmt = $pdo->prepare("SELECT id FROM tbl_delivery_company WHERE name LIKE '%ecotrack%' OR name LIKE '%eco track%' LIMIT 1");
    $stmt->execute();
    $company_id = $stmt->fetchColumn();
    if (!$company_id) {
        $pdo->exec("INSERT INTO tbl_delivery_company (name, active) VALUES ('Ecotrack', 1)");
        $company_id = $pdo->lastInsertId();
    }

    $settings = ecotrack_normalize_settings(front_get_settings($pdo));
    
    // 1. Fetch Wilayas
    $wilayas_req = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/wilayas', [], null, 'bearer');
    if (empty($wilayas_req['json']) || !is_array($wilayas_req['json'])) {
        delivery_cache_log_error($pdo, $company_id, 'Sync Wilayas', 'Failed to fetch wilayas');
        return ['success' => false, 'message' => 'Failed to fetch wilayas from Ecotrack.'];
    }
    $api_wilayas = $wilayas_req['json'];
    
    // 2. Fetch Fees
    $fees_req = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/fees', [], null, 'bearer');
    $api_fees = [];
    if (!empty($fees_req['json']['livraison']) && is_array($fees_req['json']['livraison'])) {
        foreach ($fees_req['json']['livraison'] as $f) {
            $api_fees[(int)$f['wilaya_id']] = [
                'home' => (float)($f['tarif'] ?? 0),
                'desk' => (float)($f['tarif_stopdesk'] ?? 0)
            ];
        }
    }
    
    // We will collect all locations into memory, then do an upsert
    $locations_data = [];
    
    foreach ($api_wilayas as $w) {
        $w_id = (int) $w['wilaya_id'];
        $w_name = trim($w['wilaya_name']);
        
        $home_price = $api_fees[$w_id]['home'] ?? 0;
        $desk_price = $api_fees[$w_id]['desk'] ?? 0;
        
        // Fetch Communes for this wilaya
        $communes_req = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/communes', ['wilaya_id' => $w_id], null, 'bearer');
        if (!empty($communes_req['json']) && is_array($communes_req['json'])) {
            foreach ($communes_req['json'] as $c) {
                $c_name = trim($c['nom']);
                $has_stop_desk = (int) ($c['has_stop_desk'] ?? 0);
                
                $locations_data[] = [
                    'company_id' => $company_id,
                    'wilaya_id' => $w_id,
                    'wilaya_name' => $w_name,
                    'commune_name' => $c_name,
                    'is_home_supported' => 1, // Usually Ecotrack supports home delivery for all returned communes
                    'home_price' => $home_price,
                    'is_desk_supported' => $has_stop_desk,
                    'desk_price' => $desk_price,
                ];
            }
        }
        // Small delay to prevent API rate limits if there are 58 wilayas
        usleep(100000); 
    }
    
    // Now insert/update into DB
    $added = 0;
    $updated = 0;
    
    $pdo->beginTransaction();
    
    // Create temp table or use ON DUPLICATE KEY UPDATE
    $stmt = $pdo->prepare("
        INSERT INTO tbl_delivery_cache_locations 
        (company_id, wilaya_id, wilaya_name, commune_name, is_home_supported, home_price, is_desk_supported, desk_price, last_updated)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        wilaya_id = VALUES(wilaya_id),
        is_home_supported = VALUES(is_home_supported),
        home_price = VALUES(home_price),
        is_desk_supported = VALUES(is_desk_supported),
        desk_price = VALUES(desk_price),
        last_updated = NOW()
    ");
    
    foreach ($locations_data as $loc) {
        $stmt->execute([
            $loc['company_id'],
            $loc['wilaya_id'],
            $loc['wilaya_name'],
            $loc['commune_name'],
            $loc['is_home_supported'],
            $loc['home_price'],
            $loc['is_desk_supported'],
            $loc['desk_price']
        ]);
        if ($stmt->rowCount() == 1) {
            $added++;
        } elseif ($stmt->rowCount() == 2) {
            $updated++;
        }
    }
    
    // Clean up old entries that weren't updated in this sync
    $del_stmt = $pdo->prepare("DELETE FROM tbl_delivery_cache_locations WHERE company_id = ? AND last_updated < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $del_stmt->execute([$company_id]);
    $deleted = $del_stmt->rowCount();
    
    $pdo->commit();
    
    // Log success
    delivery_cache_log($pdo, $company_id, 'Sync All', 'Success', "Synced Ecotrack locations.", $added, $updated, $deleted);
    
    // Build JSON File
    $json_res = delivery_cache_build_json($pdo, $company_id);
    if (!$json_res['success']) {
        delivery_cache_log_error($pdo, $company_id, 'Build JSON', $json_res['message']);
    } else {
        delivery_cache_log($pdo, $company_id, 'Build JSON', 'Success', $json_res['message'], 0, 0, 0);
    }
    
    return [
        'success' => true,
        'message' => 'Ecotrack synced successfully',
        'stats' => ['added' => $added, 'updated' => $updated, 'deleted' => $deleted]
    ];
}

function delivery_cache_log_error(PDO $pdo, $company_id, $type, $message) {
    delivery_cache_log($pdo, $company_id, $type, 'Error', $message, 0, 0, 0);
}

function delivery_cache_log(PDO $pdo, $company_id, $type, $status, $message, $loc_added, $loc_updated, $loc_deleted) {
    $stmt = $pdo->prepare("INSERT INTO tbl_delivery_cache_logs (company_id, sync_type, status, message, locations_added, locations_updated, locations_deleted, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$company_id, $type, $status, $message, $loc_added, $loc_updated, $loc_deleted]);
}


function delivery_cache_build_json(PDO $pdo, $company_id) {
    delivery_cache_ensure_schema($pdo);
    $cache_dir = __DIR__ . '/../admin/cache/delivery';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // 1. Fetch from DB
    $stmt = $pdo->prepare("SELECT wilaya_id, wilaya_name, commune_name, is_home_supported, home_price, is_desk_supported, desk_price FROM tbl_delivery_cache_locations WHERE company_id = ? ORDER BY COALESCE(wilaya_id, 999), wilaya_name ASC, commune_name ASC");
    $stmt->execute([$company_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [
        'wilayas' => [],
        'communes' => []
    ];
    
    $total_prices = 0;
    $wilaya_ar_names = delivery_cache_arabic_wilaya_names();
    $commune_ar_map = delivery_cache_load_commune_arabic_map($pdo);
    $seen_wilayas = [];
    foreach ($rows as $r) {
        $w = $r['wilaya_name'];
        $w_id = (int)($r['wilaya_id'] ?? 0);
        if ($w_id <= 0) {
            $w_id = delivery_cache_guess_wilaya_id($w);
        }
        $w_ar = $wilaya_ar_names[$w_id] ?? '';
        if (!isset($seen_wilayas[$w])) {
            $data['wilayas'][] = [
                'id' => $w_id,
                'code' => $w_id > 0 ? str_pad((string)$w_id, 2, '0', STR_PAD_LEFT) : '',
                'name' => $w,
                'name_ar' => $w_ar,
                'label' => delivery_cache_location_label($w, $w_ar)
            ];
            $seen_wilayas[$w] = true;
        }
        
        if (!isset($data['communes'][$w])) {
            $data['communes'][$w] = [];
        }
        
        $commune_name = (string)$r['commune_name'];
        $commune_ar = '';
        if ($w_id > 0 && !empty($commune_ar_map[$w_id])) {
            $commune_ar = $commune_ar_map[$w_id][delivery_cache_normalize_wilaya_key($commune_name)] ?? '';
        }
        $data['communes'][$w][] = [
            'name' => $commune_name,
            'name_ar' => $commune_ar,
            'label' => delivery_cache_location_label($commune_name, $commune_ar),
            'wilaya_id' => $w_id,
            'home' => (int)$r['is_home_supported'],
            'home_price' => (float)$r['home_price'],
            'desk' => (int)$r['is_desk_supported'],
            'desk_price' => (float)$r['desk_price']
        ];
        if ((int)$r['is_home_supported']) $total_prices++;
        if ((int)$r['is_desk_supported']) $total_prices++;
    }
    
    $stmt_desks = $pdo->prepare("SELECT wilaya_id, wilaya_name, commune_name, desk_id, desk_name, desk_address FROM tbl_delivery_cache_desks WHERE company_id = ?");
    $stmt_desks->execute([$company_id]);
    $desk_rows = $stmt_desks->fetchAll(PDO::FETCH_ASSOC);

    $data['desks'] = [];
    $total_desks = 0;
    foreach ($desk_rows as $dr) {
        $w = $dr['wilaya_name'];
        $w_id = (int)($dr['wilaya_id'] ?? 0);
        if ($w_id <= 0) {
            $w_id = delivery_cache_guess_wilaya_id($w);
        }
        if (!isset($data['desks'][$w])) {
            $data['desks'][$w] = [];
        }
        $data['desks'][$w][] = [
            'commune' => $dr['commune_name'],
            'wilaya_id' => $w_id,
            'id' => $dr['desk_id'],
            'name' => $dr['desk_name'],
            'address' => $dr['desk_address']
        ];
        $total_desks++;
    }
    
    // Validation
    $total_wilayas = count($data['wilayas']);
    $total_communes = count($rows);
    if ($total_wilayas == 0) {
        return ['success' => false, 'message' => 'No data found in DB to build JSON.'];
    }
    
    $json_content = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'JSON Encoding failed: ' . json_last_error_msg()];
    }
    
    $file_path = $cache_dir . '/delivery_cache_' . $company_id . '.json';
    $meta_path = $cache_dir . '/delivery_cache_meta_' . $company_id . '.json';
    $backup_path = $cache_dir . '/delivery_cache_' . $company_id . '_backup.json';
    
    // Backup existing
    if (file_exists($file_path)) {
        copy($file_path, $backup_path);
    }
    
    // Write new
    $result = file_put_contents($file_path, $json_content);
    if ($result === false) {
        // Restore backup
        if (file_exists($backup_path)) {
            copy($backup_path, $file_path);
        }
        return ['success' => false, 'message' => 'Failed to write JSON file.'];
    }
    
    // Meta data
    $meta = [
        'version' => time(),
        'company_id' => $company_id,
        'last_sync_time' => date('Y-m-d H:i:s'),
        'total_wilayas' => $total_wilayas,
        'total_communes' => $total_communes,
        'total_desks' => $total_desks,
        'total_prices' => $total_prices,
        'file_size' => filesize($file_path)
    ];
    file_put_contents($meta_path, json_encode($meta, JSON_UNESCAPED_UNICODE));
    
    return ['success' => true, 'message' => 'JSON Cache built successfully.'];
}

function delivery_cache_get_frontend_data(PDO $pdo, $company_id = 0) {
    if ($company_id == 0) return ['wilayas' => [], 'communes' => [], 'desks' => []];
    
    $file_path = __DIR__ . '/../admin/cache/delivery/delivery_cache_' . $company_id . '.json';
    if (!file_exists($file_path)) {
        return ['wilayas' => [], 'communes' => [], 'desks' => []];
    }
    
    $content = file_get_contents($file_path);
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data['wilayas'])) {
        return ['wilayas' => [], 'communes' => [], 'desks' => []];
    }

    foreach ($data['wilayas'] as $idx => $wilaya) {
        if (is_array($wilaya)) {
            $name = delivery_cache_wilaya_name($wilaya);
            $id = (int)($wilaya['id'] ?? $wilaya['wilaya_id'] ?? 0);
            $name_ar = trim((string)($wilaya['name_ar'] ?? ''));
        } else {
            $name = trim((string)$wilaya);
            $id = delivery_cache_guess_wilaya_id($name);
            $name_ar = '';
        }
        if ($name_ar === '') {
            $name_ar = delivery_cache_arabic_wilaya_names()[$id] ?? '';
        }
        $data['wilayas'][$idx] = [
            'id' => $id,
            'code' => $id > 0 ? str_pad((string)$id, 2, '0', STR_PAD_LEFT) : '',
            'name' => $name,
            'name_ar' => $name_ar,
            'label' => delivery_cache_location_label($name, $name_ar)
        ];
    }

    $commune_ar_map = delivery_cache_load_commune_arabic_map($pdo);
    foreach (($data['communes'] ?? []) as $wilaya_name => $communes) {
        foreach ((array)$communes as $idx => $commune) {
            if (!is_array($commune)) {
                $commune = ['name' => trim((string)$commune)];
            }
            $name = trim((string)($commune['name'] ?? ''));
            $wilaya_id = (int)($commune['wilaya_id'] ?? delivery_cache_guess_wilaya_id($wilaya_name));
            $name_ar = trim((string)($commune['name_ar'] ?? ''));
            if ($name_ar === '' && $wilaya_id > 0 && !empty($commune_ar_map[$wilaya_id])) {
                $name_ar = $commune_ar_map[$wilaya_id][delivery_cache_normalize_wilaya_key($name)] ?? '';
            }
            if ($name_ar === '') {
                $name_ar = delivery_cache_phonetic_arabic_location_name($name);
            }
            $commune['name'] = $name;
            $commune['wilaya_id'] = $wilaya_id;
            $commune['name_ar'] = $name_ar;
            $commune['label'] = delivery_cache_location_label($name, $name_ar);
            $data['communes'][$wilaya_name][$idx] = $commune;
        }
    }
    
    return $data;
}

function delivery_cache_validate_checkout_data(PDO $pdo, $company_id, $wilaya, $commune, $delivery_type, $desk_id = null) {
    $data = delivery_cache_get_frontend_data($pdo, $company_id);
    if (empty($data['wilayas'])) return false;
    
    $valid_wilayas = array_map('delivery_cache_wilaya_name', $data['wilayas']);
    if (!in_array($wilaya, $valid_wilayas, true)) return false;
    
    if (empty($data['communes'][$wilaya])) return false;
    
    $commune_data = null;
    foreach ($data['communes'][$wilaya] as $c) {
        if ($c['name'] === $commune) {
            $commune_data = $c;
            break;
        }
    }
    if (!$commune_data) return false;
    
    $is_desk = ($delivery_type === 'مكتب' || $delivery_type === 'office' || $delivery_type === 'stopdesk' || strpos(strtolower($delivery_type), 'desk') !== false || strpos($delivery_type, 'Ã') !== false);
    
    if ($is_desk) {
        if (!$commune_data['desk']) return false;
        
        if (empty($data['desks'][$wilaya])) return false;
        $valid_desk = false;
        foreach ($data['desks'][$wilaya] as $d) {
            if ($d['commune'] === $commune && $d['id'] == $desk_id) {
                $valid_desk = true;
                break;
            }
        }
        if (!$valid_desk) return false;
    } else {
        if (!$commune_data['home']) return false;
    }
    
    return true;
}

