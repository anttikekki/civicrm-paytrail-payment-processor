<?php

/**
* Paytrail payment helper to handle embedded buttons mode and full page mode
*/
class PaytrailPaymentHelper {
  
  /**
  * Paytrail config helper instance
  *
  * @var CRM_Paytrail_ConfigHelper
  */
  private $paytrailConfig;

  /**
  * Create ne helper
  */
  public function __construct() {
    $this->paytrailConfig = new CRM_Paytrail_ConfigHelper();
  }
  
  /**
  * Return config instance
  *
  * @return CRM_Paytrail_ConfigHelper
  */
  public function &getPaytrailConfig() {
    return $this->paytrailConfig;
  }
  
  /**
  * Is embedded payment buttons mode active?
  * 
  * @return boolean
  */
  public function isEmbeddedButtonsEnabled() {
    return $this->paytrailConfig->get("embeddedPaymentButtons") === 'true';
  }

  /**
  * Add embedded payment buttons to page. This only works in confirmation page.
  *
  * @param array $params name value pair of Payment processor data
  * @param string $component name of CiviCRM component that is using this Payment Processor (contribute or event)
  */
  public function embeddPaymentButtons(&$params, $component) {
    $this->paytrailConfig->setPaymentProcessorParams($params);
  
    //Load payment processor
    $paymentProcessorID = $params['payment_processor'];
    $processorDAO = new CRM_Financial_DAO_PaymentProcessor();
    $processorDAO->get("id", $paymentProcessorID);
    $merchantId = $processorDAO->user_name;
    $merchantSecret = $processorDAO->password;
    
    $result = $this->processPayment($params, $component, $merchantId, $merchantSecret);
  
    CRM_Core_Resources::singleton()->addScriptFile('com.github.anttikekki.payment.paytrail', 'payment-widget-v1.0-custom.js');
    CRM_Core_Resources::singleton()->addScriptFile('com.github.anttikekki.payment.paytrail', 'initPaymentWidget.js');
    CRM_Core_Resources::singleton()->addSetting(array('paytrail' => array('token' => $result->getToken())));
  }

  /**
  * Process payment by sending payment info to Paytrail with REST API.
  *
  * @param array $params name value pair of Payment processor data
  * @param string $component name of CiviCRM component that is using this Payment Processor (contribute or event)
  * @param string $merchantId Paytrail merchant id
  * @param string $merchantSecret Paytrail merchant secret
  * @return Verkkomaksut_Module_Rest_Result REST call result with redirect URL and token
  */
  public function processPayment(&$params, $component, $merchantId, $merchantSecret) {
    $this->paytrailConfig->setPaymentProcessorParams($params);
  
    if ($component != 'contribute' && $component != 'event') {
      CRM_Core_Error::fatal(ts('Component is invalid'));
    }

    // Create payment. Default Paytrail API mode is E1.
    $payment = &$this->createPaymentObject($params, $component);
    $payment->setLocale($this->paytrailConfig->get('locale'));
    // Send request to https://payment.verkkomaksut.fi with Merchant ID and Merchant secret
    $module = new Verkkomaksut_Module_Rest($merchantId, $merchantSecret);
    try {
      $result = $module->processPayment($payment);
    }
    catch(Verkkomaksut_Exception $e) {
      CRM_Core_Error::fatal("Error contacting Paytrail: ".$e->getMessage().". Order number: ".$params['invoiceID']);
      exit();
    }
    
    return $result;
  }
  
  /**
  * Create Paytrail payment object for S1 or E1 API version.
  *
  * @link http://docs.paytrail.com/en/ch05s02.html#idp140474540882720
  * @param array $params name value pair of form data
  * @param string $component name of CiviCRM component that is using this Payment Processor (contribute or event)
  * @return Verkkomaksut_Module_Rest_Payment Paytrail payment object
  */
  private function &createPaymentObject(&$params, $component) {
    // Return URL from Paytrail to extern directory PaytrailNotify.php
    $notifyURL = CRM_Core_Config::singleton()->userFrameworkResourceURL . "extern/PaytrailNotify.php";
  
    $urlset = new Verkkomaksut_Module_Rest_Urlset(
      $notifyURL, // success
      $notifyURL, // failure
      $notifyURL, // notify
      ""  // pending url is not in use
    );
  
    if($this->paytrailConfig->get("apiMode") == CRM_Paytrail_ConfigHelper::API_MODE_S1) {
      return $this->createS1PaymentObject($params, $urlset, $component);
    }
    else {
      return $this->createE1PaymentObject($params, $urlset, $component);
    }
  }
  
