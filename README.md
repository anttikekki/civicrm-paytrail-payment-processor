civicrm-paytrail-payment-processor
==================================

[CiviCRM](https://civicrm.org/) Payment Processor for [Paytrail](http://paytrail.com) (formerly Suomen Verkkomaksut). It uses Paytrail [REST API](http://docs.paytrail.com/en/ch04s03.html) and includes Paytrail REST [PHP Module file](http://docs.paytrail.com/files/Verkkomaksut_Module_Rest.php.zip). Default Paytrail API mode is E1. This payment processor support embedded payment buttons /default mode) and full page redirect to Paytrail payment selection page.

This payment processor is only tested with Dupal 7 and CiviCRM 4.4.

#### Version history

- [v1.2](https://github.com/anttikekki/civicrm-paytrail-payment-processor/releases/tag/1.2) Added E1 API mode support and configuration support
- [v1.1](https://github.com/anttikekki/civicrm-paytrail-payment-processor/releases/tag/1.1) Licence change to GNU Affero General Public License
- [v1.0](https://github.com/anttikekki/civicrm-paytrail-payment-processor/releases/tag/1.0) Initial realease

#### Information sent to Paytrail

This payment processor can use S1 or E1 version of [payment API](http://docs.paytrail.com/en/ch05s02.html#idp140474540882720). 

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

3. Configure payment processor in _Administer->Customize->Manage CiviCRM Extensions_. You need to insert `Merchant id` and `Merchant secret` information that Paytrail has provided. URL field can be left blank. To test payment processor you can use test id from [Paytrail docs](http://docs.paytrail.com/en/ch03.html).

#### Licence
GNU Affero General Public License

#### Embedded payment buttons
Embedded payment buttons require product price and unique invoice id (in S1 mode. A lot more info is required in E1 mode). This means that embedded buttons can not be shown in main Contribution or Event page. Buttons are embedded to confirmation page because of this limitation. Confirmation page has to be enabled in Contribution page settings. All other content in confirmation page is removed.

Embedded buttons are enabled by default. Full page redirect to Paytrail payment selection page can be activated in configuration.

#### Configuration
Payment processor can be configured and customized by adding rows to `civicrm_paytrail_payment_processor_config` table. This table has two columns: `config_key` and `config_value`. 

Payment processor has some default values for some options. These defaults are set in [PaytrailConfigHelper.php](https://github.com/anttikekki/civicrm-paytrail-payment-processor/blob/master/PaytrailConfigHelper.php) to `$configDefaults` variable. These defaults are listed in tables below.

Configuration allows to customize data sent to Paytrail in [E1](http://docs.paytrail.com/en/ch05s02.html#idp140474540882720) API mode. Default values for all payments are handy if all required values are not in Contribution page form or Event page form.

Configurations have priority:
1. Values in database
2. Values in Contribution or Event page form
3. Default values in PaytrailConfigHelper.php

This priority order means that database configuration always overwrites other values. If value from database is not found then value from Contribution or Event page is used. PaytrailConfigHelper.php defaults are used only if everything else fails.

##### Common configuration

| Key | Default | Description  |
| ------------- |-------------| -----|
| `apiMode` | `E1` | API mode. `E1` or `S1` |
| `embeddedPaymentButtons` | `true` | Enable embedded payment buttons? `true` for enable, any other value or no row at all for `false` |


##### Contribution page payment default values

| Key | Default | Type [max length] | Required in E1 API | Description  |
| --- |---------| ------------------| ------------------ | ------------ |
| `e1.contribute.value.firstName` | | all characters[64] | Required | Customer first name |
| `e1.contribute.value.lastName` | | all characters[64] | Required | Customer last name |
| `e1.contribute.value.email` | | all characters[255] | Required | Customer email |
| `e1.contribute.value.streetAddress` | | all characters[64] | Required | Customer street address |
| `e1.contribute.value.postalCode` | | all characters[16] | Required | Customer post code |
| `e1.contribute.value.city` | | all characters[64] | Required | Customer city (Postal office) |
| `e1.contribute.value.country` | FI | capital letters[2] | Required | Customer country |
| `e1.contribute.value.telephone` | | all characters[64] | Optional | Customer telephone number |
| `e1.contribute.value.mobile` | | all characters[64] | Optional | Customer mobile number |
| `e1.contribute.value.companyName` | | all characters[128] | Optional | Company name |
| `e1.contribute.value.productQuantity` | 1.00 | number[10] | Required | Product quantity |
| `e1.contribute.value.productVat` | 0.00 | decimal number[10] | Required | Tax (VAT) |
| `e1.contribute.value.productDiscountPercentage` | 0.00 | decimal number[10] | Required | Discount percentage |
| `e1.contribute.value.productType` | 1 | integer[1] | Optional | Product type. 1 for normal products. 2 for posting expences. 3 for handling expences |
| `e1.contribute.value.productCode` | | all characters[16] | Optional | Product code |
| `e1.contribute.value.productPrice` | | decimal number[10] | Required | Products price |


##### Event page payment default values

| Key | Default | Type [max length] | Required in E1 API | Description  |
| --- |---------| ------------------| ------------------ | ------------ |
| `e1.event.value.firstName` | | all characters[64] | Required | Customer first name |
| `e1.event.value.lastName` | | all characters[64] | Required | Customer last name |
| `e1.event.value.email` | | all characters[255] | Required | Customer email |
| `e1.event.value.streetAddress` | | all characters[64] | Required | Customer street address |
| `e1.event.value.postalCode` | | all characters[16] | Required | Customer post code |
| `e1.event.value.city` | | all characters[64] | Required | Customer city (Postal office) |
| `e1.event.value.country` | FI | capital letters[2] | Required | Customer country |
| `e1.event.value.telephone` | | all characters[64] | Optional | Customer telephone number |
| `e1.event.value.mobile` | | all characters[64] | Optional | Customer mobile number |
| `e1.event.value.companyName` | | all characters[128] | Optional | Company name |
| `e1.event.value.productQuantity` | 1.00 | number[10] | Required | Product quantity |
| `e1.event.value.productVat` | 0.00 | decimal number[10] | Required | Tax (VAT) |
| `e1.event.value.productDiscountPercentage` | 0.00 | decimal number[10] | Required | Discount percentage |
| `e1.event.value.productType` | 1 | integer[1] | Optional | Product type. 1 for normal products. 2 for posting expences. 3 for handling expences |
| `e1.event.value.productCode` | | all characters[16] | Optional | Product code |
| `e1.event.value.productPrice` | | decimal number[10] | Required | Products price |


##### Form field names
Form field value retrieval have additional configuration. Name of the input (the name of element in HTML form) that value is fetched can be changed. This is needed if Contribution or Event oage has multiple contact info forms.

| Key | Default form field name | Description  |
| --- | ------------------------| -------------|
| `e1.contribute.field.firstName` | first_name | Customer first name field |
| `e1.contribute.field.lastName` | last_name | Customer last name field |
| `e1.contribute.field.email` | email-5 | Customer email field |
| `e1.contribute.field.streetAddress` | street_address-1 | Customer street address field |
| `e1.contribute.field.postalCode` | postal_code-1 | Customer postal code field |
| `e1.contribute.field.city` | city-1 | Customer city (post office) field |
| `e1.contribute.field.country` | country-1 | Customer country field |
| `e1.contribute.field.productTitle` | item_name | Product title field |
| `e1.contribute.field.productPrice` | amount | Product price field |

| Key | Default form field name | Description  |
| --- | ------------------------| -------------|
| `e1.event.field.firstName` | first_name | Customer first name field |
| `e1.event.field.lastName` | last_name | Customer last name field |
| `e1.event.field.email` | email-5 | Customer email field |
| `e1.event.field.streetAddress` | street_address-1 | Customer street address field |
| `e1.event.field.postalCode` | postal_code-1 | Customer postal code field |
| `e1.event.field.city` | city-1 | Customer city (post office) field |
| `e1.event.field.country` | country-1 | Customer country field |
| `e1.event.field.productTitle` | item_name | Product title field |
| `e1.event.field.productPrice` | amount | Product price field |
