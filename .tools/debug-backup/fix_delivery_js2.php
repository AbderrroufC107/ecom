<?php
$file = 'landing_page.php';
$lines = file($file);

foreach ($lines as $i => $line) {
    if (strpos($line, "button.classList.add('is-hidden');") !== false) {
        $lines[$i] = "// " . $line;
    }
    if (strpos($line, "button.disabled = true;") !== false) {
        $lines[$i] = "// " . $line;
    }
    if (strpos($line, "button.classList.toggle('is-hidden', !isAvailable);") !== false) {
        $lines[$i] = "// " . $line;
    }
    if (strpos($line, "button.disabled = !isAvailable;") !== false) {
        $lines[$i] = "// " . $line;
    }
}

file_put_contents($file, implode("", $lines));
echo "Done replacing";
?>
