ALTER TABLE `acl_rules`  ADD COLUMN `crm_only` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `superadmin_only`;

UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=5 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=6 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=7 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=100 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=130 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=150 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=160 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=170 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=180 LIMIT 1;
UPDATE `acl_rules` SET `crm_only`='Y' WHERE `rule_id`=190 LIMIT 1;

INSERT INTO `acl_modules` (`module_id`, `module_name`) VALUES ('crm', 'CRM Admin');
INSERT INTO `acl_rules` (`rule_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2000, 'crm', 'CRM Manage', 'crm-manage', 1, 'Y', 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2001, 2000, 'crm', 'Manage Users', 'crm-admin', 1, 'Y', 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2002, 2001, 'crm', 'Define CRM users', 'crm-manage-users', 1, 'Y', 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2003, 2001, 'crm', 'Define CRM roles', 'crm-manage-roles', 1, 'Y', 1);


INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) 
VALUES (2010, 2000, 'crm', 'Settings', 'crm-settings', 1, 'Y', 1, 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2011, 2010, 'crm', 'Change own password', 'crm-manage-own-password', 1, 'Y', 1);



INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) 
VALUES (2100, 5, 'crm', 'Companies', 'crm-companies-view', 1, 'Y', 1, 3);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2101, 2100, 'crm', 'New company', 'crm-companies-add', 1, 'Y', 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2102, 2100, 'crm', 'Edit company', 'crm-companies-edit', 1, 'Y', 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2103, 2100, 'crm', 'Delete company', 'crm-companies-delete', 1, 'Y', 1);



INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) 
VALUES (2200, 5, 'crm', 'Prospects', 'crm-prospects-view', 1, 'Y', 1, 4);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2201, 2200, 'crm', 'New prospect', 'crm-prospect-add', 1, 'Y', 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2202, 2200, 'crm', 'Edit prospect', 'crm-prospect-edit', 1, 'Y', 1);
INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`) 
VALUES (2203, 2200, 'crm', 'Delete prospect', 'crm-prospect-delete', 1, 'Y', 1);


INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`) VALUES (2000, 'crm', 'index');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2001, 'crm', 'admin', 'index');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2002, 'crm', 'define-users', '');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2003, 'crm', 'define-roles', '');

INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2010, 'crm', 'settings', 'index');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2011, 'crm', 'settings', 'update-pass');

INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2100, 'crm', 'companies', 'index');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2101, 'crm', 'companies', 'add');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2102, 'crm', 'companies', 'edit');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2103, 'crm', 'companies', 'delete');

INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2200, 'crm', 'prospects', 'index');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2201, 'crm', 'prospects', 'add');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2202, 'crm', 'prospects', 'edit');
INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2203, 'crm', 'prospects', 'delete');

INSERT INTO `acl_roles` (`role_id`, `role_name`, `role_type`, `role_parent_id`, `role_child_id`, `role_visible`) VALUES 
(0, 'CRM Admin role', 'crmuser', 'company_0_crmrole_1', 'guest', 1);
INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 5);
INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 6);

INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 2000);
INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 2001);
INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 2002);
INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 2003);
INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 2010);
INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('company_0_crmrole_1', 2011);


INSERT INTO `members` (`member_id`, `company_id`, `userType`, `username`, `password`, `emailAddress`, `fName`, `lName`, `status`) VALUES 
(0, 0, 6, 'crm_admin', '+0loXhYHCj8djcJ3vNIHD7u5UTePaXZtXb1dIofFX+E=', 'superadmin@uniques.com', 'CRM User', 'LName', 1);

/*
Also create a new record in member_roles table - for this user + role created above
*/