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
  //Create civicrm_paytrail_payment_processor_config -table for configuration data
  $sql = "
    CREATE TABLE IF NOT EXISTS civicrm_paytrail_payment_processor_config (
      config_key varchar(255) NOT NULL,
      config_value varchar(255) NOT NULL,
      PRIMARY KEY (`config_key`)
    ) ENGINE=InnoDB;
  ";
  CRM_Core_DAO::executeQuery($sql);
  
  //Create civicrm_paytrail_payment_processor_invoice_data -table for invoice data
  $sql = "
    CREATE TABLE IF NOT EXISTS civicrm_paytrail_payment_processor_invoice_data (
      invoice_id varchar(255) NOT NULL,
      component varchar(255) NOT NULL,
      contact_id int(10),
      contribution_id int(10),
      contribution_financial_type_id int(10),
      event_id int(10),
      participant_id int(10),
      membership_id int(10),
      amount decimal(20,2),
      qfKey varchar(255) NOT NULL,
      contributionPageCmsUrl TEXT NOT NULL,
      PRIMARY KEY (`invoice_id`)
    ) ENGINE=InnoDB;
  ";
  CRM_Core_DAO::executeQuery($sql);
}

// alter table civicrm_paytrail_payment_processor_invoice_data add column contributionPageCmsUrl TEXT NOT NULL; (for upgrade)

/**
* Implements CiviCRM 'buildForm' hook.
*
* @param string $formName Name of current form.
* @param CRM_Core_Form $form Current form.
*/
function Paytrail_civicrm_buildForm($formName, &$form) {
  //Confirm Contribution
  if($form instanceof CRM_Contribute_Form_Contribution_Confirm) {
    $paymentHelper = new PaytrailPaymentHelper();
    if(!$paymentHelper->isEmbeddedButtonsEnabled()) {
      return;
    }
  
    //Get form params to get customer contact info and product info
    $component = "contribute";
    $params = $form->get("params");
    $params['item_name'] = $form->_values['title'];
    
    $paymentHelper->embeddPaymentButtons($params, $component);
  }
  //Confirm Event participation
  else if($form instanceof CRM_Event_Form_Registration_Confirm) {
    $paymentHelper = new PaytrailPaymentHelper();
    if(!$paymentHelper->isEmbeddedButtonsEnabled()) {
      return;
    }
    
    //Get form params to get customer contact info and product info
    $component = "event";
    $formParamsArray = $form->get("params");
    $params = $formParamsArray[0];
    $params['item_name'] = $form->_values['event']['title'];
    
    $paymentHelper->embeddPaymentButtons($params, $component);
  }
}

/**
* Implements CiviCRM 'alterContent' hook.
*
* @param string $content - previously generated content
* @param string $context - context of content - page or form
* @param string $tplName - the file name of the tpl
* @param object $object - a reference to the page or form object
*/
function Paytrail_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  //Confirm Contribution
  if($object instanceof CRM_Contribute_Form_Contribution_Confirm) {
    $paymentHelper = new PaytrailPaymentHelper();
    if(!$paymentHelper->isEmbeddedButtonsEnabled()) {
      return;
    }
  
    //Hide original form. Do not remove it because we want to submit it
    $content = str_replace('id="Confirm"' , 'id="Confirm" style="display: none;"' , $content);
  }
  //Confirm Event participation
  else if($object instanceof CRM_Event_Form_Registration_Confirm) {
    $paymentHelper = new PaytrailPaymentHelper();
    if(!$paymentHelper->isEmbeddedButtonsEnabled()) {
      return;
    }
  
    //Hide original form. Do not remove it because we want to submit it
    $content = str_replace('id="Confirm"' , 'id="Confirm" style="display: none;"' , $content);
  }
}

