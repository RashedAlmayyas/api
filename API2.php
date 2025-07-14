<?php
// 1. إعدادات الاتصال بقاعدة البيانات
$connection_string = "192.168.0.10:1521/Orcl2"; 
$username = "WEB_USER";
$password = "WEB_DEV$02";
$conn = oci_connect($username, $password, $connection_string, 'AL32UTF8');

if (!$conn) {
    die(json_encode([
        'status' => 'error',
        'message' => 'فشل الاتصال بقاعدة البيانات: ' . htmlspecialchars(oci_error()['message'])
    ]));
}

// 2. استعلام بيانات الفاتورة الأساسية
$BRANCH_NO = '002'; // تم تصحيحه ليكون نصياً
$INVOICE_ID = '1401202500210814'; // تم إضافة معرف الفاتورة كمتغير

$query = "SELECT * FROM WEB_SALES_TAX_INVOICE_VIEW WHERE BRANCH_NO = :branch_no";
$stid = oci_parse($conn, $query);
oci_bind_by_name($stid, ":branch_no", $BRANCH_NO);
oci_execute($stid);
$invoice_data = oci_fetch_assoc($stid);

if (!$invoice_data) {
    die(json_encode([
        'status' => 'error',
        'message' => 'لم يتم العثور على الفاتورة المطلوبة.'
    ]));
}

// 3. استعلام عناصر الفاتورة
$queryItems = "SELECT * FROM WEB_SALES_TAX_INVOICE_DTL_VIEW WHERE BRANCH_NO = :branch_no AND ID = :invoice_id";
$stidItems = oci_parse($conn, $queryItems);
oci_bind_by_name($stidItems, ":branch_no", $BRANCH_NO);
oci_bind_by_name($stidItems, ":invoice_id", $INVOICE_ID);
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

// 5. إضافة البيانات الأساسية للفاتورة (محدث وفقاً لمتطلبات النظام الأردني)
$cbc = $dom->createElement("cbc:CustomizationID", "urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0");
$invoice->appendChild($cbc);

$profileID = $dom->createElement("cbc:ProfileID", "reporting:1.0");
$invoice->appendChild($profileID);

$id = $dom->createElement("cbc:ID", htmlspecialchars($invoice_data['ID']));
$invoice->appendChild($id);

$uuid = $dom->createElement("cbc:UUID", htmlspecialchars($invoice_data['UUID']));
$invoice->appendChild($uuid);

$issueDate = $dom->createElement("cbc:IssueDate", date('Y-m-d', strtotime($invoice_data['ISSUEDATE'])));
$invoice->appendChild($issueDate);

$issueTime = $dom->createElement("cbc:IssueTime", date('H:i:s', strtotime($invoice_data['ISSUEDATE'])));
$invoice->appendChild($issueTime);

$invoiceTypeCode = $dom->createElement("cbc:InvoiceTypeCode", $invoice_data['INVOICETYPECODE']);
$invoiceTypeCode->setAttribute("name", $invoice_data['PRE_INVOICETYPECODE']);
$invoice->appendChild($invoiceTypeCode);

$note = $dom->createElement("cbc:Note", htmlspecialchars($invoice_data['NOTE']));
$invoice->appendChild($note);

$documentCurrencyCode = $dom->createElement("cbc:DocumentCurrencyCode", "JOD");
$invoice->appendChild($documentCurrencyCode);

$taxCurrencyCode = $dom->createElement("cbc:TaxCurrencyCode", "JOD");
$invoice->appendChild($taxCurrencyCode);

// 6. مرجع المستند الإضافي (محدث)
$additionalDocRef = $dom->createElement("cac:AdditionalDocumentReference");
$docRefID = $dom->createElement("cbc:ID", "ICV");
$additionalDocRef->appendChild($docRefID);
$invoice->appendChild($additionalDocRef);

// 7. معلومات المورد (البائع) - محدث وفقاً للمتطلبات الأردنية
$supplier = $dom->createElement("cac:AccountingSupplierParty");
$party = $dom->createElement("cac:Party");

// تعريف المورد
$partyIdentification = $dom->createElement("cac:PartyIdentification");
$partyID = $dom->createElement("cbc:ID", $invoice_data['COMPANYID']);
$partyID->setAttribute("schemeID", "VAT");
$partyIdentification->appendChild($partyID);
$party->appendChild($partyIdentification);

