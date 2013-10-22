<?php

/**
 * Paytrail payment module to CiviCRM
 *
 * Portions of this file were based off the payment processor extension tutorial at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/Example+of+creating+a+payment+processor+extension
 *
 * Portions of this file were based off the Ogone payment processor extension at: 
 * https://github.com/cray146/CiviCRM-Ogone-Payment-Processor
 */

require_once 'Verkkomaksut_Module_Rest.php';

class paytrail extends CRM_Core_Payment {
  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * mode of operation: live or test
   *
   * @var object
   * @static
   */
  static protected $_mode = null;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct( $mode, &$paymentProcessor ) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('Paytrail');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   *
   */
  static function &singleton( $mode, &$paymentProcessor ) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === null ) {
      self::$_singleton[$processorName] = new paytrail($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig( ) {
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

  function doDirectPayment(&$params) {
    CRM_Core_Error::fatal(ts('doDirectPayment() function is not implemented'));
  }

  /**
   * Sets appropriate parameters for checking out to Paytrail
   *
   * @param array $params  name value pair of contribution data
   *
   * @return void
   * @access public
   *
   */
  function doTransferCheckout(&$params, $component) {
    $config = CRM_Core_Config::singleton();

    if ($component != 'contribute' && $component != 'event') {
      CRM_Core_Error::fatal(ts('Component is invalid'));
    }

    //Add CiviCRM paramters to order ID. Order Id is returned to PaytrailIPN in ORDER_NUMBER-parameter.
    $orderID = array(
      $params['invoiceID'],
      $params['contactID'],
      $params['contributionID'],
      $params['contributionTypeID'],
      $params['eventID'],
      $params['participantID'],
	  $params['qfKey'],
	  $params['membershipID']
    );

    //CRM_Core_Error::debug_var('Paytrail orderID', $orderID);
    
    // Return URLs
    $notifyURL = $config->userFrameworkResourceURL . "extern/PaytrailNotify.php";
    $urlset = new Verkkomaksut_Module_Rest_Urlset(
      $notifyURL, // success
      $notifyURL, // failure
      $notifyURL,  // notify
      ""  // pending url is not in use
    );

    // Create payment
    $orderNumber = implode('-', $orderID); //Max 64 chars: http://docs.paytrail.com/fi/ch04s04.html#idp1141168
    $price = (float)$params['amount'];
    $payment = new Verkkomaksut_Module_Rest_Payment_S1($orderNumber, $urlset, $price);

    // Send request to https://payment.verkkomaksut.fi with Merchant ID and Merchant secret
    $merchantId = $this->_paymentProcessor['user_name'];
    $merchantSecret = $this->_paymentProcessor['password'];
    $module = new Verkkomaksut_Module_Rest($merchantId, $merchantSecret);
    try {
      $result = $module->processPayment($payment);
    }
    catch(Verkkomaksut_Exception $e) {
      CRM_Core_Error::fatal("Error contacting Paytrail: ".$e->getMessage());
      exit();
    }

    // Redirect to URL received from REST request
    CRM_Utils_System::redirect($result->getUrl());
  }
}


