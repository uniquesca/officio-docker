DROP TABLE IF EXISTS `automated_billing_blacklist`;
CREATE TABLE IF NOT EXISTS `automated_billing_blacklist` (
  `pt_error_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pt_error_code` varchar(3) NOT NULL,
  `pt_error_description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`pt_error_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `automated_billing_blacklist` (`pt_error_code`, `pt_error_description`) VALUES
  ('04', 'Pickup (HOLD - CALL 12003)'),
  ('43', 'LOST/STOLEN CARD');


DROP TABLE IF EXISTS `automated_billing_log_sessions`;
CREATE TABLE `automated_billing_log_sessions` (
	`log_session_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`log_session_date` DATETIME NOT NULL,
	PRIMARY KEY (`log_session_id`),
	INDEX `log_session_date` (`log_session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

 DROP TABLE IF EXISTS `automated_billing_log`;
CREATE TABLE `automated_billing_log` (
	`log_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`log_session_id` INT(11) UNSIGNED NOT NULL,
	`log_invoice_id` INT(11) UNSIGNED NULL DEFAULT NULL,
	`log_retry` ENUM('Y','N') NOT NULL,
	
	`log_company` VARCHAR(255) NOT NULL,
	`log_company_show_dialog_date` DATE NULL DEFAULT NULL,
	`log_amount` DOUBLE(11,2) UNSIGNED NOT NULL,
	`log_old_billing_date` DATE NULL DEFAULT NULL,
	`log_new_billing_date` DATE NULL DEFAULT NULL,
	`log_status` ENUM('C','F') NOT NULL,

	`log_error_code` VARCHAR(3) NULL DEFAULT NULL,
	`log_error_message` TEXT NULL,
	PRIMARY KEY (`log_id`),
	CONSTRAINT `FK_automated_billing_log_automated_billing_log_sessions` FOREIGN KEY (`log_session_id`) REFERENCES `automated_billing_log_sessions` (`log_session_id`) ON UPDATE NO ACTION ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

 
DROP TABLE IF EXISTS `hst_companies`;
CREATE TABLE `hst_companies` (
  `province_id` int(11) unsigned NOT NULL auto_increment,
  `province` char(255) default NULL,
  `rate` double(13,4) unsigned default NULL,
  `tax_label` CHAR(255) DEFAULT 'GST',
  `tax_type` ENUM('exempt','included','excluded') NOT NULL DEFAULT 'excluded',
  `is_system` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `province_order` TINYINT(3) NOT NULL DEFAULT '2',
  PRIMARY KEY  (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `hst_companies` (`province_id`, `province`, `rate`, `tax_label`, `province_order`) VALUES
    (0, 'Exempt', 0, 'Y', 0),
    (1, 'Alberta', 5.00, 'GST', 2),
    (2, 'British Columbia', 12.00, 'HST', 2),
    (3, 'Manitoba', 5.00, 'GST', 2),
    (4, 'New Brunswick', 13.00, 'HST', 2),
    (5, 'Newfoundland and Labrador', 13.00, 'HST', 2),
    (6, 'Northwest Territories', 5.00, 'GST', 2),
    (7, 'Nova Scotia', 15.00, 'HST', 2),
    (8, 'Nunavut', 5.00, 'GST', 2),
    (9, 'Ontario', 13.00, 'HST', 2),
    (10, 'Prince Edward Island', 5.00, 'GST', 2),
    (11, 'Quebec', 5.00, 'GST', 2),
    (12, 'Saskatchewan', 5.00, 'GST', 2),
    (13, 'Yukon', 5.00, 'GST', 2)
;

DROP TABLE IF EXISTS `hst_officio`;
CREATE TABLE `hst_officio` (
  `province_id` int(11) unsigned NOT NULL auto_increment,
  `province` char(255) default NULL,
  `rate` double(13,4) unsigned default NULL,
  `tax_label` CHAR(255) DEFAULT 'GST',
  `tax_type` ENUM('exempt','included','excluded') NOT NULL DEFAULT 'excluded',
  `is_system` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `province_order` TINYINT(3) NOT NULL DEFAULT '2',
  PRIMARY KEY  (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `hst_officio` (`province_id`, `province`, `rate`, `tax_label`, `province_order`) VALUES
    (0, 'Exempt', 0, 'Y', 0),
    (1, 'Alberta', 5.00, 'GST', 2),
    (2, 'British Columbia', 12.00, 'HST', 2),
    (3, 'Manitoba', 5.00, 'GST', 2),
    (4, 'New Brunswick', 13.00, 'HST', 2),
    (5, 'Newfoundland and Labrador', 13.00, 'HST', 2),
    (6, 'Northwest Territories', 5.00, 'GST', 2),
    (7, 'Nova Scotia', 15.00, 'HST', 2),
    (8, 'Nunavut', 5.00, 'GST', 2),
    (9, 'Ontario', 13.00, 'HST', 2),
    (10, 'Prince Edward Island', 5.00, 'GST', 2),
    (11, 'Quebec', 5.00, 'GST', 2),
    (12, 'Saskatchewan', 5.00, 'GST', 2),
    (13, 'Yukon', 5.00, 'GST', 2)
;

DROP TABLE IF EXISTS `superadmin_smtp`;
CREATE TABLE `superadmin_smtp` (
  `smtp_id` int(11) unsigned NOT NULL auto_increment,
  `smtp_on` enum('Y','N') default 'N',
  `smtp_server` char(255) default NULL,
  `smtp_port` int(4) unsigned default NULL,
  `smtp_username` char(255) default NULL,
  `smtp_password` char(255) default NULL,
  `smtp_use_ssl` ENUM('','ssl','tls') NOT NULL,
  PRIMARY KEY  (`smtp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `superadmin_smtp` (`smtp_id`,`smtp_on`,`smtp_server`,`smtp_port`,`smtp_username`,`smtp_password`,`smtp_use_ssl`) VALUES (1,'Y','officio.ca',25,'crmteam','Spring2009','');
INSERT INTO `superadmin_smtp` (`smtp_id`,`smtp_on`,`smtp_server`,`smtp_port`,`smtp_username`,`smtp_password`,`smtp_use_ssl`) VALUES (2,'Y','officio.ca',25,'crmteam','Spring2009','');

CREATE TABLE `superadmin_searches` (
	`search_id` INT(11) NOT NULL AUTO_INCREMENT,
  `search_title` CHAR(255) NULL DEFAULT NULL,
  `search_query` TEXT NULL,
	PRIMARY KEY (`search_id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

DROP TABLE IF EXISTS `system_templates`;
CREATE TABLE `system_templates` (
  `system_template_id` int(11) unsigned NOT NULL auto_increment,
  `type` ENUM('system','mass_email','other') NOT NULL DEFAULT 'other',
  `title` varchar(255) default NULL,
  `subject` varchar(255) default NULL,
  `from` varchar(255) default NULL,
  `to` varchar(255) default NULL,
  `cc` varchar(255) default NULL,
  `bcc` varchar(255) default NULL,
  `template` text,
  `create_date` date default NULL,
  PRIMARY KEY  (`system_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `system_templates` VALUES (0,'system','First Invoice',NULL,NULL,NULL,NULL,NULL,'<table border="1" cellpadding="5" cellspacing="0" style="width:40%;"><tr><td>BILL TO</td></tr><tr><td>{prospects: salutation} {prospects: name} {prospects: last name}<br>{prospects: company}<br>{prospects: address}</td></tr></table><br /><br /><br /><table border="1" cellpadding="5" cellspacing="0" style="width:70%;" align="right"><tr><td align="center">INVOICE NO.</td><td align="center">DATE</td><td align="center">TERMS</td></tr><tr><td align="left">{invoice: number}</td><td align="center">{invoice: date}</td><td></td></tr></table><br /><br /><table border="1" cellpadding="5" cellspacing="0"><tr><td width="50%" align="center">DESCRIPTION</td><td width="20%" align="center">RATE</td><td width="15%" align="center">QTY</td><td width="15%" align="center">AMOUNT</td></tr><tr><td width="50%">{prospects: package} Office</td><td width="20%" align="center"> </td><td align="center" width="15%"> </td><td align="right" width="15%">$ {invoice: subscription fee}</td></tr><tr><td width="50%">Support and Training</td><td width="20%" align="center"></td><td align="center" width="15%"></td><td align="right" width="15%">${invoice: support fee}</td></tr><tr><td width="50%">Additional Users</td><td width="20%" align="center">{invoice: price per user}$ / 1 user</td><td align="center" width="15%">{invoice: additional users}</td><td align="right" width="15%">${invoice: additional users fee}</td></tr><tr><td width="50%">Additional Storage</td><td width="20%" align="center">{invoice: price per storage}$ / 1Gb</td><td align="center" width="15%">{invoice: additional storage}</td><td align="right" width="15%">${invoice: additional storage charges}</td></tr><tr><td width="85%" colspan="3"> </td><td align="right" width="15%"><b>${invoice: subtotal}</b></td></tr><tr><td width="100%" colspan="4"> </td></tr><tr><td width="85%" colspan="3" align="left">GST  (Registration No. 896191244)</td><td align="right" width="15%">${invoice: gst/hst fee}</td></tr><tr><td width="100%" colspan="4"> </td></tr><tr><td width="85%" colspan="3" align="right"><b>TOTAL</b></td><td align="right" width="15%"><b>${invoice: total}</b></td></tr></table><br /><div><center><b>Thank you for using our services. Please do not hesitate to contact us, if we can be of further assistance in the future.</b></center></div>','2010-01-01');
INSERT INTO `system_templates` VALUES (0,'system','Recurring Invoice',NULL,NULL,NULL,NULL,NULL,'<table border="1" cellpadding="5" cellspacing="0" style="width:40%;"><tr><td>BILL TO</td></tr><tr><td>{admin first name} {admin last name}<br>{company name}<br>{company address}</td></tr></table><br /><br /><br /><table border="1" cellpadding="5" cellspacing="0" style="width:70%;" align="right"><tr><td align="center">INVOICE NO.</td><td align="center">DATE</td><td align="center">TERMS</td></tr><tr><td align="left">{invoice: number}</td><td align="center">{invoice: date}</td><td></td></tr></table><br /><br /><table border="1" cellpadding="5" cellspacing="0"><tr><td width="50%" align="center">DESCRIPTION</td><td width="20%" align="center">RATE</td><td width="15%" align="center">QTY</td><td width="15%" align="center">AMOUNT</td></tr><tr><td width="50%">{subscription description} Office</td><td width="20%" align="center"> </td><td align="center" width="15%"> </td><td align="right" width="15%">$ {invoice: subscription fee}</td></tr><tr><td width="50%">Additional Users</td><td width="20%" align="center">{invoice: price per user}$ / 1 user</td><td align="center" width="15%">{invoice: additional users}</td><td align="right" width="15%">${invoice: additional users fee}</td></tr><tr><td width="50%">Additional Storage</td><td width="20%" align="center">{invoice: price per storage}$ / 1Gb</td><td align="center" width="15%">{invoice: additional storage}</td><td align="right" width="15%">${invoice: additional storage charges}</td></tr><tr><td width="85%" colspan="3"> </td><td align="right" width="15%"><b>${invoice: subtotal}</b></td></tr><tr><td width="100%" colspan="4"> </td></tr><tr><td width="85%" colspan="3" align="left">GST  (Registration No. 896191244)</td><td align="right" width="15%">${invoice: gst/hst fee}</td></tr><tr><td width="100%" colspan="4"> </td></tr><tr><td width="85%" colspan="3" align="right"><b>TOTAL</b></td><td align="right" width="15%"><b>${invoice: total}</b></td></tr></table><br /><div><center><b>Thank you for using our services. Please do not hesitate to contact us, if we can be of further assistance in the future.</b></center></div>','2010-01-01');
INSERT INTO `system_templates` VALUES (0,'system','Reply to Request a Demo','The requested Demo',NULL,NULL,'support@officio.ca','','<p>Dear {prospects: salutation} {prospects: last name},</p>\n\n<p>Thank you for your interest in Officio.</p>   \n\nPlease click on the following link to view a three minute demo of Officio.\n<a href=\"http://www.officio.ca/demo/intro\">www.officio.ca/demo/intro</a>\n\n<p class=\"footer\">If you have any questions, please reply to this email or call us at:<br>\nToll free:&nbsp;&nbsp;&nbsp;1(888) 703-7073  (Canada and US)<br>\nOffice:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1(604) 986-6858\n</p>\n\n<p>\nRegards,<br>\nOfficio Support Team</p>','2010-01-01');
INSERT INTO `system_templates` VALUES (0,'system','Support Email','Officio Support {support email counter}', '{support request: email}', 'support@uniques.ca', '', '', 'Dear {support request: name},<br><br>Thank you for your inquiry.<br><br><br><br><br>If we can be of further assistance, please do not hesitate to contact us.<br><br>Best regards,<br>Officio Support Team<br><br>----------------------------------------------------------------------------------<br><br>Name: {support request: name}<br><br>Company: {support request: company}<br><br>Tel: {support request: phone}<br><br>Email: {support request: email}<br><br>Your Request:<br><br>{support request: request}<br>','2010-05-12');
INSERT INTO `system_templates` VALUES (0,'system','Signup Completed',NULL,NULL,NULL,NULL,'support@uniques.ca','<p>Dear {prospects: salutation} {prospects: last name},</p><p>Thank you for your interest in Officio.</p><p>Your Registration Key: {prospects: reg. key}</p><p><a href="http://secure.officio.ca/companywizard?key=%7Bprospects:%20reg.%20key%7D">http://secure.officio.ca/companywizard</a><a href="http://secure.officio.ca/companywizard?key=%7Bprospects:%20reg.%20key%7D">?key={prospects: reg. key}</a></p><a href="http://secure.officio.ca/wizard-prospect?key=%7Bprospects:%20reg.%20key%7D"></a><a href="http://secure.officio.ca/wizard-prospect?key=%7Bprospects:%20reg.%20key%7D"></a><p class="footer">Please note that the above key is valid until your company account is created.&nbsp; After that you can login as the admin and manage your company settings from within Officio.<br></p><p class="footer">If you have any questions, please reply to this email or call us at:<br>Toll free:&nbsp;&nbsp;&nbsp;1(888) 703-7073 (Canada and US)<br>Office:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1(604) 986-6858</p><p>Regards,<br>Officio Support Team</p','2010-01-01');
/*INSERT INTO `system_templates` VALUES (0,'system','Credit card not processed',NULL,NULL,NULL,NULL,NULL,'Dear {admin first name} {admin last name}<br><br>Our automatic system were not able to process your credit card for the Officio Subscription on {next billing date}.<br><br>Often this is because of expired card, account changes etc.<br><br>Please click on the following link and update your credit card information:<br>https://secure.officio.ca/payments/updatecc?{company ID}<br><br>Please then inform us by replying to the email to manualy process your last bill.<br><br>Regards<br>Uniques Software','2010-01-01');*/
INSERT INTO `system_templates` VALUES (0,'system','New user notification on Company Creation',NULL,NULL,NULL,NULL,NULL,'Welcome to Officio.<br>you have been added to Officio by your company admin.<br>Here is your Username: {user: username}<br>and password: {user: password}<br><br>to login, please go to the following link <a href="http://secure.officio.ca">secure.officio.ca</a><br><br>Regards,<br>Officio support team','2010-01-01');
INSERT INTO `system_templates` VALUES (0,'system','Forgotten Email','{user: email}',NULL,NULL,NULL,NULL,'Thank you. Your login details were sent to your e-mail. Please also ensure to check your spam folder.','2010-01-01');
INSERT INTO `system_templates` VALUES (0,'system','Special CC Charge','Special CC Charge',NULL,NULL,NULL,NULL,'<table border="1" cellpadding="3" width="50%"><tr><td>Amount:</td><td>${special cc charge: amount}</td></tr><tr><td>Notes:</td><td>{special cc charge: notes}</td></tr></table>','2010-01-01');
INSERT INTO `system_templates` VALUES (0,'system','Credit Card Processing Error', 'Officio Billing- Please update your credit card information', '{admin email}', 'support@uniques.ca', NULL, NULL, 'Dear {admin first name} {admin last name},<br><br>Our automated system shows the following error in processing your credit card:<br>{error message}<br><br>To update your credit card please update the credit card information under your company details.&nbsp;&nbsp; The step by step instructions as follows:<br><br>-&nbsp; Please login to your Officio account, <br>-&nbsp; Go to "Company Details" under your Admin tab, and <br>-&nbsp; Click on Account Details,<br>-&nbsp; Click on Update Credit Card on File<br>-&nbsp; Update your credit card information and click on Save<br><br>We will be automatically notified and will proceed automatically.<br><br><br>If\n we can be of further assistance, please do not hesitate to contact us.<br><br>Best\n regards,<br>Officio Billing Team', '2010-05-21');
INSERT INTO `system_templates` VALUES (0,'system','Credit card on file was updated by admin', 'Admin of  {company name}​ updated their credit card on file', 'support@uniques.ca', 'Support@uniques.ca', NULL, NULL, '<br>The following company has updated their credit card information on file.&nbsp;&nbsp; Please check their account and process any invoice that is outstanding.<br><br>Their detail:<br>Company Name: {company name}<br>Company City: {company city}<br>Company Phone 1: {company phone 1}<br>Company Phone 2: {company phone 2}<br>Admin:{admin first name} {admin last name}<br>Admin email: {admin email}', '2010-05-21');
INSERT INTO `system_templates` VALUES (0,'system','Notes or Special Invoice','Notes or Special Invoice',NULL,NULL,NULL,NULL,'&nbsp;<table border="1" cellpadding="5" cellspacing="0"><tbody><tr><td>BILL TO</td></tr><tr><td>{admin first name} {admin last name}<br>{company name}<br>{company address}</td></tr></tbody></table><br><br><br><table align="right" border="1" cellpadding="5" cellspacing="0"><tbody><tr><td align="center">INVOICE NO.</td><td align="center">DATE</td><td align="center">TERMS</td></tr><tr><td align="left">{invoice: number}</td><td align="center">{invoice: date}</td><td><br></td></tr></tbody></table><br><br><br><br><br><table border="1" cellpadding="5" cellspacing="0"><tbody><tr><td width="50%" align="center">DESCRIPTION</td><td width="20%" align="center">RATE</td><td width="15%" align="center">QTY</td><td width="15%" align="center">AMOUNT</td></tr><tr><td width="50%">{subscription description} Office</td><td width="20%" align="center"> <br></td><td width="15%" align="center"> <br></td><td width="15%" align="right">$ {invoice: subscription fee}</td></tr><tr><td width="50%">Additional Users</td><td width="20%" align="center">{invoice: price per user}$ / 1 user</td><td width="15%" align="center">{invoice: additional users}</td><td width="15%" align="right">${invoice: additional users fee}</td></tr><tr><td width="50%">Additional Storage</td><td width="20%" align="center">{invoice: price per storage}$ / 1Gb</td><td width="15%" align="center">{invoice: additional storage}</td><td width="15%" align="right">${invoice: additional storage charges}</td></tr><tr><td colspan="3" width="85%"> <br></td><td width="15%" align="right"><b>${invoice: subtotal}</b></td></tr><tr><td colspan="4" width="100%"> <br></td></tr><tr><td colspan="3" width="85%" align="left">GST  (Registration No. 896191244)</td><td width="15%" align="right">${invoice: gst/hst fee}</td></tr><tr><td colspan="4" width="100%"> <br></td></tr><tr><td colspan="3" width="85%" align="right"><b>TOTAL</b></td><td width="15%" align="right">${special cc charge: amount}</td></tr></tbody></table><br><p></p><center><b>Thank you for using our services. Please do not hesitate to contact us, if we can be of further assistance in the future.</b></center><p></p>','2010-01-01');
INSERT INTO `system_templates` VALUES (0,'system', 'Subscription Invoice', 'Invoice for {company package} - {billing frequency} Subscription', 'support@uniques.ca', '{company email}', '', 'support@uniques.ca', '<table>\r\n    <tr>\r\n        <td>\r\n            <img src="{settings: images path}/default/officio_logo_with_bg.png" alt="logo" width="600" height="94" />\r\n        </td>\r\n    </tr>\r\n\r\n    <tr>\r\n\r\n    <td>\r\n      <table width="600" align="center" bgcolor="#b3bcbf" border="0" cellpadding="6" cellspacing="1">\r\n        <tbody>\r\n          <tr>\r\n            <td bgcolor="#ffffff">\r\n              <table width="100%" border="0" cellpadding="5" cellspacing="0">\r\n                <tbody>\r\n                  <tr>\r\n                    <td style="font: 11px Arial,Helvetica,sans-serif; text-align: left;" bgcolor="#f0f0f0">\r\n\r\n                        <table width="100%" border="0" cellpadding="5">\r\n                          <tbody>\r\n                            <tr>\r\n                              <td>\r\n                                  <div style="font: 11px Arial,Helvetica,sans-serif;">Dear {admin first name} {admin last name},<br><br>Thank you for your order. Here are your order details:</div>\r\n                              </td>\r\n                            </tr>\r\n                          </tbody>\r\n                        </table>\r\n\r\n                      <table width="100%" border="1" cellpadding="6">\r\n                        <tbody>\r\n                          <tr>\r\n                            <td bgcolor="#ffffff">\r\n                              <table width="100%" border="0" cellpadding="0" cellspacing="0">\r\n                                <tbody>\r\n                                  <tr>\r\n                                    <td style="font: 11px Arial,Helvetica,sans-serif;" nowrap="nowrap"><span style="color: #4F8CBC; font-weight: bold;">{company package} - {billing frequency} Subscription</span><br>\r\n                                        <strong>Licensee Name:</strong>&nbsp;&nbsp;{company name}\r\n                                    </td>\r\n                                  </tr>\r\n                                </tbody>\r\n                              </table>\r\n                            </td>\r\n                          </tr>\r\n                        </tbody>\r\n                      </table>\r\n\r\n                      <br>\r\n\r\n                      <table width="100%" bgcolor="#f0f0f0" border="0" cellpadding="0" cellspacing="0">\r\n                        <tbody>\r\n                          <tr>\r\n                            <td colspan="4" style="font: 11px Arial,Helvetica,sans-serif;" bgcolor="#e3e2e2" nowrap="nowrap">\r\n                              <span style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Your order summary:</span><br>\r\n                              <table width="100%" border="0" cellpadding="5" cellspacing="0">\r\n                                <tbody>\r\n                                    <tr>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;" width="20%">Invoice #:</td>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif;">{invoice: number}</td>\r\n                                    </tr>\r\n                                    <tr>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Date:</td>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif;">{invoice: date}</td>\r\n                                    </tr>\r\n                                    <tr>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Next billing date:</td>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif;">{next billing date}</td>\r\n                                    </tr>\r\n                                </tbody>\r\n                              </table>\r\n                            </td>\r\n                          </tr>\r\n                        </tbody>\r\n                      </table>\r\n                        \r\n                      <br>\r\n\r\n                        <table width="100%" bgcolor="#f0f0f0" border="0" cellpadding="3" cellspacing="0" style=''background: url("{settings: images path}/default/officio_watermark.png") no-repeat center;''>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" width="40%"><strong>Description</strong></td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center" width="20%"><strong>Qty</strong></td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center" width="20%"><strong>Unit Price</strong></td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right" width="20%"><strong>Amount</strong></td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;"><span style="font-weight: bold;">{company package} - {billing frequency} Subscription</span>\r\n                                      <br/>Includes:<br>\r\n                                        - {invoice: free storage} Gb of space<br>\r\n                                        - {invoice: free users} user license(s)\r\n                                  </td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: quantity}</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: currency} {invoice: subscription fee}</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: subscription fee}</td>\r\n                            </tr>\r\n                            <tr {invoice: hide if empty discount}>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">Discount</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} -{invoice: discount}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Additional user license(s)</td>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: additional users}</td>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: currency} {invoice: price per user}</td>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: additional users fee}</td>\r\n                            </tr>\r\n                            <tr><td colspan="4"><hr></td></tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">Sub Total</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: subtotal}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td colspan="3" style="font: 11px Arial,Helvetica,sans-serif;" align="right">GST/HST (Registration No. 896191244)</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: gst/hst fee}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;" align="right">Total</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: total}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;" align="right">Paid</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} -{invoice: amount paid}</td>\r\n                            </tr>\r\n                            <tr>\r\n                              <td colspan="4" nowrap="nowrap" height="38">\r\n                                <table width="100%" border="0">\r\n                                    <tr>\r\n                                      <td style="font: 11px Arial,Helvetica,sans-serif;" width="18%" nowrap="nowrap"><strong>Payment method:</strong></td>\r\n                                      <td style="font: 11px Arial,Helvetica,sans-serif;" width="82%">{invoice: payment method}</td>\r\n                                    </tr>\r\n                                </table>\r\n                              </td>\r\n                            </tr>\r\n                        </table>\r\n\r\n                        <table width="100%" border="0" cellpadding="5">\r\n                          <tbody>\r\n                            <tr>\r\n                              <td style="font: 11px Arial,Helvetica,sans-serif;">\r\n                                  <p><strong>A couple of quick notes and reminders:</strong></p>\r\n                                  <ul>\r\n                                    <li><strong>Payment:</strong> Your credit card&nbsp; has been debited with {invoice: currency} {invoice: amount paid}, your card statement will list &quot;UNIQUES SOFTWARE&quot;.</li>\r\n                                    <li><strong>My account:</strong> You can always access your invoices by logging into Officio, and going to Admin | Company Details | Company Invoices. </li>\r\n                                  </ul>\r\n                                  <p>Thank you for your order. If we can do anything to enhance your experience with Officio, please do not hesitate to let us know.</p>\r\n                                  <br><br>\r\n                                  <p>Best regards,</p>\r\n                                  <p><strong>The Officio Team</strong></p>\r\n                                  <p>officio.ca, a product of Uniques Software Corp.</p>\r\n                              </td>\r\n                            </tr>\r\n                          </tbody>\r\n                        </table>\r\n\r\n                        <p>\r\n                    </td>\r\n                  </tr>\r\n                </tbody>\r\n              </table>\r\n            </td>\r\n          </tr>\r\n        </tbody>\r\n      </table>\r\n    </td>\r\n  </tr>\r\n</table>', '2011-03-17');
INSERT INTO `system_templates` VALUES (0,'system', 'Renew Invoice', 'Invoice for {company package} - {billing frequency} Renew', 'support@uniques.ca', '{company email}', '', 'support@uniques.ca', '<table>\r\n    <tr>\r\n        <td>\r\n            <img src="{settings: images path}/default/officio_logo_with_bg.png" alt="logo" width="600" height="94" />\r\n        </td>\r\n    </tr>\r\n\r\n    <tr>\r\n\r\n    <td>\r\n      <table width="600" align="center" bgcolor="#b3bcbf" border="0" cellpadding="6" cellspacing="1">\r\n        <tbody>\r\n          <tr>\r\n            <td bgcolor="#ffffff">\r\n              <table width="100%" border="0" cellpadding="5" cellspacing="0">\r\n                <tbody>\r\n                  <tr>\r\n                    <td style="font: 11px Arial,Helvetica,sans-serif; text-align: left;" bgcolor="#f0f0f0">\r\n\r\n                        <table width="100%" border="0" cellpadding="5">\r\n                          <tbody>\r\n                            <tr>\r\n                              <td>\r\n                                  <div style="font: 11px Arial,Helvetica,sans-serif;">Dear {admin first name} {admin last name},<br><br>Thank you for your order. Here are your order details:</div>\r\n                              </td>\r\n                            </tr>\r\n                          </tbody>\r\n                        </table>\r\n\r\n                      <table width="100%" border="1" cellpadding="6">\r\n                        <tbody>\r\n                          <tr>\r\n                            <td bgcolor="#ffffff">\r\n                              <table width="100%" border="0" cellpadding="0" cellspacing="0">\r\n                                <tbody>\r\n                                  <tr>\r\n                                    <td style="font: 11px Arial,Helvetica,sans-serif;" nowrap="nowrap"><span style="color: #4F8CBC; font-weight: bold;">{company package} - {billing frequency} Subscription</span><br>\r\n                                        <strong>Licensee Name:</strong>&nbsp;&nbsp;{company name}\r\n                                    </td>\r\n                                  </tr>\r\n                                </tbody>\r\n                              </table>\r\n                            </td>\r\n                          </tr>\r\n                        </tbody>\r\n                      </table>\r\n\r\n                      <br>\r\n\r\n                      <table width="100%" bgcolor="#f0f0f0" border="0" cellpadding="0" cellspacing="0">\r\n                        <tbody>\r\n                          <tr>\r\n                            <td colspan="4" style="font: 11px Arial,Helvetica,sans-serif;" bgcolor="#e3e2e2" nowrap="nowrap">\r\n                              <span style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Your order summary:</span><br>\r\n                              <table width="100%" border="0" cellpadding="5" cellspacing="0">\r\n                                <tbody>\r\n                                    <tr>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;" width="20%">Invoice #:</td>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif;">{invoice: number}</td>\r\n                                    </tr>\r\n                                    <tr>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Date:</td>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif;">{invoice: date}</td>\r\n                                    </tr>\r\n                                    <tr>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Next billing date:</td>\r\n                                        <td style="font: 11px Arial,Helvetica,sans-serif;">{next billing date}</td>\r\n                                    </tr>\r\n                                </tbody>\r\n                              </table>\r\n                            </td>\r\n                          </tr>\r\n                        </tbody>\r\n                      </table>\r\n                        \r\n                      <br>\r\n\r\n                        <table width="100%" bgcolor="#f0f0f0" border="0" cellpadding="3" cellspacing="0" style=''background: url("{settings: images path}/default/officio_watermark.png") no-repeat center;''>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" width="40%"><strong>Description</strong></td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center" width="20%"><strong>Qty</strong></td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center" width="20%"><strong>Unit Price</strong></td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right" width="20%"><strong>Amount</strong></td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;"><span style="font-weight: bold;">{company package} - {billing frequency} Subscription</span>\r\n                                      <br/>Includes:<br>\r\n                                        - {invoice: free storage} Gb of space<br>\r\n                                        - {invoice: free users} user license(s)\r\n                                  </td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: quantity}</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: currency} {invoice: subscription fee}</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: subscription fee}</td>\r\n                            </tr>\r\n                            <tr {invoice: hide if empty discount}>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">Discount</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} -{invoice: discount}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;">Additional user license(s)</td>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: additional users}</td>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">{invoice: currency} {invoice: price per user}</td>\r\n                                <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: additional users fee}</td>\r\n                            </tr>\r\n                            <tr><td colspan="4"><hr></td></tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">Sub Total</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: subtotal}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td colspan="3" style="font: 11px Arial,Helvetica,sans-serif;" align="right">GST/HST (Registration No. 896191244)</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: gst/hst fee}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;" align="right">Total</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} {invoice: total}</td>\r\n                            </tr>\r\n                            <tr>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="center">&nbsp;</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif; font-weight: bold;" align="right">Paid</td>\r\n                                  <td style="font: 11px Arial,Helvetica,sans-serif;" align="right">{invoice: currency} -{invoice: amount paid}</td>\r\n                            </tr>\r\n                            <tr>\r\n                              <td colspan="4" nowrap="nowrap" height="38">\r\n                                <table width="100%" border="0">\r\n                                    <tr>\r\n                                      <td style="font: 11px Arial,Helvetica,sans-serif;" width="18%" nowrap="nowrap"><strong>Payment method:</strong></td>\r\n                                      <td style="font: 11px Arial,Helvetica,sans-serif;" width="82%">{invoice: payment method}</td>\r\n                                    </tr>\r\n                                </table>\r\n                              </td>\r\n                            </tr>\r\n                        </table>\r\n\r\n                        <table width="100%" border="0" cellpadding="5">\r\n                          <tbody>\r\n                            <tr>\r\n                              <td style="font: 11px Arial,Helvetica,sans-serif;">\r\n                                  <p><strong>A couple of quick notes and reminders:</strong></p>\r\n                                  <ul>\r\n                                    <li><strong>Payment:</strong> Your credit card&nbsp; has been debited with {invoice: currency} {invoice: amount paid}, your card statement will list &quot;UNIQUES SOFTWARE&quot;.</li>\r\n                                    <li><strong>My account:</strong> You can always access your invoices by logging into Officio, and going to Admin | Company Details | Company Invoices. </li>\r\n                                  </ul>\r\n                                  <p>Thank you for your order. If we can do anything to enhance your experience with Officio, please do not hesitate to let us know.</p>\r\n                                  <br><br>\r\n                                  <p>Best regards,</p>\r\n                                  <p><strong>The Officio Team</strong></p>\r\n                                  <p>officio.ca, a product of Uniques Software Corp.</p>\r\n                              </td>\r\n                            </tr>\r\n                          </tbody>\r\n                        </table>\r\n\r\n                        <p>\r\n                    </td>\r\n                  </tr>\r\n                </tbody>\r\n              </table>\r\n            </td>\r\n          </tr>\r\n        </tbody>\r\n      </table>\r\n    </td>\r\n  </tr>\r\n</table>', '2011-03-17');
INSERT INTO `system_templates` VALUES (0, 'system', 'Password Changed', 'Officio - Password Changed', 'support@uniques.ca', '{user: email}', '', '', '<font face="arial" size="2">Dear {user: first name},</font>\n<div>Your password was changed.</div>', '2014-04-25');
INSERT INTO `system_templates` VALUES (0, 'system', 'User account locked', 'Officio - User account locked', 'support@uniques.ca', 'support@uniques.ca', '', '', '<div>User account locked: {user: username}.</div>', NOW());
/*
 * Admin/Superadmin tables
 */
DROP TABLE IF EXISTS `prospects`;
CREATE TABLE `prospects` (
  `prospect_id` int(11) unsigned NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `salutation` varchar(255) default NULL,
  `name` varchar(255) default NULL,
  `last_name` varchar(255) default NULL,
  `company` varchar(255) default NULL,
  `company_abn` VARCHAR(255) default NULL,
  `email` varchar(255) default NULL,
  `phone_w` varchar(255) default NULL,
  `phone_m` varchar(255) default NULL,
  `source` varchar(255) default NULL,
  `key` varchar(255) default NULL,
  `key_status` enum('Active','Used Once','Disable') default 'Active',
  `address` tinytext,
  `city` varchar(255) default NULL,
  `state` varchar(255) default NULL,
  `country` varchar(3) default NULL,
  `zip` varchar(50) default NULL,
  `package_type` varchar(255) default NULL,
  `support` enum('Y', 'N') default NULL,
  `payment_term` varchar(255) default NULL,
  `paymentech_profile_id` varchar(255) default NULL,
  `paymentech_mode_of_payment` ENUM('Visa','Mastercard') NULL DEFAULT NULL,
  `status` enum('Active','Closed') default 'Active',
  `notes` tinytext,
  `sign_in_date` date default NULL,
  `subscription_fee` double(11,2) unsigned default NULL,
  `support_fee` double(11,2) unsigned default NULL,
  `free_users` int(11) unsigned default NULL,
  `extra_users` INT(11) UNSIGNED NULL DEFAULT NULL,
  `free_storage` int(11) unsigned default NULL,
  PRIMARY KEY  (`prospect_id`),
  CONSTRAINT `FK_prospects_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `automatic_reminders`;
CREATE TABLE `automatic_reminders` (
  `automatic_reminder_id` int(11) unsigned NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `template_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `assigned_to` tinyint(3) default NULL,
  `assign_to_role_id` int(11) default NULL,
  `assign_to_member_id` bigint(20) default NULL,
  `type` ENUM('TRIGGER','CLIENT_PROFILE','PROFILE','FILESTATUS') NULL DEFAULT 'TRIGGER',
  `trigger` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT '1 - Payment is due, 2 - Client mark a form as Complete, 3 -Client uploads Documents',
  `number` int(11) unsigned default NULL,
  `days` enum('CALENDAR','BUSINESS') default 'CALENDAR',
  `ba` enum('BEFORE','AFTER') default 'AFTER',
  `prof` int(11) unsigned default NULL,
  `file_status` int(11) unsigned default NULL,
  `reminder` text,
  `message` TEXT NULL,
  `active_clients_only` ENUM('Y','N') NULL DEFAULT 'Y',
  `notify_client` ENUM('Y','N') NULL DEFAULT 'N',
  `create_date` date default NULL,
  PRIMARY KEY  (`automatic_reminder_id`),
  CONSTRAINT `FK_automatic_reminders_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_automatic_reminders_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`) ON UPDATE SET NULL ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `automatic_reminders_processed`;
CREATE TABLE `automatic_reminders_processed` (
  `automatic_reminders_processed_id` int(11) unsigned NOT NULL auto_increment,
  `automatic_reminder_id` int(11) unsigned NULL,
  `member_id` BIGINT(20) NULL DEFAULT NULL,
  `year` YEAR NULL,
  PRIMARY KEY  (`automatic_reminders_processed_id`),
  CONSTRAINT `FK_automatic_reminders_processed_automatic_reminders` FOREIGN KEY (`automatic_reminder_id`) REFERENCES `automatic_reminders` (`automatic_reminder_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_automatic_reminders_processed_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `acl_modules`;
CREATE TABLE `acl_modules` (
  `module_id` varchar(50) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  PRIMARY KEY  (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_modules` (`module_id`, `module_name`) VALUES
  ('default', 'Staff Tabs'),
  ('superadmin', 'Make the user an Admin, show admin tab, and give access to all offices to the user.'),
  ('trust-account', 'Client Account'),
  ('documents', 'Files & Folders'),
  ('help', 'Help'),
  ('templates', 'Templates'),
  ('clients', 'Clients'),
  ('tasks', 'Tasks'),
  ('notes', 'Notes'),
  ('links', 'Links'),
  ('forms', 'Forms'),
  ('calendar', 'Calendar'),
  ('mail', 'Mail'),
  ('news', 'Announcements'),
  ('rss', 'Immigration News'),
  ('signup', 'Signup'),
  ('system', 'System'),
  ('prospects', 'Prospects'),
  ('qnr', 'Questionnaires'),
  ('websites', 'Company Websites'),
  ('applicants', 'Applicants'),
  ('profile', 'User Profile')
;

DROP TABLE IF EXISTS `acl_roles`;
CREATE TABLE `acl_roles` (
  `role_id` int(11) NOT NULL auto_increment,
  `company_id` BIGINT(20) NOT NULL DEFAULT 0,
  `role_name` varchar(50) NOT NULL,
  `role_type` varchar(50) NOT NULL,
  `role_parent_id` varchar(50) NOT NULL,
  `role_child_id` varchar(50) default NULL,
  `role_visible` tinyint(1) NOT NULL default '0',
  `role_status` int(1) NOT NULL default '1',
  `can_admin_edit` int(1) NOT NULL default '0',
  `role_regTime` int(11) NOT NULL default '0',
  PRIMARY KEY  (`role_parent_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `FK_acl_roles_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


INSERT INTO `acl_roles` (`role_id`, `role_name`, `role_type`, `role_parent_id`, `role_child_id`, `role_visible`, `role_status`, `company_id`, `role_regTime`) VALUES
 (1, 'Default Guest Role',             'guest',             'guest',             NULL,     0, 1, 0, UNIX_TIMESTAMP()),
 (2, 'Default Individual Client Role', 'individual_client', 'individual_client', 'guest',  1, 1, 0, UNIX_TIMESTAMP()),
 (3, 'Default User Role',              'user',              'user',              'guest',  1, 1, 0, UNIX_TIMESTAMP()),
 (4, 'Default Admin Role',             'admin',             'admin',             'user',   1, 1, 0, UNIX_TIMESTAMP()),
 (5, 'Default Superadmin Role',        'superadmin',        'superadmin',        'admin',  0, 1, 0, UNIX_TIMESTAMP()),
 (6, 'Site Admin',                     'superadmin',        'siteadmin',         'guest',  0, 1, 0, UNIX_TIMESTAMP()),
 (7, 'Support Admin',                  'superadmin',        'supportadmin',      'guest',  0, 1, 0, UNIX_TIMESTAMP()),
 (2, 'Default Employer Client Role',   'employer_client',   'employer_client',   'guest',  1, 1, 0, UNIX_TIMESTAMP()),
 (8, 'Support-CA',                     'superadmin',        'supportadmin_ca',   'guest',  0, 1, 0, UNIX_TIMESTAMP());

DROP TABLE IF EXISTS `acl_rules`;
CREATE TABLE  `acl_rules` (
  `rule_id` int(11) NOT NULL auto_increment,
  `rule_parent_id` int(11) default NULL,
  `module_id` varchar(50) NOT NULL,
  `rule_description` varchar(100) NOT NULL,
  `rule_check_id` varchar(100) NOT NULL,
  `superadmin_only` tinyint(1) NOT NULL default '0',
  `rule_visible` tinyint(1) NOT NULL default '0',
  `rule_order` int(11) NOT NULL default '0',

  PRIMARY KEY  (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
 (1,NULL, 'default', 'Default access rights in default module', 'access-to-default',0,0,0),
 (2,NULL, 'superadmin', 'Default access rights in superadmin module', 'access-to-superadmin',0,0,0),

 (3,NULL,'','Default full access to whole web site', 'whole-web-site',0,0,0),

 (4,NULL, 'superadmin', 'Admin Tab', 'admin-view',0,1,0),
 
 (5,NULL, 'default', 'Staff Tabs', 'staff-tabs-view', 0,1,0),

 (6,   5, 'default', 'Home page', 'index-view', 0, 1, 2),
 (7,   6, 'notes',   'Notes on Homepage', 'user-notes-view',0,1,0),
 (130, 6, 'links',   'My Bookmarks', 'links-view', 0, 1, 0),
 (170, 6, 'news',    'Announcements', 'news-view', 0,1,0),
 (175, 6, 'rss',     'Immigration News', 'rss-view', 0,1,0),

 (10, 5,  'clients', 'Clients',       'clients-view', 0, 1, 4),
 (11, 10, 'clients', 'Change/Save Profile',  'clients-profile-edit', 0, 1, 6),
 (12, 10, 'clients', 'Delete Client', 'clients-profile-delete', 0, 1, 2),
 (13, 10, 'clients', 'New Client',    'clients-profile-new', 0, 1, 4),

 (20, 5, 'clients', 'Employer Client Login', 'clients-employer-client-login', 0, 1, 5),
 (21, 5, 'clients', 'Individual Client Login', 'clients-individual-client-login', 0, 1, 6),

 (25, 10, 'tasks', 'Tasks', 'clients-tasks-view', 0,1,7),
 (30, 10, 'notes', 'Notes and Activities', 'clients-notes-view', 0,1,8),
 (31, 30, 'notes', 'Add Notes',    'clients-notes-add', 0,1,0),
 (32, 30, 'notes', 'Edit Notes',   'clients-notes-edit', 0,1,0),
 (33, 30, 'notes', 'Delete Notes', 'clients-notes-delete', 0,1,0),
 
 (60, 10, 'clients', 'Client Documents', 'client-documents-view', 0,1,12),
 (61, 4, 'superadmin','Client Documents Settings','client-documents-settings-view',0,1,3),
 (70, 10, 'clients', 'Accounting',       'clients-accounting-view', 0,1, 14),

 (80, 10, 'clients', 'Time Tracker', 'clients-time-tracker', 0, 1, 15),
 (81, 80, 'clients', 'Popup Time Tracker Dialog', 'clients-time-tracker-popup-dialog', 0, 1, 16),
 (82, 80, 'clients', 'Show Time Log', 'clients-time-tracker-show', 0, 1, 17),
 (83, 82, 'clients', 'Time Log Add', 'clients-time-tracker-add', 0, 1, 18),
 (84, 82, 'clients', 'Time Log Edit', 'clients-time-tracker-edit', 0, 1, 19),
 (85, 82, 'clients', 'Time Log Delete', 'clients-time-tracker-delete', 0, 1, 20),

 (90, 10, 'clients', 'Advanced search', 'clients-advanced-search-run', 0, 1, 21),
 (91, 90, 'clients', 'Export', 'clients-advanced-search-export', 0, 1, 0),
 (92, 90, 'clients', 'Print', 'clients-advanced-search-print', 0, 1, 0),

 (95, 10, 'applicants', 'Office/Queue', 'clients-queue-run', 0, 1, 22),
 (96, 95, 'applicants', 'Export', 'clients-queue-export', 0, 1, 0),
 (97, 95, 'applicants', 'Print', 'clients-queue-print', 0, 1, 0),
 (98, 95, 'applicants', 'Push to Office/Queue', 'clients-queue-push-to-queue', 0, 1, 0),
 (99, 95, 'applicants', 'Change File Status', 'clients-queue-change-file-status', 0, 1, 0),

 (100, 5, 'documents', 'My Documents', 'my-documents-view', 0, 1, 10),
 
 (101, 110, 'trust-account', 'Client Account History',   'trust-account-history-view', 0,1,0),
 (102, 110, 'trust-account', 'Client Account Import',    'trust-account-import-view', 0,1,0),
 (103, 110, 'trust-account', 'Client Account Assign',    'trust-account-assign-view', 0,1,0),
 (104, 110, 'trust-account', 'Client Account Edit',      'trust-account-edit-view', 0,1,0),
 (105, 110, 'superadmin',    'Client Account Settings',  'trust-account-settings-view',0,1,0),
 (106, 100, 'documents',     'New Letter on Letterhead', 'new-letter-on-letterhead', 0,1,0),
 (110, 5,   'trust-account', 'Client Account',           'trust-account-view', 0,1,14),
 
 (140, 10,   'forms', 'Forms', 'forms-view', 0,1,10),
 (141, 140,  'forms', 'Enable Assign Forms', 'forms-assign', 0,1,0),
 (142, 140,  'forms', 'Can Finalize a Form', 'forms-finalize', 0,1,0),
 (143, 140,  'forms', 'Can Lock and Unlock Forms', 'forms-lock-unlock', 0,1,0),
 (144, 140,  'forms', 'Can Complete a Form', 'forms-complete', 0,1,0),
 
 (150, 5,    'calendar', 'Calendar', 'calendar-view', 0, 1, 8),
 
 (160, 5,    'mail', 'Mail', 'mail-view', 0, 1, 6),
 
 
 (180, 5, 'templates', 'Templates', 'templates-view', 0, 1, 12),
 (181, 180, 'templates', 'Manage templates', 'templates-manage', 0, 1, 0),

 (190, 5, 'help', 'Help', 'help-view', 0, 1, 18),
 (191, 5, 'help', 'F.A.Q.', 'faq-view', 0, 1, 19),
 (192, 5, 'help', 'F.A.Q.', 'faq-public-view', 0, 1, 20),

  (200, 5, 'prospects', 'Prospects', 'prospects-view', 0, 1, 20),
  (210, 5, 'tasks', 'My Tasks', 'tasks-view', 0, 1, 11),

  (220, 10, 'applicants', 'ABN/ACN Check', 'abn-check', 0, 1, 0),

 (300, 3, 'websites', 'Company Websites', 'websites-view', 0, 1, 0),

  (400, 5, 'applicants', 'Contacts', 'contacts-view', 0, 1, 30),
  (401, 400, 'applicants', 'Change/Save Profile', 'contacts-profile-edit', 0, 1, 31),
  (402, 400, 'applicants', 'Delete Contact', 'contacts-profile-delete', 0, 1, 32),
  (403, 400, 'applicants', 'New Contact', 'contacts-profile-new', 0, 1, 33),

  (500, 5, 'profile', 'User Profile', 'user-profile-view', 0, 1, 34),

/* Superadmin and admin section */
 (1000, 4, 'superadmin', 'Change My Password Page', 'admin-mypassword',0,1,0),

 (1010, 4,    'superadmin', 'Manage Roles', 'admin-roles-view', 0,1,0),
 (1011, 1010, 'superadmin', 'Add New Roles', 'admin-roles-add', 0,1,0),
 (1012, 1010, 'superadmin', 'View Roles Details', 'admin-roles-view-details', 0, 1, 0),
 (1013, 1010, 'superadmin', 'Delete Roles', 'admin-roles-delete', 0, 1, 0),
 (1014, 1012, 'superadmin', 'Edit Roles ', 'admin-roles-edit', 0, 1, 0),

 (1020, 4,    'superadmin', 'Manage Super Admin Users', 'manage-admin-user-view', 1,1,0),
 (1021, 1020, 'superadmin', 'Add New Super Admin User', 'manage-admin-user-add', 1,1,0),
 (1022, 1020, 'superadmin', 'Edit Super Admin User',    'manage-admin-user-edit', 1,1,0),
 (1023, 1020, 'superadmin', 'Delete Super Admin User',  'manage-admin-user-delete', 1,1,0),

 (1030, 4,    'superadmin', 'Manage Users', 'manage-members', 0,1,0),
 (1031, 1030, 'superadmin', 'Add Users', 'manage-members-add', 0,1,0),

 (1032, 1030, 'superadmin', 'View Users Details', 'manage-members-view-details', 0, 1, 0),
 (1033, 1030, 'superadmin', 'Delete Users', 'manage-members-delete', 0, 1, 0),
 (1034, 1032, 'superadmin', 'Edit Users', 'manage-members-edit', 0, 1, 0),
 (1035, 1032, 'superadmin', 'Change Users Password', 'manage-members-change-password', 0, 1, 0),

 (1040, 4,    'superadmin','Manage Companies','manage-company',0,1,0),
 (1042, 1040, 'superadmin', 'Edit Company', 'manage-company-edit', 0, 1, 1),
 (1043, 1040, 'superadmin', 'Delete Company', 'manage-company-delete', 1, 1, 2),
 (1044, 1040, 'superadmin', 'Login as User/Admin', 'manage-company-as-admin', 0, 1, 3),
 (1046, 1040, 'superadmin', 'Change Company Status', 'manage-company-change-status', 1, 1, 4),
 (1047, 1040, 'superadmin', 'View Companies List', 'manage-company-view-companies', 1, 1, 5),
 (1048, 1040, 'superadmin', 'Manage Company Email', 'manage-company-email', 1, 1, 0),
 (1049, 1042, 'superadmin', 'Edit Company Extra Details', 'edit-company-extra-details', 0, 1, 0),

 (1050, 4,  'superadmin','Manage Cases fields/groups/layouts', 'manage-groups-view', 0,1,0),
 (1051, 4,  'superadmin','Manage Individuals fields/groups/layouts', 'manage-individuals-fields', 0,1,0),
 (1052, 4,  'superadmin','Manage Employers fields/groups/layouts', 'manage-employers-fields', 0,1,0),
 (1053, 4,  'superadmin','Manage Contacts fields/groups/layouts', 'manage-contacts-fields', 0,1,0),
 (1054, 4,  'superadmin','Manage Internal Contacts fields/groups/layouts', 'manage-internals-fields', 0,1,0),

 (1060, 4,  'superadmin','Manage forms', 'manage-forms-view', 1,1,0),
 (1061, 1060, 'superadmin', 'Manage Landing Pages', 'manage-forms-view-landing-pages', 1, 1, 0),

 (1070, 4,  'superadmin','Announcements', 'manage-news-view', 1,1,0),
 (1080, 4,  'superadmin','Shared Templates', 'shared-templates-view', 1,1,0),
 (1090, 4, 'superadmin','Default Searches', 'default-searches-view', 1,1,0),
 (1100, 4, 'superadmin', 'Manage Templates', 'faq-view', 1, 1, 0),
 (1120, 4, 'superadmin','Automatic Tasks', 'automatic-reminders', 0,1,0),
 (1130, 4, 'system', 'System', 'system', 0, 0, 0),
 (1140, 4, 'superadmin', 'Manage Help', 'manage-faq-view', 1, 1, 0),
 (1150, 4, 'superadmin', 'Manage Prospects', 'manage-prospects', 1, 1, 0),
 (1160, 5, 'signup', 'Signup', 'signup', 0, 0, 0),
 (1170, 4, 'superadmin', 'Mail Server Settings', 'smtp-view', 1, 1, 0),
 (1180, 4, 'superadmin', 'Last Logged In Info', 'last-logged-in-view', 1, 1, 0),
 (1190, 4, 'superadmin', 'Manage Company Prospects', 'manage-company-prospects', 0, 1, 0),
 (1200, 4, 'superadmin', 'Manage GST/HST', 'manage-hst', 1, 1, 0),
 (1210, 4, 'superadmin', 'Manage CMI', 'manage-cmi', 1, 1, 0),
 (1220, 4, 'superadmin', 'Trial users pricing', 'manage-trial-users-pricing', 1, 1, 0),

 (1230, 4, 'superadmin', 'Manage PT Invoices', 'manage-invoices', 1, 1, 0),
 (1231, 1230, 'superadmin', 'Generate Invoice Template', 'manage-invoices-generate-template', 1, 0, 0),
 (1232, 1230, 'superadmin', 'Run Charge', 'manage-invoices-run-charge', 1, 0, 0),

 (1240, 4, 'superadmin', 'Manage pricing', 'manage-pricing', 1, 1, 0),
 (1250, 4, 'superadmin', 'Bad debts log', 'manage-bad-debts-log', 1, 1, 0),
 (1260, 4, 'superadmin', 'Automated billing log', 'automated-billing-log', 1, 1, 0),
 (1270, 4, 'superadmin', 'Manage PT Error codes', 'manage-pt-error-codes', 1, 1, 0),
 (1280, 4, 'superadmin', 'Accounts', 'manage-accounts-view', 1, 1, 0),
 (1290, 4, 'superadmin', 'Customize Forms', 'forms-default', 0, 1, 0),
 (1300, 4, 'superadmin', 'Statistics', 'statistics', 1, 1, 0),
 (1310, 4, 'superadmin', 'Import Clients', 'import-clients-view', 0, 1, 0),
 (1311, 4, 'superadmin', 'Import Client Notes', 'import-client-notes-view', 0, 1, 0),
 (1320, 4, 'superadmin', 'Prospects Matching', 'prospects', 1, 1, 0),
 (1330, 4, 'superadmin', 'Manage news black list', 'manage-rss-feed', 1, 1, 0),
 (1350, 1040, 'superadmin', 'Advanced Search', 'manage-advanced-search', 1, 1, 0),
 (1360, 1040, 'superadmin', 'Quick Search', 'run-companies-search', 1, 1, 0),
 (1370, 1040, 'superadmin', 'Allow Export', 'allow-export', 1, 0, 0),
 (1380, 4, 'superadmin', 'Manage system variables', 'system-variables', 1, 1, 0),
 (1390, 4, 'superadmin', 'View Superadmin Tab', 'admin-tab-view', 1, 1, 0),

 (1400, 4, 'superadmin', 'Company Website', 'company-website', 0, 1, 0),

 (1410, 4,    'superadmin', 'Access Logs', 'access-logs-view', 0,1,0),
 (1411, 1410, 'superadmin', 'Delete Access Logs', 'access-logs-delete', 0,1,0),

 (1420, 1040, 'superadmin', 'Manage Company Packages', 'manage-company-packages', 1, 1, 4),
 (1421, 1420, 'superadmin', 'Manage Company Packages Extra Details', 'manage-company-packages-extra-details', 1, 1, 0),

 (1430, 1040, 'superadmin', 'Send Mass Email', 'mass-email', 1, 0, 0),

 (1440, 1040, 'superadmin', 'Manage Company Tickets', 'manage-company-tickets', 1, 1, 0),
 (1441, 1440, 'superadmin', 'Add Company Tickets', 'manage-company-tickets-add', 1, 1, 0),
 (1442, 1440, 'superadmin', 'Manage Company Tickets Status', 'manage-company-tickets-status', 1, 1, 0),

  (1450, 4, 'superadmin', 'Manage Zoho settings', 'zoho-settings', 1, 1, 0),

 (2204, 4, 'superadmin', 'Contacts', 'manage-company-contacts-types', 0, 1, 0),

 (2210, 4, 'superadmin', 'Letterheads', 'manage-letterheads', 0, 1, 0)
;


DROP TABLE IF EXISTS `acl_rule_details`;
CREATE TABLE  `acl_rule_details` (
  `rule_id` int(11) NOT NULL,
  `module_id` varchar(50) NOT NULL,
  `resource_id` varchar(50) NOT NULL default '',
  `resource_privilege` varchar(50) NOT NULL default '',
  `rule_allow` tinyint(1) NOT NULL default '1',

  PRIMARY KEY  (`rule_id`,`module_id`,`resource_id`,`resource_privilege`),
  CONSTRAINT `FK_acl_rule_details_1` FOREIGN KEY `FK_acl_rule_details_1` (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
 /* Default Access Rights for Guest role */
 (1,'default','error','',1),
 (1,'default','auth','',1),
 (1,'default','index','cron',1),
 (1,'default','index','terms',1),
 (1,'default','index','privacy',1),
 (1,'documents','index','get-file',1),
 (1,'documents','index','get-image',1),
 (1,'documents','index','get-pdf',1),
 (1,'documents','index','open-file',1),
 (1,'documents','index','save-file',1),
 (1,'documents','index','open-pdf',1),

 (1,'prospects','index','get-file',1),
 (1,'prospects','index','save-file',1),

 (1,'documents','index','save-letter-template-file',1),
 (1,'documents','index','get-file',1),

 (1,'api','index','add-company',1),
 (1,'api','index','add-prospect',1),
 (1,'api','index','send-support-request',1),
 (1,'api','index','get-clients-list',1),
 (1,'api','index','receive-eml-file',1),
 (1,'api','index','add-recurring-invoice',1),
 (1,'api','index','check-username',1),
 (1,'api','index','run-recurring-payments',1),
 (1,'api','index','get-prices',1),
 (1,'api','remote','',1),
 (1,'api','gv','',1),
 (1,'wizard','index','',1),
 (1,'specialoffer','index','',1),
 (1,'cmi-signup','index','',1),
 (1,'freetrial','index','',1),
 (1,'companywizard','index','',1),
 (1,'qnr','index','index',1),
 (1,'qnr','index','save',1),
 (1,'signup','index','',1),
 (1,'forms','sync','',1),
 (2,'superadmin','auth','',1),
 (2,'superadmin','error','',1),

 /* Super admin can access to all sources */
 (3,'default','','',1),
 (3,'superadmin','','',1),
 (3,'calendar','','',1),
 (3,'clients','','',1),
 (3,'crm','','',1),
 (3,'documents','','',1),
 (3,'forms','','',1),
 (3,'help','','',1),
 (3,'links','','',1),
 (3,'mail','','',1),
 (3,'news','','',1),
 (3,'notes','','',1),
 (3,'prospects','','',1),
 (3,'qnr','','',1),
 (3,'signup','','',1),
 (3,'system','','',1),
 (3,'tasks','','',1),
 (3,'templates','','',1),
 (3,'trust-account','','',1),
 (3,'websites','','',1),
 (3, 'applicants', 'search', '', 1),
 (4,'superadmin','index','',1),
 
 (6,'default','admin','',1),
 (6,'default','index','',1),
 (6,'default','trial','',1),
 (6,'homepage','index','',1),
 (6,'clients','index','get-client-company-logo',1),
 (7,'notes','index','add',1),
 (7,'notes','index','edit',1),
 (7,'notes','index','delete',1),
 (7,'notes','index','get-note',1),
 (7,'notes','index','get-notes-list',1),

  (10, 'clients', 'index', 'get-sub-tab', 1),
  (10, 'applicants', 'index', '', 1),
  (10, 'applicants', 'search', '', 1),
  (10, 'applicants', 'profile', 'index', 1),
  (10, 'applicants', 'profile', 'check-employer-case', 1),
  (10, 'applicants', 'profile', 'load-employer-cases-list', 1),
  (10, 'applicants', 'profile', 'view-image', 1),
  (10, 'applicants', 'profile', 'get-login-info', 1),
  (10, 'applicants', 'profile', 'load', 1),
  (10, 'applicants', 'profile', 'load-short-info', 1),
  (10, 'applicants', 'profile', 'change-my-password', 1),
  (11, 'applicants', 'profile', 'save', 1),
  (11, 'applicants', 'profile', 'generate-case-number', 1),
  (11, 'applicants', 'profile', 'update-login-info', 1),
  (11, 'applicants', 'profile', 'delete-file', 1),
  (11, 'applicants', 'profile', 'download-file', 1),
  (11, 'applicants', 'profile', 'link-case-to-employer', 1),
  (12, 'applicants', 'profile', 'delete', 1),
  (13, 'applicants', 'profile', 'save', 1),
  (13, 'applicants', 'profile', 'update-login-info', 1),

  (20, 'applicants', 'index', 'index', 1),
  (21, 'applicants', 'index', 'index', 1),

 (25, 'tasks', 'index', '', 1),
 
 (30,'notes','index','get-notes-list',1),
 (30,'notes','index','get-notes',1),
 (30,'notes','index','get-note',1),
 (31,'notes','index','add',1),
 (32,'notes','index','edit',1),
 (33,'notes','index','delete',1),
 
 
 (60,'documents','index','',1),
 (61,'superadmin','client-documents','',1),
 (70,'clients','accounting','',1),

 (80, 'clients', 'time-tracker', 'index', 1),
 (80, 'clients', 'time-tracker', 'print', 1),
 (81, 'clients', 'time-tracker', 'create', 1),
 (82, 'clients', 'time-tracker', 'get-list', 1),
 (82, 'clients', 'time-tracker', 'mark-billed', 1),
 (83, 'clients', 'time-tracker', 'add', 1),
 (84, 'clients', 'time-tracker', 'edit', 1),
 (85, 'clients', 'time-tracker', 'delete', 1),

 (90, 'applicants', 'search', 'run-search', 1),
 (91, 'applicants', 'search', 'export-to-excel', 1),
 (92, 'applicants', 'search', 'print', 1),

 (95, 'applicants', 'queue', 'run', 1),
 (95, 'applicants', 'queue', 'load-settings', 1),
 (95, 'applicants', 'queue', 'save-settings', 1),
 (96, 'applicants', 'queue', 'export-to-excel', 1),
 (97, 'applicants', 'queue', 'print', 1),
 (98, 'applicants', 'queue', 'push-to-queue', 1),
 (99, 'applicants', 'queue', 'change-file-status', 1),

 (100,'documents','index','',1),
 (105, 'superadmin', 'trust-account-settings', 'settings', 1),

 (110,'trust-account','index','',1),
 (110,'clients','accounting','',1),
 (110,'default','deposit-types','',1),
 (110,'default','destination-account','',1),
 (110,'default','withdrawal-types','',1),
 
 (101,'trust-account','history','',1),
 (102,'trust-account','import','',1),
 (103,'trust-account','assign','',1),
 (104,'trust-account','edit','',1),
 (105,'superadmin','trust-account-settings','',1),
 (106, 'documents', 'index', 'get-letterheads-list', 1),


 (130,'links','index','',1),
 
 (140,'forms','index','list',1),
 (140,'forms','index','delete',1),
 (140,'forms','forms-folders','list',1),
 (140,'forms','index','get-family-members',1),
 (140,'forms','index','load-settings',1),
 (140,'forms','index','open-assigned-pdf',1),
 (140,'forms','index','open-assigned-xfdf',1),
 (140,'forms','index','open-pdf-and-xfdf',1),
 (140,'forms','forms-folders','files',1),
 (140,'forms','index','open-version-pdf',1),
 (140,'forms','index','open-embed-pdf',1),
 (140,'forms', 'index', 'print', 1),
 (140,'forms', 'index', 'open-pdf-print', 1),
 (140,'forms', 'index', 'email', 1),
 (140,'forms', 'index', 'search', 1),
 (140,'forms','index','open-xdp',1),
 (140,'forms','index','upload-revision',1),
 (140,'forms','index','download-revision',1),
 
 (141,'forms','index','assign',1),
 (142,'forms','index','finalize',1),
 (143,'forms','index','lock-and-unlock',1),
 (144,'forms','index','complete',1),
 
 (150,'calendar','index','',1),
 
 (160,'mail','index','',1),
 
 (170,'news','index','',1),
 (175,'rss','index','',1),

 (180,'templates','index','get-templates-list',1),
 (180,'templates','index','get-message',1),
 (180,'templates','index','get-email-template',1),
 (180,'templates','index','view-pdf',1),
 (180,'templates','index','show-pdf',1),
 (181,'templates','index','',1),

 (190,'help','index','',1),
 (192,'help','public','',1),

 (200, 'prospects', 'index', '', 1),
 (200, 'superadmin', 'manage-company-prospects', 'get-templates-list', 1),

 (210, 'tasks', 'index', '', 1),

 (220, 'applicants', 'index', 'open-link', 1),

 (300, 'websites', 'index', '', 1),

  (400, 'applicants', 'index', '', 1),
  (400, 'applicants', 'search', '', 1),
  (400, 'applicants', 'profile', 'index', 1),
  (400, 'applicants', 'profile', 'check-employer-case', 1),
  (400, 'applicants', 'profile', 'load-employer-cases-list', 1),
  (400, 'applicants', 'profile', 'view-image', 1),
  (400, 'applicants', 'profile', 'get-login-info', 1),
  (400, 'applicants', 'profile', 'load', 1),
  (400, 'applicants', 'profile', 'load-short-info', 1),
  (401, 'applicants', 'profile', 'save', 1),
  (401, 'applicants', 'profile', 'update-login-info', 1),
  (401, 'applicants', 'profile', 'delete-image', 1),
  (402, 'applicants', 'profile', 'delete', 1),
  (403, 'applicants', 'profile', 'save', 1),
  (403, 'applicants', 'profile', 'update-login-info', 1),

  (500, 'profile', 'index', '', 1),
 
 (1000,'superadmin','change-my-password','',1),

 (1010, 'superadmin','roles','index',1),
 (1011, 'superadmin','roles','add',1),
 (1011, 'superadmin','roles','check-role',1),
 (1012, 'superadmin','roles','edit',1),
 (1012, 'superadmin','roles','check-role',1),
 (1013, 'superadmin','roles','delete',1),
 (1014, 'superadmin', 'roles', 'edit-extra-details', 1),

 (1020,'superadmin','manage-admin-users','index',1),
 (1021,'superadmin','manage-admin-users','add',1),
 (1021,'superadmin','manage-admin-users','check-username',1),
 (1022,'superadmin','manage-admin-users','edit',1),
 (1022,'superadmin','manage-admin-users','check-username',1),
 (1023,'superadmin','manage-admin-users','delete',1),

 (1030,'superadmin','manage-members','index',1),
 (1031,'superadmin','manage-members','add',1),
 (1031, 'superadmin', 'manage-members', 'check-is-user-exists', 1),
 (1032,'superadmin','manage-members','edit',1),
 (1033,'superadmin','manage-members','delete',1),
 (1034, 'superadmin', 'manage-members', 'edit-extra-details', 1),
 (1035, 'superadmin', 'manage-members', 'change-password', 1),
 (1030,'superadmin','manage-members','get-email-accounts',1),
 (1030,'superadmin','manage-members','save-email-account',1),
 (1030,'superadmin','manage-members','delete-email-account',1),
 (1030,'superadmin','manage-members','set-email-account-by-default',1),
 (1030,'superadmin','manage-members','test-mail-settings',1),
 
 (1040, 'superadmin', 'manage-company','get-company-logo',1),
 (1040, 'superadmin', 'manage-company','index',1),
 (1040, 'superadmin', 'manage-company','get-invoice',1),
 (1040, 'superadmin', 'manage-company', 'show-invoice-pdf', 1),
 (1040, 'superadmin', 'manage-company', 'update-packages-cc', 1),
 (1040, 'superadmin', 'manage-company', 'check-cc-info', 1),
 (1040, 'superadmin', 'manage-own-company','index',1),
 (1040, 'superadmin', 'manage-own-company', 'file', 1),
 (1040, 'superadmin', 'manage-own-company', 'category', 1),
 (1040, 'superadmin', 'manage-own-company', 'visa', 1),
 (1040, 'superadmin', 'manage-own-company', 'office', 1),
 (1040, 'superadmin', 'manage-company', 'case-number-settings', 1),
 (1040, 'superadmin', 'manage-company', 'case-number-settings-save', 1),
 (1040, 'superadmin', 'manage-company', 'get-company-details', 1),

 (1041,'superadmin','manage-company','add',1),
 (1042,'superadmin','manage-company','edit',1),
 (1042,'superadmin','manage-company','remove-company-logo',1),
 (1043,'superadmin','manage-company','delete',1),
 (1044,'superadmin','manage-company-as-admin','index',1),
 (1046,'superadmin','manage-company','update-status',1),
 (1047,'superadmin','manage-company','get-companies',1),
 (1048, 'superadmin', 'manage-company', 'email', 1),
 (1049, 'superadmin', 'manage-company', '', 1),
 
 (1050,'superadmin','manage-fields-groups','',1),
 (1050,'superadmin','manage-company','options-get',1),
 (1050,'superadmin','manage-company','options-manage',1),
 (1050,'superadmin','manage-company','options-delete',1),

 (1051,'superadmin','manage-applicant-fields-groups','index',1),
 (1051,'superadmin','manage-applicant-fields-groups','individuals',1),
 (1051,'superadmin','manage-applicant-fields-groups','add-block',1),
 (1051,'superadmin','manage-applicant-fields-groups','edit-block',1),
 (1051,'superadmin','manage-applicant-fields-groups','remove-block',1),
 (1051,'superadmin','manage-applicant-fields-groups','add-group',1),
 (1051,'superadmin','manage-applicant-fields-groups','edit-group',1),
 (1051,'superadmin','manage-applicant-fields-groups','delete-group',1),
 (1051,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
 (1051,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
 (1051,'superadmin','manage-applicant-fields-groups','get-field-info',1),
 (1051,'superadmin','manage-applicant-fields-groups','delete-field',1),
 (1051,'superadmin','manage-applicant-fields-groups','save-order',1),
 (1051,'superadmin','manage-applicant-fields-groups','edit-field',1),

 (1052,'superadmin','manage-applicant-fields-groups','index',1),
 (1052,'superadmin','manage-applicant-fields-groups','employers',1),
 (1052,'superadmin','manage-applicant-fields-groups','add-block',1),
 (1052,'superadmin','manage-applicant-fields-groups','edit-block',1),
 (1052,'superadmin','manage-applicant-fields-groups','remove-block',1),
 (1052,'superadmin','manage-applicant-fields-groups','add-group',1),
 (1052,'superadmin','manage-applicant-fields-groups','edit-group',1),
 (1052,'superadmin','manage-applicant-fields-groups','delete-group',1),
 (1052,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
 (1052,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
 (1052,'superadmin','manage-applicant-fields-groups','get-field-info',1),
 (1052,'superadmin','manage-applicant-fields-groups','delete-field',1),
 (1052,'superadmin','manage-applicant-fields-groups','save-order',1),
 (1052,'superadmin','manage-applicant-fields-groups','edit-field',1),

 (1053,'superadmin','manage-applicant-fields-groups','index',1),
 (1053,'superadmin','manage-applicant-fields-groups','applicant-types',1),
 (1053,'superadmin','manage-applicant-fields-groups','get-applicant-types',1),
 (1053,'superadmin','manage-applicant-fields-groups','add-applicant-type',1),
 (1053,'superadmin','manage-applicant-fields-groups','update-applicant-type',1),
 (1053,'superadmin','manage-applicant-fields-groups','delete-applicant-type',1),
 (1053,'superadmin','manage-applicant-fields-groups','add-block',1),
 (1053,'superadmin','manage-applicant-fields-groups','edit-block',1),
 (1053,'superadmin','manage-applicant-fields-groups','remove-block',1),
 (1053,'superadmin','manage-applicant-fields-groups','add-group',1),
 (1053,'superadmin','manage-applicant-fields-groups','edit-group',1),
 (1053,'superadmin','manage-applicant-fields-groups','delete-group',1),
 (1053,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
 (1053,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
 (1053,'superadmin','manage-applicant-fields-groups','get-field-info',1),
 (1053,'superadmin','manage-applicant-fields-groups','delete-field',1),
 (1053,'superadmin','manage-applicant-fields-groups','save-order',1),
 (1053,'superadmin','manage-applicant-fields-groups','edit-field',1),

 (1054,'superadmin','manage-applicant-fields-groups','index',1),
 (1054,'superadmin','manage-applicant-fields-groups','internals',1),
 (1054,'superadmin','manage-applicant-fields-groups','add-block',1),
 (1054,'superadmin','manage-applicant-fields-groups','edit-block',1),
 (1054,'superadmin','manage-applicant-fields-groups','remove-block',1),
 (1054,'superadmin','manage-applicant-fields-groups','add-group',1),
 (1054,'superadmin','manage-applicant-fields-groups','edit-group',1),
 (1054,'superadmin','manage-applicant-fields-groups','delete-group',1),
 (1054,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
 (1054,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
 (1054,'superadmin','manage-applicant-fields-groups','get-field-info',1),
 (1054,'superadmin','manage-applicant-fields-groups','delete-field',1),
 (1054,'superadmin','manage-applicant-fields-groups','save-order',1),
 (1054,'superadmin','manage-applicant-fields-groups','edit-field',1),

 
 (1060,'superadmin','forms','',1),
 (1060,'superadmin','forms-maps','',1),
 (1060,'forms','forms-folders','',1),
 (1061, 'superadmin', 'landing-pages', 'manage-landing-pages', 1),

 (1070,'superadmin','news','',1),
 (1080,'superadmin','shared-templates','',1),
 (1090,'superadmin','default-searches','',1),
 (1100, 'superadmin', 'manage-templates', '', 1),
 (1120,'superadmin','automatic-reminders','',1),
 (1130, 'system', 'index', '', 1),
 (1130, 'system', 'import', '', 1),
 (1140, 'superadmin', 'manage-faq', '', 1),
 (1150, 'superadmin', 'manage-prospects', '', 1),
 (1170, 'superadmin', 'smtp', '', 1),
 (1180, 'superadmin', 'last-logged-in', '', 1),
 (1190, 'superadmin', 'manage-company-prospects', '', 1),
 (1200, 'superadmin', 'manage-hst', '', 1),
 (1210, 'superadmin', 'manage-cmi', '', 1),
 (1220, 'superadmin', 'manage-trial-pricing', '', 1),
 (1230, 'superadmin', 'manage-invoices', '', 1),
 (1231, 'superadmin', 'manage-company', 'generate-invoice-template', 1),
 (1232, 'superadmin', 'manage-company', 'run-charge', 1),

 (1240, 'superadmin', 'manage-pricing', '', 1),
 (1250, 'superadmin', 'manage-bad-debts-log', '', 1),
 (1260, 'superadmin', 'automated-billing-log', '', 1),
 (1270, 'superadmin', 'manage-pt-error-codes', '', 1),
 (1280, 'superadmin', 'accounts', '', 1),
 (1290, 'superadmin', 'forms-default', '', 1),
 (1300, 'superadmin', 'statistics', '', 1),
 (1310, 'superadmin', 'import-clients', '', 1),
 (1311, 'superadmin', 'import-client-notes', '', 1),
 (1320, 'superadmin', 'prospects-matching', '', 1),
 (1330, 'superadmin', 'manage-rss-feed', '', 1),
 (1350, 'superadmin', 'advanced-search', '', 1),
 (1360, 'superadmin', 'manage-company', 'company-search', 1),
 (1370, 'superadmin', 'manage-company', 'export', 1),
 (1380, 'superadmin', 'system-variables', '', 1),
 (1390, 'superadmin', 'admin', '', 1),

 (1400, 'superadmin', 'company-website', '', 1),

 (1410,'superadmin','access-logs','index',1),
 (1410,'superadmin','access-logs','list',1),
 (1411,'superadmin','access-logs','delete',1),

 (1420, 'superadmin', 'manage-company', 'save-packages', 1),
 (1420, 'superadmin', 'manage-company-packages', 'index', 1),
 (1421, 'superadmin', 'manage-company-packages', 'edit-extra-details', 1),
 (1430, 'superadmin', 'manage-company', 'mass-email', 1),

 (1440, 'superadmin', 'tickets', 'get-tickets', 1),
 (1440, 'superadmin', 'tickets', 'get-ticket', 1),
 (1441, 'superadmin', 'tickets', 'add', 1),
 (1442, 'superadmin', 'tickets', 'change-status', 1),

 (1450, 'superadmin', 'zoho', '', 1),

 (2204, 'superadmin', 'manage-company-contacts-types', '', 1),

 (2210, 'superadmin', 'letterheads', '', 1)
;

DROP TABLE IF EXISTS `acl_role_access`;
CREATE TABLE `acl_role_access` (
  `role_id` varchar(50) NOT NULL,
  `rule_id` int(11) NOT NULL,

  PRIMARY KEY  (`role_id`, `rule_id`),
  CONSTRAINT `FK_acl_role_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_parent_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_acl_role_access_1` FOREIGN KEY `FK_acl_role_access_1` (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `acl_role_access` (`role_id`,`rule_id`) VALUES
 /* These are default guest role access rights */
 ('guest', 1),
 ('guest', 2),
 ('guest', 300),
 ('guest', 192),

/* These are default superadmin role access rights */  
 ('superadmin', 3),

/* These are default client role access rights */
	
 ('client',6),
 ('client',10),
 ('client',30),
 ('client',60),
 ('client',70),
 ('client',100),
 ('client',140),
 ('client',190),

/* These are default user role access rights */
  ('user',6),
  ('user',7),
  ('user',10),
  ('user',25),
  ('user',30),
  ('user',60),
  ('user',70),
  ('user',90),
  ('user',91),
  ('user',92),
  ('user',95),
  ('user',96),
  ('user',97),
  ('user',98),
  ('user',99),
  ('user',101),
  ('user',102),
  ('user',103),
  ('user',104),
  ('user',110),  
  ('user',120),
  ('user',130),
  ('user',140),
  ('user',141),
  ('user',142),
  ('user',143),
  ('user',150),
  ('user',160),
  ('user',170),
  ('user',175),
  ('user',180),
  ('user',181),
  ('user',190),
  ('user',200),
  ('user',210),
  ('user',220),
  ('user',500),
  ('user',1130),
  ('user',1190),

/* These are default admin role access rights */
  
  ('admin',6),
  ('admin',7),
  ('admin',10),
  ('admin',25),
  ('admin',30),
  ('admin',31),
  ('admin',32),
  ('admin',33),
  ('admin',60),
  ('admin',61),
  ('admin',70),
  ('admin',90),
  ('admin',91),
  ('admin',92),
  ('admin',95),
  ('admin',96),
  ('admin',97),
  ('admin',98),
  ('admin',99),
  ('admin',100),
  ('admin',101),
  ('admin',102),
  ('admin',103),
  ('admin',104),
  ('admin',105),
  ('admin',106),
  ('admin',110),
  ('admin',120),
  ('admin',130),
  ('admin',140),
  ('admin',141),
  ('admin',142),
  ('admin',143),
  ('admin',150),
  ('admin',160),
  ('admin',170),
  ('admin',175),
  ('admin',180),
  ('admin',181),
  ('admin',190),
  ('admin',200),	
  ('admin',210),
  ('admin',220),
  ('admin',500),

  ('admin', 4),
  ('admin', 5),
  ('admin',1000),
  ('admin',1010),
  ('admin',1011),
  ('admin',1012),
  ('admin',1013),
  ('admin',1030),
  ('admin',1031),
  ('admin',1032),
  ('admin',1033),
  ('admin',1040),
  ('admin',1042),
  ('admin',1050),
  ('admin',1051),
  ('admin',1052),
  ('admin',1053),
  ('admin',1080),
  ('admin',1120),
  ('admin',1130),
  ('admin',1190),
  ('admin',1290),
  ('admin', 2204),
  ('admin', 1410),
  ('admin', 1411),
  ('admin',2210),
  ('admin', 1054)
;

DROP TABLE IF EXISTS `packages`;
CREATE TABLE `packages` (
  `package_id` int(11) NOT NULL auto_increment,
  `package_name` varchar(100) NOT NULL,
  `package_description` varchar(255) NOT NULL,
  `required` int(1) NOT NULL default '0',
  `status` int(1) NOT NULL default '1',
  PRIMARY KEY  (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `packages` (`package_id`, `package_name`, `package_description`, `required`, `status`) VALUES
 (1, 'Package 1', 'This is the main package. It contains main functionality.', 1, 1),
 (2, 'Package 2', 'This is an additional package. Client Login, Client Account and Accounting Sub tab.', 0, 1),
 (3, 'Package 3', 'This is an additional package. Email, Calendar &amp; My Document.', 0, 1),
 (4, 'Package 4', 'This is an additional package. Prospects.', 0, 1)
;

DROP TABLE IF EXISTS `company_packages`;
CREATE TABLE `company_packages` (
  `company_id` BIGINT(20) NOT NULL,
  `package_id` int(11) NOT NULL,
  PRIMARY KEY  (`company_id`, `package_id`),
  CONSTRAINT `FK_company_packages_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `packages_details`;
CREATE TABLE `packages_details` (
  `package_detail_id` int(11) NOT NULL auto_increment,
  `package_id` int(11) NOT NULL,
  `rule_id` int(11) NOT NULL,
  `package_detail_description` varchar(255) NOT NULL,
  `visible` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`package_detail_id`),
  CONSTRAINT `FK_packages_details_packages` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_packages_details_acl_rules` FOREIGN KEY (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
  /* **** Package 1 **** */
  /* Admin section */
 (1, 4, 'Administration section', 0),
 (1, 5, 'Staff tabs', 0),
 (1, 6, 'Home', 0),
 
 (1, 1000, 'Change Password', 0),

 (1, 1010, 'Manage Roles', 1),
 (1, 1011, 'Add New Roles', 0),
 (1, 1012, 'View Roles Details', 0),
 (1, 1013, 'Delete Roles', 0),
 (1, 1014, 'Edit Roles', 1),
 
 (1, 1030, 'Manage Users', 1),
 (1, 1031, 'Add Users', 0),
 (1, 1032, 'View Users Details', 0),
 (1, 1033, 'Delete Users', 0),
 (1, 1034, 'Edit Users', 1),
 (1, 1035, 'Change Users Password', 1),
 
 (1, 1050, 'Manage Cases fields/groups/layouts', 1),
 (1, 1051, 'Manage Individuals fields/groups/layouts', 1),
 (1, 1052, 'Manage Employers fields/groups/layouts', 1),
 (1, 1053, 'Manage Contacts fields/groups/layouts', 1),
 (1, 1310, 'Import Clients', 1),
 (1, 1311, 'Import Client Notes', 1),
 (1, 1054, 'Manage Internal Contacts fields/groups/layouts', 1)

 (1, 1040, 'Manage Company Details', 1),
 (1, 1041, 'Add Company', 0),
 (1, 1042, 'Edit Company',0),
 (1, 1043, 'Delete Company', 0),
 (1, 1044, 'Manage Company As Admin', 0),
 (1, 1046, 'Change Company Status', 0),
 (1, 1047, 'View Companies List', 0),
 (1, 1048, 'Company Email', 0),
 (1, 1049, 'Edit Company Extra Details', 1),

 (1, 1090, 'Default Searches',1),
 
 /* Main web site section */
 (1, 7, 'Notes on Homepage', 1),
 (1, 10, 'Manage Clients', 1),
 (1, 11, 'Edit Profile', 1),
 (1, 12, 'Delete Client', 1),
 (1, 13, 'New Client', 1),
 (1, 25, 'Tasks', 1),
 (1, 30, 'Notes and Activities', 1),
 (1, 31, 'Add Notes', 1),
 (1, 32, 'Edit Notes', 1),
 (1, 33, 'Delete Notes', 1),
 (1, 80, 'Client\'s TimeTracker', 1),
 (1, 81, 'Client\'s TimeTracker - Popup Dialog', 1),
 (1, 82, 'Client\'s TimeTracker - Show Time Log', 1),
 (1, 83, 'Client\'s TimeTracker - Time Log Add', 1),
 (1, 84, 'Client\'s TimeTracker - Time Log Edit', 1),
 (1, 85, 'Client\'s TimeTracker - Time Log Delete', 1),
 (1, 90, 'Advanced search - run', 1),
 (1, 91, 'Advanced search - export', 1),
 (1, 92, 'Advanced search - print', 1),
 (1, 95, 'Queue - run', 1),
 (1, 96, 'Queue - export', 1),
 (1, 97, 'Queue - print', 1),
 (1, 98, 'Queue - push to office/queue', 1),
 (1, 99, 'Queue - change file status', 1),
 (1, 180, 'Templates', 1),
 (1, 181, 'Manage templates', 1),
 (1, 190, 'Help', 1),
 (1, 191, 'F.A.Q.', 1),
 (1, 192, 'F.A.Q.', 1),
 (1, 210, 'My Tasks', 1),
 (1, 130, 'Links', 1),

 (1, 140, 'Forms', 1),
 (1, 141, 'Enable Assign Forms', 1),
 (1, 142, 'Can Finalize a Form', 1),
 (1, 143, 'Can Lock and Unlock Forms', 1),
 (1, 144, 'Can Complete a Form', 1),
 (1, 170, 'Announcements', 1),
 (1, 175, 'Immigration News', 1),

  (1, 400, 'Manage Contacts', 1),
  (1, 401, 'Edit Contact', 1),
  (1, 402, 'Delete Contact', 1),
  (1, 403, 'New Contact', 1),

  (1, 500, 'User Profile', 1),

 (1, 1060, 'Administer Forms', 1),
 (1, 1061, 'Manage Landing Pages', 1),
 (1, 1070, 'Announcements', 0),
 (1, 1080, 'Shared Templates', 1),
 (1, 1100, 'Manage Templates', 0),
 (1, 1290, 'Default Forms', 1),

 (1, 1350, 'Advanced Search', 0),
 (1, 1360, 'Quick Search', 0),
 (1, 1370, 'Allow Export', 0),
 (1, 1410, 'Access Logs', 1),
 (1, 1411, 'Delete Access Logs', 1),

 (1, 106, 'New Letter on Letterhead', 1),
 (1, 2210, 'Letterheads', 1),

 /* **** Package 2 **** */
 /* Main web site section */
 (2, 110, 'Client Account', 1),
 (2, 101, 'Client Account History', 0),
 (2, 102, 'Client Account Import', 0),
 (2, 103, 'Client Account Assign', 0),
 (2, 104, 'Client Account Edit', 0),
 (2, 105, 'Client Account Settings', 0),
 
 (2, 70, 'Client\'s Accounting', 1),

 (2, 1120, 'Automatic Tasks', 1),
  (2, 20, 'Employer Client Login', 1),
  (2, 21, 'Individual Client Login', 1),
  (2, 220, 'ABN/ACN Check', 1),

 /* **** Package 3 **** */
 (3, 60, 'Client Documents', 1),
 (3, 61, 'Client Documents Settings', 1),
 (3, 150, 'Calendar', 1),
 (3, 160, 'Mail', 1),
 (3, 100, 'My Documents', 1),

 /* **** Package 4 **** */
 (4, 200, 'Prospects', 1),
 (4, 300, 'Company Websites', 1),

 (4, 1020, 'Manage Super Admin Users', 0),
 (4, 1021, 'Add New Super Admin User', 0),
 (4, 1022, 'Edit Super Admin User', 0),
 (4, 1023, 'Delete Super Admin User', 0),

 (4, 1130, 'System', 1),
 (4, 1140, 'Manage Help', 0),
 (4, 1150, 'Manage Prospects', 0),
 (4, 1160, 'Signup', 1),
 (4, 1170, 'Mail Server Settings', 0),
 (4, 1180, 'Last Logged In Info', 0),
 (4, 1190, 'Manage Company Prospects', 1),
 (4, 1200, 'Manage GST/HST', 0),
 (4, 1210, 'Manage CMI', 0),
 (4, 1220, 'Trial users pricing', 0),

 (4, 1230, 'Manage PT Invoices', 0),
 (4, 1231, 'Generate Invoice Template', 0),
 (4, 1232, 'Run Charge', 0),

 (4, 1240, 'Manage pricing', 0),
 (4, 1250, 'Bad debts log', 0),
 (4, 1260, 'Automated billing log', 0),
 (4, 1270, 'Manage PT Error codes', 0),
 (4, 1280, 'Accounts', 0),
 (4, 1300, 'Statistics', 0),
 (4, 1320, 'Prospects Matching', 0),
 (4, 1330, 'Manage RSS feed', 0),
 (4, 1380, 'Manage system variables', 0),
 (4, 1390, 'View Superadmin Tab', 0),
 (4, 1400, 'Company Website', 1),

 (4, 1420, 'Manage Company Packages', 0),
 (4, 1421, 'Manage Company Packages Extra Details', 0),

 (4, 1430, 'Send Mass Email', 0),

 (4, 1440, 'Manage Company Tickets', 1),
 (4, 1441, 'Add Company Tickets', 1),
 (4, 1442, 'Manage Company Tickets Status', 1),

 (4, 1450, 'Manage Zoho settings', 0),

 (4, 2000, 'CRM Manage', 0),
 (4, 2001, 'Manage Users', 0),
 (4, 2002, 'Define CRM users', 0),
 (4, 2003, 'Define CRM roles', 0),

 (4, 2010, 'Settings', 0),
 (4, 2011, 'Change own password', 1),

 (4, 2100, 'Companies', 1),
 (4, 2101, 'New company', 0),
 (4, 2102, 'Edit company', 0),
 (4, 2103, 'Delete company', 0),

 (4, 2200, 'Prospects', 0),
 (4, 2201, 'New prospect', 0),
 (4, 2202, 'Edit prospect', 0),
 (4, 2203, 'Delete prospect', 0),
 (4, 2204, 'Contacts', 1);

DROP TABLE IF EXISTS `company_websites_templates`;
CREATE TABLE IF NOT EXISTS `company_websites_templates` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `template_name` varchar(255) NOT NULL,
  `options` longtext,
  `created_date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_websites_templates` (`id`, `template_name`, `options`, `created_date`) VALUES (1, 'boleo', NULL, '2012-05-03 00:00:00');
INSERT INTO `company_websites_templates` (`id`, `template_name`, `options`, `created_date`) VALUES (2, 'prospect', NULL, '2012-06-07 00:00:00');
INSERT INTO `company_websites_templates` (`id`, `template_name`, `options`, `created_date`) VALUES (3, 'DK2', NULL, '2012-06-12 00:00:00');
INSERT INTO `company_websites_templates` (`id`, `template_name`, `options`, `created_date`) VALUES (4, 'gipo', NULL, '2012-06-13 00:00:00');
INSERT INTO `company_websites_templates` (`id`, `template_name`, `options`, `created_date`) VALUES (4, 'gourmet', NULL, '2012-06-14 00:00:00');

DROP TABLE IF EXISTS `company_websites`;
CREATE TABLE IF NOT EXISTS `company_websites` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) NOT NULL,
  `template_id` tinyint(3) unsigned NOT NULL,
  `company_name` TEXT NULL DEFAULT NULL,
  `entrance_name` varchar(100) NOT NULL,
  `title` VARCHAR(255) NULL DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_skype` varchar(255) DEFAULT NULL,
  `company_fax` varchar(255) DEFAULT NULL,
  `company_linkedin` VARCHAR(512) NULL DEFAULT NULL,
  `company_facebook` VARCHAR(512) NULL DEFAULT NULL,
  `company_twitter` VARCHAR(512) NULL DEFAULT NULL,
  `homepage_on` enum('Y','N') DEFAULT 'Y',
  `homepage_name` varchar(100) DEFAULT NULL,
  `homepage_text` mediumtext,
  `about_on` enum('Y','N') DEFAULT 'N',
  `about_name` varchar(100) DEFAULT NULL,
  `about_text` mediumtext,
  `canada_on` enum('Y','N') DEFAULT 'N',
  `canada_name` varchar(100) DEFAULT NULL,
  `canada_text` mediumtext,
  `immigration_on` enum('Y','N') DEFAULT 'N',
  `immigration_name` varchar(100) DEFAULT NULL,
  `immigration_text` mediumtext,
  `assessment_on` enum('Y','N') DEFAULT 'N',
  `assessment_name` varchar(100) DEFAULT NULL,
  `assessment_url` VARCHAR(512) NULL DEFAULT NULL,
  `assessment_banner` VARCHAR(255) NULL DEFAULT NULL,
  `assessment_background` VARCHAR(7) NULL DEFAULT NULL,
  `assessment_foreground` VARCHAR(7) NULL DEFAULT '#000000',
  `contact_on` enum('Y','N') DEFAULT 'Y',
  `contact_name` varchar(100) DEFAULT NULL,
  `contact_text` mediumtext,
  `contact_map` ENUM('Y','N') NULL DEFAULT 'N',
  `contact_map_coords` VARCHAR(128) NULL DEFAULT NULL,
  `login_block_on` ENUM('Y','N') NULL DEFAULT 'N',
  `footer_text` text,
  `external_links_on` enum('Y','N') DEFAULT 'N',
  `external_links_title` varchar(100) DEFAULT NULL,
  `external_links` longtext,
  `options` longtext,
  `visible` enum('Y','N') NOT NULL DEFAULT 'N',
  `updated_date` datetime NOT NULL,
  `created_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entrance_name` (`entrance_name`),
  KEY `FK_company_websites_company` (`company_id`),
  KEY `FK_company_websites_company_websites_templates` (`template_id`),
  CONSTRAINT `FK_company_websites_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `FK_company_websites_company_websites_templates` FOREIGN KEY (`template_id`) REFERENCES `company_websites_templates` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `access_logs`;
CREATE TABLE `access_logs` (
	`log_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`log_section` VARCHAR(100) NULL DEFAULT NULL,
	`log_action` VARCHAR(100) NULL DEFAULT NULL,
	`log_description` VARCHAR(255) NULL DEFAULT NULL,
	`log_company_id` BIGINT(20) NULL DEFAULT NULL,
	`log_created_by` BIGINT(20) NULL DEFAULT NULL,
	`log_created_on` DATETIME NOT NULL,
	`log_action_applied_to` BIGINT(20) NULL DEFAULT NULL,
	`log_ip` VARCHAR(39) NULL DEFAULT NULL,
	PRIMARY KEY (`log_id`),
	CONSTRAINT `FK_access_logs_company` FOREIGN KEY (`log_company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_access_logs_members` FOREIGN KEY (`log_created_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_access_logs_members_2` FOREIGN KEY (`log_action_applied_to`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `letterheads` (
	`letterhead_id` INT(11) NOT NULL AUTO_INCREMENT,
	`company_id` BIGINT(20) NOT NULL,
	`name` VARCHAR(50) NULL DEFAULT NULL,
	`create_date` DATE NULL DEFAULT NULL,
	`type` ENUM('a4','letter') NULL DEFAULT NULL,
	`created_by` BIGINT(20) NOT NULL,
	`same_subsequent` INT(1) NULL DEFAULT '1',
	PRIMARY KEY (`letterhead_id`),
	INDEX `FK_company_id` (`company_id`),
	INDEX `FK_created_by` (`created_by`),
	CONSTRAINT `FK_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;

CREATE TABLE `letterheads_files` (
	`letterhead_file_id` INT(11) NOT NULL AUTO_INCREMENT,
	`letterhead_id` INT(11) NOT NULL,
	`file_name` VARCHAR(50) NOT NULL,
	`size` VARCHAR(45) NULL DEFAULT NULL,
	`margin_left` INT(11) NULL DEFAULT NULL,
	`margin_top` INT(11) NULL DEFAULT NULL,
	`margin_right` INT(11) NULL DEFAULT NULL,
	`margin_bottom` INT(11) NULL DEFAULT NULL,
	`number` INT(11) NULL DEFAULT NULL,
	PRIMARY KEY (`letterhead_file_id`),
	INDEX `FK_letterhead_id` (`letterhead_id`),
	CONSTRAINT `FK_letterhead_id` FOREIGN KEY (`letterhead_id`) REFERENCES `letterheads` (`letterhead_id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;

CREATE TABLE `members_queues` (
	`member_id` BIGINT(20) NOT NULL,
	`queue_member_allowed_queues` TEXT NULL,
	`queue_member_selected_queues` TEXT NULL,
	`queue_columns` TEXT NULL,
	INDEX `FK_members_queues_members` (`member_id`),
	CONSTRAINT `FK_members_queues_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