// عنوان المورد
$postalAddress = $dom->createElement("cac:PostalAddress");
$streetName = $dom->createElement("cbc:StreetName", htmlspecialchars($invoice_data['SELLER_STREET'] ?? ''));
$postalAddress->appendChild($streetName);
$buildingNumber = $dom->createElement("cbc:BuildingNumber", htmlspecialchars($invoice_data['SELLER_BUILDING'] ?? ''));
$postalAddress->appendChild($buildingNumber);
$cityName = $dom->createElement("cbc:CityName", htmlspecialchars($invoice_data['SELLER_CITY'] ?? ''));
$postalAddress->appendChild($cityName);
$postalZone = $dom->createElement("cbc:PostalZone", htmlspecialchars($invoice_data['SELLER_POSTAL_CODE'] ?? ''));
$postalAddress->appendChild($postalZone);

$country = $dom->createElement("cac:Country");
$countryName = $dom->createElement("cbc:Name", "الأردن");
$country->appendChild($countryName);
$countryCode = $dom->createElement("cbc:IdentificationCode", "JO");
$country->appendChild($countryCode);
$postalAddress->appendChild($country);
$party->appendChild($postalAddress);

// معلومات ضريبة المورد
$partyTaxScheme = $dom->createElement("cac:PartyTaxScheme");
$companyID = $dom->createElement("cbc:CompanyID", htmlspecialchars($invoice_data['COMPANYID']));
$partyTaxScheme->appendChild($companyID);
$taxScheme = $dom->createElement("cac:TaxScheme");
$taxSchemeID = $dom->createElement("cbc:ID", "VAT");
$taxScheme->appendChild($taxSchemeID);
$partyTaxScheme->appendChild($taxScheme);
$party->appendChild($partyTaxScheme);

// الكيان القانوني للمورد
$legalEntity = $dom->createElement("cac:PartyLegalEntity");
$regName = $dom->createElement("cbc:RegistrationName", htmlspecialchars($invoice_data['REGISTRATIONNAMESELLER']));
$legalEntity->appendChild($regName);
$party->appendChild($legalEntity);

$supplier->appendChild($party);
$invoice->appendChild($supplier);

// 8. معلومات العميل (المشتري) - محدث
$customer = $dom->createElement("cac:AccountingCustomerParty");
$customerParty = $dom->createElement("cac:Party");

// تعريف العميل
$partyIdentification = $dom->createElement("cac:PartyIdentification");
$partyID = $dom->createElement("cbc:ID", $invoice_data['PARTYIDENTIFICATION']);
$partyID->setAttribute("schemeID", $invoice_data['PARTYIDENTIFICATION_SCHEME'] ?? 'TN');
$partyIdentification->appendChild($partyID);
$customerParty->appendChild($partyIdentification);

// عنوان العميل
$customerPostalAddress = $dom->createElement("cac:PostalAddress");
$custStreetName = $dom->createElement("cbc:StreetName", htmlspecialchars($invoice_data['BUYER_STREET'] ?? ''));
$customerPostalAddress->appendChild($custStreetName);
$custBuildingNumber = $dom->createElement("cbc:BuildingNumber", htmlspecialchars($invoice_data['BUYER_BUILDING'] ?? ''));
$customerPostalAddress->appendChild($custBuildingNumber);
$custCityName = $dom->createElement("cbc:CityName", htmlspecialchars($invoice_data['BUYER_CITY'] ?? ''));
$customerPostalAddress->appendChild($custCityName);
$custPostalZone = $dom->createElement("cbc:PostalZone", htmlspecialchars($invoice_data['POSTALZONE'] ?? ''));
$customerPostalAddress->appendChild($custPostalZone);

$custCountry = $dom->createElement("cac:Country");
$custCountryName = $dom->createElement("cbc:Name", "الأردن");
$custCountry->appendChild($custCountryName);
$custCountryCode = $dom->createElement("cbc:IdentificationCode", "JO");
$custCountry->appendChild($custCountryCode);
$customerPostalAddress->appendChild($custCountry);
$customerParty->appendChild($customerPostalAddress);

// معلومات ضريبة العميل
$customerTaxScheme = $dom->createElement("cac:PartyTaxScheme");
$customerCompanyID = $dom->createElement("cbc:CompanyID", htmlspecialchars($invoice_data['BUYER_COMPANYID'] ?? $invoice_data['COMPANYID']));
$customerTaxScheme->appendChild($customerCompanyID);
$customerTaxSchemeType = $dom->createElement("cac:TaxScheme");
$customerTaxSchemeTypeID = $dom->createElement("cbc:ID", "VAT");
$customerTaxSchemeType->appendChild($customerTaxSchemeTypeID);
$customerTaxScheme->appendChild($customerTaxSchemeType);
$customerParty->appendChild($customerTaxScheme);

