CREATE TABLE IF NOT EXISTS civicrm_paytrail_payment_processor_config (
  config_key varchar(255) NOT NULL,
  config_value varchar(255) NOT NULL,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB;
  
  
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
  PRIMARY KEY (`invoice_id`)
) ENGINE=InnoDB;