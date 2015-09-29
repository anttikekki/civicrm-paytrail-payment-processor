<?php

/**
* Paytrail payment processor configuration helper.
*
* Provides reasonable defaults to values not commonly in contribution or event form (e.g. tax, quantity).
* Values and form fields can also be customized with configuration in civicrm_paytrail_payment_processor_config 
* table.
*
* Default Paytrail API mode is E1.
*
* @link http://docs.paytrail.com/en/ch05s02.html#idp140474540882720
*/
class CRM_Paytrail_ConfigHelper {

  /**
  * API mode name for S1.
  */
  const API_MODE_S1 = "S1";
  
  /**
  * API mode name for E1.
  */
  const API_MODE_E1 = "E1";

  /**
  * Configuration defaults.
  *
  * @var array
  */
  private $configDefaults = array(
    //Common config
    "apiMode" => "E1",
    "embeddedPaymentButtons" => "true",
    "locale" => "fi_FI",
    
    //Contribute: parameter field names in Payment Processor params
    "e1.contribute.field.firstName" => "first_name",
    "e1.contribute.field.lastName" => "last_name",
    "e1.contribute.field.email" => "email-5",
    "e1.contribute.field.streetAddress" => "street_address-1",
    "e1.contribute.field.postalCode" => "postal_code-1",
    "e1.contribute.field.city" => "city-1",
    "e1.contribute.field.country" => "country-1",
    "e1.contribute.field.productTitle" => "item_name",
    "e1.contribute.field.productPrice" => "amount",
    
    //Contribute: default field values
    "e1.contribute.value.firstName" => "",
    "e1.contribute.value.lastName" => "",
    "e1.contribute.value.email" => "",
    "e1.contribute.value.streetAddress" => "",
    "e1.contribute.value.postalCode" => "",
    "e1.contribute.value.city" => "",
    "e1.contribute.value.country" => "FI",
    "e1.contribute.value.telephone" => "",
    "e1.contribute.value.mobile" => "",
    "e1.contribute.value.companyName" => "",
    "e1.contribute.value.productQuantity" => "1.00",
    "e1.contribute.value.productVat" => "0.00",
    "e1.contribute.value.productDiscountPercentage" => "0.00",
    "e1.contribute.value.productType" => "1", //TYPE_NORMAL = 1, TYPE_POSTAL = 2, TYPE_HANDLING = 3
    "e1.contribute.value.productCode" => "",
    "e1.contribute.value.productPrice" => "",
    
    //Event: parameter field names in Payment Processor params
    "e1.event.field.firstName" => "first_name",
    "e1.event.field.lastName" => "last_name",
    "e1.event.field.email" => "email-5",
    "e1.event.field.streetAddress" => "street_address-1",
    "e1.event.field.postalCode" => "postal_code-1",
    "e1.event.field.city" => "city-1",
    "e1.event.field.country" => "country-1",
    "e1.event.field.productTitle" => "item_name",
    "e1.event.field.productPrice" => "amount",
    
    //Event: default field values
    "e1.event.value.firstName" => "",
    "e1.event.value.lastName" => "",
    "e1.event.value.email" => "",
    "e1.event.value.streetAddress" => "",
    "e1.event.value.postalCode" => "",
    "e1.event.value.city" => "",
    "e1.event.value.country" => "FI",
    "e1.event.value.telephone" => "",
    "e1.event.value.mobile" => "",
    "e1.event.value.companyName" => "",
    "e1.event.value.productQuantity" => "1.00",
    "e1.event.value.productVat" => "0.00",
    "e1.event.value.discountPercentage" => "0.00",
    "e1.event.value.productType" => "1", //TYPE_NORMAL = 1, TYPE_POSTAL = 2, TYPE_HANDLING = 3
    "e1.event.value.productCode" => "",
    "e1.event.value.productPrice" => ""
  );

  /**
  * Configuration from civicrm_paytrail_payment_processor_config database table.
  *
  * @var array
  */
  private $databaseConfig;
  
  /**
  * Payment processor form parameters.
  *
  * @var array
  */
  private $paymentProcessorParams;

  /**
  * Creates new helper. Loads all configurations from database on creation.
  *
  * @param array|NULL $paymentProcessorParams Name value pair of form data passed to Payment processor
  */
  public function __construct($paymentProcessorParams = array()) {
    $this->databaseConfig = $this->loadConfigurationsFromDatabase();
    $this->paymentProcessorParams = $paymentProcessorParams;
  }
  
  /**
  * Returns all database config rows.
  *
  * @return array Array where key is config key and value is config value
  */
  public function getAllDatabaseConfigs() {
    return $this->databaseConfig;
  }
  
