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
require_once 'PaytrailPaymentHelper.php';

/**
* Implementation of hook_civicrm_managed
*
* Add Payment processor type entity to create/deactivate/delete when this module
* is installed, disabled, uninstalled.
*
* @param array $entities The list of entity declarations
*/
function Paytrail_civicrm_managed(&$entities) {
  $entities[] = array(
    'module' => 'com.github.anttikekki.payment.paytrail',
    'name' => 'Paytrail',
    'entity' => 'PaymentProcessorType',
    'params' => array(
    'version' => 3,
      'name' => 'Paytrail',
      'title' => 'Paytrail',
      'description' => 'Paytrail Payment Processor',
      'class_name' => 'com.github.anttikekki.payment.paytrail',
      'billing_mode' => 'notify',
      'user_name_label' => 'Merchant id',
      'password_label' => 'Merchant secret',
      'is_recur' => 0,
      'payment_type' => 1
    )
  );
}

/**
* Implementation of hook_civicrm_install
*/
function Paytrail_civicrm_install() {
  //Create civicrm_paytrail_payment_processor_config -table
  $sql = "
    CREATE TABLE IF NOT EXISTS civicrm_paytrail_payment_processor_config (
      config_key varchar(255) NOT NULL,
      config_value varchar(255) NOT NULL,
      PRIMARY KEY (`config_key`)
    ) ENGINE=InnoDB;
  ";
  CRM_Core_DAO::executeQuery($sql);
}

function Paytrail_civicrm_buildForm( $formName, &$form ) {
  if($form instanceof CRM_Contribute_Form_Contribution_Confirm) {
    $paymentHelper = new PaytrailPaymentHelper();
    if($paymentHelper->getPaytrailConfig()->get("embeddedPaymentButtons") !== 'true') {
      return;
    }
  
    $component = "contribute";
    
    $params = $form->get("params");
    $params['contactID'] = $form->_contactID;
    $params['item_name'] = $form->_values['title'];
    
    $paymentHelper->embeddPaymentButtons($params, $component);
  }
  else if($form instanceof CRM_Event_Form_Registration_Confirm) {
    $paymentHelper = new PaytrailPaymentHelper();
    if($paymentHelper->getPaytrailConfig()->get("embeddedPaymentButtons") !== 'true') {
      return;
    }
    
    $component = "event";
  
    $params = $form->get("params")[0];
    $params['eventID'] = $form->_eventId;
    $params['contactID'] = $params['contact_id'];
    $params['item_name'] = $form->_values['event']['title'];
    
    $paymentHelper->embeddPaymentButtons($params, $component);
  }
}

/**
* Payment processor implementation
*/
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
    if(isset($_GET["paytrailEmbeddedButtonsRedirectURL"])) {
      $paytrailDirectURL = urldecode($_GET["paytrailEmbeddedButtonsRedirectURL"]);
      CRM_Utils_System::redirect($paytrailDirectURL);
    }
  
    $merchantId = $this->_paymentProcessor['user_name'];
    $merchantSecret = $this->_paymentProcessor['password'];
    
    $paymentHelper = new PaytrailPaymentHelper();
    $result = $paymentHelper->processPayment($params, $component, $merchantId, $merchantSecret);
    
    // Redirect to URL received from REST request
    CRM_Utils_System::redirect($result->getUrl());
  }
  
  
}