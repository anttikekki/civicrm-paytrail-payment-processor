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
require_once 'PaytrailConfigHelper.php';

/**
* Implements CiviCRM 'install' hook.
*/
function paytrail_civicrm_install() {
  //Add table for configuration
  $sql = "
    CREATE TABLE IF NOT EXISTS civicrm_paytrail_payment_processor_config (
      config_key varchar(255) NOT NULL,
      config_value varchar(255) NOT NULL,
      PRIMARY KEY (`config_key`)
    ) ENGINE=InnoDB;
  ";
  CRM_Core_DAO::executeQuery($sql);
}

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
   * Sets appropriate parameters for checking out to Paytrail. Parameters can be customized with PaytrailConfigHelper.
   *
   * @param array $params name value pair of form data
   * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
   */
  public function doTransferCheckout(&$params, $component) {
    $civiCRMConfig = CRM_Core_Config::singleton();
    $paytrailConfig = new PaytrailConfigHelper($params);

    if ($component != 'contribute' && $component != 'event') {
      CRM_Core_Error::fatal(ts('Component is invalid'));
    }
    
    // Return URL from Paytrail
    $notifyURL = $civiCRMConfig->userFrameworkResourceURL . "extern/PaytrailNotify.php";
    
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
    if($paytrailConfig->get("apiMode") == PaytrailConfigHelper::API_MODE_S1) {
      $payment = &$this->createS1PaymentObject($params, $urlset, $component, $paytrailConfig);
    }
    else {
      $payment = &$this->createE1PaymentObject($params, $urlset, $component, $paytrailConfig);
    }

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
  
  /**
  * Create Paytrail payment object for S1 API version. S1 API requires only order number and price.
  *
  * @link http://docs.paytrail.com/en/ch05s02.html#idp140474540882720
  * @param array $params name value pair of form data
  * @param Verkkomaksut_Module_Rest_Urlset $urlset Paytrail object holding all return URLs
  * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
  * @param PaytrailConfigHelper $paytrailConfig Paytrail config helper instance
  */
  private function &createS1PaymentObject(&$params, $urlset, $component, &$paytrailConfig) {
    $orderNumber = $params['invoiceID'];
    $price = (float)$params['amount'];
    $payment = new Verkkomaksut_Module_Rest_Payment_S1($orderNumber, $urlset, $price);
    
    return $payment;
  }
  
  /**
  * Create Paytrail payment object for E1 API version. E1 API requires following fields:
  * - First name
  * - Last name
  * - Email
  * - Street address
  * - Post code
  * - Post office
  * - Country
  * - Product title
  * - Product quantity
  * - Product price
  * - Tax (VAT)
  *
  * @link http://docs.paytrail.com/en/ch05s02.html#idp140474540882720
  * @param array $params name value pair of form data
  * @param Verkkomaksut_Module_Rest_Urlset $urlset Paytrail object holding all return URLs
  * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
  * @param PaytrailConfigHelper $paytrailConfig Paytrail config helper instance
  */
  private function &createE1PaymentObject(&$params, $urlset, $component, &$paytrailConfig) {
    $orderNumber = $params['invoiceID'];
    $price = (float)$params['amount'];
    
    // An object is created to model payer’s data
    $contact = new Verkkomaksut_Module_Rest_Contact(
      $paytrailConfig->get("e1.$component.value.firstName"),      //First name. Required
      $paytrailConfig->get("e1.$component.value.lastName"),       //Last name. Required
      $paytrailConfig->get("e1.$component.value.email"),          //Email. Required
      $paytrailConfig->get("e1.$component.value.streetAddress"),  //Street address. Required
      $paytrailConfig->get("e1.$component.value.postalCode"),     //Post code. Required
      $paytrailConfig->get("e1.$component.value.city"),           //Post office. Required
      $paytrailConfig->get("e1.$component.value.country"),        //Country ISO-3166-1 code. Required
      $paytrailConfig->get("e1.$component.value.telephone"),      // Telephone number. Optional
      $paytrailConfig->get("e1.$component.value.mobile"),         // Mobile phone number. Optional
      $paytrailConfig->get("e1.$component.value.companyName")     // Company name. Optional
    );

    // Payment creation
    $payment = new Verkkomaksut_Module_Rest_Payment_E1($orderNumber, $urlset, $contact);
    
    //Set optional description. This is only visible in Paytrail Merchant admin panel.
    $description = $paytrailConfig->get("e1.$component.value.firstName")." "
      .$paytrailConfig->get("e1.$component.value.lastName").". "
      .$paytrailConfig->get("e1.$component.value.productTitle").". "
      .$paytrailConfig->get("e1.$component.value.productPrice")." €";
    $payment->setDescription();

    // Adding one or more product rows to the payment
    $payment->addProduct(
      $paytrailConfig->get("e1.$component.value.productTitle"),               // product title. Required
      $paytrailConfig->get("e1.$component.value.productCode"),                // product code. Optional
      $paytrailConfig->get("e1.$component.value.productQuantity"),            // product quantity. Required
      $paytrailConfig->get("e1.$component.value.productPrice"),               // product price (/apiece). Required
      $paytrailConfig->get("e1.$component.value.productVat"),                 // Tax percentage (VAT). Required
      $paytrailConfig->get("e1.$component.value.productDiscountPercentage"),  // Discount percentage, Optional
      $paytrailConfig->get("e1.$component.value.productType")	              // Product type, Optional		
    );
    
    return $payment;
  }
}