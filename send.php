<?php
// معلومات الـ API
$api_url = "https://backend.jofotara.gov.jo/core/invoices/";

// بيانات المصادقة والـ Headers
$client_id = "b1d7548d-1";
$secret_key = "Gj5nS9wyYH";

// البيانات التي سيتم إرسالها (مع استبدال "Encrypted XML Code" بكود XML المشفر)
$invoice_data = array(
    "invoice" => "Encrypted XML Code" // استبدل بـ الكود المشفر للفاتورة
);

// تحويل البيانات إلى JSON
$json_data = json_encode($invoice_data);

// تهيئة cURL
$ch = curl_init($api_url);

// تعيين الخيارات الخاصة بـ cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data); // إرسال البيانات في الجسم
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Client-Id: $client_id",
    "Secret-Key: $secret_key",
    "Content-Type: application/json"
));

// تنفيذ الطلب والحصول على الاستجابة
$response = curl_exec($ch);

// التحقق من وجود أخطاء
if(curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
} else {
    // التحقق من حالة HTTP
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo 'HTTP Response Code: ' . $http_code . '<br>';
    
    // طباعة الاستجابة من الـ API
    echo 'Response from API: ' . $response;
}

// إغلاق الاتصال بـ cURL
curl_close($ch);
?>
