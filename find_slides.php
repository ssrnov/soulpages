<?php
$lines = file('p.php');
$output = "";
foreach ($lines as $idx => $line) {
    if (strpos($line, 'PREMIUM:') !== false || strpos($line, '_images') !== false || strpos($line, '_video') !== false) {
        if (strpos($line, 'elseif') !== false || strpos($line, 'if') !== false || strpos($line, '$imgs') !== false || strpos($line, '$slide_key') !== false) {
            $output .= "Line " . ($idx + 1) . ": " . trim($line) . "\n";
        }
    }
}
file_put_contents('find_slides_result.txt', $output);
echo "Done! Output written to find_slides_result.txt";
