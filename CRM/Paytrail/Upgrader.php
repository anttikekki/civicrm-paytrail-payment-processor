<?php
use CRM_Paytrail_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Paytrail_Upgrader extends CRM_Paytrail_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  public function install() {
    $this->executeSqlFile('sql/install.sql');
  }


}
