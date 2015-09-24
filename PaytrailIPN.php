<?php

/** 
 * Paytrail IPN.
 *
 * Portions of this file were based off the payment processor extension tutorial at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/Example+of+creating+a+payment+processor+extension
 *
 * Portions of this file were based off the Ogone payment processor extension at: 
 * https://github.com/cray146/CiviCRM-Ogone-Payment-Processor
 *
 */
 
require_once 'CRM/Core/Payment/BaseIPN.php';

class com_github_anttikekki_payment_paytrailIPN extends CRM_Core_Payment_BaseIPN {

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * mode of operation: live or test
   *
   * @var string
   */
  protected $_mode = null;

  static function retrieve($name, $type, $object, $abort = true) {
    $value = CRM_Utils_Array::value($name, $object);
    if ($abort && $value === null) {
      CRM_Core_Error::debug_log_message("Could not find an entry for $name");
      echo "Failure: Missing Parameter {$name}<p>";
      exit();
    }
      
    if ($value) {
      if (!CRM_Utils_Type::validate($value, $type)) {
        CRM_Core_Error::debug_log_message("Could not find a valid entry for $name");
        echo "Failure: Invalid Parameter<p>";
        exit();
      }
    }
    return $value;
  }


  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct($mode, &$paymentProcessor) {
    parent::__construct();
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   * The function gets called when a new order takes place.
   * 
   * @param bool    $success               whether the transaction was approved
   * @param array   $privateData           contains the contribution / event parameters
   * @param array   $component             contribution type
   * @param amount  $amount                amount
   * @param string  $transactionReference  transaction reference
   */
  function newOrderNotify($status, $privateData, $component, $amount, $transactionReference) {
    $ids = $input = $params = array( );
   
    $input['component'] = strtolower($component);

    $ids['contact']          = self::retrieve( 'contactID'     , 'Integer', $privateData, true );
    $ids['contribution']     = self::retrieve( 'contributionID', 'Integer', $privateData, true );

    if ( $input['component'] == "event" ) {
      $ids['event']       = self::retrieve( 'eventID'      , 'Integer', $privateData, true );
      $ids['participant'] = self::retrieve( 'participantID', 'Integer', $privateData, true );
      $ids['membership']  = null;
    } else {
      $ids['membership'] = self::retrieve( 'membershipID'  , 'Integer', $privateData, false );
    }
    $ids['contributionRecur'] = $ids['contributionPage'] = null;

    if ( ! $this->validateData( $input, $ids, $objects ) ) {
      return false;
    }

      /* Make sure the invoice is valid and matches what we have in the contribution record */
    $input['invoice']    =  $privateData['invoiceID'];
    $contribution        =& $objects['contribution'];
    $input['trxn_id']  =    $transactionReference;

    if ( $contribution->invoice_id != $input['invoice'] ) {
      CRM_Core_Error::debug_log_message( "Invoice values dont match between database and IPN request" );
      echo "Failure: Invoice values dont match between database and IPN request<p>";
      return;
    }

    $input['amount'] = $amount;

    if ( $contribution->total_amount != $input['amount'] ) {
      CRM_Core_Error::debug_log_message( "Amount values dont match between database and IPN request" );
      echo "Failure: Amount values dont match between database and IPN request.".$contribution->total_amount."/".$input['amount']."<p>";
      return;
    }

    require_once 'CRM/Core/Transaction.php';
    $transaction = new CRM_Core_Transaction( );

    // check if contribution is already completed, if so we ignore this ipn

    if ( $contribution->contribution_status_id == 1 ) {
      CRM_Core_Error::debug_log_message( "returning since contribution has already been handled" );
      echo "Success: Contribution has already been handled<p>";
      return true;
    } else {
      /* Since trxn_id hasn't got any use here,
       * lets make use of it by passing the eventID/membershipTypeID to next level.
       * And change trxn_id to the payment processor reference before finishing db update */
      if ( !empty($ids['event'])) {
        $contribution->trxn_id =
          $ids['event']       . CRM_Core_DAO::VALUE_SEPARATOR .
          $ids['participant'] ;
      } else {
        $contribution->trxn_id = $ids['membership'];
      }
    }
    $this->completeTransaction ( $input, $ids, $objects, $transaction);
    return true;
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   * @param string $component name of CiviCRM component that is using this Payment Processor (contribute, event)
   * @param object $paymentProcessor the details of the payment processor being invoked
   *
   * @return object
   * @static
   */
  static function &singleton($mode, $component, &$paymentProcessor) {
    if (self::$_singleton === null) {
      self::$_singleton = new com_github_anttikekki_payment_paytrailIPN($mode, $paymentProcessor);
    }
    return self::$_singleton;
  }

  /**
   * The function returns the component(Event/Contribute..)and whether it is Test or not
   *
   * @param array   $privateData    contains the name-value pairs of transaction related data
   *
   * @return array context of this call (test, component, payment processor id)
   * @static
   */
  static function getContext($privateData)	{
    require_once 'CRM/Contribute/DAO/Contribution.php';
    
    $component = null;
    $isTest = null;

    $contributionID = $privateData['contributionID'];
    $contribution = new CRM_Contribute_DAO_Contribution();
    $contribution->id = $contributionID;

    if (!$contribution->find(true)) {
      CRM_Core_Error::debug_log_message("Could not find contribution record: $contributionID");
      echo "Failure: Could not find contribution record for $contributionID<p>";
      exit();
    }
    
    if ($contribution->contribution_page_id) {
      $component = 'contribute';
    }
    elseif ($privateData['eventID']) {
      $component = 'event';
    }
    else {
      CRM_Core_Error::fatal("contribution_page_id is null and eventID is null. Can not solve context.");
      return;
    }
    
    $isTest = $contribution->is_test;

    $duplicateTransaction = 0;
    if ($contribution->contribution_status_id == 1) {
      //contribution already handled. (some processors do two notifications so this could be valid)
      $duplicateTransaction = 1;
    }

    if ($component == 'contribute') {
      if (!$contribution->contribution_page_id) {
        CRM_Core_Error::debug_log_message("Could not find contribution page for contribution record: $contributionID");
        echo "Failure: Could not find contribution page for contribution record: $contributionID<p>";
        exit();
      }

      // get the payment processor id from contribution page
      $paymentProcessorID = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionPage',
                            $contribution->contribution_page_id, 'payment_processor');
    }
    else {
      $eventID = $privateData['eventID'];

      if (!$eventID) {
        CRM_Core_Error::debug_log_message("Could not find event ID");
        echo "Failure: Could not find eventID<p>";
        exit();
      }

      // we are in event mode
      // make sure event exists and is valid
      require_once 'CRM/Event/DAO/Event.php';
      $event = new CRM_Event_DAO_Event();
      $event->id = $eventID;
      if (!$event->find(true)) {
        CRM_Core_Error::debug_log_message("Could not find event: $eventID");
        echo "Failure: Could not find event: $eventID<p>";
        exit();
      }

      // get the payment processor id from event
      $paymentProcessorID = $event->payment_processor;
    }

    if (!$paymentProcessorID) {
      CRM_Core_Error::debug_log_message("Could not find payment processor for contribution record: $contributionID");
      echo "Failure: Could not find payment processor for contribution record: $contributionID<p>";
      exit();
    }

    return array($isTest, $component, $paymentProcessorID, $duplicateTransaction);
  }


  /**
   * This method is handles the response that will be invoked (from PaytrailNotify.php) every time
   * a notification or request is sent by the Paytrail Server.
   */
  static function main() {

    require_once 'CRM/Utils/Request.php';
    $config = CRM_Core_Config::singleton();

    //Get payment info from civicrm_paytrail_payment_processor_invoice_data table. These are saved to table before payment.
    $invoiceID = $_GET["ORDER_NUMBER"];
    $invoiceData = self::readInvoiceData($invoiceID);
    $contributionPageCmsUrl = $invoiceData['contributionPageCmsUrl'] ?: '';
    $privateData['invoiceID'] = 		   $invoiceData['invoice_id'];
    $privateData['contactID'] = 		   $invoiceData['contact_id'];
    $privateData['contributionID'] = 	 $invoiceData['contribution_id'];
    $privateData['contributionTypeID'] =  $invoiceData['contribution_financial_type_id'];
    $privateData['eventID'] = 			   $invoiceData['event_id'];
    $privateData['participantID'] = 	 $invoiceData['participant_id'];
    $privateData['membershipID'] = 		 $invoiceData['membership_id'];
    $privateData['amount'] = 		       $invoiceData['amount'];
    $privateData['qfKey'] =            $invoiceData['qfKey'];

    list($mode, $component, $paymentProcessorID, $duplicateTransaction) = self::getContext($privateData);
    $mode = $mode ? 'test' : 'live';

    $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($paymentProcessorID, $mode);
	
    //Validate Paytrail result
    require_once("Verkkomaksut_Module_Rest.php");
    $merchantId = $paymentProcessor['user_name'];
    $merchantSecret = $paymentProcessor['password'];
    $module = new Verkkomaksut_Module_Rest($merchantId, $merchantSecret);
    
    if($module->confirmPayment($_GET["ORDER_NUMBER"], $_GET["TIMESTAMP"], $_GET["PAID"], $_GET["METHOD"], $_GET["RETURN_AUTHCODE"])) {
      $success = true;
    }
    else {
      CRM_Core_Error::debug_log_message("Failure: Paytrail notify is incorrect");
      $success = false;
    }
    $finalUrlAction = ($component == 'event') ? 'civicrm/event/register' : 'civicrm/contribute/transact';
    $finalUrlParams = array(
        'qfKey' => $privateData['qfKey']
    );
    // Redirect our users to the correct url.
    if ($success) {
      // Success. Process the transaction.
      if ($duplicateTransaction == 0) {
        $ipn = self::singleton($mode, $component, $paymentProcessor);
        $amount = (float) $privateData['amount'];
        $transactionId = $_GET["ORDER_NUMBER"];
        $ipn->newOrderNotify($success, $privateData, $component, $amount, $transactionId);
      }
      $finalUrlParams['_qf_ThankYou_display'] = 1;

    } else {
      $finalUrlParams[($component == 'event') ? '_qf_Register_display'   : '_qf_Main_display'] = 1;
      $finalUrlParams['cancel'] = 1;
    }
    if ($contributionPageCmsUrl) {
      $queryParams = array_merge(array(
        'page' => 'CiviCRM',
        'q' => $finalUrlAction,
      ), $finalUrlParams);
      $glue = strpos($contributionPageCmsUrl, '?' === FALSE) ? '?' : '&';
      $finalUrl = $contributionPageCmsUrl . $glue . http_build_query($queryParams, null, '&');
      CRM_Utils_System::redirect($finalUrl);
    }
    else {
      CRM_Utils_System::redirect(CRM_Utils_System::url($finalUrlAction, $finalUrlParams, true, null, false, true));
    }
  }
  
  /**
  * Get payment invoice data from civicrm_paytrail_payment_processor_invoice_data table. 
  * Invoice data is stored there before payment URL redirect.
  *
  * @return array Payment data
  */
  private static function readInvoiceData($invoiceID) {
    $sql = "
      SELECT *
      FROM civicrm_paytrail_payment_processor_invoice_data
      WHERE invoice_id = %1
    ";
    
    $sqlParams = array(
      1  => array($invoiceID, 'String')
    );
 
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    
    $data = array();
    while ($dao->fetch()) {
      $data['invoice_id'] = $dao->invoice_id;
      $data['component'] = $dao->component;
      $data['contact_id'] = $dao->contact_id;
      $data['contribution_id'] = $dao->contribution_id;
      $data['contribution_financial_type_id'] = $dao->contribution_financial_type_id;
      $data['event_id'] = $dao->event_id;
      $data['participant_id'] = $dao->participant_id;
      $data['membership_id'] = $dao->membership_id;
      $data['amount'] = $dao->amount;
      $data['contributionPageCmsUrl'] = $dao->contributionPageCmsUrl;
      $data['qfKey'] = $dao->qfKey;
    }
    
    return $data;
  }
}