// الكيان القانوني للعميل
$customerLegalEntity = $dom->createElement("cac:PartyLegalEntity");
$customerRegName = $dom->createElement("cbc:RegistrationName", htmlspecialchars($invoice_data['REGISTRATIONNAMEBUYER']));
$customerLegalEntity->appendChild($customerRegName);
$customerParty->appendChild($customerLegalEntity);

// معلومات الاتصال بالعميل
if (!empty($invoice_data['TELEPHONE'])) {
    $accountingContact = $dom->createElement("cac:AccountingContact");
    $telephone = $dom->createElement("cbc:Telephone", htmlspecialchars($invoice_data['TELEPHONE']));
    $accountingContact->appendChild($telephone);
    $customerParty->appendChild($accountingContact);
}

$customer->appendChild($customerParty);
$invoice->appendChild($customer);

// 9. بائع التاجر (اختياري) - محدث
if (!empty($invoice_data['SELLER_ID'])) {
    $sellerSupplier = $dom->createElement("cac:SellerSupplierParty");
    $sellerParty = $dom->createElement("cac:Party");
    $sellerPartyIdentification = $dom->createElement("cac:PartyIdentification");
    $sellerID = $dom->createElement("cbc:ID", htmlspecialchars($invoice_data['SELLER_ID']));
    $sellerPartyIdentification->appendChild($sellerID);
    $sellerParty->appendChild($sellerPartyIdentification);
    $sellerSupplier->appendChild($sellerParty);
    $invoice->appendChild($sellerSupplier);
}

// 10. بدل/رسوم الخصم (محدث)
if (!empty($invoice_data['ALLOWANCETOTALAMOUNT']) && floatval($invoice_data['ALLOWANCETOTALAMOUNT']) > 0) {
    $allowanceCharge = $dom->createElement("cac:AllowanceCharge");
    $chargeIndicator = $dom->createElement("cbc:ChargeIndicator", "false");
    $allowanceCharge->appendChild($chargeIndicator);
    $chargeReason = $dom->createElement("cbc:AllowanceChargeReason", htmlspecialchars($invoice_data['ALLOWANCEREASON'] ?? 'discount'));
    $allowanceCharge->appendChild($chargeReason);
    $chargeAmount = $dom->createElement("cbc:Amount", number_format(floatval($invoice_data['ALLOWANCETOTALAMOUNT']), 5, '.', ''));
    $chargeAmount->setAttribute("currencyID", "JOD");
    $allowanceCharge->appendChild($chargeAmount);
    $invoice->appendChild($allowanceCharge);
}

// 11. إجمالي الضرائب (محدث)
$taxTotal = $dom->createElement("cac:TaxTotal");
$taxAmount = $dom->createElement("cbc:TaxAmount", number_format(floatval($invoice_data['TAXAMOUNT']), 5, '.', ''));
$taxAmount->setAttribute("currencyID", "JOD");
$taxTotal->appendChild($taxAmount);

// تفاصيل الضريبة
$taxSubtotal = $dom->createElement("cac:TaxSubtotal");
$taxableAmount = $dom->createElement("cbc:TaxableAmount", number_format(floatval($invoice_data['TAXEXCLUSIVEAMOUNT']), 5, '.', ''));
$taxableAmount->setAttribute("currencyID", "JOD");
$taxSubtotal->appendChild($taxableAmount);

$subtotalTaxAmount = $dom->createElement("cbc:TaxAmount", number_format(floatval($invoice_data['TAXAMOUNT']), 5, '.', ''));
$subtotalTaxAmount->setAttribute("currencyID", "JOD");
$taxSubtotal->appendChild($subtotalTaxAmount);

$taxCategory = $dom->createElement("cac:TaxCategory");
$categoryID = $dom->createElement("cbc:ID", $invoice_data['TAX_CATEGORY_ID'] ?? 'S');
$categoryID->setAttribute("schemeAgencyID", "6");
$categoryID->setAttribute("schemeID", "UN/ECE 5305");
$taxCategory->appendChild($categoryID);

$taxPercent = $dom->createElement("cbc:Percent", $invoice_data['PERCENT_TAX'] ?? '16');
$taxCategory->appendChild($taxPercent);

$taxScheme = $dom->createElement("cac:TaxScheme");
$taxSchemeID = $dom->createElement("cbc:ID", "VAT");
$taxScheme->appendChild($taxSchemeID);
$taxCategory->appendChild($taxScheme);

$taxSubtotal->appendChild($taxCategory);
$taxTotal->appendChild($taxSubtotal);
$invoice->appendChild($taxTotal);

