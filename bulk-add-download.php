<?php

define('PASSCODE', '12345678');

require_once( dirname(__FILE__) . '/wp-load.php' );

if (!isset($_GET['pass'])) {
    echo "Pass code is required";
    exit;
}
$pass = $_GET['pass'];
if ($pass != PASSCODE) {
    echo "Pass code is incorrect";
    exit;
}

if (!isset($_GET['old-product-id'])) {
    echo "Old product ID is required";
    exit;
}
$oldProductID = (int)$_GET['old-product-id'];

if (!isset($_GET['new-product-id'])) {
    echo "New product ID is required";
    exit;
}
$newProductID = (int)$_GET['new-product-id'];

$newProduct = wc_get_product($newProductID);
$newFiles = $newProduct->get_files();
if (count($newFiles) == 0) {
    echo "This product has no file";
    exit;
}

$newFileID = '';
foreach ($newFiles as $key => $file) {
    $newFileID = $key;
    break;
}

$table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
$rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table where product_id = %d", $oldProductID), ARRAY_A);

if ($rows === false || !is_array($rows)) {
    echo "Error in querying permissions with product ID of " . $oldProductID;
    die;
}

echo "Number of permissions with product ID of " . $oldProductID . ': ' . count($rows) . "<br />";

$totalInsertion = 0;
$totalSuccessInsertion = 0;
$totalInsertionSkipped = 0;
foreach ($rows as $row) {

    $r = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE order_id = %d AND product_id = %d", $row['order_id'], $newProductID));
    if ($r !== null && $r !== false) {
        $r = (int)$r;
        if ($r > 0) {
            ++$totalInsertionSkipped;
            continue;
        }
    }

    $accessGrantedDT = new DateTime('now');
    $accessGrantedDTS = $accessGrantedDT->format("Y-m-d H:i:s");

    $ret = $wpdb->insert($table, array(
        'product_id' => $newProductID,
        'order_id' => $row['order_id'],
        'order_key' => $row['order_key'],
        'user_email' => $row['user_email'],
        'user_id' => $row['user_id'],
        'downloads_remaining' => $row['downloads_remaining'],
        'access_granted' => $accessGrantedDTS,
        //'access_expires' => null, // no insert, so will be null
        'download_count' => 0,
        'download_id' => $newFileID
        ));

    ++$totalInsertion;
    if ($ret !== false) {
        ++$totalSuccessInsertion;
    }
}

echo "Total number of insertion skipped: " . $totalInsertionSkipped . "<br />";
echo "Total number of tried insertion: " . $totalInsertion . "<br />";
echo "Total number of successful insertion: " . $totalSuccessInsertion  . "<br />";