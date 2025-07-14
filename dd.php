<?php
// 1- login
$login_url = 'https://portal.jofotara.gov.jo/login';
$invoice_url = 'https://portal.jofotara.gov.jo/invoice/download/123456';

$ch = curl_init();

// تسجيل الدخول
curl_setopt($ch, CURLOPT_URL, $login_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=USERNAME&password=PASSWORD');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);

// 2- طلب الفاتورة بالكوكي
curl_setopt($ch, CURLOPT_URL, $invoice_url);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
$pdf = curl_exec($ch);

file_put_contents('invoice.pdf', $pdf);

curl_close($ch);

?>
