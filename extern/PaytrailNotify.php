<?php

/**
 * Paytrail Instant Payment Notification (IPN).
 * Paytrail calls this php file directly after payment with url 
 * http://domain.example/sites/all/modules/civicrm/extern/PaytrailNotify.php
 *
 * Portions of this file were based off the payment processor extension tutorial at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/Example+of+creating+a+payment+processor+extension
 *
 */

session_start( );

require_once '../civicrm.config.php';

$config = CRM_Core_Config::singleton();
$log = new CRM_Utils_SystemLogger();
$log->alert('payment_notification processor_name=Paytrail', $_REQUEST);

require_once 'PaytrailIPN.php';

try {
    $ipn = com_github_anttikekki_payment_paytrailIPN::main();
}
catch (CRM_Core_Exception $e) {
    CRM_Core_Error::debug_log_message($e->getMessage());
    CRM_Core_Error::debug_var('error data', $e->getErrorData(), TRUE, TRUE);
    CRM_Core_Error::debug_var('REQUEST', $_REQUEST, TRUE, TRUE);
    echo "The transaction has failed. Please review the log for more detail";
}
