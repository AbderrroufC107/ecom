<?php
/* Merge manual Arabic corrections for the 36 Ecotrack communes that had no
 * exact/fuzzy match in the dataset. Keyed by the Ecotrack Latin name. */

function norm($name) {
    $name = trim((string)$name);
    $tr = ['À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n'];
    $name = strtr($name, $tr);
    $name = strtolower($name);
    return preg_replace('/[^a-z0-9]+/', '', $name);
}

$OVERRIDES = [
    'El Fedjoudj Boughrara Sa' => 'الفجوج بوغرارة سعودي',
    'Azil Abedelkader'         => 'أزيل عبد القادر',
    'Beni Dejllil'             => 'بني جليل',
    'Dra El Caid'              => 'ذراع القايد',
    'Fenaia Il Maten'          => 'فناية إلماثن',
    'Tinebdar'                 => 'تينبدار',
    'Mechraa H.boumediene'     => 'مشرع هواري بومدين',
    'Taourirt'                 => 'تاوريرت',
    'Ain Amguel'               => 'عين أمقيل',
    'Bir Mokkadem'             => 'بئر مقدم',
    'El Malabiod'              => 'الماء الأبيض',
    'El Ogla El Malha'         => 'العقلة المالحة',
    'Beni Khaled'              => 'بني خالد',
    'Djebilet Rosfa'           => 'جبيلات الروصفة',
    'Djebel Aissa Mimoun'      => 'جبل عيسى ميمون',
    'Larbaa Nath Irathen'      => 'الأربعاء ناث إيراثن',
    'Bains Romains'            => 'الحمامات الرومانية',
    'Bologhine Ibnou Ziri'     => 'بولوغين ابن زيري',
    'Kheraisia'                => 'الخرايسية',
    'Mohamed Belouzdad'        => 'محمد بلوزداد',
    'Khiri Oued Adjoul'        => 'خيري واد عجول',
    'Beni Oussine'             => 'بني وسين',
    'Hamam Soukhna'            => 'حمام السخنة',
    'Djendel Saadi Mohamed'    => 'جندل سعدي محمد',
    'Ouled Habbeba'            => 'أولاد حبابة',
    'Sidi Dahou Zairs'         => 'سيدي داهو الزاير',
    'Ain Hessania'             => 'عين الحسانية',
    'Hamam Debagh'             => 'حمام دباغ',
    'Sidi Sandel'              => 'عين صندل',
    'Damiat'                   => 'دميات',
    'Sidi Rabie'               => 'سيدي الربيع',
    'Benabdelmalek Ramdane'    => 'بن عبد المالك رمضان',
    'Mazagran'                 => 'مزغران',
    'Sidi Belaattar'           => 'سيدي بلعطار',
    'El Gueitena'              => 'القيطنة',
    'Tamellalet'               => 'تملالت',
];

$file = __DIR__ . '/inc/commune_ar_map.php';
$map = include $file;
if (!is_array($map)) { fwrite(STDERR, "map not loadable\n"); exit(1); }

$added = 0;
foreach ($OVERRIDES as $latin => $ar) {
    $k = norm($latin);
    $map[$k] = $ar; // force-correct
    $added++;
}
ksort($map);

$php = "<?php\n/* AUTO-GENERATED from communes.json (Algeria wilayas & communes 2026 dataset)\n"
     . " * + 36 manual corrections for Ecotrack-only spellings.\n"
     . " * FLAT map: [ normalized_latin_commune_name => correct_arabic_name ].\n"
     . " * Delivery VALUE stays Latin; this only sets the shown Arabic label. */\n"
     . "return " . var_export($map, true) . ";\n";
file_put_contents($file, $php);
echo "overrides applied: $added ; total keys now: " . count($map) . "\n";
