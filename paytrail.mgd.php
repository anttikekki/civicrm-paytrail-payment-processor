<?php

/**
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/Hook+Reference
 */
return array(
  0 => array(
    'module' => 'com.github.anttikekki.payment.paytrail',
    'name' => 'Paytrail',
    'entity' => 'PaymentProcessorType',
    'params' => array(
    'version' => 3,
      'name' => 'Paytrail',
      'title' => 'Paytrail',
      'description' => 'Paytrail Payment Processor',
      'class_name' => 'Payment_Paytrail', // CRM_Core_Payment_Paytrail without CRM_Core_ prefix
      'billing_mode' => 'notify',
      'user_name_label' => 'Merchant id',
      'password_label' => 'Merchant secret',
      'is_recur' => 0,
      'payment_type' => 1
    )
  )
);