DROP VIEW IF EXISTS `view_last_mail_used`;
CREATE VIEW `view_last_mail_used` AS
SELECT `c`.`company_id` AS `company_id`, MAX(`a`.`last_manual_check`) AS `last_manual_check`, MAX(`a`.`last_mass_mail`) AS `last_mass_mail`
FROM `company` `c`
LEFT JOIN `members` `m` ON `m`.`company_id` = `c`.`company_id`
LEFT JOIN `eml_accounts` `a` ON `m`.`member_id` = `a`.`member_id`
GROUP BY `c`.`company_id`;


DROP VIEW IF EXISTS `view_last_ta_uploaded`;
CREATE VIEW `view_last_ta_uploaded` AS
SELECT `c`.`company_id` AS `company_id`, MAX(`i`.`import_datetime`) AS `last_ta_uploaded_date`
FROM `company` `c`
LEFT JOIN `company_ta` `ta` ON `c`.`company_id` = `ta`.`company_id`
LEFT JOIN `u_import_transactions` `i` ON `i`.`company_ta_id` = `ta`.`company_ta_id`
GROUP BY `c`.`company_id`;

DROP VIEW IF EXISTS `view_active_users`;
CREATE VIEW `view_active_users` AS
SELECT c.company_id, COUNT(member_id) AS active_users_count
FROM company AS c
LEFT JOIN members AS m ON m.company_id = c.company_id
WHERE m.userType IN (2, 4) AND m.status = 1
GROUP BY c.company_id ;

DROP VIEW IF EXISTS `view_clients_count`;
CREATE VIEW `view_clients_count` AS
SELECT c.company_id, COUNT(member_id) AS clients_count
FROM company AS c
LEFT JOIN members AS m ON m.company_id = c.company_id
WHERE m.userType = 3
GROUP BY c.company_id ;