  /**
  * Returns default configs.
  *
  * @return array Array where key is config key and value is config value
  */
  public function getDefaultValues() {
    return $this->configDefaults;
  }
  
  /**
  * Set Payment processor params
  *
  * @param array $paymentProcessorParams Name value pair of form data passed to Payment processor
  */
  public function setPaymentProcessorParams($paymentProcessorParams) {
    $this->paymentProcessorParams = $paymentProcessorParams;
  }
  
  /**
  * Loads all rows from civicrm_paytrail_payment_processor_config table.
  */
  private function loadConfigurationsFromDatabase() {
    $sql = "SELECT config_key, config_value 
      FROM civicrm_paytrail_payment_processor_config
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    
    $databaseConfig = array();
    while ($dao->fetch()) {
      $databaseConfig[$dao->config_key] = $dao->config_value;
    }
    
    return $databaseConfig;
  }
  
  /**
  * Get configuration value for given key.
  *
  * - Highest priority is on values stored in database civicrm_paytrail_payment_processor_config table.
  * - Second highest priority is form parameters passed in this helper constructor.
  * - Lowest priority is default values from this helper.
  *
  * Form parameters retrieval can be customized in two phases. First is field name that is used to find value 
  * from form parameters (e.g. e1.event.field.country). Second is the value itself (e.g. e1.event.value.country).
  *
  * @param string $configKey Congiguration key (e.g. e1.event.value.country) that value is returned.
  * @return string Value for configuration key. Can be NULL if no default or custom value is set or key is not present in form paramters.
  */
  public function get($configKey) {
    //Priority 1: config from database
    $value = $this->getDatabaseConfig($configKey);
    
    //Priority 2: value from Payment Processor parameters
    if(!isset($value)) {
      $value = $this->getPaymentProcessorParameter($configKey);
    }
    
    //Priority 3: value from defaults
    if(!isset($value)) {
      $value = $this->getDefaultConfig($configKey);
    }
    
    return $value;
  }
  
  /**
  * Get value for key from civicrm_paytrail_payment_processor_config table.
  * 
  * @param string $configKey Congiguration key (e.g. e1.event.value.country) that value is returned.
  * @return string Value for configuration key. Can be NULL if no value is found for key.
  */
  private function getDatabaseConfig($configKey) {
    return isset($this->databaseConfig[$configKey]) ? $this->databaseConfig[$configKey] : NULL;
  }
  
  /**
  * Get value for key from form parameters.
  *
  * First searches field name for given value by replacing value by field in key name (e1.event.value.country -> e1.event.field.country). 
  * Second phase is to search value for key (e1.event.field.country) from form parameters.
  *
  * Converts CiviCRM country id to ISO-3166-1 country code.
  * 
  * @param string $configKey Congiguration key (e.g. e1.event.value.country) that value is returned.
  * @return string Value for configuration key. Can be NULL if no value is found in form parameters.
  */
  private function getPaymentProcessorParameter($configKey) {
    $fieldConfigKey = str_replace(".value.", ".field.", $configKey);
    
    $parameterFieldName = $this->getDatabaseConfig($fieldConfigKey);
    
    if(!isset($parameterFieldName)) {
      $parameterFieldName = $this->getDefaultConfig($fieldConfigKey);
    }
    
    if(!isset($parameterFieldName)) {
      return NULL;
    }
    
    $value = isset($this->paymentProcessorParams[$parameterFieldName]) ? $this->paymentProcessorParams[$parameterFieldName] : NULL;
    
    if(isset($value) && strstr($configKey, "value.country") !== FALSE) {
      //Country code conversion from CiviCRM Country Id to ISO-3166-1 code
      $value = $this->getCountryISOCode($value);
    }
    
    return $value;
  }
  
  /**
  * Get value for key from helper defaults.
  * 
  * @param string $configKey Congiguration key (e.g. e1.event.value.country) that value is returned.
  * @return string Value for configuration key. Can be NULL if no value is found in defaults for key.
  */
  private function getDefaultConfig($configKey) {
    return isset($this->configDefaults[$configKey]) ? $this->configDefaults[$configKey] : NULL;
  }
  
  /**
  * Get ISO-3166-1 country code for CiviCRM country id from civicrm_country country table.
  * 
  * @param string|int $countryId Country id
  * @return string ISO-3166-1 country code (e.g. FI, EN, SV).
  */
  private function getCountryISOCode($countryId) {
    $countryId = (int) $countryId;
  
    $sql = "
      SELECT iso_code
      FROM civicrm_country
      WHERE id = $countryId
    ";
    
    return CRM_Core_DAO::singleValueQuery($sql);
  }
}