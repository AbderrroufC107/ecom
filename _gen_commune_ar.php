<?php
/*
 * One-off generator: builds inc/commune_ar_map.php
 * Produces [wilaya_id => [ normalized_latin => arabic ]] for every commune in the
 * delivery cache, using (1) full-name overrides, (2) a rich Algerian-toponym token
 * dictionary, (3) an improved letter fallback. The DELIVERY VALUE stays Latin — this
 * only affects the Arabic label shown to the customer.
 */

$src = json_decode(file_get_contents(__DIR__ . '/scratch_communes.json'), true);

function norm($s) {
    $s = trim((string)$s);
    $tr = ['À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n'];
    $s = strtr($s, $tr);
    $s = strtolower($s);
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

// ---- token dictionary: common Algerian toponym words → correct Arabic ----
$TOK = [
  'ain'=>'عين','ai'=>'عين','ayn'=>'عين',
  'ouled'=>'أولاد','oulad'=>'أولاد','awlad'=>'أولاد','ould'=>'ولد',
  'beni'=>'بني','bni'=>'بني','ben'=>'بن','bin'=>'بن',
  'sidi'=>'سيدي','sid'=>'سيد',
  'bordj'=>'برج','borj'=>'برج','bourdj'=>'برج',
  'oued'=>'وادي','wadi'=>'وادي','wad'=>'وادي',
  'ras'=>'رأس','raas'=>'رأس',
  'hassi'=>'حاسي','has'=>'حاسي',
  'bir'=>'بئر','bhir'=>'بئر',
  'dar'=>'دار',
  'ksar'=>'قصر','kasr'=>'قصر','guesar'=>'قصر',
  'foum'=>'فم',
  'teniet'=>'ثنية','tenient'=>'ثنية','tenia'=>'ثنية',
  'tizi'=>'تيزي',
  'draa'=>'ذراع','drea'=>'ذراع',
  'ghar'=>'غار',
  'had'=>'الحد',
  'souk'=>'سوق','souq'=>'سوق',
  'hammam'=>'حمام','hamam'=>'حمام',
  'kef'=>'كاف',
  'guelb'=>'قلب','kelb'=>'قلب',
  'el'=>'ال','al'=>'ال','le'=>'ال','la'=>'ال',
  'ben'=>'بن',
  'si'=>'سي',
  'mechta'=>'مشتة',
  'reggane'=>'رقان',
  'tit'=>'تيت',
  'zaouiet'=>'زاوية','zaouia'=>'زاوية','zawiya'=>'زاوية',
  'sebt'=>'السبت','sebaa'=>'السبع',
  'mers'=>'مرسى','marsa'=>'مرسى',
  'chaabet'=>'شعبة','chaaba'=>'الشعبة',
  'djebel'=>'جبل','jbel'=>'جبل',
  'birine'=>'بيرين',
  'tamda'=>'تامدة',
  'freha'=>'فريحة',
  'ma'=>'الماء','maa'=>'الماء',
  'kadi'=>'قاضي','abdelkader'=>'عبد القادر','abedelkader'=>'عبد القادر','abdelaziz'=>'عبد العزيز','abdellah'=>'عبد الله','abdennour'=>'عبد النور',
  'mohamed'=>'محمد','mohammed'=>'محمد','ahmed'=>'أحمد','ali'=>'علي','slimane'=>'سليمان','salah'=>'صالح','said'=>'سعيد','yahia'=>'يحيى','moussa'=>'موسى','embarek'=>'مبارك','brahim'=>'إبراهيم','ibrahim'=>'إبراهيم','aissa'=>'عيسى','omar'=>'عمر','amar'=>'عمار','ammar'=>'عمار','okba'=>'عقبة','younes'=>'يونس','khaled'=>'خالد','rachid'=>'رشيد','tayeb'=>'الطيب','lakhdar'=>'لخضر','madani'=>'مدني',
  'beida'=>'البيضاء','baida'=>'البيضاء','beidha'=>'البيضاء',
  'kebira'=>'الكبيرة','kbira'=>'الكبيرة','sghira'=>'الصغيرة','seghira'=>'الصغيرة',
  'djedid'=>'الجديد','jdid'=>'الجديد','djedida'=>'الجديدة',
  'gharbi'=>'الغربي','gharbia'=>'الغربية','chergui'=>'الشرقي','cherguia'=>'الشرقية','bahri'=>'البحري',
  'assafir'=>'العصافير','aioun'=>'العيون','abed'=>'العابد','hakania'=>'الحكانية','hamra'=>'الحمراء','hammam'=>'حمام',
  'taga'=>'تاقة','chaib'=>'الشايب','melouk'=>'الملوك',
];

// ---- full-name overrides (normalized latin => arabic) : capitals & distinctive names ----
$OVR = [
  // wilaya capitals & very well-known communes
  'adrar'=>'أدرار','chlef'=>'الشلف','laghouat'=>'الأغواط','oumelbouaghi'=>'أم البواقي','batna'=>'باتنة','bejaia'=>'بجاية','biskra'=>'بسكرة','bechar'=>'بشار','blida'=>'البليدة','bouira'=>'البويرة','tamanrasset'=>'تمنراست','tebessa'=>'تبسة','tlemcen'=>'تلمسان','tiaret'=>'تيارت','tiziouzou'=>'تيزي وزو','alger'=>'الجزائر','djelfa'=>'الجلفة','jijel'=>'جيجل','setif'=>'سطيف','saida'=>'سعيدة','skikda'=>'سكيكدة','sidibelabbes'=>'سيدي بلعباس','annaba'=>'عنابة','guelma'=>'قالمة','constantine'=>'قسنطينة','medea'=>'المدية','mostaganem'=>'مستغانم','msila'=>'المسيلة','mascara'=>'معسكر','ouargla'=>'ورقلة','oran'=>'وهران','elbayadh'=>'البيض','illizi'=>'إليزي','bordjbouarreridj'=>'برج بوعريريج','boumerdes'=>'بومرداس','eltarf'=>'الطارف','tindouf'=>'تندوف','tissemsilt'=>'تيسمسيلت','eloued'=>'الوادي','khenchela'=>'خنشلة','soukahras'=>'سوق أهراس','tipaza'=>'تيبازة','mila'=>'ميلة','ainDefla'=>'عين الدفلى','aindefla'=>'عين الدفلى','naama'=>'النعامة','ainTemouchent'=>'عين تموشنت','aintemouchent'=>'عين تموشنت','ghardaia'=>'غرداية','relizane'=>'غليزان','timimoun'=>'تيميمون','bordjbadjimokhtar'=>'برج باجي مختار','ouleddjellal'=>'أولاد جلال','beniabbes'=>'بني عباس','inSalah'=>'عين صالح','insalah'=>'عين صالح','inguezzam'=>'عين قزام','inguezzam'=>'عين قزام','touggourt'=>'تقرت','djanet'=>'جانت','elmghair'=>'المغير','elmenia'=>'المنيعة','elmeniaa'=>'المنيعة',
  // distinctive Batna-area names (verified sample)
  'arris'=>'أريس','barika'=>'بريكة','merouana'=>'مروانة','timgad'=>'تيمقاد','tazoult'=>'تازولت','ngaous'=>'نقاوس','tkout'=>'تكوت','menaa'=>'منعة','seriana'=>'سريانة','chemora'=>'الشمرة','djezzar'=>'جزار','ichmoul'=>'إشمول','ksarbellezma'=>'قصر بلزمة','elmadher'=>'المعذر','ainTouta'=>'عين التوتة','aintouta'=>'عين التوتة','tigharghar'=>'تيغرغار','tighanimine'=>'تيغانمين','fesdis'=>'فسديس','oueltaga'=>'واد الطاقة','ouedtaga'=>'واد الطاقة',
];

$map = [];
$stats = ['override'=>0,'token'=>0,'letter'=>0,'total'=>0];

// improved single-word letter fallback (better than current)
function letterword($w) {
    $digraphs = ['ch'=>'ش','kh'=>'خ','gh'=>'غ','dj'=>'ج','ou'=>'و','oo'=>'و','ph'=>'ف','th'=>'ث','sh'=>'ش','tch'=>'تش'];
    $letters = ['a'=>'ا','b'=>'ب','c'=>'ك','d'=>'د','e'=>'ي','f'=>'ف','g'=>'ق','h'=>'ه','i'=>'ي','j'=>'ج','k'=>'ك','l'=>'ل','m'=>'م','n'=>'ن','o'=>'و','p'=>'ب','q'=>'ق','r'=>'ر','s'=>'س','t'=>'ت','u'=>'و','v'=>'ف','w'=>'و','x'=>'كس','y'=>'ي','z'=>'ز'];
    $w = preg_replace('/[^a-z]/','',strtolower($w));
    $out=''; $len=strlen($w);
    for($i=0;$i<$len;$i++){
        $tri=substr($w,$i,3); $pair=substr($w,$i,2);
        if(isset($digraphs[$tri])){$out.=$digraphs[$tri];$i+=2;continue;}
        if(isset($digraphs[$pair])){$out.=$digraphs[$pair];$i++;continue;}
        if(isset($letters[$w[$i]])) $out.=$letters[$w[$i]];
    }
    // drop leading alef when it would just double a vowel start is acceptable
    return $out;
}

function to_ar($name, $TOK, $OVR, &$stats) {
    $key = norm($name);
    if ($key === '') return '';
    if (isset($OVR[$key])) { $stats['override']++; return $OVR[$key]; }
    // word by word, with "el/al" attaching as the article ال to the next word
    $words = preg_split('/[\s\'\-]+/', trim($name));
    $parts = []; $usedTok = false; $usedLetter = false; $pendingArticle = false;
    foreach ($words as $w) {
        $wk = preg_replace('/[^a-z]/','',strtolower(strtr($w, ['é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a','ï'=>'i','î'=>'i','ô'=>'o','û'=>'u','ç'=>'c'])));
        if ($wk==='') continue;
        if (in_array($wk, ['el','al','le','la'], true)) { $pendingArticle = true; continue; }
        if (isset($TOK[$wk])) { $ar = $TOK[$wk]; $usedTok=true; }
        else { $ar = letterword($wk); $usedLetter=true; }
        if ($pendingArticle) {
            // attach ال unless the token already carries it
            if (mb_substr($ar, 0, 2) !== 'ال') $ar = 'ال' . $ar;
            $pendingArticle = false;
        }
        $parts[] = $ar;
    }
    if ($pendingArticle) $parts[] = 'ال';
    if ($usedLetter && !$usedTok) $stats['letter']++; else $stats['token']++;
    return trim(implode(' ', $parts));
}

foreach ($src as $wid => $info) {
    foreach ($info['communes'] as $c) {
        $stats['total']++;
        $ar = to_ar($c, $TOK, $OVR, $stats);
        if ($ar !== '') $map[(int)$wid][norm($c)] = $ar;
    }
}

// write the PHP data file
$php = "<?php\n/* AUTO-GENERATED Arabic commune labels. Key = [wilaya_id][normalized_latin] => Arabic.\n * Delivery value stays Latin; this only sets the displayed Arabic name.\n * To correct a name, edit its Arabic value here. */\nreturn " . var_export($map, true) . ";\n";
file_put_contents(__DIR__ . '/inc/commune_ar_map.php', $php);

echo "total={$stats['total']} override={$stats['override']} token={$stats['token']} letter-only={$stats['letter']}\n";
echo "wrote inc/commune_ar_map.php\n";
// sample Batna
echo "\n--- sample Batna (wid=5) ---\n";
foreach (array_slice($map[5], 0, 20, true) as $k=>$v) echo "  $k => $v\n";
