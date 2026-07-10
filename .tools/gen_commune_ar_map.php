<?php
/* Build inc/commune_ar_map.php as a FLAT map [normalized_latin => arabic]
 * from the user-provided communes.json (correct Arabic dataset), matched to the
 * actual Ecotrack commune names (exact first, then a safe fuzzy fallback).
 * Keyed by name because the dataset's province numbering differs from Ecotrack's. */

$SRC = 'C:/Users/Abderraouf Chenna/Downloads/algeria-wilayas-communes-2026-main';

function norm($name) {
    $name = trim((string)$name);
    $tr = ['À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n'];
    $name = strtr($name, $tr);
    $name = strtolower($name);
    return preg_replace('/[^a-z0-9]+/', '', $name);
}

$raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($SRC.'/communes.json'));
$rows = json_decode($raw, true);
if (!is_array($rows)) { fwrite(STDERR, "cannot parse communes.json\n"); exit(1); }

// dataset index: normalized name => arabic (first wins)
$ds = [];
foreach ($rows as $r) {
    $k = norm($r['name'] ?? ''); $ar = trim((string)($r['ar_name'] ?? ''));
    if ($k === '' || $ar === '') continue;
    if (!isset($ds[$k])) $ds[$k] = $ar;
}
$dsKeys = array_keys($ds);

// best fuzzy dataset key for an ecotrack key (safe thresholds)
function best_match($key, $dsKeys, $ds) {
    if ($key === '' || strlen($key) < 5) return null;
    $best = null; $bestD = 3; // require distance <= 2
    foreach ($dsKeys as $dk) {
        if (abs(strlen($dk) - strlen($key)) > 3) continue;
        if ($dk[0] !== $key[0] && substr($dk,0,2) !== 'el' && substr($key,0,2) !== 'el') continue;
        $d = levenshtein($key, $dk);
        if ($d < $bestD) { $bestD = $d; $best = $dk; if ($d === 1) break; }
    }
    return $best !== null ? $ds[$best] : null;
}

// Start the final map with every dataset key (covers exact + any other pages).
$map = $ds;

// Then guarantee every ECOTRACK commune key is present (exact or fuzzy).
$cov_total = 0; $cov_exact = 0; $cov_fuzzy = 0; $miss = [];
if (is_file(__DIR__.'/scratch_communes.json')) {
    $cache = json_decode(file_get_contents(__DIR__.'/scratch_communes.json'), true);
    foreach ($cache as $wid => $info) {
        foreach ($info['communes'] as $c) {
            $cov_total++; $k = norm($c);
            if (isset($ds[$k])) { $cov_exact++; continue; }
            $ar = best_match($k, $dsKeys, $ds);
            if ($ar !== null) { $map[$k] = $ar; $cov_fuzzy++; }
            else $miss[] = $c;
        }
    }
}
ksort($map);

$php = "<?php\n/* AUTO-GENERATED from communes.json (Algeria wilayas & communes 2026 dataset).\n"
     . " * FLAT map: [ normalized_latin_commune_name => correct_arabic_name ].\n"
     . " * Delivery VALUE stays Latin; this only sets the shown Arabic label.\n"
     . " * Regenerate with _gen_from_dataset.php. To fix one name, edit its Arabic value here. */\n"
     . "return " . var_export($map, true) . ";\n";
file_put_contents(__DIR__.'/inc/commune_ar_map.php', $php);

$hit = $cov_exact + $cov_fuzzy;
$pct = $cov_total ? round($hit*100/$cov_total,1) : 0;
echo "map keys: ".count($map)."\n";
echo "Ecotrack coverage: $hit / $cov_total ($pct%)  [exact=$cov_exact fuzzy=$cov_fuzzy]\n";
echo "remaining misses (".count($miss)."): ".implode(' | ', array_slice(array_unique($miss),0,30))."\n";
