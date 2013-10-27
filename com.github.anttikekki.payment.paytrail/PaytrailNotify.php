<?php

/** 
 * Paytrail IPN.
 *
 * Portions of this file were based off the payment processor extension tutorial at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/Example+of+creating+a+payment+processor+extension
 *
 */

session_start( );

require_once '../civicrm.config.php';
require_once 'CRM/Core/Config.php';

$config = CRM_Core_Config::singleton();

require_once 'PaytrailIPN.php';
com_github_anttikekki_payment_paytrailIPN::main();
