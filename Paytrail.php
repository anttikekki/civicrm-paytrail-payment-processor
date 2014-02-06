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

class com_github_anttikekki_payment_paytrail extends CRM_Core_Payment {
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
      self::$_singleton[$processorName] = new com_github_anttikekki_payment_paytrail($mode, $paymentProcessor);
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
   * Sets appropriate parameters for checking out to Paytrail
   *
   * @param array $params name value pair of contribution data
   * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
   */
  public function doTransferCheckout(&$params, $component) {
    $config = CRM_Core_Config::singleton();

    if ($component != 'contribute' && $component != 'event') {
      CRM_Core_Error::fatal(ts('Component is invalid'));
    }
    
    // Return URL from Paytrail
    $notifyURL = $config->userFrameworkResourceURL . "extern/PaytrailNotify.php";
    
    //Add CiviCRM data to return URL
    $notifyURL .= "?qfKey=" .             $params['qfKey'];
    $notifyURL .= "&contactID=" .         ((isset($params['contactID'])) ? $params['contactID'] : '');
    $notifyURL .= "&contributionID=" .    ((isset($params['contributionID'])) ? $params['contributionID'] : '');
    $notifyURL .= "&contributionTypeID=" . ((isset($params['contributionTypeID'])) ? $params['contributionTypeID'] : '');
    $notifyURL .= "&eventID=" .           ((isset($params['eventID'])) ? $params['eventID'] : '');
    $notifyURL .= "&participantID=" .     ((isset($params['participantID'])) ? $params['participantID'] : '');
    $notifyURL .= "&membershipID=" .      ((isset($params['membershipID'])) ? $params['membershipID'] : '');
    $notifyURL .= "&amount=" .            ((isset($params['amount'])) ? $params['amount'] : '');
    
    $urlset = new Verkkomaksut_Module_Rest_Urlset(
      $notifyURL, // success
      $notifyURL, // failure
      $notifyURL, // notify
      ""  // pending url is not in use
    );

    // Create payment
    $payment = &$this->createS1PaymentObject($params, $urlset);

    // Send request to https://payment.verkkomaksut.fi with Merchant ID and Merchant secret
    $merchantId = $this->_paymentProcessor['user_name'];
    $merchantSecret = $this->_paymentProcessor['password'];
    $module = new Verkkomaksut_Module_Rest($merchantId, $merchantSecret);
    try {
      $result = $module->processPayment($payment);
    }
    catch(Verkkomaksut_Exception $e) {
      CRM_Core_Error::fatal("Error contacting Paytrail: ".$e->getMessage().". Order number: ".$orderNumber);
      exit();
    }

    // Redirect to URL received from REST request
    CRM_Utils_System::redirect($result->getUrl());
  }
  
  private function &createS1PaymentObject(&$params, $urlset) {
    $orderNumber = $params['invoiceID'];
    $price = (float)$params['amount'];
    $payment = new Verkkomaksut_Module_Rest_Payment_S1($orderNumber, $urlset, $price);
    
    return $payment;
  }
  
  /**
  * http://docs.paytrail.com/en/ch05s02.html#idp140474540882720
  */
  private function &createE1PaymentObject(&$params, $urlset) {
    $orderNumber = $params['invoiceID'];
    $price = (float)$params['amount'];
    
    // An object is created to model payer’s data
    $contact = new Verkkomaksut_Module_Rest_Contact(
        $params['first_name'],                          //First name. Required
        $params['last_name'],                           //Last name. Required
        $params['email-5'],                             //Email. Required
        $params['street_address-1'],                    //Street address. Required
        $params['postal_code-1'],                       //Post code. Required
        $params['city-1'],                              //Post office. Required
        $this->getCountryISOCOde($params['country-1']), //Country ISO-3166-1 code. Required
        "",                                             // Telephone number. Optional
        "",                                             // Mobile phone number. Optional
        ""                                              // Company name. Optional
    );

    // Payment creation
    $payment = new Verkkomaksut_Module_Rest_Payment_E1($orderNumber, $urlset, $contact);
    
    //Set optional description. This is only visible in Paytrail Merchant admin panel.
    $description = $params['first_name']." "
      .$params['last_name'].". "
      .$params['item_name'].". "
      .$params['amount']." €";
    $payment->setDescription();

    // Adding one or more product rows to the payment
    $payment->addProduct(
        $params['item_name'],               // product title. Required
        "",                                 // product code. Optional
        "1.00",                             // product quantity. Required
        $params['amount'],                  // product price (/apiece). Required
        "0.00",                             // Tax percentage (VAT). Required
        "0.00",                             // Discount percentage, Optional
        Verkkomaksut_Module_Rest_Product::TYPE_NORMAL	// Product type, Optional		
    );
    
    return $payment;
  }
  
  private function getCountryISOCOde($countryId) {
    $countryId = (int) $countryId;
  
    $sql = "
      SELECT iso_code
      FROM civicrm_country
      WHERE id = $countryId
    ";
    
    return CRM_Core_DAO::singleValueQuery($sql);
  }
}