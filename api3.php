<?php
// إعداد رابط الـ API
$url = 'http://fawtara.link/api';

// إضافة المعلمات المطلوبة إلى الرابط
$url .= '?client_id=' . urlencode("YOUR_CLIENT_ID");
$url .= '&secret_key=' . urlencode("YOUR_SECRET_CODE");
$url .= '&type=new_cash';
$url .= '&invoice_id=12345';
$url .= '&date=2024-10-23';
$url .= '&Notes=' . urlencode('Some Notes');
$url .= '&vat_id=YOUR_VAT_ID';
$url .= '&company_name=' . urlencode('Fawtara Link Co.');
$url .= '&customer_name=' . urlencode('Your Customer Name');
$url .= '&customer_id=' . urlencode('9080706050');
$url .= '&customer_phone=0770000000';
$url .= '&seller_id=YOUR_SELLER_ID';
$url .= '&discount=0';
$url .= '&TaxExclusiveAmount=172';
$url .= '&TaxInclusiveAmount=170';
$url .= '&AllowanceTotalAmount=2';
$url .= '&PayableAmount=170';
$url .= '&item[0][name]=' . urlencode('Test Item') . '&item[0][price]=4&item[0][qty]=43&item[0][discount]=2';

// استدعاء الـ API باستخدام file_get_contents
$response = file_get_contents($url);

// تحويل الاستجابة من JSON إلى كائن
$response = json_decode($response);

// طباعة الـ QR الخاص بالفاتورة
echo $response->EINV_QR;




?>