/**
* Implemets CiviCRM 'alterTemplateFile' hook.
*
* @param String $formName Name of current form.
* @param CRM_Core_Form $form Current form.
* @param CRM_Core_Form $context Page or form.
* @param String $tplName The file name of the tpl - alter this to alter the file in use.
*/
function Paytrail_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  //Extension admin page
  if($form instanceof CRM_Paytrail_Page_Admin) {
    $res = CRM_Core_Resources::singleton();
    $res->addScriptFile('com.github.anttikekki.payment.paytrail', 'assets/js/admin.js');
    
    //Add CMS neutral ajax callback URLs
    $res->addSetting(array('paytrail' => 
      array(
        'getInitDataAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/getInitData', NULL, FALSE, NULL, FALSE),
        'getConfigAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/getConfig', NULL, FALSE, NULL, FALSE),
        'saveConfigRowAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/saveConfigRow', NULL, FALSE, NULL, FALSE),
        'deleteConfigRowAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/deleteConfigRow', NULL, FALSE, NULL, FALSE)
      )
    ));
  }
}

/**
* Implemets CiviCRM 'config' hook.
*
* @param object $config the config object
*/
function Paytrail_civicrm_config(&$config= NULL) {
  static $configured = FALSE;
  if ($configured) {
    return;
  }
  $configured = TRUE;

  $template =& CRM_Core_Smarty::singleton();

  $extRoot = dirname(__FILE__) . DIRECTORY_SEPARATOR;
  $extDir = $extRoot . 'templates';

  if ( is_array( $template->template_dir ) ) {
    array_unshift( $template->template_dir, $extDir );
  }
  else {
    $template->template_dir = array( $extDir, $template->template_dir );
  }

  $include_path = $extRoot . PATH_SEPARATOR . get_include_path( );
  set_include_path($include_path);

}

/**
* Implements CiviCRM 'xmlMenu' hook.
*
* @param array $files the array for files used to build the menu. You can append or delete entries from this file. 
* You can also override menu items defined by CiviCRM Core.
*/
function Paytrail_civicrm_xmlMenu( &$files ) {
  //Add Ajax and Admin page URLs to civicrm_menu table so that they work
  $files[] = dirname(__FILE__)."/menu.xml";
}

/**
* Implemets CiviCRM 'navigationMenu' hook.
*
* @param array $params the navigation menu array
*/
function Paytrail_civicrm_navigationMenu(&$params) {
    //Find last index of Administer menu children
    $maxKey = max(array_keys($params[108]['child']));
    
    //Add extension menu as Admin menu last children
    $params[108]['child'][$maxKey+1] = array(
       'attributes' => array (
          'label'      => 'Paytrail',
          'name'       => 'Paytrail',
          'url'        => null,
          'permission' => null,
          'operator'   => null,
          'separator'  => null,
          'parentID'   => null,
          'navID'      => $maxKey+1,
          'active'     => 1
        ),
       'child' =>  array (
          '1' => array (
            'attributes' => array (
               'label'      => 'Settings',
               'name'       => 'Settings',
               'url'        => 'civicrm/paytrail/settings',
               'permission' => 'administer CiviCRM',
               'operator'   => null,
               'separator'  => 1,
               'parentID'   => $maxKey+1,
               'navID'      => 1,
               'active'     => 1
                ),
            'child' => null
          )
        )
    );
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
      $paymentHelper = new PaytrailPaymentHelper();

      // For now works only with WordPress
      if (function_exists('get_permalink') && $contributionPageCmsUrl = get_permalink()) {
        $params['contributionPageCmsUrl'] = $contributionPageCmsUrl;
      }

    //Save payment info for PaytrailIPN.php to retrieve
    $paymentHelper->insertInvoiceInfo($params, $component);
    
    /*
    * Don't process payment (do full page redirect to Paytrail) if embedded payment buttons are enabled. 
    * Get URL parameter that holds direct link to bank page and redirect to there.
    */
    if($paymentHelper->isEmbeddedButtonsEnabled()) {
      if(isset($_GET["paytrailEmbeddedButtonsRedirectURL"])) {
        $paytrailDirectURL = urldecode($_GET["paytrailEmbeddedButtonsRedirectURL"]);
        CRM_Utils_System::redirect($paytrailDirectURL);
      }
    }
  
    $merchantId = $this->_paymentProcessor['user_name'];
    $merchantSecret = $this->_paymentProcessor['password'];
    
    $result = $paymentHelper->processPayment($params, $component, $merchantId, $merchantSecret);
    
    // Redirect to URL received from REST request
    CRM_Utils_System::redirect($result->getUrl());
  }
  
  
}