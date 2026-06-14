<?php
$file = 'landing_page.php';
$lines = file($file);

$wilaya_start = null;
$delivery_start = null;
$delivery_end = null;

foreach ($lines as $i => $line) {
    // Find wilaya flex block (first one after form tag)
    if ($wilaya_start === null && strpos($line, 'display:flex; gap:10px; margin-bottom:15px; direction:rtl;') !== false) {
        $counter = isset($counter) ? $counter + 1 : 1;
        if ($counter == 2) $wilaya_start = $i; // second flex div is wilaya/commune
    }
    // Find delivery-options label
    if ($delivery_start === null && strpos($line, 'delivery-options"') !== false && strpos($line, 'class=') !== false) {
        // Go back to find the enclosing form-group
        for ($j = $i; $j >= max(0, $i-3); $j--) {
            if (strpos($lines[$j], 'form-group') !== false && strpos($lines[$j], '<div') !== false) {
                $delivery_start = $j;
                break;
            }
        }
    }
    // Find delivery-options-note end
    if ($delivery_start !== null && $delivery_end === null && $i > ($delivery_start + 5) && strpos($line, 'deliveryOptionsNote') !== false) {
        // The end is 2 lines after the note
        $delivery_end = $i + 2;
    }
}

echo "wilaya_start: $wilaya_start\n";
echo "delivery_start: $delivery_start\n";
echo "delivery_end: $delivery_end\n";

if ($wilaya_start !== null && $delivery_start !== null && $delivery_end !== null) {
    // Extract delivery block
    $delivery_block = array_slice($lines, $delivery_start, $delivery_end - $delivery_start + 1);
    
    // Remove delivery block
    array_splice($lines, $delivery_start, $delivery_end - $delivery_start + 1);
    
    // Recalculate wilaya_start since we removed lines above it
    if ($delivery_start < $wilaya_start) {
        $wilaya_start -= ($delivery_end - $delivery_start + 1);
    }
    
    // Insert delivery block before wilaya block
    array_splice($lines, $wilaya_start, 0, $delivery_block);
    
    file_put_contents($file, implode('', $lines));
    echo "Done: delivery moved before wilaya\n";
} else {
    echo "Could not find blocks\n";
}
?>
