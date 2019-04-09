<?php

use CRM_Paytrail_ExtensionUtil as E;

require_once(CRM_Paytrail_ExtensionUtil::path("vendor/Paytrail_Module_Rest.php"));

require_once 'CRM/Core/Payment.php';
require_once 'PaytrailConfigHelper.php';
require_once 'PaytrailPaymentHelper.php';

class CRM_Core_Payment_Paytrail extends CRM_Core_Payment {
  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * Mode of operation: live or test
   *
   * @var string
   */
  protected $_mode = null;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   * @param object $paymentProcessor the details of the payment processor being invoked
   */
  function __construct( $mode, &$paymentProcessor ) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('Paytrail');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string  $mode the mode of operation: live or test
   * @param object  $paymentProcessor the details of the payment processor being invoked
   * @param object  $paymentForm      reference to the form object if available
   * @param boolean $force            should we force a reload of this payment object
   *
   * @return object
   */
  static function &singleton($mode = 'test', &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === null ) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_Paytrail($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }
  

  /**
   * This function checks to see if we have the right config values
   *
   * @param  string $mode the mode we are operating in (live or test)
   *
   * @return string the error message if any
   */
  public function checkConfig( ) {
    $config = CRM_Core_Config::singleton();
    $error = array();
    
    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('The "Merchant ID" is not set in the Administer CiviCRM Payment Processor.');
    }
    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('The "Merchant secret" is not set in the Administer CiviCRM Payment Processor.');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    } else {
      return NULL;
    }
  }

  /**
   * This function collects all the information from a web/api form and invokes
   * the relevant payment processor specific functions to perform the transaction
   *
   * @param  array $params assoc array of input parameters for this transaction
   *
   * @return array the result in an nice formatted array (or an error object)
   */
  public function doDirectPayment(&$params) {
    CRM_Core_Error::fatal(ts('doDirectPayment() function is not implemented'));
  }

  /**
   * Sets appropriate parameters for checking out to Paytrail. Parameters can be customized with PaytrailConfigHelper.
   *
   * @param array $params name value pair of form data
   * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
   */
  public function doTransferCheckout(&$params, $component) {
    $paymentHelper = new PaytrailPaymentHelper();
    
    //Save payment info for PaytrailIPN.php to retrieve
    $paymentHelper->insertInvoiceInfo($params, $component);
  
    $merchantId = $this->_paymentProcessor['user_name'];
    $merchantSecret = $this->_paymentProcessor['password'];
    
    $result = $paymentHelper->processPayment($params, $component, $merchantId, $merchantSecret);
    
    // Redirect to URL received from REST request
    CRM_Utils_System::redirect($result->getUrl());
  }
  
  
}
