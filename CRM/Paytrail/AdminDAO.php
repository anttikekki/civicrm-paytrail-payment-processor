<?php

/**
* DAO for saving and deleting Paytrail payment processor extension configuration rows.
*/
class CRM_Paytrail_AdminDAO {
  
  /**
  * Checks if configuration rows exists for given primary keys.
  *
  * @param string $config_key Config key
  * @return boolean True if row exists, else false.
  */
  public static function configRowExists($config_key) {
    $sql = "
      SELECT config_key
      FROM civicrm_paytrail_payment_processor_config
      WHERE config_key = %1
    ";
    $sqlParams = array(
      1  => array($config_key, 'String')
    );
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    return $dao->fetch();
  }

  /**
  * Saves configuration row. Updates old row if it exists. Creates new row if old is not found.
  *
  * @param array $row Array of parameters for save. Required parameters: config_key and config_value.
  * @return string. "ok" if save was succesfull. All other return values are error messages.
  */
  public static function saveConfigRow($row) {
    $rowExists = static::configRowExists($row["config_key"]);
  
    if($rowExists) {
      static::updateConfigRow($row);
    }
    else {
      static::createConfigRow($row);
    }
    
    return "ok";
  }
  
  /**
  * Creates new configuration row.
  *
  * @param array $row Array of parameters for save. Required parameters: relationship_type_id, custom_field_id and display_order.
  */
  public static function createConfigRow($row) {
    $sql = "
      INSERT INTO civicrm_paytrail_payment_processor_config (config_key, config_value)
      VALUES(%1, %2)
    ";
    $sqlParams = array(
      1  => array($row["config_key"], 'String'),
      2  => array($row["config_value"], 'String')
    );
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }
  
  /**
  * Updates old configuration row.
  *
  * @param array $row Array of parameters for save. Required parameters: config_key and config_value.
  */
  public static function updateConfigRow($row) {  
    $sql = "
      UPDATE civicrm_paytrail_payment_processor_config
      SET config_value = %2
      WHERE config_key = %1
    ";
    $sqlParams = array(
      1  => array($row["config_key"], 'String'),
      2  => array($row["config_value"], 'String')
    );
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }
  
  /**
  * Deletes configuration row-
  *
  * @param array $row Array of parameters for save. Required parameters: config_key.
  */
  public static function deleteConfigRow($row) {
    $sql = "
      DELETE FROM civicrm_paytrail_payment_processor_config
      WHERE config_key = %1
    ";
    $sqlParams = array(
      1  => array($row["config_key"], 'String')
    );
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }
}