// 12. الإجماليات المالية (محدث)
$monetaryTotal = $dom->createElement("cac:LegalMonetaryTotal");
$lineExtensionAmount = $dom->createElement("cbc:LineExtensionAmount", number_format(floatval($invoice_data['LINE_EXTENSION_AMOUNT'] ?? $invoice_data['TAXEXCLUSIVEAMOUNT']), 5, '.', ''));
$lineExtensionAmount->setAttribute("currencyID", "JOD");
$monetaryTotal->appendChild($lineExtensionAmount);

$taxExclusiveAmount = $dom->createElement("cbc:TaxExclusiveAmount", number_format(floatval($invoice_data['TAXEXCLUSIVEAMOUNT']), 5, '.', ''));
$taxExclusiveAmount->setAttribute("currencyID", "JOD");
$monetaryTotal->appendChild($taxExclusiveAmount);

$taxInclusiveAmount = $dom->createElement("cbc:TaxInclusiveAmount", number_format(floatval($invoice_data['TAXINCLUSIVEAMOUNT']), 5, '.', ''));
$taxInclusiveAmount->setAttribute("currencyID", "JOD");
$monetaryTotal->appendChild($taxInclusiveAmount);

$allowanceTotalAmount = $dom->createElement("cbc:AllowanceTotalAmount", number_format(floatval($invoice_data['ALLOWANCETOTALAMOUNT'] ?? 0), 5, '.', ''));
$allowanceTotalAmount->setAttribute("currencyID", "JOD");
$monetaryTotal->appendChild($allowanceTotalAmount);

$payableAmount = $dom->createElement("cbc:PayableAmount", number_format(floatval($invoice_data['PAYABLEAMOUNT']), 5, '.', ''));
$payableAmount->setAttribute("currencyID", "JOD");
$monetaryTotal->appendChild($payableAmount);

$invoice->appendChild($monetaryTotal);

// 13. بنود الفاتورة (محدثة)
$lineNumber = 1;
while ($item = oci_fetch_assoc($stidItems)) {
    $invoiceLine = $dom->createElement("cac:InvoiceLine");
    
    $lineID = $dom->createElement("cbc:ID", $lineNumber++);
    $invoiceLine->appendChild($lineID);

    $quantity = $dom->createElement("cbc:InvoicedQuantity", number_format(floatval($item['INVOICEDQUNTITY']), 2, '.', ''));
    $quantity->setAttribute("unitCode", $item['UNITCODE'] ?? 'PCE');
    $invoiceLine->appendChild($quantity);

    $lineAmount = $dom->createElement("cbc:LineExtensionAmount", number_format(floatval($item['EXTENSIONAMOUNT']), 5, '.', ''));
    $lineAmount->setAttribute("currencyID", "JOD");
    $invoiceLine->appendChild($lineAmount);

    // معلومات الضريبة للبند
    $taxTotal = $dom->createElement("cac:TaxTotal");
    $taxAmount = $dom->createElement("cbc:TaxAmount", number_format(floatval($item['TAXAMOUNT']), 5, '.', ''));
    $taxAmount->setAttribute("currencyID", "JOD");
    $taxTotal->appendChild($taxAmount);
    
    $taxSubtotal = $dom->createElement("cac:TaxSubtotal");
    $taxableAmount = $dom->createElement("cbc:TaxableAmount", number_format(floatval($item['EXTENSIONAMOUNT']), 5, '.', ''));
    $taxableAmount->setAttribute("currencyID", "JOD");
    $taxSubtotal->appendChild($taxableAmount);
    
    $taxAmount = $dom->createElement("cbc:TaxAmount", number_format(floatval($item['TAXAMOUNT']), 5, '.', ''));
    $taxAmount->setAttribute("currencyID", "JOD");
    $taxSubtotal->appendChild($taxAmount);
    
    $taxCategory = $dom->createElement("cac:TaxCategory");
    $categoryID = $dom->createElement("cbc:ID", $item['TAX_CATEGORY_ID'] ?? 'S');
    $categoryID->setAttribute("schemeAgencyID", "6");
    $categoryID->setAttribute("schemeID", "UN/ECE 5305");
    $taxCategory->appendChild($categoryID);
    
    $taxPercent = $dom->createElement("cbc:Percent", $item['PERCENT_TAX'] ?? '16');
    $taxCategory->appendChild($taxPercent);
    
    $taxScheme = $dom->createElement("cac:TaxScheme");
    $taxSchemeID = $dom->createElement("cbc:ID", "VAT");
    $taxScheme->appendChild($taxSchemeID);
    $taxCategory->appendChild($taxScheme);
    
    $taxSubtotal->appendChild($taxCategory);
    $taxTotal->appendChild($taxSubtotal);
    $invoiceLine->appendChild($taxTotal);

    // معلومات الصنف
    $itemElement = $dom->createElement("cac:Item");
    $itemName = $dom->createElement("cbc:Name", htmlspecialchars($item['ITEMNAME']));
    $itemElement->appendChild($itemName);
    
    // تصنيف الصنف
    $classifiedTaxCategory = $dom->createElement("cac:ClassifiedTaxCategory");
    $classCategoryID = $dom->createElement("cbc:ID", $item['TAX_CATEGORY_ID'] ?? 'S');
    $classCategoryID->setAttribute("schemeAgencyID", "6");
    $classCategoryID->setAttribute("schemeID", "UN/ECE 5305");
    $classifiedTaxCategory->appendChild($classCategoryID);
    
    $taxPercent = $dom->createElement("cbc:Percent", $item['PERCENT_TAX'] ?? '16');
    $classifiedTaxCategory->appendChild($taxPercent);
    
    $taxScheme = $dom->createElement("cac:TaxScheme");
    $taxSchemeID = $dom->createElement("cbc:ID", "VAT");
    $taxScheme->appendChild($taxSchemeID);
    $classifiedTaxCategory->appendChild($taxScheme);
    
    $itemElement->appendChild($classifiedTaxCategory);
    $invoiceLine->appendChild($itemElement);
    
    // السعر
    $price = $dom->createElement("cac:Price");
    $priceAmount = $dom->createElement("cbc:PriceAmount", number_format(floatval($item['PRICEAMOUNT']), 5, '.', ''));
    $priceAmount->setAttribute("currencyID", "JOD");
    $price->appendChild($priceAmount);
    
    $invoiceLine->appendChild($price);
    $invoice->appendChild($invoiceLine);
}

