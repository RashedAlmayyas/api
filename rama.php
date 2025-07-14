<?php
$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

// ====== Root Invoice Element with Namespaces ======
$invoice = $doc->createElementNS(
    'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
    'tns:Invoice'
);
$invoice->setAttribute('xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
$invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
$invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$doc->appendChild($invoice);

// ====== UBLExtensions ======
$ubleExtensions = $doc->createElement('ext:UBLExtensions');
$extension = $doc->createElement('ext:UBLExtension');
$extContent = $doc->createElement('ext:ExtensionContent');
// Placeholder for signature (تضع فيه التوقيع لاحقًا)
$extContent->appendChild($doc->createComment('Signature will be placed here'));
$extension->appendChild($extContent);
$ubleExtensions->appendChild($extension);
$invoice->appendChild($ubleExtensions);

// ====== Invoice Header Fields ======
$fields = [
    'cbc:ProfileID' => 'reporting:1.0',
    'cbc:ID' => 'J10012505260604211240801',
    'cbc:UUID' => '257a1900-5bc2-4be5-8e67-32ea00791fb0',
    'cbc:IssueDate' => '2025-05-26',
    'cbc:IssueTime' => '06:04:21'
];
foreach ($fields as $name => $value) {
    $elem = $doc->createElement($name, $value);
    $invoice->appendChild($elem);
}

$invoiceTypeCode = $doc->createElement('cbc:InvoiceTypeCode', '388');
$invoiceTypeCode->setAttribute('name', '0200000');
$invoice->appendChild($invoiceTypeCode);

$invoice->appendChild($doc->createElement('cbc:DocumentCurrencyCode', 'SAR'));
$invoice->appendChild($doc->createElement('cbc:TaxCurrencyCode', 'SAR'));
$invoice->appendChild($doc->createElement('cbc:LineCountNumeric', '1'));

// ====== Supplier Party ======
$supplier = $doc->createElement('cac:AccountingSupplierParty');
$party = $doc->createElement('cac:Party');

$partyId = $doc->createElement('cac:PartyIdentification');
$id = $doc->createElement('cbc:ID', '2050006430');
$id->setAttribute('schemeID', 'CRN');
$partyId->appendChild($id);
$party->appendChild($partyId);

// Address
$address = $doc->createElement('cac:PostalAddress');
$addressFields = [
    'cbc:StreetName' => 'القدس',
    'cbc:BuildingNumber' => '2755',
    'cbc:PlotIdentification' => '7519',
    'cbc:CitySubdivisionName' => 'DAMMAM',
    'cbc:CityName' => 'DAMMAM',
    'cbc:PostalZone' => '32236',
    'cbc:CountrySubentity' => 'SA'
];
foreach ($addressFields as $k => $v) {
    $address->appendChild($doc->createElement($k, $v));
}
$country = $doc->createElement('cac:Country');
$country->appendChild($doc->createElement('cbc:IdentificationCode', 'SA'));
$address->appendChild($country);
$party->appendChild($address);

// Tax Scheme
$taxScheme = $doc->createElement('cac:PartyTaxScheme');
$taxScheme->appendChild($doc->createElement('cbc:CompanyID', '300465395500003'));
$tax = $doc->createElement('cac:TaxScheme');
$tax->appendChild($doc->createElement('cbc:ID', 'VAT'));
$taxScheme->appendChild($tax);
$party->appendChild($taxScheme);

// Legal Entity
$legalEntity = $doc->createElement('cac:PartyLegalEntity');
$legalEntity->appendChild($doc->createElement('cbc:RegistrationName', 'الشركة السعودية للتسويق أسواق المزرعة مساهمة عامة'));
$party->appendChild($legalEntity);

$supplier->appendChild($party);
$invoice->appendChild($supplier);

// ====== Customer Party ======
$customer = $doc->createElement('cac:AccountingCustomerParty');
$customerParty = $doc->createElement('cac:Party');

$custAddress = $doc->createElement('cac:PostalAddress');
$custAddressFields = [
    'cbc:StreetName' => '1234',
    'cbc:BuildingNumber' => '2755',
    'cbc:PlotIdentification' => '7519',
    'cbc:CitySubdivisionName' => 'DAMMAM',
    'cbc:CityName' => 'DAMMAM',
    'cbc:PostalZone' => '32236'
];
foreach ($custAddressFields as $k => $v) {
    $custAddress->appendChild($doc->createElement($k, $v));
}
$custCountry = $doc->createElement('cac:Country');
$custCountry->appendChild($doc->createElement('cbc:IdentificationCode', 'SA'));
$custAddress->appendChild($custCountry);
$customerParty->appendChild($custAddress);

// Customer Tax Scheme
$custTaxScheme = $doc->createElement('cac:PartyTaxScheme');
$custTax = $doc->createElement('cac:TaxScheme');
$custTax->appendChild($doc->createElement('cbc:ID', 'VAT'));
$custTaxScheme->appendChild($custTax);
$customerParty->appendChild($custTaxScheme);

// Legal Entity
$custLegal = $doc->createElement('cac:PartyLegalEntity');
$custLegal->appendChild($doc->createElement('cbc:RegistrationName', 'حسن ال رضوان'));
$customerParty->appendChild($custLegal);

$customer->appendChild($customerParty);
$invoice->appendChild($customer);

// ====== Delivery ======
$delivery = $doc->createElement('cac:Delivery');
$delivery->appendChild($doc->createElement('cbc:ActualDeliveryDate', '2025-05-26'));
$invoice->appendChild($delivery);

// ====== Payment Means ======
$paymentMeans = $doc->createElement('cac:PaymentMeans');
$paymentMeans->appendChild($doc->createElement('cbc:PaymentMeansCode', '42'));
$invoice->appendChild($paymentMeans);

// ====== Tax Total ======
$taxTotal = $doc->createElement('cac:TaxTotal');
$taxTotal->appendChild($doc->createElement('cbc:TaxAmount', '0.91'))->setAttribute('currencyID', 'SAR');

$taxSubTotal = $doc->createElement('cac:TaxSubtotal');
$taxSubTotal->appendChild($doc->createElement('cbc:TaxableAmount', '6.09'))->setAttribute('currencyID', 'SAR');
$taxSubTotal->appendChild($doc->createElement('cbc:TaxAmount', '0.91'))->setAttribute('currencyID', 'SAR');

$taxCategory = $doc->createElement('cac:TaxCategory');
$taxCategory->appendChild($doc->createElement('cbc:ID', 'S'));
$taxCategory->appendChild($doc->createElement('cbc:Percent', '15.00'));

$taxScheme = $doc->createElement('cac:TaxScheme');
$taxScheme->appendChild($doc->createElement('cbc:ID', 'VAT'));
$taxCategory->appendChild($taxScheme);

$taxSubTotal->appendChild($taxCategory);
$taxTotal->appendChild($taxSubTotal);
$invoice->appendChild($taxTotal);

// ====== Legal Monetary Total ======
$legalMonetary = $doc->createElement('cac:LegalMonetaryTotal');
$legalMonetary->appendChild($doc->createElement('cbc:LineExtensionAmount', '6.09'))->setAttribute('currencyID', 'SAR');
$legalMonetary->appendChild($doc->createElement('cbc:TaxExclusiveAmount', '6.09'))->setAttribute('currencyID', 'SAR');
$legalMonetary->appendChild($doc->createElement('cbc:TaxInclusiveAmount', '7.00'))->setAttribute('currencyID', 'SAR');
$legalMonetary->appendChild($doc->createElement('cbc:PayableAmount', '7.00'))->setAttribute('currencyID', 'SAR');
$invoice->appendChild($legalMonetary);

// ====== Invoice Line ======
$invoiceLine = $doc->createElement('cac:InvoiceLine');
$invoiceLine->appendChild($doc->createElement('cbc:ID', '1'));
$invoiceLine->appendChild($doc->createElement('cbc:InvoicedQuantity', '1.00'));
$invoiceLine->appendChild($doc->createElement('cbc:LineExtensionAmount', '6.09'))->setAttribute('currencyID', 'SAR');

$lineTaxTotal = $doc->createElement('cac:TaxTotal');
$lineTaxTotal->appendChild($doc->createElement('cbc:TaxAmount', '0.91'))->setAttribute('currencyID', 'SAR');
$lineTaxTotal->appendChild($doc->createElement('cbc:RoundingAmount', '7.00'))->setAttribute('currencyID', 'SAR');
$invoiceLine->appendChild($lineTaxTotal);

$item = $doc->createElement('cac:Item');
$item->appendChild($doc->createElement('cbc:Name', 'S'));
$classTax = $doc->createElement('cac:ClassifiedTaxCategory');
$classTax->appendChild($doc->createElement('cbc:ID', 'S'));
$classTax->appendChild($doc->createElement('cbc:Percent', '15.00'));
$taxScheme = $doc->createElement('cac:TaxScheme');
$taxScheme->appendChild($doc->createElement('cbc:ID', 'VAT'));
$classTax->appendChild($taxScheme);
$item->appendChild($classTax);
$invoiceLine->appendChild($item);

$price = $doc->createElement('cac:Price');
$price->appendChild($doc->createElement('cbc:PriceAmount', '6.09'))->setAttribute('currencyID', 'SAR');
$invoiceLine->appendChild($price);
$invoice->appendChild($invoiceLine);

// ====== Save XML ======
file_put_contents('full_zatca_invoice.xml', $doc->saveXML());
echo "✅ تم إنشاء ملف XML كامل باسم full_zatca_invoice.xml";
?>