  /**
  * Create Paytrail payment object for S1 API version. S1 API requires only order number and price.
  *
  * @link http://docs.paytrail.com/en/ch05s02.html#idp140474540882720
  * @param array $params name value pair of form data
  * @param Verkkomaksut_Module_Rest_Urlset $urlset Paytrail object holding all return URLs
  * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
  * @return Verkkomaksut_Module_Rest_Payment Paytrail payment object
  */
  private function &createS1PaymentObject(&$params, $urlset, $component) {
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
  * @return Verkkomaksut_Module_Rest_Payment Paytrail payment object
  */
  private function &createE1PaymentObject(&$params, $urlset, $component) {
    $orderNumber = $params['invoiceID'];
    $price = (float)$params['amount'];
    
    // An object is created to model payer’s data
    $contact = new Verkkomaksut_Module_Rest_Contact(
      $this->paytrailConfig->get("e1.$component.value.firstName"),      //First name. Required
      $this->paytrailConfig->get("e1.$component.value.lastName"),       //Last name. Required
      $this->paytrailConfig->get("e1.$component.value.email"),          //Email. Required
      $this->paytrailConfig->get("e1.$component.value.streetAddress"),  //Street address. Required
      $this->paytrailConfig->get("e1.$component.value.postalCode"),     //Post code. Required
      $this->paytrailConfig->get("e1.$component.value.city"),           //Post office. Required
      $this->paytrailConfig->get("e1.$component.value.country"),        //Country ISO-3166-1 code. Required
      $this->paytrailConfig->get("e1.$component.value.telephone"),      // Telephone number. Optional
      $this->paytrailConfig->get("e1.$component.value.mobile"),         // Mobile phone number. Optional
      $this->paytrailConfig->get("e1.$component.value.companyName")     // Company name. Optional
    );

    // Payment creation
    $payment = new Verkkomaksut_Module_Rest_Payment_E1($orderNumber, $urlset, $contact);
    
    //Set optional description. This is only visible in Paytrail Merchant admin panel.
    $description = $this->paytrailConfig->get("e1.$component.value.firstName")." "
      .$this->paytrailConfig->get("e1.$component.value.lastName").". "
      .$this->paytrailConfig->get("e1.$component.value.productTitle").". "
      .$this->paytrailConfig->get("e1.$component.value.productPrice")." €";
    $payment->setDescription($description);

    // Adding one or more product rows to the payment
    $payment->addProduct(
      $this->paytrailConfig->get("e1.$component.value.productTitle"),               // product title. Required
      $this->paytrailConfig->get("e1.$component.value.productCode"),                // product code. Optional
      $this->paytrailConfig->get("e1.$component.value.productQuantity"),            // product quantity. Required
      $this->paytrailConfig->get("e1.$component.value.productPrice"),               // product price (/apiece). Required
      $this->paytrailConfig->get("e1.$component.value.productVat"),                 // Tax percentage (VAT). Required
      $this->paytrailConfig->get("e1.$component.value.productDiscountPercentage"),  // Discount percentage, Optional
      $this->paytrailConfig->get("e1.$component.value.productType")	              // Product type, Optional		
    );
    
    return $payment;
  }
  
  /**
  * Insert new row to civicrm_paytrail_payment_processor_invoice_data table that contains 
  * all invoice info that PaytrailIPN.php requires.
  *
  * @param array $params name value pair of Payment processor data
  * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
  */
  public function insertInvoiceInfo(&$params, $component) {
    $sql = "
      INSERT INTO civicrm_paytrail_payment_processor_invoice_data (
        invoice_id, 
        component, 
        contact_id,
        contribution_id,
        contribution_financial_type_id,
        event_id,
        participant_id,
        membership_id,
        amount,
        qfKey,
        contributionPageCmsUrl
      )
      VALUES (%1, %2 , %3 , %4 , %5 , %6 , %7 , %8 , %9 , %10, %11)
    ";
    
    $sqlParams = array(
      1  => array($params['invoiceID'],                                         'String'),
      2  => array($component,                                                   'String'),
      3  => array((int) CRM_Utils_Array::value('contactID', $params),           'Integer'),
      4  => array((int) CRM_Utils_Array::value('contributionID', $params),      'Integer'),
      5  => array((int) CRM_Utils_Array::value('contributionTypeID', $params),  'Integer'),
      6  => array((int) CRM_Utils_Array::value('eventID', $params),             'Integer'),
      7  => array((int) CRM_Utils_Array::value('participantID', $params),       'Integer'),
      8  => array((int) CRM_Utils_Array::value('membershipID', $params),        'Integer'),
      9  => array((float) CRM_Utils_Array::value('amount', $params),            'Float'),
      10  => array($params['qfKey'],                                            'String'),
      11  => array($params['contributionPageCmsUrl'],                           'String')
    );
 
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }
}