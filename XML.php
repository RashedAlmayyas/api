<?php
//$connection_string = "192.168.0.10:1521/Orcl2"; 
$username = "WEB_USER";
$password = "WEB_DEV$02";
$conn = oci_connect($username, $password, $connection_string, 'AL32UTF8'); 

if (!$conn) {
    die("فشل الاتصال بقاعدة البيانات: " . htmlspecialchars(oci_error()['message']));
}

$BRANCH_NO = 041; 
$query = "SELECT * FROM WEB_SALES_TAX_INVOICE_VIEW WHERE BRANCH_NO = 003 ";
$stid = oci_parse($conn, $query);
oci_execute($stid);
$invoice_data = oci_fetch_assoc($stid);

if (!$invoice_data) {
  
    die("لم يتم العثور على الفاتورة المطلوبة.");
}

$queryItems = "SELECT * FROM WEB_SALES_TAX_INVOICE_DTL_VIEW WHERE BRANCH_NO = 003  ";
$stidItems = oci_parse($conn, $queryItems);
oci_execute($stidItems);

$dom = new DOMDocument("1.0", "UTF-8");
$dom->formatOutput = true;

$invoice = $dom->createElement("Invoice");
$invoice->setAttribute("xmlns", "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2");
$invoice->setAttribute("xmlns:cac", "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");
$invoice->setAttribute("xmlns:cbc", "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
$invoice->setAttribute("xmlns:ext", "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2");

$profileID = $dom->createElement("cbc:ProfileID", "reporting:1.0");
$invoice->appendChild($profileID);

$id = $dom->createElement("cbc:ID", htmlspecialchars($invoice_data['ID']));
$invoice->appendChild($id);

$uuid = $dom->createElement("cbc:UUID", htmlspecialchars($invoice_data['UUID']));
$invoice->appendChild($uuid);

// Convert the Oracle date to ISO format
$oracleDate = $invoice_data['ISSUEDATE'];
$isoDate = date('Y-m-d', strtotime($oracleDate));
$issueDate = $dom->createElement("cbc:IssueDate", $isoDate);
$invoice->appendChild($issueDate);

$invoiceTypeCode = $dom->createElement("cbc:InvoiceTypeCode", $invoice_data['INVOICETYPECODE']);
$invoiceTypeCode->setAttribute("name", $invoice_data['PRE_INVOICETYPECODE']);
$invoice->appendChild($invoiceTypeCode);

$note = $dom->createElement("cbc:Note", htmlspecialchars($invoice_data['NOTE']));
$invoice->appendChild($note);

$currencyCode = $dom->createElement("cbc:DocumentCurrencyCode", "JOD");
$invoice->appendChild($currencyCode);

$taxCurrencyCode = $dom->createElement("cbc:TaxCurrencyCode", "JOD");
$invoice->appendChild($taxCurrencyCode);

$additionalDocRef = $dom->createElement("cac:AdditionalDocumentReference");
$docRefID = $dom->createElement("cbc:ID", "ICV");
$additionalDocRef->appendChild($docRefID);

$docRefUUID = $dom->createElement("cbc:UUID", $invoice_data['UUID']);
$additionalDocRef->appendChild($docRefUUID);
$invoice->appendChild($additionalDocRef);

$supplier = $dom->createElement("cac:AccountingSupplierParty");
$party = $dom->createElement("cac:Party");

$postalAddress = $dom->createElement("cac:PostalAddress");
$country = $dom->createElement("cac:Country");
$countryCode = $dom->createElement("cbc:IdentificationCode", "JO");
$country->appendChild($countryCode);
$postalAddress->appendChild($country);
$party->appendChild($postalAddress);

$taxScheme = $dom->createElement("cac:PartyTaxScheme");
$companyID = $dom->createElement("cbc:CompanyID",htmlspecialchars($invoice_data['COMPANYID']));
$taxScheme->appendChild($companyID);
$scheme = $dom->createElement("cac:TaxScheme");
$schemeID = $dom->createElement("cbc:ID", "VAT");
$scheme->appendChild($schemeID);
$taxScheme->appendChild($scheme);
$party->appendChild($taxScheme);

$legalEntity = $dom->createElement("cac:PartyLegalEntity");
$regName = $dom->createElement("cbc:RegistrationName", $invoice_data['REGISTRATIONNAMEBUYER']);
$legalEntity->appendChild($regName);
$party->appendChild($legalEntity);
$supplier->appendChild($party);
$invoice->appendChild($supplier);

$customer = $dom->createElement("cac:AccountingCustomerParty");
$customerParty = $dom->createElement("cac:Party");

$partyIdentification = $dom->createElement("cac:PartyIdentification");
$partyID = $dom->createElement("cbc:ID", $invoice_data['PARTYIDENTIFICATION']);
$partyID->setAttribute("schemeID",  $invoice_data['SCHEMETYPE']);
$partyIdentification->appendChild($partyID);
$customerParty->appendChild($partyIdentification);
$customerPostalAddress = $dom->createElement("cac:PostalAddress");

$custPostalZone = $dom->createElement("cbc:PostalZone");
    $custPostalZone->appendChild($dom->createTextNode(htmlspecialchars($invoice_data['COUNTRYSUBENTITYCODE'])));

$customerPostalAddress->appendChild($custPostalZone);

$CountrySubentityCode = $dom->createElement("cbc:CountrySubentityCode", htmlspecialchars($invoice_data['COUNTRYSUBENTITYCODE']));
$customerPostalAddress->appendChild($CountrySubentityCode);

$custCountry = $dom->createElement("cac:Country");

$custCountryCode = $dom->createElement("cbc:IdentificationCode", "JO");
$custCountry->appendChild($custCountryCode);

$customerPostalAddress->appendChild($custCountry);

$customerParty->appendChild($customerPostalAddress);

$customerTaxScheme = $dom->createElement("cac:PartyTaxScheme");
$customerCompanyID = $dom->createElement("cbc:CompanyID", htmlspecialchars($invoice_data['COMP_NO']));
$customerTaxScheme->appendChild($customerCompanyID);
$customerTaxSchemeType = $dom->createElement("cac:TaxScheme");
$customerTaxSchemeTypeID = $dom->createElement("cbc:ID", "VAT");
$customerTaxSchemeType->appendChild($customerTaxSchemeTypeID);
$customerTaxScheme->appendChild($customerTaxSchemeType);
$customerParty->appendChild($customerTaxScheme);

$customerLegalEntity = $dom->createElement("cac:PartyLegalEntity");
$customerRegName = $dom->createElement("cbc:RegistrationName", htmlspecialchars($invoice_data['REGISTRATIONNAMESELLER']));
$customerLegalEntity->appendChild($customerRegName);
$customerParty->appendChild($customerLegalEntity);
$customer->appendChild($customerParty);

$accountingContact = $dom->createElement("cac:AccountingContact");
$telephone = $dom->createElement("cbc:Telephone", htmlspecialchars($invoice_data['TELEPHONE']));
$accountingContact->appendChild($telephone);
$customer->appendChild($accountingContact);

$invoice->appendChild($customer);

// 9. بائع التاجر (اختياري)
$sellerSupplier = $dom->createElement("cac:SellerSupplierParty");
$sellerParty = $dom->createElement("cac:Party");
$sellerPartyIdentification = $dom->createElement("cac:PartyIdentification");
$sellerID = $dom->createElement("cbc:ID", "202942");
$sellerPartyIdentification->appendChild($sellerID);
$sellerParty->appendChild($sellerPartyIdentification);
$sellerSupplier->appendChild($sellerParty);
$invoice->appendChild($sellerSupplier);
function formatAmount($value) {
    return number_format((float)$value, 9, '.', '');
}
// 10. بدل/رسوم الخصم
$allowanceCharge = $dom->createElement("cac:AllowanceCharge");
$chargeIndicator = $dom->createElement("cbc:ChargeIndicator", "false");
$allowanceCharge->appendChild($chargeIndicator);
$chargeReason = $dom->createElement("cbc:AllowanceChargeReason", "discount");
$allowanceCharge->appendChild($chargeReason);
$chargeAmount = $dom->createElement("cbc:Amount",formatAmount($invoice_data['DISCOUNTAMOUNT']));
$chargeAmount->setAttribute("currencyID", "JO");
$allowanceCharge->appendChild($chargeAmount);
$invoice->appendChild($allowanceCharge);

// 11. إجمالي الضرائب
$taxTotal = $dom->createElement("cac:TaxTotal");
$taxAmount = $dom->createElement("cbc:TaxAmount",formatAmount($invoice_data['TAXAMOUNT']));
$taxAmount->setAttribute("currencyID", "JO");
$taxTotal->appendChild($taxAmount);
$invoice->appendChild($taxTotal);


// 12. الإجماليات المالية
$monetaryTotal = $dom->createElement("cac:LegalMonetaryTotal");
$taxExclusiveAmount = $dom->createElement("cbc:TaxExclusiveAmount",formatAmount($invoice_data['TAXEXCLUSIVEAMOUNT']));
$taxExclusiveAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($taxExclusiveAmount);

$taxInclusiveAmount = $dom->createElement("cbc:TaxInclusiveAmount",formatAmount($invoice_data['TAXINCLUSIVEAMOUNT']));
$taxInclusiveAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($taxInclusiveAmount);

$allowanceTotalAmount = $dom->createElement("cbc:AllowanceTotalAmount", formatAmount($invoice_data['ALLOWANCETOTALAMOUNT']));
$allowanceTotalAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($allowanceTotalAmount);

$payableAmount = $dom->createElement("cbc:PayableAmount",formatAmount($invoice_data['PAYABLEAMOUNT']));
$payableAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($payableAmount);

$invoice->appendChild($monetaryTotal);

// 13. بنود الفاتورة
while ($item = oci_fetch_assoc($stidItems)) {
    $invoiceLine = $dom->createElement("cac:InvoiceLine");
    
    $itemID = $dom->createElement("cbc:ID", htmlspecialchars($item['ID']));
    $invoiceLine->appendChild($itemID);

    $quantity = $dom->createElement("cbc:InvoicedQuantity", formatAmount($item['INVOICEDQUNTITY']));
    $quantity->setAttribute("unitCode", "PCE");
    $invoiceLine->appendChild($quantity);

    $lineAmount = $dom->createElement("cbc:LineExtensionAmount",formatAmount($item['EXTENSIONAMOUNT']));
    $lineAmount->setAttribute("currencyID", "JO");
    $invoiceLine->appendChild($lineAmount);

    $lineTaxTotal = $dom->createElement("cac:TaxTotal");
    $lineTaxAmount = $dom->createElement("cbc:TaxAmount", formatAmount($item['TAXAMOUNT']));
    $lineTaxAmount->setAttribute("currencyID", "JO");
    $lineTaxTotal->appendChild($lineTaxAmount);
    
    $lineRoundingAmount = $dom->createElement("cbc:RoundingAmount",formatAmount($item['ROUNDINGAMOUNT']));
    $lineRoundingAmount->setAttribute("currencyID", "JO");
    $lineTaxTotal->appendChild($lineRoundingAmount);
    
    $taxSubtotal = $dom->createElement("cac:TaxSubtotal");
    $subtotalTaxAmount = $dom->createElement("cbc:TaxAmount", formatAmount($item['TAXAMOUNT']));
    $subtotalTaxAmount->setAttribute("currencyID", "JO");
    $taxSubtotal->appendChild($subtotalTaxAmount);
    
    $taxCategory = $dom->createElement("cac:TaxCategory");
    $categoryID = $dom->createElement("cbc:ID", $item['TAX_CATEGORY_ID']);
    $categoryID->setAttribute("schemeAgencyID", "6");
    $categoryID->setAttribute("schemeID", "UN/ECE 5305");
    $taxCategory->appendChild($categoryID);
    
    $taxPercent = $dom->createElement("cbc:Percent", formatAmount($item['PERCENT_TAX']));
    $taxCategory->appendChild($taxPercent);
    
    $taxScheme = $dom->createElement("cac:TaxScheme");
    $taxSchemeID = $dom->createElement("cbc:ID","VAT" /*$item['TAX_CATEGORY_ID']*/);
    $taxSchemeID->setAttribute("schemeAgencyID", "6");
    $taxSchemeID->setAttribute("schemeID", "UN/ECE 5153");
    $taxScheme->appendChild($taxSchemeID);
    $taxCategory->appendChild($taxScheme);
    
    $taxSubtotal->appendChild($taxCategory);
    $lineTaxTotal->appendChild($taxSubtotal);
    $invoiceLine->appendChild($lineTaxTotal);

    $itemElement = $dom->createElement("cac:Item");
    $itemName = $dom->createElement("cbc:Name",$item['ITEMNAME']);
    $itemElement->appendChild($itemName);
    
    $invoiceLine->appendChild($itemElement);
    
    $price = $dom->createElement("cac:Price");
    $priceAmount = $dom->createElement("cbc:PriceAmount", formatAmount($item['PRICEAMOUNT']));
    $priceAmount->setAttribute("currencyID", "JO");
    $price->appendChild($priceAmount);
    
    $priceAllowanceCharge = $dom->createElement("cac:AllowanceCharge");
    $priceChargeIndicator = $dom->createElement("cbc:ChargeIndicator", "false");
    $priceAllowanceCharge->appendChild($priceChargeIndicator);
    $priceChargeReason = $dom->createElement("cbc:AllowanceChargeReason", "DISCOUNT");
    $priceAllowanceCharge->appendChild($priceChargeReason);
    $priceChargeAmount = $dom->createElement("cbc:Amount",formatAmount($item['ALLOWANCECHARGERESONE']));
    $priceChargeAmount->setAttribute("currencyID", "JO");
    $priceAllowanceCharge->appendChild($priceChargeAmount);
    $price->appendChild($priceAllowanceCharge);

    $invoiceLine->appendChild($price);
    $invoice->appendChild($invoiceLine);
}
$dom->appendChild($invoice);
$xmlContent = $dom->saveXML();
file_put_contents('invoice_debug.xml', $xmlContent);

$encodedXmlContent = base64_encode($xmlContent); 
$clientId = "b1d7548d-191c-4343-b0f5-0c0e3fa128a3";
$secretKey = "Gj5nS9wyYHRadaVffz5VKB4v4wlVWyPhcJvrTD4NHtPvwBVLwYycGApVuAfyISBNTXd4ce2a7R6Cjvw9hnJs/v/TVHP+JjcBlc+bfPV98sVohXI82ICIhUw/nvnFCmY8eu0OVYvLuKi4RmFk0ayC8GBfX/wNSQUA47VX/aQdSioBr/QGpes2bnyNHuC4rgx90poioCvwi6avMVoUgybHupSoBRYeooSkrvSs6mmgX+m1x62r8DzFDCqQR8hez7gkZAO0r6yD+2dSwEanh+DyJA==";

$ch = curl_init("https://backend.jofotara.gov.jo/core/invoices/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3000);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$jsonPayload = json_encode(['invoice' => $encodedXmlContent]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

$headers = [
    "Client-Id: $clientId",
    "Secret-Key: $secretKey",
    "Content-Type: application/json"
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$cookieFile = __DIR__ . '/cookies.txt';
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$info = curl_getinfo($ch); 

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);
// تابع تنفيذ الكود السابق...
if ($response === false) {
    $error_message = "❌ فشل الاتصال بـ API: " . curl_error($ch);
} else {
    $responseData = json_decode($response, true);
    file_put_contents('api_response_debug.json', json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $transId = $invoice_data['TRANS_ID'];

    if (
        $responseData['EINV_RESULTS']['status'] === 'PASS' &&
        (
            $responseData['EINV_STATUS'] === 'SUBMITTED' ||
            $responseData['EINV_STATUS'] === 'ALREADY_SUBMITTED'
        )
    ) {
        $success_message = "✅ تم إرسال الفاتورة بنجاح.";
        $qrValue = isset($responseData['EINV_QR']) ? $responseData['EINV_QR'] : '';
        if (!$qrValue) $error_message_qr = "❌ لم يتم العثور على كود QR.";

   $sql = 'BEGIN ERP.WEB_PKG.UPDATE_TAX_INVOICE_API_FLAG(:P_TRANS_ID); END;';
   $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':P_TRANS_ID', $transId);
        if (oci_execute($stmt)) {
            $db_success_message = "✅ تم تحديث حالة الفاتورة في قاعدة البيانات بنجاح.";
        } else {
            $e = oci_error($stmt);
            $db_error_message = "❌ خطأ أثناء تنفيذ البروسيجر: " . htmlspecialchars($e['message']);
        }
        oci_free_statement($stmt);
    } else {
        $error_message = "❌ فشل إرسال الفاتورة.";
    }
}
oci_close($conn);
curl_close($ch);
?>

<!DOCTYPE html>
<html lang="En" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>نظام الفوترة الاردني - سامح مول  </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body>
<div class="vh-100 d-flex justify-content-center align-items-center">
    <div class="col-md-4">
        <div class="card bg-white shadow p-4 text-center" align="center">
        <div class="text-center">

            <div id="qrcode" align="center"></div>
            <canvas id="qrCanvas" style="display: none;" align="center"></canvas>
            <img id="finalQR" align="center" />
            </div>
            <h1 class="mt-3">شكراً لك!</h1>
            <?php if (isset($success_message)) echo "<div class='text-success'>$success_message</div>"; ?>
            <?php if (isset($error_message)) echo "<div class='text-danger'>$error_message</div>"; ?>
            <?php if (isset($error_message_qr)) echo "<div class='text-danger'>$error_message_qr</div>"; ?>
            <?php if (isset($db_success_message)) echo "<div class='text-success'>$db_success_message</div>"; ?>
            <?php if (isset($db_error_message)) echo "<div class='text-danger'>$db_error_message</div>"; ?>

            <button class="btn btn-outline-success mt-3" onclick="downloadQR()">تحميل QR</button>
        </div>
    </div>
</div>

<script>
    const qrValue = <?php echo json_encode($qrValue); ?>;

    const tempDiv = document.createElement("div");
    const qr = new QRCode(tempDiv, {
        text: qrValue,
        width: 300,
        height: 300,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    setTimeout(() => {
        const qrCanvas = document.querySelector("#qrCanvas");
        const context = qrCanvas.getContext("2d");

        const qrImage = tempDiv.querySelector("img") || tempDiv.querySelector("canvas");
        const qrSize = 300;

        qrCanvas.width = qrSize;
        qrCanvas.height = qrSize;

        context.drawImage(qrImage, 0, 0, qrSize, qrSize);

        const logo = new Image();
        logo.src = "logo2.png"; // ← تأكد من وجود الشعار
        logo.onload = () => {
            const logoSize = 60;
            const x = (qrSize - logoSize) / 2;
            const y = (qrSize - logoSize) / 2;

            context.save();
            context.beginPath();
            context.arc(x + logoSize / 2, y + logoSize / 2, logoSize / 2, 0, Math.PI * 2);
            context.closePath();
            context.clip();

            context.drawImage(logo, x, y, logoSize, logoSize);
            context.restore();

            // عرض الصورة النهائية
            document.getElementById("finalQR").src = qrCanvas.toDataURL("image/png");
            document.getElementById("finalQR").style.width = "300px";
            document.getElementById("finalQR").style.height = "300px";

            // ✅ حفظ تلقائي على السيرفر
            uploadQR();
        };
    }, 500);

    function downloadQR() {
        const canvas = document.getElementById("qrCanvas");
        const link = document.createElement("a");
        link.download = "invoice_qr.png";
        link.href = canvas.toDataURL("image/png");
        link.click();
    }

    function uploadQR() {
        const canvas = document.getElementById("qrCanvas");
        const imageData = canvas.toDataURL("image/png");

        const formData = new FormData();
        formData.append("qrImage", imageData);
        formData.append("invoice_id", <?php echo json_encode($invoice_data['ID']); ?>);

        fetch("save_qr.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.text())
        .then(data => {
            console.log("✅ حفظ تلقائي:", data);
        })
        .catch(err => {
            console.error("❌ فشل الحفظ:", err);
        });
    }
</script>
</body>
</html>
