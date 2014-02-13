<?php

class PaytrailPaymentHelper {
  
  /**
  * Paytrail config helper instance
  *
  * @var PaytrailConfigHelper
  */
  private $paytrailConfig;

  public function __construct() {
    $this->paytrailConfig = new PaytrailConfigHelper();
  }
  
  public function &getPaytrailConfig() {
    return $this->paytrailConfig;
  }

  public function embeddPaymentButtons(&$params, $component) {
    $this->paytrailConfig->setPaymentProcessorParams($params);
  
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

  public function processPayment(&$params, $component, $merchantId, $merchantSecret) {
    $this->paytrailConfig->setPaymentProcessorParams($params);
  
    if ($component != 'contribute' && $component != 'event') {
      CRM_Core_Error::fatal(ts('Component is invalid'));
    }

    // Create payment. Default Paytrail API mode is E1.
    $payment = &$this->createPaymentObject($params, $component);

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
  
  private function createNotifyURL(&$params) {
    // Return URL from Paytrail
    $notifyURL = CRM_Core_Config::singleton()->userFrameworkResourceURL . "extern/PaytrailNotify.php";
    
    //Add CiviCRM data to return URL
    $notifyURL .= "?qfKey=" .             $params['qfKey'];
    $notifyURL .= "&contactID=" .         ((isset($params['contactID'])) ? $params['contactID'] : '');
    $notifyURL .= "&contributionID=" .    ((isset($params['contributionID'])) ? $params['contributionID'] : '');
    $notifyURL .= "&contributionTypeID=" . ((isset($params['contributionTypeID'])) ? $params['contributionTypeID'] : ''); //Financial type
    $notifyURL .= "&eventID=" .           ((isset($params['eventID'])) ? $params['eventID'] : '');
    $notifyURL .= "&participantID=" .     ((isset($params['participantID'])) ? $params['participantID'] : '');
    $notifyURL .= "&membershipID=" .      ((isset($params['membershipID'])) ? $params['membershipID'] : '');
    $notifyURL .= "&amount=" .            ((isset($params['amount'])) ? $params['amount'] : '');
    
    return $notifyURL;
  }
  
  private function &createPaymentObject(&$params, $component) {
    // Return URL from Paytrail to extern directory PaytrailNotify.php
    $notifyURL = $this->createNotifyURL($params);
  
    $urlset = new Verkkomaksut_Module_Rest_Urlset(
      $notifyURL, // success
      $notifyURL, // failure
      $notifyURL, // notify
      ""  // pending url is not in use
    );
  
    if($this->paytrailConfig->get("apiMode") == PaytrailConfigHelper::API_MODE_S1) {
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
}