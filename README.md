civicrm-paytrail-payment-processor
==================================

[CiviCRM](https://civicrm.org/) Payment Processor for [Paytrail](http://paytrail.com) (formerly Suomen Verkkomaksut). 

It uses Paytrail [REST API](http://docs.paytrail.com/en/ch04s03.html) and includes Paytrail REST [PHP Module file](http://docs.paytrail.com/files/Verkkomaksut_Module_Rest.php.zip). Default Paytrail API mode is E1.

This payment processor only supports type 4 (notify) payment type (more info of all the types from CiviCRM [wiki](http://wiki.civicrm.org/confluence/display/CRMDOC/Create+a+Payment-Processor+Extension)). Browser is redirected to Paytrail site for payment and then redirected back to CiviCRM. No payment info is gathered in CiviCRM. CiviCRM receives notification after succesfull or canceled payment.

This payment processor is only tested with Dupal 7 and CiviCRM 4.4.

#### Version history

- 1.0 Initial realease ([download](https://github.com/anttikekki/civicrm-paytrail-payment-processor/archive/1.0.zip))
- 1.1 Licence change ([download](https://github.com/anttikekki/civicrm-paytrail-payment-processor/archive/1.1.zip))

#### Information sent to Paytrail

This payment processor can use S1 or E1 version of payment info API ([example code](http://docs.paytrail.com/en/ch04s03.html#idp769920)). 

In S1 version only order number (CiviCRM invoice id) and amount is sent to Paytrail. This means there is no information about contribution or event visible in Paytrail admin site other than CiviCRM invoice id.

In E1 version there is a lot more info that is sent to Paytrail. Required fields by E1 API:
* Order number (CiviCRM invoice id)
* First name
* Last name
* Email
* Street address
* Post code
* Post office
* Country
* Product title
* Product quantity
* Product price
* Tax (VAT)

#### Transaction identification

CiviCRM contribution invoice id is sent to Paytrail as transaction identification ORDER_NUMBER-field. This order id/invoice id is visible in CiviCRM contribution information. This links Paytrail transaction to CiviCRM transaction. 

#### Restrictions

- Paytrail only supports payments in Euros
- Paytrail and this payment processor does not support recurring payments

#### Installation

1. Create `com.github.anttikekki.payment.paytrail` folder to CiviCRM `extension` directory and copy files into it. Extension directory has to be configured in _Administer->Configure->Global Settings->Directories->CiviCRM Extensions Directory_.

2. Copy `PaytrailIPN.php`, `PaytrailNotify.php` and `Verkkomaksut_Module_Rest.php` to CiviCRM `extern` directory. Extern directory is in `[JOOMLA_DIRECTORY]/administrator/components/com_civicrm/civicrm/extern` in Joomla and `[DRUPAL_DIRECTORY]/sites/all/modules/civicrm/extern` in Drupal. There must also be copy of `Verkkomaksut_Module_Rest.php` in `com.github.anttikekki.payment.paytrail` (yep, same file in two places).

3. Configure payment processor in _Administer->Customize->Manage CiviCRM Extensions_. You need to insert `Merchant id` and `Merchant secret` information that Paytrail has provided. URL field can be left blank. To test payment processor you can use test id from [Paytrail docs](http://docs.paytrail.com/en/ch04s02.html).

#### Licence
GNU Affero General Public License

#### Configuration
Payment processor can be configured and customized by adding rows to `civicrm_paytrail_payment_processor_config` table. This table has two columns: `config_key` and `config_value`. 

Payment processor has some default values for some options. These defaults are set in [PaytrailConfigHelper.php](https://github.com/anttikekki/civicrm-paytrail-payment-processor/blob/master/PaytrailConfigHelper.php) to `$configDefaults` variable. These defaults are listed in tables below.

Configuration allows to customize data sent to Paytrail in E1 API mode. Default values for all payments are handy if all required values are not in Contribution page form or Event page form.

**Common configuration**

| Key | Default | Description  |
| ------------- |-------------| -----|
| `apiMode` | `E1` | API mode. `E1` or `S1` |


**Contribution page default field values**

| Key | Default | Type [max length] | Description  |
| --- |---------| ------------------| ------------ |
| `e1.contribute.value.firstName` | | all characters[64] | Customer first name |
| `e1.contribute.value.lastName` | | all characters[64] | Customer last name |
| `e1.contribute.value.email` | | all characters[255] | Customer email |
| `e1.contribute.value.streetAddress` | | all characters[64] | Customer street address |
| `e1.contribute.value.postalCode` | | all characters[16] | Customer post code |
| `e1.contribute.value.city` | | all characters[64] | Customer city (Postal office) |
| `e1.contribute.value.country` | FI | capital letters[2] | Customer country |
| `e1.contribute.value.telephone` | | all characters[64] | Customer telephone number |
| `e1.contribute.value.mobile` | | all characters[64] | Customer mobile number |
| `e1.contribute.value.companyName` | | all characters[128] | Company name |
| `e1.contribute.value.productQuantity` | 1.00 | number[10] | Product quantity |
| `e1.contribute.value.productVat` | 0.00 | decimal number[10] | Tax (VAT) |
| `e1.contribute.value.productDiscountPercentage` | 0.00 | decimal number[10] | Discount percentage |
| `e1.contribute.value.productType` | 1 | integer[1] | Product type. 1 for normal products. 2 for posting expences. 3 for handling expences |
| `e1.contribute.value.productCode` | | all characters[16] | Product code |
| `e1.contribute.value.productPrice` | | decimal number[10] | Products price |
