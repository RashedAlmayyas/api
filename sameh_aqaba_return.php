<?php
require_once 'phpqrcode/qrlib.php'; 
$connection_string = "192.168.0.10:1521/ORCL2"; 
$username = "WEB_USER";
$password = "WEB_DEV$02";
$conn = oci_connect($username, $password, $connection_string, 'AL32UTF8'); 

if (!$conn) {
    die("فشل الاتصال بقاعدة البيانات: " . htmlspecialchars(oci_error()['message']));
}
   function formatAmount($value) {
        return number_format((float)$value, 9, '.', '');
    }$query = "SELECT * FROM WEB_SALES_TAX_INVOICE_VIEW WHERE COMP_NO=5 ORDER BY ID";
$stid = oci_parse($conn, $query);
oci_execute($stid);

while ($invoice_data = oci_fetch_assoc($stid)) {
    $invoice_data = array_map('trim', $invoice_data);

    $header_id = $invoice_data['ID'];

    $queryItems = "SELECT * FROM WEB_SALES_TAX_INVOICE_DTL_VIEW WHERE COMP_NO=5 AND HEADER_ID = :header_id";
    $item_stmt = oci_parse($conn, $queryItems);
    oci_bind_by_name($item_stmt, ":header_id", $header_id);
    oci_execute($item_stmt);
  $dom = new DOMDocument("1.0", "UTF-8");
$dom->formatOutput = true;
	
$invoice = $dom->createElement("Invoice");
$invoice->setAttribute("xmlns", "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2");
$invoice->setAttribute("xmlns:cac", "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");
$invoice->setAttribute("xmlns:cbc", "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
$invoice->setAttribute("xmlns:ext", "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2");

$invoice->appendChild($dom->createElement("cbc:ProfileID", "reporting:1.0"));
$invoice->appendChild($dom->createElement("cbc:ID", htmlspecialchars($invoice_data['ID'])));
$invoice->appendChild($dom->createElement("cbc:UUID", htmlspecialchars($invoice_data['UUID'])));
$invoice->appendChild($dom->createElement("cbc:IssueDate", date('Y-m-d', strtotime($invoice_data['ISSUEDATE']))));

$invoiceTypeCode = $dom->createElement("cbc:InvoiceTypeCode", $invoice_data['INVOICETYPECODE']);
$invoiceTypeCode->setAttribute("name", $invoice_data['PRE_INVOICETYPECODE']);
$invoice->appendChild($invoiceTypeCode);

$invoice->appendChild($dom->createElement("cbc:Note", htmlspecialchars($invoice_data['NOTE'])));
$invoice->appendChild($dom->createElement("cbc:DocumentCurrencyCode", "JOD"));
$invoice->appendChild($dom->createElement("cbc:TaxCurrencyCode", "JOD"));

$billingReference = $dom->createElement("cac:BillingReference");
$invoiceDocumentReference = $dom->createElement("cac:InvoiceDocumentReference");
$invoiceDocumentReference->appendChild($dom->createElement("cbc:ID", htmlspecialchars($invoice_data['REF_ID'] ?? "")));
$invoiceDocumentReference->appendChild($dom->createElement("cbc:UUID", htmlspecialchars($invoice_data['REF_UUID'] ?? "")));
$invoiceDocumentReference->appendChild($dom->createElement("cbc:DocumentDescription", htmlspecialchars($invoice_data['BILLING_DESCRIPTION'] ?? "950.0002")));
$billingReference->appendChild($invoiceDocumentReference);
$invoice->appendChild($billingReference);

$additionalDocRef = $dom->createElement("cac:AdditionalDocumentReference");
$additionalDocRef->appendChild($dom->createElement("cbc:ID", "ICV"));
$additionalDocRef->appendChild($dom->createElement("cbc:UUID", htmlspecialchars($invoice_data['UUID'])));
$invoice->appendChild($additionalDocRef);

$supplier = $dom->createElement("cac:AccountingSupplierParty");
$party = $dom->createElement("cac:Party");
$postalAddress = $dom->createElement("cac:PostalAddress");
$country = $dom->createElement("cac:Country");
$country->appendChild($dom->createElement("cbc:IdentificationCode", "JO"));
$postalAddress->appendChild($country);
$party->appendChild($postalAddress);

$taxScheme = $dom->createElement("cac:PartyTaxScheme");
$taxScheme->appendChild($dom->createElement("cbc:CompanyID", htmlspecialchars($invoice_data['COMPANYID'])));
$taxSchemeType = $dom->createElement("cac:TaxScheme");
$taxSchemeType->appendChild($dom->createElement("cbc:ID", "VAT"));
$taxScheme->appendChild($taxSchemeType);
$party->appendChild($taxScheme);

$legalEntity = $dom->createElement("cac:PartyLegalEntity");
$legalEntity->appendChild($dom->createElement("cbc:RegistrationName", htmlspecialchars($invoice_data['REGISTRATIONNAMEBUYER'])));
$party->appendChild($legalEntity);

$supplier->appendChild($party);
$invoice->appendChild($supplier);

$customer = $dom->createElement("cac:AccountingCustomerParty");
$customerParty = $dom->createElement("cac:Party");
$postalAddress = $dom->createElement("cac:PostalAddress");
$country = $dom->createElement("cac:Country");
$country->appendChild($dom->createElement("cbc:IdentificationCode", "JO"));
$postalAddress->appendChild($country);
$customerParty->appendChild($postalAddress);

$taxScheme = $dom->createElement("cac:PartyTaxScheme");
$taxSchemeType = $dom->createElement("cac:TaxScheme");
$taxSchemeType->appendChild($dom->createElement("cbc:ID", "VAT"));
$taxScheme->appendChild($taxSchemeType);
$customerParty->appendChild($taxScheme);

$legalEntity = $dom->createElement("cac:PartyLegalEntity");
$regName = htmlspecialchars($invoice_data['REGISTRATIONNAMESELLER'] ?? '');
if (!empty($regName)) {
    $legalEntity->appendChild($dom->createElement("cbc:RegistrationName", $regName));
}
$customerParty->appendChild($legalEntity);

$customer->appendChild($customerParty);
$invoice->appendChild($customer);

$sellerSupplier = $dom->createElement("cac:SellerSupplierParty");
$sellerParty = $dom->createElement("cac:Party");
$partyIdentification = $dom->createElement("cac:PartyIdentification");
$partyIdentification->appendChild($dom->createElement("cbc:ID", "15098907")); 
$sellerParty->appendChild($partyIdentification);
$sellerSupplier->appendChild($sellerParty);
$invoice->appendChild($sellerSupplier);

$paymentMeans = $dom->createElement("cac:PaymentMeans");
$paymentMeansCode = $dom->createElement("cbc:PaymentMeansCode", "10");
$paymentMeansCode->setAttribute("listID", "UN/ECE 4461");
$paymentMeans->appendChild($paymentMeansCode);
$instructionNote = $dom->createElement("cbc:InstructionNote", htmlspecialchars($invoice_data['INSTRUCTION_NOTE'] ?? "change price"));
$paymentMeans->appendChild($instructionNote);
$invoice->appendChild($paymentMeans);

// AllowanceCharge
$allowanceCharge = $dom->createElement("cac:AllowanceCharge");
$allowanceCharge->appendChild($dom->createElement("cbc:ChargeIndicator", "false"));
$allowanceCharge->appendChild($dom->createElement("cbc:AllowanceChargeReason", "discount"));
$amount = $dom->createElement("cbc:Amount", formatAmount($invoice_data['DISCOUNTAMOUNT']));
$amount->setAttribute("currencyID", "JO");
$allowanceCharge->appendChild($amount);
$invoice->appendChild($allowanceCharge);

// TaxTotal1
 $TaxTotal = $dom->createElement("cac:TaxTotal");

// عنصر TaxAmount الأساسي
$taxAmount = $dom->createElement("cbc:TaxAmount", htmlspecialchars($invoice_data['TAXAMOUNT']));
$taxAmount->setAttribute("currencyID", "JO");
$TaxTotal->appendChild($taxAmount);

// عنصر TaxSubtotal
$TaxSubtotal = $dom->createElement("cac:TaxSubtotal");

// TaxableAmount
$TaxableAmount = $dom->createElement("cbc:TaxableAmount", htmlspecialchars($invoice_data['TAXAMOUNT']));
$TaxableAmount->setAttribute("currencyID", "JO");
$TaxSubtotal->appendChild($TaxableAmount);

// TaxAmount داخل TaxSubtotal
$TaxAmount = $dom->createElement("cbc:TaxAmount", htmlspecialchars($invoice_data['TAXAMOUNT']));
$TaxAmount->setAttribute("currencyID", "JO");
$TaxSubtotal->appendChild($TaxAmount);

// TaxCategory
$TaxCategory = $dom->createElement("cac:TaxCategory");

// ID
$categoryID = $dom->createElement("cbc:ID", $item_data['TAX_CATEGORY_ID'] ?? 'S');
$categoryID->setAttribute("schemeID", "UN/ECE 5305");
$categoryID->setAttribute("schemeAgencyID", "6");
$TaxCategory->appendChild($categoryID);

// Percent
$taxPercent = $dom->createElement("cbc:Percent", "8");
$TaxCategory->appendChild($taxPercent);

// TaxScheme
$TaxScheme = $dom->createElement("cac:TaxScheme");
$TaxSchemeID = $dom->createElement("cbc:ID", "VAT");
$TaxSchemeID->setAttribute("schemeID", "UN/ECE 5153");
$TaxSchemeID->setAttribute("schemeAgencyID", "6");
$TaxScheme->appendChild($TaxSchemeID);

// ربط العناصر ببعضها
$TaxCategory->appendChild($TaxScheme);
$TaxSubtotal->appendChild($TaxCategory);
$TaxTotal->appendChild($TaxSubtotal);

$invoice->appendChild($TaxTotal);

// LegalMonetaryTotal
$monetaryTotal = $dom->createElement("cac:LegalMonetaryTotal");

$taxExclusiveAmount = $dom->createElement("cbc:TaxExclusiveAmount", formatAmount($invoice_data['TAXEXCLUSIVEAMOUNT']));
$taxExclusiveAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($taxExclusiveAmount);

$taxInclusiveAmount = $dom->createElement("cbc:TaxInclusiveAmount", formatAmount($invoice_data['TAXINCLUSIVEAMOUNT']));
$taxInclusiveAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($taxInclusiveAmount);

$allowanceTotalAmount = $dom->createElement("cbc:AllowanceTotalAmount", formatAmount($invoice_data['ALLOWANCETOTALAMOUNT']));
$allowanceTotalAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($allowanceTotalAmount);

$PrepaidAmount = $dom->createElement("cbc:PrepaidAmount","0");
$PrepaidAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($PrepaidAmount);

$payableAmount = $dom->createElement("cbc:PayableAmount", formatAmount($invoice_data['PAYABLEAMOUNT']));
$payableAmount->setAttribute("currencyID", "JO");
$monetaryTotal->appendChild($payableAmount);

$invoice->appendChild($monetaryTotal);

    while ($item_data = oci_fetch_assoc($item_stmt)) {
        $invoiceLine = $dom->createElement("cac:InvoiceLine");
        $itemID = $dom->createElement("cbc:ID", htmlspecialchars($item_data['ID']));
        $invoiceLine->appendChild($itemID);
        $quantity = $dom->createElement("cbc:InvoicedQuantity", formatAmount($item_data['INVOICEDQUNTITY']));
        $quantity->setAttribute("unitCode", "PCE");
        $invoiceLine->appendChild($quantity);
        $lineAmount = $dom->createElement("cbc:LineExtensionAmount",formatAmount($item_data['EXTENSIONAMOUNT']));
        $lineAmount->setAttribute("currencyID", "JO");
        $invoiceLine->appendChild($lineAmount);
        $lineTaxTotal = $dom->createElement("cac:TaxTotal");
        $lineTaxAmount = $dom->createElement("cbc:TaxAmount", formatAmount($item_data['TAXAMOUNT']));
        $lineTaxAmount->setAttribute("currencyID", "JO");
        $lineTaxTotal->appendChild($lineTaxAmount);
        $lineRoundingAmount = $dom->createElement("cbc:RoundingAmount",formatAmount($item_data['ROUNDINGAMOUNT']));
        $lineRoundingAmount->setAttribute("currencyID", "JO");
        $lineTaxTotal->appendChild($lineRoundingAmount);
        $taxSubtotal = $dom->createElement("cac:TaxSubtotal");
        $subtotalTaxAmount = $dom->createElement("cbc:TaxAmount", formatAmount($item_data['TAXAMOUNT']));
        $subtotalTaxAmount->setAttribute("currencyID", "JO");
        $taxSubtotal->appendChild($subtotalTaxAmount);      
        $taxCategory = $dom->createElement("cac:TaxCategory");
        $categoryID = $dom->createElement("cbc:ID", $item_data['TAX_CATEGORY_ID']);
        $categoryID->setAttribute("schemeAgencyID", "6");
        $categoryID->setAttribute("schemeID", "UN/ECE 5305");
        $taxCategory->appendChild($categoryID);
        $taxPercent = $dom->createElement("cbc:Percent", $item_data['PERCENT_TAX']);
        $taxCategory->appendChild($taxPercent);
        $taxScheme = $dom->createElement("cac:TaxScheme");
        $taxSchemeID = $dom->createElement("cbc:ID","VAT");
        $taxSchemeID->setAttribute("schemeAgencyID", "6");
        $taxSchemeID->setAttribute("schemeID", "UN/ECE 5153");
        $taxScheme->appendChild($taxSchemeID);
        $taxCategory->appendChild($taxScheme);
        $taxSubtotal->appendChild($taxCategory);
        $lineTaxTotal->appendChild($taxSubtotal);
        $invoiceLine->appendChild($lineTaxTotal);
        $itemElement = $dom->createElement("cac:Item");
//$itemName = $dom->createElement("cbc:Name", htmlspecialchars($item_data['ITEMNAME'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        $itemName = $dom->createElement("cbc:Name",$item_data['ITEMNAME']);

        $itemElement->appendChild($itemName);
        $invoiceLine->appendChild($itemElement);
        $price = $dom->createElement("cac:Price");
        $priceAmount = $dom->createElement("cbc:PriceAmount", formatAmount($item_data['PRICEAMOUNT']));
        $priceAmount->setAttribute("currencyID", "JO");
        $price->appendChild($priceAmount);
        $priceAllowanceCharge = $dom->createElement("cac:AllowanceCharge");
        $priceChargeIndicator = $dom->createElement("cbc:ChargeIndicator", "false");
        $priceAllowanceCharge->appendChild($priceChargeIndicator);
        $priceChargeReason = $dom->createElement("cbc:AllowanceChargeReason", "DISCOUNT");
        $priceAllowanceCharge->appendChild($priceChargeReason);
        $priceChargeAmount = $dom->createElement("cbc:Amount",formatAmount($item_data['ALLOWANCECHARGERESONE']));
        $priceChargeAmount->setAttribute("currencyID", "JO");
        $priceAllowanceCharge->appendChild($priceChargeAmount);
        $price->appendChild($priceAllowanceCharge);
        $invoiceLine->appendChild($price);
        $invoice->appendChild($invoiceLine);
    }
    $dom->appendChild($invoice);
    $xmlContent = $dom->saveXML();
    $filename = "invoice_" . $invoice_data['ID'] . ".xml";
    if (!file_exists('invoices')) {
        mkdir('invoices', 0777, true);
    }
    
    $dom->save("invoices/" . $filename);
    $encodedXmlContent = base64_encode($xmlContent); 
    $clientId = "1ff902fb-8abf-4660-8e9a-73f2be8f6bde";
    $secretKey = "Gj5nS9wyYHRadaVffz5VKB4v4wlVWyPhcJvrTD4NHtObQEUXWiz0VOtB7O4Vbi8QADot1jaji73yhyfULV9LQ33ilbRfS9MH/s9ni3+npaeiXX9H6xaXSxdBxOh6z76M4PUHA5DNpKlwt7rd4vFM39cGiPgeuc1Qx/P3FECCSpn81nng0Sh+XzpHFiH3vDcB5u3oyysLuZRIvSsF71w0jHgqG2atWP6/9rw8PSIdjdd1faUFLn0v2pQK7wo89klR4eOtBHmwzvMv5HF10f4Uvw==";
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
    if ($response === false) {
        $error_message = "❌ فشل الاتصال بـ API: " . curl_error($ch);
    } else {
          $responseData = json_decode($response, true);

$jsonContent = file_get_contents('api_response_debug.json');
if ($jsonContent === false) {
    die("فشل في قراءة ملف JSON.");
}

$transId = $invoice_data['TRANS_ID']; 
$clob = oci_new_descriptor($conn, OCI_D_LOB);
if (!$clob) {
    die("فشل في إنشاء CLOB Descriptor");
}

$sql5 = "BEGIN ERP.WEB_PKG.INSERT_API_TAX_INV_AUDIT(:P_TRANS_ID, :P_API_NOTE); END;";
$stid5 = oci_parse($conn, $sql5);

oci_bind_by_name($stid5, ":P_TRANS_ID", $transId);
oci_bind_by_name($stid5, ":P_API_NOTE", $clob, -1, OCI_B_CLOB);

$clob->writeTemporary($jsonContent, OCI_TEMP_CLOB);

if (oci_execute($stid5, OCI_DEFAULT)) {
    oci_commit($conn);
} else {
    $e = oci_error($stid5);
    echo "❌ خطأ في التنفيذ: " . $e['message'];
}

$clob->free();
oci_free_statement($stid5);  if (
            isset($responseData['EINV_RESULTS']['status']) && 
            $responseData['EINV_RESULTS']['status'] === 'PASS' &&
            isset($responseData['EINV_STATUS']) &&
            (
                $responseData['EINV_STATUS'] === 'SUBMITTED' ||
                $responseData['EINV_STATUS'] === 'ALREADY_SUBMITTED'
            )
        ) {
            $success_message = "✅ تم إرسال الفاتورة بنجاح.";
            $qrValue1 = isset($responseData['EINV_QR']) ? $responseData['EINV_QR'] : '';
            $db_success_message = "✅ تم تحديث حالة الفاتورة في قاعدة البيانات بنجاح.";
           
                if (!empty($qrValue1) && !empty($invoice_data['ID'])) {
                $invoiceId = $invoice_data['ID'];
                $tempPngPath = '//192.168.0.7/Invoice/tax_aqaba/' . $invoiceId . '.png';
                $finalJpgPath = '//192.168.0.7/Invoice/tax_aqaba/' . $invoiceId . '.jpg';
                QRcode::png($qrValue1, $tempPngPath, QR_ECLEVEL_H, 5);
                $image = imagecreatefrompng($tempPngPath);
                if ($image !== false) {
                    imagejpeg($image, $finalJpgPath, 100); 
                    imagedestroy($image);
            
                   
                    unlink($tempPngPath);
            
                    echo "✅ تم حفظ QR بصيغة JPG: $finalJpgPath";
                } else {
                    echo "❌ فشل في تحميل صورة PNG المؤقتة.";
                }
            }
           
           $sql = 'BEGIN ERP.WEB_PKG.UPDATE_TAX_INVOICE_API_FLAG(:P_TRANS_ID); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':P_TRANS_ID', $transId);
            if (oci_execute($stmt)) {
            } else {
                $e = oci_error($stmt);
                echo "❌ woring " . htmlspecialchars($e['message']);
            }
            oci_free_statement($stmt);
        } else {
            echo "❌ error";
        }
    }
    curl_close($ch);
      };
    oci_close($conn);
    ?>