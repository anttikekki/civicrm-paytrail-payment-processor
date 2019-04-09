<?php

require_once 'paytrail.civix.php';

use CRM_Paytrail_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function paytrail_civicrm_config(&$config) {
  _paytrail_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function paytrail_civicrm_xmlMenu(&$files) {
  _paytrail_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function paytrail_civicrm_install() {
  _paytrail_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function paytrail_civicrm_postInstall() {
  _paytrail_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function paytrail_civicrm_uninstall() {
  _paytrail_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function paytrail_civicrm_enable() {
  _paytrail_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function paytrail_civicrm_disable() {
  _paytrail_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function paytrail_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _paytrail_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function paytrail_civicrm_managed(&$entities) {
  _paytrail_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function paytrail_civicrm_caseTypes(&$caseTypes) {
  _paytrail_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function paytrail_civicrm_angularModules(&$angularModules) {
  _paytrail_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function paytrail_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _paytrail_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function paytrail_civicrm_entityTypes(&$entityTypes) {
  _paytrail_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function paytrail_civicrm_navigationMenu(&$menu) {
  _paytrail_civix_insert_navigation_menu($menu, 'Administer/System Settings', array(
    'label' => E::ts('Paytrail Settings'),
    'name' => 'paytrail_settings',
    'url' => 'civicrm/paytrail/settings',
    'permission' => 'administer CiviCRM',
    'separator' => 2
  ));
  _paytrail_civix_navigationMenu($menu);
}

/**
* Implemets CiviCRM 'alterTemplateFile' hook.
*
* @param String $formName Name of current form.
* @param CRM_Core_Form $form Current form.
* @param CRM_Core_Form $context Page or form.
* @param String $tplName The file name of the tpl - alter this to alter the file in use.
*/
function paytrail_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  //Extension admin page
  if($form instanceof CRM_Paytrail_Form_Admin) {
    $res = CRM_Core_Resources::singleton();
    $res->addScriptFile('com.github.anttikekki.payment.paytrail', 'CRM/Paytrail/Form/admin.js');
    
    //Add CMS neutral ajax callback URLs
    $res->addSetting(array('paytrail' => 
      array(
        'getInitDataAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/getInitData'),
        'getConfigAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/getConfig'),
        'saveConfigRowAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/saveConfigRow'),
        'deleteConfigRowAjaxURL' =>  CRM_Utils_System::url('civicrm/paytrail/settings/ajax/deleteConfigRow')
      )
    ));
  }
}