$dom->appendChild($invoice);
$xmlContent = $dom->saveXML();

// التحقق من صحة XML قبل الإرسال
if (!$xmlContent) {
    die(json_encode([
        'status' => 'error',
        'message' => 'فشل في إنشاء مستند XML'
    ]));
}

// حفظ XML لأغراض التصحيح
file_put_contents('invoice_'.date('YmdHis').'.xml', $xmlContent);

// 2. إعداد بيانات API
$clientId = "b1d7548d-1";
$secretKey = "Gj5nS9wyYH";

// 3. إعداد الاتصال باستخدام cURL
$ch = curl_init("https://backend.jofotara.gov.jo/core/invoices/");
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// إذا كنت بحاجة لإرسال بيانات POST
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoice_data));
// 4. تفعيل وضع التصحيح
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// 5. تجهيز الترويسات
$headers = [
    "Client-Id: $clientId",
    "Secret-Key: $secretKey",
    "Content-Type: application/xml",
    "Accept: application/json"
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// 6. تفعيل حفظ الكوكيز تلقائيًا
$cookieFile = __DIR__ . '/cookies.txt';
if (!file_exists($cookieFile)) {
    file_put_contents($cookieFile, '');
}
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

// 7. تجهيز البيانات المرسلة (تم التغيير لإرسال XML مباشرة)
curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlContent);

// 8. تنفيذ الطلب
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// 9. قراءة لوج التصحيح
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
file_put_contents('curl_debug_'.date('YmdHis').'.log', $verboseLog);
fclose($verbose);

// 10. التعامل مع الاستجابة
if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    die(json_encode([
        'status' => 'error',
        'message' => 'فشل الاتصال بـ API: ' . $error
    ]));
}

$responseData = json_decode($response, true);

// حفظ الاستجابة للفحص
file_put_contents('api_response_'.date('YmdHis').'.json', json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

curl_close($ch);

// 11. تحليل الاستجابة
if ($httpCode == 200) {
    if (isset($responseData['success']) && $responseData['success']) {
        echo json_encode([
            'status' => 'success',
            'message' => 'تم إرسال الفاتورة بنجاح',
            'qrCode' => $responseData['qrCode'] ?? '',
            'invoiceNumber' => $responseData['invoiceNumber'] ?? '',
            'uuid' => $responseData['uuid'] ?? ''
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'فشل إرسال الفاتورة: ' . ($responseData['message'] ?? 'Unknown error'),
            'details' => $responseData['errors'] ?? []
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'فشل إرسال الفاتورة. رمز الحالة: ' . $httpCode,
        'details' => $responseData['message'] ?? $response
    ], JSON_UNESCAPED_UNICODE);
}

// تنظيف الموارد
oci_free_statement($stid);
oci_free_statement($stidItems);
oci_close($conn);
?>