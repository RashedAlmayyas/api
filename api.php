<?php
// 1. إعدادات الاتصال بقاعدة البيانات
$connection_string = "192.168.0.10:1521/Orcl2"; 
$username = "WEB_USER";
$password = "WEB_DEV$02";
$conn = oci_connect($username, $password, $connection_string, 'AL32UTF8'); // تحديد الترميز

if (!$conn) {
    die("فشل الاتصال بقاعدة البيانات: " . htmlspecialchars(oci_error()['message']));
}

// 2. استعلام بيانات الفاتورة الأساسية
$BRANCH_NO = 002; 
$query = "SELECT * FROM WEB_SALES_TAX_INVOICE_VIEW WHERE BRANCH_NO = 002";
$stid = oci_parse($conn, $query);
oci_execute($stid);
$invoice_data = oci_fetch_assoc($stid);

if (!$invoice_data) {
    die("لم يتم العثور على الفاتورة المطلوبة.");
}

// 3. استعلام عناصر الفاتورة
$queryItems = "SELECT * FROM WEB_SALES_TAX_INVOICE_DTL_VIEW WHERE BRANCH_NO = 002 AND ID = 1401202500210814";
$stidItems = oci_parse($conn, $queryItems);
oci_execute($stidItems);

// 4. إنشاء مستند XML
$dom = new DOMDocument("1.0", "UTF-8");
$dom->formatOutput = true;

