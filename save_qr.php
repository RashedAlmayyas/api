<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['qrImage'], $_POST['invoice_id'])) {
        $qrImage = $_POST['qrImage'];
        $invoiceId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $_POST['invoice_id']);

        $folderPath =  $_POST['path'];
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0777, true);
        }

        $image_parts = explode(";base64,", $qrImage);
        if (count($image_parts) === 2) {
            $image_base64 = base64_decode($image_parts[1]);
            $filePath = $folderPath . $invoiceId . '.png';

            if (file_put_contents($filePath, $image_base64)) {
                echo "✅ تم حفظ QR باسم: $invoiceId.png";
            } else {
                echo "❌ فشل في حفظ الصورة";
            }
        } else {
            echo "❌ تنسيق صورة غير صالح";
        }
    } else {
        echo "❌ بيانات ناقصة";
    }
} else {
    echo "❌ الطلب غير صالح";
}
?>
