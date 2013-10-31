civicrm-paytrail-payment-processor
==================================

[CiviCRM](https://civicrm.org/) Payment Processor for [Paytrail](http://paytrail.com) (formerly Suomen Verkkomaksut). 

It uses Paytrail [REST API](http://docs.paytrail.com/en/ch04s03.html) and includes Paytrail REST [PHP Module file](http://docs.paytrail.com/files/Verkkomaksut_Module_Rest.php.zip).

This payment processor only supports type 4 (notify) payment type (more info of all the types from CiviCRM [wiki](http://wiki.civicrm.org/confluence/display/CRMDOC/Create+a+Payment-Processor+Extension)). Browser is redirected to Paytrail site for payment and then redirected back to CiviCRM. No payment info is gathered in CiviCRM. CiviCRM receives notification after succesfull or canceled payment.

This payment processor is only tested with Dupal 7 and CiviCRM 4.4.

#### Version history

- 1.0 Initial realease ([download](https://github.com/anttikekki/civicrm-paytrail-payment-processor/archive/1.0.zip))

#### Information sent to Paytrail

This payment processor uses limited S1 version of payment info API ([example code](http://docs.paytrail.com/en/ch04s03.html#idp769920)). This means only order number (CiviCRM invoice id) and amount is sent to Paytrail. This means there is no information about contribution or event visible in Paytrail admin site other than CiviCRM invoice id.

#### Transaction identification

CiviCRM contribution invoice id is sent to Paytrail as transaction identification ORDER_NUMBER-field. This order id/invoice id is visible in CiviCRM contribution information. This links Paytrail transaction to CiviCRM transaction. 

#### Restrictions

- Paytrail only supports payments in Euros
- Paytrail and this payment processor does not support recurring payments

## Installation

1. Copy `com.github.anttikekki.payment.paytrail` folder to CiviCRM `extension` directory. Extension directory has to be configured in _Administer->Configure->Global Settings->Directories->CiviCRM Extensions Directory_.

2. Copy `PaytrailIPN.php`, `PaytrailNotify.php` and `Verkkomaksut_Module_Rest.php` to CiviCRM `extern` directory. Extern directory is in `[JOOMLA_DIRECTORY]/administrator/components/com_civicrm/civicrm/extern` in Joomla and `[DRUPAL_DIRECTORY]/sites/all/modules/civicrm/extern` in Drupal.

3. Configure payment processor in _Administer->Customize->Manage CiviCRM Extensions_. You need to insert `Merchant id` and `Merchant secret` information that Paytrail has provided. URL field can be left blank. To test payment processor you can use test id from [Paytrail docs](http://docs.paytrail.com/en/ch04s02.html).
