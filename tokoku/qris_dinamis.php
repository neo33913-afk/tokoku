<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$nominal = (int) ($_GET['nominal'] ?? 0);
$statis = '00020101021126610014COM.GO-JEK.WWW01189360091438657083600210G8657083600303UMI51440014ID.CO.QRIS.WWW0215ID10266455842550303UMI5204549953033605802ID5906TANAGO6008PONOROGO61056349262070703A0163041B87';

try {
    echo qris_dinamis($statis, $nominal);
} catch (Throwable $e) {
    http_response_code(400);
    echo $statis;
}