// إنشاء عنصر الفاتورة الأساسي
$invoice = $dom->createElement("Invoice");
$invoice->setAttribute("xmlns", "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2");
$invoice->setAttribute("xmlns:cac", "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");
$invoice->setAttribute("xmlns:cbc", "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
$invoice->setAttribute("xmlns:ext", "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2");

// 5. إضافة البيانات الأساسية للفاتورة
$profileID = $dom->createElement("cbc:ProfileID", "reporting:1.0");
$invoice->appendChild($profileID);

$id = $dom->createElement("cbc:ID", htmlspecialchars($invoice_data['ID']));
$invoice->appendChild($id);

$uuid = $dom->createElement("cbc:UUID", htmlspecialchars($invoice_data['UUID']));
$invoice->appendChild($uuid);

$issueDate = $dom->createElement("cbc:IssueDate", htmlspecialchars($invoice_data['ISSUEDATE']));
$invoice->appendChild($issueDate);

$invoiceTypeCode = $dom->createElement("cbc:InvoiceTypeCode", $invoice_data['INVOICETYPECODE']);
$invoiceTypeCode->setAttribute("name", $invoice_data['PRE_INVOICETYPECODE']);
$invoice->appendChild($invoiceTypeCode);

$note = $dom->createElement("cbc:Note", htmlspecialchars($invoice_data['NOTE']));
$invoice->appendChild($note);

$currencyCode = $dom->createElement("cbc:DocumentCurrencyCode", "JO");
$invoice->appendChild($currencyCode);

$taxCurrencyCode = $dom->createElement("cbc:TaxCurrencyCode", "JO");
$invoice->appendChild($taxCurrencyCode);

// 6. مرجع المستند الإضافي
$additionalDocRef = $dom->createElement("cac:AdditionalDocumentReference");
$docRefID = $dom->createElement("cbc:ID", "ICV");
$additionalDocRef->appendChild($docRefID);

$docRefUUID = $dom->createElement("cbc:UUID", $invoice_data['UUID']);
$additionalDocRef->appendChild($docRefUUID);
$invoice->appendChild($additionalDocRef);

// 7. معلومات المورد (البائع)
$supplier = $dom->createElement("cac:AccountingSupplierParty");
$party = $dom->createElement("cac:Party");

// عنوان المورد
$postalAddress = $dom->createElement("cac:PostalAddress");
$country = $dom->createElement("cac:Country");
$countryCode = $dom->createElement("cbc:IdentificationCode", "JO");
$country->appendChild($countryCode);
$postalAddress->appendChild($country);
$party->appendChild($postalAddress);

// معلومات ضريبة المورد
$taxScheme = $dom->createElement("cac:PartyTaxScheme");
$companyID = $dom->createElement("cbc:CompanyID", htmlspecialchars($invoice_data['COMP_NO']));
$taxScheme->appendChild($companyID);
$scheme = $dom->createElement("cac:TaxScheme");
$schemeID = $dom->createElement("cbc:ID", "VAT");
$scheme->appendChild($schemeID);
$taxScheme->appendChild($scheme);
$party->appendChild($taxScheme);

// الكيان القانوني للمورد
$legalEntity = $dom->createElement("cac:PartyLegalEntity");
$regName = $dom->createElement("cbc:RegistrationName", $invoice_data['REGISTRATIONNAMEBUYER']);
$legalEntity->appendChild($regName);
$party->appendChild($legalEntity);
$supplier->appendChild($party);
$invoice->appendChild($supplier);

// 8. معلومات العميل (المشتري)
$customer = $dom->createElement("cac:AccountingCustomerParty");
$customerParty = $dom->createElement("cac:Party");

// تعريف العميل
$partyIdentification = $dom->createElement("cac:PartyIdentification");
$partyID = $dom->createElement("cbc:ID", $invoice_data['PARTYIDENTIFICATION']);
$partyID->setAttribute("schemeID",  $invoice_data['SCHEMETYPE']);
$partyIdentification->appendChild($partyID);
$customerParty->appendChild($partyIdentification);
// عنوان العميل
$customerPostalAddress = $dom->createElement("cac:PostalAddress");

// إضافة PostalZone بشكل صحيح
$custPostalZone = $dom->createElement("cbc:PostalZone");

    $custPostalZone->appendChild($dom->createTextNode(htmlspecialchars($invoice_data['COUNTRYSUBENTITYCODE'])));

$customerPostalAddress->appendChild($custPostalZone);

// إضافة CountrySubentityCode
$CountrySubentityCode = $dom->createElement("cbc:CountrySubentityCode", htmlspecialchars($invoice_data['COUNTRYSUBENTITYCODE']));
$customerPostalAddress->appendChild($CountrySubentityCode);

// إنشاء عنصر Country
$custCountry = $dom->createElement("cac:Country");

// إضافة IdentificationCode داخل Country
$custCountryCode = $dom->createElement("cbc:IdentificationCode", "JO");
$custCountry->appendChild($custCountryCode);

// إضافة Country إلى PostalAddress
$customerPostalAddress->appendChild($custCountry);

// إضافة PostalAddress إلى Party
$customerParty->appendChild($customerPostalAddress);

// معلومات ضريبة العميل
$customerTaxScheme = $dom->createElement("cac:PartyTaxScheme");
$customerCompanyID = $dom->createElement("cbc:CompanyID", htmlspecialchars($invoice_data['COMP_NO']));
$customerTaxScheme->appendChild($customerCompanyID);
$customerTaxSchemeType = $dom->createElement("cac:TaxScheme");
$customerTaxSchemeTypeID = $dom->createElement("cbc:ID", "VAT");
$customerTaxSchemeType->appendChild($customerTaxSchemeTypeID);
$customerTaxScheme->appendChild($customerTaxSchemeType);
$customerParty->appendChild($customerTaxScheme);

// الكيان القانوني للعميل
$customerLegalEntity = $dom->createElement("cac:PartyLegalEntity");
$customerRegName = $dom->createElement("cbc:RegistrationName", htmlspecialchars($invoice_data['REGISTRATIONNAMESELLER']));
$customerLegalEntity->appendChild($customerRegName);
$customerParty->appendChild($customerLegalEntity);
$customer->appendChild($customerParty);

// معلومات الاتصال بالعميل
$accountingContact = $dom->createElement("cac:AccountingContact");
$telephone = $dom->createElement("cbc:Telephone", htmlspecialchars($invoice_data['TELEPHONE']));
$accountingContact->appendChild($telephone);
$customer->appendChild($accountingContact);

$invoice->appendChild($customer);

// 9. بائع التاجر (اختياري)
$sellerSupplier = $dom->createElement("cac:SellerSupplierParty");
$sellerParty = $dom->createElement("cac:Party");
$sellerPartyIdentification = $dom->createElement("cac:PartyIdentification");
$sellerID = $dom->createElement("cbc:ID", "262562");
$sellerPartyIdentification->appendChild($sellerID);
$sellerParty->appendChild($sellerPartyIdentification);
$sellerSupplier->appendChild($sellerParty);
$invoice->appendChild($sellerSupplier);

// 10. بدل/رسوم الخصم
$allowanceCharge = $dom->createElement("cac:AllowanceCharge");
$chargeIndicator = $dom->createElement("cbc:ChargeIndicator", "false");
$allowanceCharge->appendChild($chargeIndicator);
$chargeReason = $dom->createElement("cbc:AllowanceChargeReason", "discount");
$allowanceCharge->appendChild($chargeReason);
$chargeAmount = $dom->createElement("cbc:Amount", $invoice_data['TAXAMOUNT']);
$chargeAmount->setAttribute("currencyID", "JO");
$allowanceCharge->appendChild($chargeAmount);
$invoice->appendChild($allowanceCharge);

// 11. إجمالي الضرائب
$taxTotal = $dom->createElement("cac:TaxTotal");
$taxAmount = $dom->createElement("cbc:TaxAmount", $invoice_data['TAXAMOUNT']);
$taxAmount->setAttribute("currencyID", "JO");
$taxTotal->appendChild($taxAmount);
$invoice->appendChild($taxTotal);

// 12. الإجماليات المالية
$monetaryTotal = $dom->createElement("cac:LegalMonetaryTotal");
$taxExclusiveAmount = $dom->createElement("cbc:TaxExclusiveAmount", $invoice_data['TAXEXCLUSIVEAMOUNT']);
$taxExclusiveAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($taxExclusiveAmount);

$taxInclusiveAmount = $dom->createElement("cbc:TaxInclusiveAmount", $invoice_data['TAXINCLUSIVEAMOUNT']);
$taxInclusiveAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($taxInclusiveAmount);

$allowanceTotalAmount = $dom->createElement("cbc:AllowanceTotalAmount", $invoice_data['ALLOWANCETOTALAMOUNT']);
$allowanceTotalAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($allowanceTotalAmount);

$payableAmount = $dom->createElement("cbc:PayableAmount", $invoice_data['PAYABLEAMOUNT']);
$payableAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($payableAmount);

$invoice->appendChild($monetaryTotal);

// 13. بنود الفاتورة
while ($item = oci_fetch_assoc($stidItems)) {
    $invoiceLine = $dom->createElement("cac:InvoiceLine");
    
    $itemID = $dom->createElement("cbc:ID", htmlspecialchars($item['ID']));
    $invoiceLine->appendChild($itemID);

    $quantity = $dom->createElement("cbc:InvoicedQuantity", $item['INVOICEDQUNTITY']);
    $quantity->setAttribute("unitCode", "PCE");
    $invoiceLine->appendChild($quantity);

    $lineAmount = $dom->createElement("cbc:LineExtensionAmount", $item['EXTENSIONAMOUNT']);
    $lineAmount->setAttribute("currencyID", "JO");
    $invoiceLine->appendChild($lineAmount);

    $lineTaxTotal = $dom->createElement("cac:TaxTotal");
    $lineTaxAmount = $dom->createElement("cbc:TaxAmount", $item['TAXAMOUNT']);
    $lineTaxAmount->setAttribute("currencyID", "JO");
    $lineTaxTotal->appendChild($lineTaxAmount);
    
    $lineRoundingAmount = $dom->createElement("cbc:RoundingAmount", $item['ROUNDINGAMOUNT']);
    $lineRoundingAmount->setAttribute("currencyID", "JO");
    $lineTaxTotal->appendChild($lineRoundingAmount);
    
    $taxSubtotal = $dom->createElement("cac:TaxSubtotal");
    $subtotalTaxAmount = $dom->createElement("cbc:TaxAmount", $item['TAXAMOUNT']);
    $subtotalTaxAmount->setAttribute("currencyID", "JO");
    $taxSubtotal->appendChild($subtotalTaxAmount);
    
    $taxCategory = $dom->createElement("cac:TaxCategory");
    $categoryID = $dom->createElement("cbc:ID", $item['TAX_CATEGORY_ID']);
    $categoryID->setAttribute("schemeAgencyID", "6");
    $categoryID->setAttribute("schemeID", "UN/ECE 5305");
    $taxCategory->appendChild($categoryID);
    
    $taxPercent = $dom->createElement("cbc:Percent", $item['PERCENT_TAX']);
    $taxCategory->appendChild($taxPercent);
    
    $taxScheme = $dom->createElement("cac:TaxScheme");
    $taxSchemeID = $dom->createElement("cbc:ID", $item['TAX_CATEGORY_ID']);
    $taxSchemeID->setAttribute("schemeAgencyID", "6");
    $taxSchemeID->setAttribute("schemeID", "UN/ECE 5305");
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
    $priceAmount = $dom->createElement("cbc:PriceAmount", $item['PRICEAMOUNT']);
    $priceAmount->setAttribute("currencyID", "JO");
    $price->appendChild($priceAmount);
    
    $priceAllowanceCharge = $dom->createElement("cac:AllowanceCharge");
    $priceChargeIndicator = $dom->createElement("cbc:ChargeIndicator", "false");
    $priceAllowanceCharge->appendChild($priceChargeIndicator);
    $priceChargeReason = $dom->createElement("cbc:AllowanceChargeReason", "DISCOUNT");
    $priceAllowanceCharge->appendChild($priceChargeReason);
    $priceChargeAmount = $dom->createElement("cbc:Amount", $item['PRICEAMOUNT']);
    $priceChargeAmount->setAttribute("currencyID", "JO");
    $priceAllowanceCharge->appendChild($priceChargeAmount);
    $price->appendChild($priceAllowanceCharge);

    $invoiceLine->appendChild($price);
    $invoice->appendChild($invoiceLine);
}

$xmlContent = $dom->saveXML();
file_put_contents('invoice_debug.xml', $xmlContent); // حفظ XML لأغراض التصحيح

// 2. إعداد بيانات API

$clientId = "b1d7548d-191c-4343-b0f5-0c0e3fa128a3";
$secretKey = "Gj5nS9wyYHRadaVffz5VKB4v4wlVWyPhcJvrTD4NHtPvwBVLwYycGApVuAfyISBNTXd4ce2a7R6Cjvw9hnJs/v/TVHP+JjcBlc+bfPV98sVohXI82ICIhUw/nvnFCmY8eu0OVYvLuKi4RmFk0ayC8GBfX/wNSQUA47VX/aQdSioBr/QGpes2bnyNHuC4rgx90poioCvwi6avMVoUgybHupSoBRYeooSkrvSs6mmgX+m1x62r8DzFDCqQR8hez7gkZAO0r6yD+2dSwEanh+DyJA==";

// 3. إعداد الاتصال باستخدام cURL
$ch = curl_init("https://backend.jofotara.gov.jo/core/invoices/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3000);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // ⚠️ فقط أثناء التطوير
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ⚠️ فقط أثناء التطوير

// 4. تفعيل وضع التصحيح
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// 5. تجهيز الترويسات (بدون كوكيز مبدئيًا)
$headers = [
    "Client-Id: $clientId",
    "Secret-Key: $secretKey",
    "Content-Type: application/json"
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// 6. تفعيل حفظ الكوكيز تلقائيًا
$cookieFile = __DIR__ . '/cookies.txt'; // ملف تخزين الكوكيز
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

// 7. تجهيز البيانات المرسلة
$postData = [
    'invoice' => $xmlContent
];
$jsonData = json_encode($postData, JSON_UNESCAPED_UNICODE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

// 8. تنفيذ الطلب
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// 9. قراءة لوج التصحيح
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

// 10. التعامل مع الاستجابة
if ($response === false) {
    die("فشل الاتصال بـ API: " . curl_error($ch));
} else {
    $responseData = json_decode($response, true);

    // حفظ الاستجابة للفحص
    file_put_contents('api_response_debug.json', json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($httpCode == 200 && isset($responseData['success']) && $responseData['success']) {
        echo "✅ تم إرسال الفاتورة بنجاح.<br>";
    } else {
     //   echo "❌ فشل إرسال الفاتورة.<br>";
    //   echo "💬 رسالة الخطأ: " . htmlspecialchars($responseData['message'] ?? 'Unknown error') . "<br>";
    $dom->appendChild($invoice);

    // إرسال الهيدر المناسب
    header('Content-Type: application/xml; charset=UTF-8');
    
    // طباعة XML
    echo $dom->saveXML();
    }
}

curl_close($ch);
?>