<?php

require_once "PaytrailConfigHelper.php";
require_once "PaytrailAdminDAO.php";

/**
* Ajax request listener for Paytrail Admin page Ajax calls.
* This listener methods intercept URLs in form civicrm/paytrail/settings/ajax/*. This is configured in menu.xml.
* All methods print JSON-response and terminates CiviCRM.
*/
class Admin_Page_PaytrailAdminAjax {

  /**
  * Returns init data required by extension admin page.
  * Listens URL civicrm/paytrail/settings/ajax/getInitData.
  *
  * Printed JSON object contains following fields:
  * config: all rows from civicrm_paytrail_payment_processor_config table
  * customGroups: all custom groups that belong to Relationships
  * customFieldsForCustomGroups: all custom fields for all custom groups
  * relationshipTypes: Relationship types with id and name
  */
  public static function getInitData() {
    $configHelper = new PaytrailConfigHelper();
  
    $result = array();
    $result["config"] = array_merge($configHelper->getDefaultValues(), $configHelper->getAllDatabaseConfigs());
  
    echo json_encode($result);
    CRM_Utils_System::civiExit();
  }
  
  /**
  * Returns all rows from civicrm_paytrail_payment_processor_config table.
  * Listens URL civicrm/paytrail/settings/ajax/getConfig.
  */
  public static function getConfig() {
    $configHelper = new PaytrailConfigHelper();
    echo json_encode(array_merge($configHelper->getDefaultValues(), $configHelper->getAllDatabaseConfigs()));
    CRM_Utils_System::civiExit();
  }
  
  /**
  * Saves (creates or updates) configuration row in civicrm_paytrail_payment_processor_config table.
  * Prints "ok" if save was succesfull. All other responses are error messages.
  * Listens URL civicrm/paytrail/settings/ajax/saveConfigRow.
  *
  * Saved parameters are queried from $_GET.
  */
  public static function saveConfigRow() {
    echo PaytrailAdminDAO::saveConfigRow($_GET);
    CRM_Utils_System::civiExit();
  }
  
  /**
  * Deletes configuration row from civicrm_paytrail_payment_processor_config table.
  * Prints "ok" if delete was succesfull.
  * Listens URL civicrm/paytrail/settings/ajax/deleteConfigRow.
  *
  * Delete parameters are queried from $_GET.
  */
  public static function deleteConfigRow() {
    PaytrailAdminDAO::deleteConfigRow($_GET);
    
    echo "ok";
    CRM_Utils_System::civiExit();
  }
}