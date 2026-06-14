<?php
$file = 'landing_page.php';
$lines = file($file);

$delivery_start = null;
$delivery_end = null;
$wilaya_start = null;
$wilaya_end = null;

// Find delivery block
foreach ($lines as $i => $line) {
    if ($delivery_start === null && strpos($line, 'delivery-options"') !== false && strpos($line, 'class=') !== false) {
        // Go back to find the enclosing form-group
        for ($j = $i; $j >= max(0, $i-3); $j--) {
            if (strpos($lines[$j], 'form-group') !== false && strpos($lines[$j], '<div') !== false) {
                $delivery_start = $j;
                break;
            }
        }
    }
    if ($delivery_start !== null && $delivery_end === null && $i > ($delivery_start + 5) && strpos($line, 'deliveryOptionsNote') !== false) {
        $delivery_end = $i + 2; // Include the closing div and blank line
    }
}

// Find wilaya block
foreach ($lines as $i => $line) {
    if ($wilaya_start === null && strpos($line, 'display:flex; gap:10px; margin-bottom:15px; direction:rtl;') !== false) {
        $wilaya_start = $i;
    }
    if ($wilaya_start !== null && $wilaya_end === null && $i > $wilaya_start && strpos($line, '</div>') !== false) {
        // Need to count divs to find the matching closing div for the flex container
        $div_count = 0;
        for ($j = $wilaya_start; $j <= $i; $j++) {
            $div_count += substr_count($lines[$j], '<div');
            $div_count -= substr_count($lines[$j], '</div');
        }
        if ($div_count === 0) {
            $wilaya_end = $i;
        }
    }
}

if ($wilaya_start !== null && $delivery_start !== null && $delivery_end !== null && $wilaya_end !== null) {
    echo "wilaya: $wilaya_start to $wilaya_end\n";
    echo "delivery: $delivery_start to $delivery_end\n";
    
    // Extract delivery block
    $delivery_block = array_slice($lines, $delivery_start, $delivery_end - $delivery_start + 1);
    
    // Remove delivery block
    array_splice($lines, $delivery_start, $delivery_end - $delivery_start + 1);
    
    // Recalculate wilaya_end since we removed lines above it
    if ($delivery_start < $wilaya_end) {
        $wilaya_end -= ($delivery_end - $delivery_start + 1);
    }
    
    // Insert delivery block AFTER wilaya block
    array_splice($lines, $wilaya_end + 1, 0, $delivery_block);
    
    file_put_contents($file, implode('', $lines));
    echo "Done: delivery moved after wilaya\n";
} else {
    echo "Could not find blocks\n";
}
?>
