<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "SoulSync AdSense Migration Script\n";
echo "=============================\n\n";

try {
    // Check if adsense_test_mode is set in site_settings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `site_settings` WHERE `setting_key` = 'adsense_test_mode'");
    $stmt->execute();
    $exists = $stmt->fetchColumn() > 0;

    if (!$exists) {
        $stmt_ins = $pdo->prepare("INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('adsense_test_mode', '1')");
        $stmt_ins->execute();
        echo "SUCCESS: 'adsense_test_mode' seeded with value '1' (test ads enabled by default).\n";
    } else {
        echo "INFO: 'adsense_test_mode' already exists in the database.\n";
    }
    
    // Also make sure enable_ads is enabled
    $stmt_upd = $pdo->prepare("UPDATE `site_settings` SET `setting_value` = '1' WHERE `setting_key` = 'enable_ads'");
    $stmt_upd->execute();
    echo "SUCCESS: 'enable_ads' has been set to '1' (AdSense active).\n";

    // Set fallback publisher ID if empty
    $curr_pub = get_setting('adsense_publisher_id', '');
    if (empty($curr_pub)) {
        $stmt_pub = $pdo->prepare("INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('adsense_publisher_id', 'ca-pub-5211469243507012') ON DUPLICATE KEY UPDATE `setting_value` = 'ca-pub-5211469243507012'");
        $stmt_pub->execute();
        echo "SUCCESS: 'adsense_publisher_id' seeded with default ca-pub-5211469243507012.\n";
    }
    
    // Verify current settings values
    $enable_ads = get_setting('enable_ads', '0');
    $test_mode = get_setting('adsense_test_mode', '0');
    $pub_id = get_setting('adsense_publisher_id', '');
    
    echo "\nVerification of Settings:\n";
    echo "  - enable_ads: " . $enable_ads . "\n";
    echo "  - adsense_test_mode: " . $test_mode . "\n";
    echo "  - adsense_publisher_id: " . $pub_id . "\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
