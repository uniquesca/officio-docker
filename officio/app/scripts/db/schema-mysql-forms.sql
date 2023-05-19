DROP TABLE IF EXISTS `FormProcessed`;
CREATE TABLE `FormProcessed` (
  `form_processed_id` int(11) unsigned NOT NULL auto_increment,
  `template_id` int(11) unsigned default NULL,
  `version` enum('FULL','LAST') default 'FULL',
  `content` longtext,
  PRIMARY KEY  (`form_processed_id`),
  CONSTRAINT `FK_formprocessed_formtemplates` FOREIGN KEY (`template_id`) REFERENCES `FormTemplates` (`template_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `FormTemplates`;
CREATE TABLE `FormTemplates` (
  `template_id` int(11) unsigned NOT NULL auto_increment,
  `folder_id` int(11) unsigned default NULL,
  `name` varchar(255) default '',
  `body` longtext,
  PRIMARY KEY  (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `FormLanding`;
CREATE TABLE `FormLanding` (
  `FolderId` int(11) unsigned NOT NULL auto_increment,
  `ParentId` int(11) unsigned default NULL,
  `Name` varchar(255) NOT NULL,
  PRIMARY KEY  (`FolderId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `FormUpload`;
CREATE TABLE `FormUpload` (
  `FormId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FolderId` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY  (`FormId`),
  CONSTRAINT `FK_formupload_formfolder` FOREIGN KEY (`FolderId`) REFERENCES `FormFolder` (`FolderId`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `FormVersion`;
CREATE TABLE `FormVersion` (
  `FormVersionId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FormId` int(10) UNSIGNED NOT NULL,
  `FormType` ENUM('','bar') NOT NULL DEFAULT '',
  `VersionDate` datetime NOT NULL,
  `FilePath` varchar(255) NOT NULL,
  `Size` varchar(45) NOT NULL,
  `UploadedDate` datetime NOT NULL,
  `UploadedBy` bigint(20) NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `Note1` varchar(255) NOT NULL,
  `Note2` varchar(255) NOT NULL,
  PRIMARY KEY  (`FormVersionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `form_default`;
CREATE TABLE `form_default` (
	`form_default_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`company_id` BIGINT(20) NOT NULL,
	`form_version_id` INT(10) UNSIGNED NOT NULL,
	`updated_by` BIGINT(20) NULL DEFAULT NULL,
	`updated_on` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`form_default_id`),
	CONSTRAINT `FK_form_default_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_form_default_FormVersion` FOREIGN KEY (`form_version_id`) REFERENCES `FormVersion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_form_default_members` FOREIGN KEY (`updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `FormFolder`;
CREATE TABLE `FormFolder` (
  `FolderId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ParentId` int(10) UNSIGNED NOT NULL,
  `FolderName` varchar(255) NOT NULL,
  PRIMARY KEY  (`FolderId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `FormMap`;
CREATE TABLE `FormMap` (
  `FormMapId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  
  `FromFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') NULL DEFAULT 'main_applicant',
  `FromSynFieldId` int(10) UNSIGNED NOT NULL,
  
  `ToFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') NULL DEFAULT 'main_applicant',
  `ToSynFieldId` int(10) UNSIGNED NOT NULL,
  
  `ToProfileFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') NULL DEFAULT 'main_applicant',
  `ToProfileFieldId` varchar(255) DEFAULT NULL,
  
  `form_map_type` varchar(255) DEFAULT NULL,
  
  PRIMARY KEY  (`FormMapId`),
  CONSTRAINT `FK_formmap_formsynfield` FOREIGN KEY (`FromSynFieldId`) REFERENCES `FormSynField` (`SynFieldId`) ON UPDATE CASCADE ON DELETE CASCADE,
 	CONSTRAINT `FK_formmap_formsynfield_2` FOREIGN KEY (`ToSynFieldId`) REFERENCES `FormSynField` (`SynFieldId`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `FormMap` (`FormMapId`,`FromFamilyMemberId`,`FromSynFieldId`,`ToFamilyMemberId`,`ToSynFieldId`,`ToProfileFamilyMemberId`,`ToProfileFieldId`,`form_map_type`) VALUES
 (1,'main_applicant',1,'spouse',13,'main_applicant','lName',NULL),
 (2,'spouse',1,'main_applicant',13,'spouse','lName',NULL),
 (3,'main_applicant',13,'spouse',1,'spouse','lName',NULL),
 (4,'spouse',13,'main_applicant',1,'main_applicant','lName',NULL),
 (5,'main_applicant',2,'spouse',14,'main_applicant','fName',NULL),
 (6,'main_applicant',14,'spouse',2,'spouse','fName',NULL),
 (7,'spouse',2,'main_applicant',14,'spouse','fName',NULL),
 (8,'spouse',14,'main_applicant',2,'main_applicant','fName',NULL),
 (9,'main_applicant',6,'spouse',18,'main_applicant','country_of_birth','country'),
 (10,'main_applicant',18,'spouse',6,'spouse','country_of_birth','country'),
 (11,'spouse',18,'main_applicant',6,'main_applicant','country_of_birth','country'),
 (12,'main_applicant',9,'spouse',21,'main_applicant','passport_number',NULL),
 (13,'main_applicant',21,'spouse',9,'spouse','passport_num',NULL),
 (14,'spouse',9,'main_applicant',21,'spouse','passport_num',NULL),
 (15,'spouse',21,'main_applicant',9,'main_applicant','passport_number',NULL),
 (17,'child1',1,'main_applicant',25,'child1','lName',NULL),
 (18,'main_applicant',7,'spouse',19,'main_applicant','country_of_citizenship','country'),
 (19,'main_applicant',19,'spouse',7,'spouse','country_of_citizenship','country'),
 (20,'spouse',7,'main_applicant',19,'spouse','country_of_citizenship','country'),
 (21,'spouse',19,'main_applicant',7,'main_applicant','country_of_citizenship','country'),
 (22,'main_applicant',8,'spouse',20,'main_applicant','country_of_residence','country'),
 (23,'main_applicant',20,'spouse',8,'spouse','country_of_residence','country'),
 (24,'spouse',8,'main_applicant',20,'spouse','country_of_residence','country'),
 (25,'spouse',20,'main_applicant',8,'main_applicant','country_of_residence','country'),
 (26,'main_applicant',25,'child1',1,'child1','lName',NULL),
 (27,'main_applicant',26,'child1',2,'child1','fName',NULL),
 (28,'child1',2,'main_applicant',26,'child1','fName',NULL),
 (29,'main_applicant',3,'spouse',15,'main_applicant','DOB','date_year'),
 (30,'main_applicant',4,'spouse',16,'main_applicant','DOB','date_month'),
 (31,'main_applicant',5,'spouse',17,'main_applicant','DOB','date_day'),
 (32,'spouse',3,'main_applicant',15,'spouse','DOB','date_year'),
 (33,'spouse',4,'main_applicant',16,'spouse','DOB','date_month'),
 (34,'spouse',5,'main_applicant',17,'spouse','DOB','date_day'),
 (35,'main_applicant',15,'spouse',3,'spouse','DOB','date_year'),
 (36,'main_applicant',16,'spouse',4,'spouse','DOB','date_month'),
 (37,'main_applicant',17,'spouse',5,'spouse','DOB','date_day'),
 (38,'main_applicant',10,'spouse',22,'main_applicant','passport_exp_date','date_year'),
 (39,'main_applicant',11,'spouse',23,'main_applicant','passport_exp_date','date_month'),
 (40,'main_applicant',12,'spouse',24,'main_applicant','passport_exp_date','date_day'),
 (41,'spouse',22,'main_applicant',10,'main_applicant','passport_exp_date','date_year'),
 (42,'spouse',23,'main_applicant',11,'main_applicant','passport_exp_date','date_month'),
 (43,'spouse',24,'main_applicant',12,'main_applicant','passport_exp_date','date_day'),
 (44,'spouse',10,'main_applicant',22,'spouse','passport_date','date_year'),
 (45,'spouse',11,'main_applicant',23,'spouse','passport_date','date_month'),
 (46,'spouse',12,'main_applicant',24,'spouse','passport_date','date_day'),
 (47,'main_applicant',22,'spouse',10,'spouse','passport_date','date_year'),
 (48,'main_applicant',23,'spouse',11,'spouse','passport_date','date_month'),
 (49,'main_applicant',24,'spouse',12,'spouse','passport_date','date_day'),
 (50,'spouse',25,'child1',1,'child1','lName',NULL),
 (51,'child1',1,'spouse',25,'child1','lName',NULL),
 (52,'spouse',26,'child1',2,'child1','fName',NULL),
 (53,'child1',2,'spouse',26,'child1','fName',NULL),
 (54,'main_applicant',27,'child1',3,'child1','DOB','date_year'),
 (55,'main_applicant',28,'child1',4,'child1','DOB','date_month'),
 (56,'main_applicant',29,'child1',5,'child1','DOB','date_day'),
 (57,'child1',3,'main_applicant',27,'child1','DOB','date_year'),
 (58,'child1',4,'main_applicant',28,'child1','DOB','date_month'),
 (59,'child1',5,'main_applicant',29,'child1','DOB','date_day'),
 (60,'spouse',27,'child1',3,'child1','DOB','date_year'),
 (61,'spouse',28,'child1',4,'child1','DOB','date_month'),
 (62,'spouse',29,'child1',5,'child1','DOB','date_day'),
 (63,'child1',3,'spouse',27,'child1','DOB','date_year'),
 (64,'child1',4,'spouse',28,'child1','DOB','date_month'),
 (65,'child1',5,'spouse',29,'child1','DOB','date_day'),
 (66,'main_applicant',33,'child1',9,'child1','passport_num',NULL),
 (67,'child1',9,'main_applicant',33,'child1','passport_num',NULL),
 (68,'spouse',33,'child1',9,'child1','passport_num',NULL),
 (69,'child1',9,'spouse',33,'child1','passport_num',NULL),
 (70,'main_applicant',34,'child1',10,'child1','passport_date','date_year'),
 (71,'main_applicant',35,'child1',11,'child1','passport_date','date_month'),
 (72,'main_applicant',36,'child1',12,'child1','passport_date','date_day'),
 (73,'child1',10,'main_applicant',34,'child1','passport_date','date_year'),
 (74,'child1',11,'main_applicant',35,'child1','passport_date','date_month'),
 (75,'child1',12,'main_applicant',36,'child1','passport_date','date_day'),
 (76,'spouse',34,'child1',10,'child1','passport_date','date_year'),
 (77,'spouse',35,'child1',11,'child1','passport_date','date_month'),
 (78,'spouse',36,'child1',12,'child1','passport_date','date_day'),
 (79,'child1',10,'spouse',34,'child1','passport_date','date_year'),
 (80,'child1',11,'spouse',35,'child1','passport_date','date_month'),
 (81,'child1',12,'spouse',36,'child1','passport_date','date_day'),
 (118,'child2',1,'main_applicant',37,'child2','lName',NULL),
 (119,'main_applicant',37,'child2',1,'child2','lName',NULL),
 (120,'main_applicant',38,'child2',2,'child2','fName',NULL),
 (121,'child2',2,'main_applicant',38,'child2','fName',NULL),
 (122,'spouse',37,'child2',1,'child2','lName',NULL),
 (123,'child2',1,'spouse',37,'child2','lName',NULL),
 (124,'spouse',38,'child2',2,'child2','fName',NULL),
 (125,'child2',2,'spouse',38,'child2','fName',NULL),
 (126,'main_applicant',39,'child2',3,'child2','DOB','date_year'),
 (127,'main_applicant',40,'child2',4,'child2','DOB','date_month'),
 (128,'main_applicant',41,'child2',5,'child2','DOB','date_day'),
 (129,'child2',3,'main_applicant',39,'child2','DOB','date_year'),
 (130,'child2',4,'main_applicant',40,'child2','DOB','date_month'),
 (131,'child2',5,'main_applicant',41,'child2','DOB','date_day'),
 (132,'spouse',39,'child2',3,'child2','DOB','date_year'),
 (133,'spouse',40,'child2',4,'child2','DOB','date_month'),
 (134,'spouse',41,'child2',5,'child2','DOB','date_day'),
 (135,'child2',3,'spouse',39,'child2','DOB','date_year'),
 (136,'child2',4,'spouse',40,'child2','DOB','date_month'),
 (137,'child2',5,'spouse',41,'child2','DOB','date_day'),
 (138,'main_applicant',45,'child2',9,'child2','passport_num',NULL),
 (139,'child2',9,'main_applicant',45,'child2','passport_num',NULL),
 (140,'spouse',45,'child2',9,'child2','passport_num',NULL),
 (141,'child2',9,'spouse',45,'child2','passport_num',NULL),
 (142,'main_applicant',46,'child2',10,'child2','passport_date','date_year'),
 (143,'main_applicant',47,'child2',11,'child2','passport_date','date_month'),
 (144,'main_applicant',48,'child2',12,'child2','passport_date','date_day'),
 (145,'child2',10,'main_applicant',46,'child2','passport_date','date_year'),
 (146,'child2',11,'main_applicant',47,'child2','passport_date','date_month'),
 (147,'child2',12,'main_applicant',48,'child2','passport_date','date_day'),
 (148,'spouse',46,'child2',10,'child2','passport_date','date_year'),
 (149,'spouse',47,'child2',11,'child2','passport_date','date_month'),
 (150,'spouse',48,'child2',12,'child2','passport_date','date_day'),
 (151,'child2',10,'spouse',46,'child2','passport_date','date_year'),
 (152,'child2',11,'spouse',47,'child2','passport_date','date_month'),
 (153,'child2',12,'spouse',48,'child2','passport_date','date_day'),
 (154,'child3',1,'main_applicant',49,'child3','lName',NULL),
 (155,'main_applicant',49,'child3',1,'child3','lName',NULL),
 (156,'main_applicant',50,'child3',2,'child3','fName',NULL),
 (157,'child3',2,'main_applicant',50,'child3','fName',NULL),
 (158,'spouse',49,'child3',1,'child3','lName',NULL),
 (159,'child3',1,'spouse',49,'child3','lName',NULL),
 (160,'spouse',50,'child3',2,'child3','fName',NULL),
 (161,'child3',2,'spouse',50,'child3','fName',NULL),
 (162,'main_applicant',51,'child3',3,'child3','DOB','date_year'),
 (163,'main_applicant',52,'child3',4,'child3','DOB','date_month'),
 (164,'main_applicant',53,'child3',5,'child3','DOB','date_day'),
 (165,'child3',3,'main_applicant',51,'child3','DOB','date_year'),
 (166,'child3',4,'main_applicant',52,'child3','DOB','date_month'),
 (167,'child3',5,'main_applicant',53,'child3','DOB','date_day'),
 (168,'spouse',51,'child3',3,'child3','DOB','date_year'),
 (169,'spouse',52,'child3',4,'child3','DOB','date_month'),
 (170,'spouse',53,'child3',5,'child3','DOB','date_day'),
 (171,'child3',3,'spouse',51,'child3','DOB','date_year'),
 (172,'child3',4,'spouse',52,'child3','DOB','date_month'),
 (173,'child3',5,'spouse',53,'child3','DOB','date_day'),
 (174,'main_applicant',57,'child3',9,'child3','passport_num',NULL),
 (175,'child3',9,'main_applicant',57,'child3','passport_num',NULL),
 (176,'spouse',57,'child3',9,'child3','passport_num',NULL),
 (177,'child3',9,'spouse',57,'child3','passport_num',NULL),
 (178,'main_applicant',58,'child3',10,'child3','passport_date','date_year'),
 (179,'main_applicant',59,'child3',11,'child3','passport_date','date_month'),
 (180,'main_applicant',60,'child3',12,'child3','passport_date','date_day'),
 (181,'child3',10,'main_applicant',58,'child3','passport_date','date_year'),
 (182,'child3',11,'main_applicant',59,'child3','passport_date','date_month'),
 (183,'child3',12,'main_applicant',60,'child3','passport_date','date_day'),
 (184,'spouse',58,'child3',10,'child3','passport_date','date_year'),
 (185,'spouse',59,'child3',11,'child3','passport_date','date_month'),
 (186,'spouse',60,'child3',12,'child3','passport_date','date_day'),
 (187,'child3',10,'spouse',58,'child3','passport_date','date_year'),
 (188,'child3',11,'spouse',59,'child3','passport_date','date_month'),
 (189,'child3',12,'spouse',60,'child3','passport_date','date_day'),
 (190,'child4',1,'main_applicant',61,'child4','lName',NULL),
 (191,'main_applicant',61,'child4',1,'child4','lName',NULL),
 (192,'main_applicant',62,'child4',2,'child4','fName',NULL),
 (193,'child4',2,'main_applicant',62,'child4','fName',NULL),
 (194,'spouse',61,'child4',1,'child4','lName',NULL),
 (195,'child4',1,'spouse',61,'child4','lName',NULL),
 (196,'spouse',62,'child4',2,'child4','fName',NULL),
 (197,'child4',2,'spouse',62,'child4','fName',NULL),
 (198,'main_applicant',63,'child4',3,'child4','DOB','date_year'),
 (199,'main_applicant',64,'child4',4,'child4','DOB','date_month'),
 (200,'main_applicant',65,'child4',5,'child4','DOB','date_day'),
 (201,'child4',3,'main_applicant',63,'child4','DOB','date_year'),
 (202,'child4',4,'main_applicant',64,'child4','DOB','date_month'),
 (203,'child4',5,'main_applicant',65,'child4','DOB','date_day'),
 (204,'spouse',63,'child4',3,'child4','DOB','date_year'),
 (205,'spouse',64,'child4',4,'child4','DOB','date_month'),
 (206,'spouse',65,'child4',5,'child4','DOB','date_day'),
 (207,'child4',3,'spouse',63,'child4','DOB','date_year'),
 (208,'child4',4,'spouse',64,'child4','DOB','date_month'),
 (209,'child4',5,'spouse',65,'child4','DOB','date_day'),
 (210,'main_applicant',69,'child4',9,'child4','passport_num',NULL),
 (211,'child4',9,'main_applicant',69,'child4','passport_num',NULL),
 (212,'spouse',69,'child4',9,'child4','passport_num',NULL),
 (213,'child4',9,'spouse',69,'child4','passport_num',NULL),
 (214,'main_applicant',70,'child4',10,'child4','passport_date','date_year'),
 (215,'main_applicant',71,'child4',11,'child4','passport_date','date_month'),
 (216,'main_applicant',72,'child4',12,'child4','passport_date','date_day'),
 (217,'child4',10,'main_applicant',70,'child4','passport_date','date_year'),
 (218,'child4',11,'main_applicant',71,'child4','passport_date','date_month'),
 (219,'child4',12,'main_applicant',72,'child4','passport_date','date_day'),
 (220,'spouse',70,'child4',10,'child4','passport_date','date_year'),
 (221,'spouse',71,'child4',11,'child4','passport_date','date_month'),
 (222,'spouse',72,'child4',12,'child4','passport_date','date_day'),
 (223,'child4',10,'spouse',70,'child4','passport_date','date_year'),
 (224,'child4',11,'spouse',71,'child4','passport_date','date_month'),
 (225,'child4',12,'spouse',72,'child4','passport_date','date_day'),
 (226,'child5',1,'main_applicant',73,'child5','lName',NULL),
 (227,'main_applicant',73,'child5',1,'child5','lName',NULL),
 (228,'main_applicant',74,'child5',2,'child5','fName',NULL),
 (229,'child5',2,'main_applicant',74,'child5','fName',NULL),
 (230,'spouse',73,'child5',1,'child5','lName',NULL),
 (231,'child5',1,'spouse',73,'child5','lName',NULL),
 (232,'spouse',74,'child5',2,'child5','fName',NULL),
 (233,'child5',2,'spouse',74,'child5','fName',NULL),
 (234,'main_applicant',75,'child5',3,'child5','DOB','date_year'),
 (235,'main_applicant',76,'child5',4,'child5','DOB','date_month'),
 (236,'main_applicant',77,'child5',5,'child5','DOB','date_day'),
 (237,'child5',3,'main_applicant',75,'child5','DOB','date_year'),
 (238,'child5',4,'main_applicant',76,'child5','DOB','date_month'),
 (239,'child5',5,'main_applicant',77,'child5','DOB','date_day'),
 (240,'spouse',75,'child5',3,'child5','DOB','date_year'),
 (241,'spouse',76,'child5',4,'child5','DOB','date_month'),
 (242,'spouse',77,'child5',5,'child5','DOB','date_day'),
 (243,'child5',3,'spouse',75,'child5','DOB','date_year'),
 (244,'child5',4,'spouse',76,'child5','DOB','date_month'),
 (245,'child5',5,'spouse',77,'child5','DOB','date_day'),
 (246,'main_applicant',81,'child5',9,'child5','passport_num',NULL),
 (247,'child5',9,'main_applicant',81,'child5','passport_num',NULL),
 (248,'spouse',81,'child5',9,'child5','passport_num',NULL),
 (249,'child5',9,'spouse',81,'child5','passport_num',NULL),
 (250,'main_applicant',82,'child5',10,'child5','passport_date','date_year'),
 (251,'main_applicant',83,'child5',11,'child5','passport_date','date_month'),
 (252,'main_applicant',84,'child5',12,'child5','passport_date','date_day'),
 (253,'child5',10,'main_applicant',82,'child5','passport_date','date_year'),
 (254,'child5',11,'main_applicant',83,'child5','passport_date','date_month'),
 (255,'child5',12,'main_applicant',84,'child5','passport_date','date_day'),
 (256,'spouse',82,'child5',10,'child5','passport_date','date_year'),
 (257,'spouse',83,'child5',11,'child5','passport_date','date_month'),
 (258,'spouse',84,'child5',12,'child5','passport_date','date_day'),
 (259,'child5',10,'spouse',82,'child5','passport_date','date_year'),
 (260,'child5',11,'spouse',83,'child5','passport_date','date_month'),
 (261,'child5',12,'spouse',84,'child5','passport_date','date_day'),
 (262,'main_applicant',25,'spouse',25,'child1','lName',NULL),
 (263,'spouse',25,'main_applicant',25,'child1','lName',NULL),
 (264,'main_applicant',26,'spouse',26,'child1','fName',NULL),
 (265,'spouse',26,'main_applicant',26,'child1','fName',NULL),
 (266,'main_applicant',27,'spouse',27,'child1','DOB','date_year'),
 (267,'main_applicant',28,'spouse',28,'child1','DOB','date_month'),
 (268,'main_applicant',29,'spouse',29,'child1','DOB','date_day'),
 (269,'spouse',27,'main_applicant',27,'child1','DOB','date_year'),
 (270,'spouse',28,'main_applicant',28,'child1','DOB','date_month'),
 (271,'spouse',29,'main_applicant',29,'child1','DOB','date_day'),
 (272,'main_applicant',33,'spouse',33,'child1','passport_num',NULL),
 (273,'spouse',33,'main_applicant',33,'child1','passport_num',NULL),
 (274,'main_applicant',34,'spouse',34,'child1','passport_date','date_year'),
 (275,'main_applicant',35,'spouse',35,'child1','passport_date','date_month'),
 (276,'main_applicant',36,'spouse',36,'child1','passport_date','date_day'),
 (277,'spouse',34,'main_applicant',34,'child1','passport_date','date_year'),
 (278,'spouse',35,'main_applicant',35,'child1','passport_date','date_month'),
 (279,'spouse',36,'main_applicant',36,'child1','passport_date','date_day'),
 (280,'main_applicant',37,'spouse',37,'child2','lName',NULL),
 (281,'spouse',37,'main_applicant',37,'child2','lName',NULL),
 (282,'main_applicant',38,'spouse',38,'child2','fName',NULL),
 (283,'spouse',38,'main_applicant',38,'child2','fName',NULL),
 (284,'main_applicant',39,'spouse',39,'child2','DOB','date_year'),
 (285,'main_applicant',40,'spouse',40,'child2','DOB','date_month'),
 (286,'main_applicant',41,'spouse',41,'child2','DOB','date_day'),
 (287,'spouse',39,'main_applicant',39,'child2','DOB','date_year'),
 (288,'spouse',40,'main_applicant',40,'child2','DOB','date_month'),
 (289,'spouse',41,'main_applicant',41,'child2','DOB','date_day'),
 (290,'main_applicant',45,'spouse',45,'child2','passport_num',NULL),
 (291,'spouse',45,'main_applicant',45,'child2','passport_num',NULL),
 (292,'main_applicant',46,'spouse',46,'child2','passport_date','date_year'),
 (293,'main_applicant',47,'spouse',47,'child2','passport_date','date_month'),
 (294,'main_applicant',48,'spouse',48,'child2','passport_date','date_day'),
 (295,'spouse',46,'main_applicant',46,'child2','passport_date','date_year'),
 (296,'spouse',47,'main_applicant',47,'child2','passport_date','date_month'),
 (297,'spouse',48,'main_applicant',48,'child2','passport_date','date_day'),
 (298,'main_applicant',49,'spouse',49,'child3','lName',NULL),
 (299,'spouse',49,'main_applicant',49,'child3','lName',NULL),
 (300,'main_applicant',50,'spouse',50,'child3','fName',NULL),
 (301,'spouse',50,'main_applicant',50,'child3','fName',NULL),
 (302,'main_applicant',51,'spouse',51,'child3','DOB','date_year'),
 (303,'main_applicant',52,'spouse',52,'child3','DOB','date_month'),
 (304,'main_applicant',53,'spouse',53,'child3','DOB','date_day'),
 (305,'spouse',51,'main_applicant',51,'child3','DOB','date_year'),
 (306,'spouse',52,'main_applicant',52,'child3','DOB','date_month'),
 (307,'spouse',53,'main_applicant',53,'child3','DOB','date_day'),
 (308,'main_applicant',57,'spouse',57,'child3','passport_num',NULL),
 (309,'spouse',57,'main_applicant',57,'child3','passport_num',NULL),
 (310,'main_applicant',58,'spouse',58,'child3','passport_date','date_year'),
 (311,'main_applicant',59,'spouse',59,'child3','passport_date','date_month'),
 (312,'main_applicant',60,'spouse',60,'child3','passport_date','date_day'),
 (313,'spouse',58,'main_applicant',58,'child3','passport_date','date_year'),
 (314,'spouse',59,'main_applicant',59,'child3','passport_date','date_month'),
 (315,'spouse',60,'main_applicant',60,'child3','passport_date','date_day'),
 (316,'main_applicant',61,'spouse',61,'child4','lName',NULL),
 (317,'spouse',61,'main_applicant',61,'child4','lName',NULL),
 (318,'main_applicant',62,'spouse',62,'child4','fName',NULL),
 (319,'spouse',62,'main_applicant',62,'child4','fName',NULL),
 (320,'main_applicant',63,'spouse',63,'child4','DOB','date_year'),
 (321,'main_applicant',64,'spouse',64,'child4','DOB','date_month'),
 (322,'main_applicant',65,'spouse',65,'child4','DOB','date_day'),
 (323,'spouse',63,'main_applicant',63,'child4','DOB','date_year'),
 (324,'spouse',64,'main_applicant',64,'child4','DOB','date_month'),
 (325,'spouse',65,'main_applicant',65,'child4','DOB','date_day'),
 (326,'main_applicant',69,'spouse',69,'child4','passport_num',NULL),
 (327,'spouse',69,'main_applicant',69,'child4','passport_num',NULL),
 (328,'main_applicant',70,'spouse',70,'child4','passport_date','date_year'),
 (329,'main_applicant',71,'spouse',71,'child4','passport_date','date_month'),
 (330,'main_applicant',72,'spouse',72,'child4','passport_date','date_day'),
 (331,'spouse',70,'main_applicant',70,'child4','passport_date','date_year'),
 (332,'spouse',71,'main_applicant',71,'child4','passport_date','date_month'),
 (333,'spouse',72,'main_applicant',72,'child4','passport_date','date_day'),
 (334,'main_applicant',73,'spouse',73,'child5','lName',NULL),
 (335,'spouse',73,'main_applicant',73,'child5','lName',NULL),
 (336,'main_applicant',74,'spouse',74,'child5','fName',NULL),
 (337,'spouse',74,'main_applicant',74,'child5','fName',NULL),
 (338,'main_applicant',75,'spouse',75,'child5','DOB','date_year'),
 (339,'main_applicant',76,'spouse',76,'child5','DOB','date_month'),
 (340,'main_applicant',77,'spouse',77,'child5','DOB','date_day'),
 (341,'spouse',75,'main_applicant',75,'child5','DOB','date_year'),
 (342,'spouse',76,'main_applicant',76,'child5','DOB','date_month'),
 (343,'spouse',77,'main_applicant',77,'child5','DOB','date_day'),
 (344,'main_applicant',81,'spouse',81,'child5','passport_num',NULL),
 (345,'spouse',81,'main_applicant',81,'child5','passport_num',NULL),
 (346,'main_applicant',82,'spouse',82,'child5','passport_date','date_year'),
 (347,'main_applicant',83,'spouse',83,'child5','passport_date','date_month'),
 (348,'main_applicant',84,'spouse',84,'child5','passport_date','date_day'),
 (349,'spouse',82,'main_applicant',82,'child5','passport_date','date_year'),
 (350,'spouse',83,'main_applicant',83,'child5','passport_date','date_month'),
 (351,'spouse',84,'main_applicant',84,'child5','passport_date','date_day'),
 (352,'main_applicant',31,'child1',7,'child1','country_of_citizenship','country'),
 (353,'spouse',31,'child1',7,'child1','country_of_citizenship','country'),
 (354,'child1',7,'main_applicant',31,'child1','country_of_citizenship','country'),
 (355,'child1',7,'spouse',31,'child1','country_of_citizenship','country'),
 (356,'main_applicant',43,'child2',7,'child2','country_of_citizenship','country'),
 (357,'spouse',43,'child2',7,'child2','country_of_citizenship','country'),
 (358,'child2',7,'main_applicant',43,'child2','country_of_citizenship','country'),
 (359,'child2',7,'spouse',43,'child2','country_of_citizenship','country'),
 (360,'main_applicant',55,'child3',7,'child3','country_of_citizenship','country'),
 (361,'spouse',55,'child3',7,'child3','country_of_citizenship','country'),
 (362,'child3',7,'main_applicant',55,'child3','country_of_citizenship','country'),
 (363,'child3',7,'spouse',55,'child3','country_of_citizenship','country'),
 (364,'main_applicant',67,'child4',7,'child4','country_of_citizenship','country'),
 (365,'spouse',67,'child4',7,'child4','country_of_citizenship','country'),
 (366,'child4',7,'main_applicant',67,'child4','country_of_citizenship','country'),
 (367,'child4',7,'spouse',67,'child4','country_of_citizenship','country'),
 (368,'main_applicant',79,'child5',7,'child5','country_of_citizenship','country'),
 (369,'spouse',79,'child5',7,'child5','country_of_citizenship','country'),
 (370,'child5',7,'main_applicant',79,'child5','country_of_citizenship','country'),
 (371,'child5',7,'spouse',79,'child5','country_of_citizenship','country'),
 (372,'main_applicant',30,'child1',6,'child1','country_of_birth','country'),
 (373,'spouse',30,'child1',6,'child1','country_of_birth','country'),
 (374,'child1',6,'main_applicant',30,'child1','country_of_birth','country'),
 (375,'child1',6,'spouse',30,'child1','country_of_birth','country'),
 (376,'main_applicant',32,'child1',8,'child1','country_of_residence','country'),
 (377,'spouse',32,'child1',8,'child1','country_of_residence','country'),
 (378,'child1',8,'main_applicant',32,'child1','country_of_residence','country'),
 (379,'child1',8,'spouse',32,'child1','country_of_residence','country'),
 (380,'main_applicant',42,'child2',6,'child2','country_of_birth','country'),
 (381,'spouse',42,'child2',6,'child2','country_of_birth','country'),
 (382,'child2',6,'main_applicant',42,'child2','country_of_birth','country'),
 (383,'child2',6,'spouse',42,'child2','country_of_birth','country'),
 (384,'main_applicant',44,'child2',8,'child2','country_of_residence','country'),
 (385,'spouse',44,'child2',8,'child2','country_of_residence','country'),
 (386,'child2',8,'main_applicant',44,'child2','country_of_residence','country'),
 (387,'child2',8,'spouse',44,'child2','country_of_residence','country'),
 (388,'main_applicant',54,'child3',6,'child3','country_of_birth','country'),
 (389,'spouse',54,'child3',6,'child3','country_of_birth','country'),
 (390,'child3',6,'main_applicant',54,'child3','country_of_birth','country'),
 (391,'child3',6,'spouse',54,'child3','country_of_birth','country'),
 (392,'main_applicant',56,'child3',8,'child3','country_of_residence','country'),
 (393,'spouse',56,'child3',8,'child3','country_of_residence','country'),
 (394,'child3',8,'main_applicant',56,'child3','country_of_residence','country'),
 (395,'child3',8,'spouse',56,'child3','country_of_residence','country'),
 (396,'main_applicant',66,'child4',6,'child4','country_of_birth','country'),
 (397,'spouse',66,'child4',6,'child4','country_of_birth','country'),
 (398,'child4',6,'main_applicant',66,'child4','country_of_birth','country'),
 (399,'child4',6,'spouse',66,'child4','country_of_birth','country'),
 (400,'main_applicant',68,'child4',8,'child4','country_of_residence','country'),
 (401,'spouse',68,'child4',8,'child4','country_of_residence','country'),
 (402,'child4',8,'main_applicant',68,'child4','country_of_residence','country'),
 (403,'child4',8,'spouse',68,'child4','country_of_residence','country'),
 (404,'main_applicant',78,'child5',6,'child5','country_of_birth','country'),
 (405,'spouse',78,'child5',6,'child5','country_of_birth','country'),
 (406,'child5',6,'main_applicant',78,'child5','country_of_birth','country'),
 (407,'child5',6,'spouse',78,'child5','country_of_birth','country'),
 (408,'main_applicant',80,'child5',8,'child5','country_of_residence','country'),
 (409,'spouse',80,'child5',8,'child5','country_of_residence','country'),
 (410,'child5',8,'main_applicant',80,'child5','country_of_residence','country'),
 (411,'child5',8,'spouse',80,'child5','country_of_residence','country'),
 (412,'spouse',6,'main_applicant',18,'spouse','country_of_birth','country'),
 (413,'main_applicant',30,'spouse',30,'child1','country_of_birth','country'),
 (414,'spouse',30,'main_applicant',30,'child1','country_of_birth','country'),
 (415,'main_applicant',42,'spouse',42,'child2','country_of_birth','country'),
 (416,'spouse',42,'main_applicant',42,'child2','country_of_birth','country'),
 (417,'main_applicant',54,'spouse',54,'child3','country_of_birth','country'),
 (418,'spouse',54,'main_applicant',54,'child3','country_of_birth','country'),
 (419,'main_applicant',66,'spouse',66,'child4','country_of_birth','country'),
 (420,'spouse',66,'main_applicant',66,'child4','country_of_birth','country'),
 (421,'main_applicant',78,'spouse',78,'child5','country_of_birth','country'),
 (422,'spouse',78,'main_applicant',78,'child5','country_of_birth','country'),
 (423,'main_applicant',31,'spouse',31,'child1','country_of_residence','country'),
 (424,'spouse',31,'main_applicant',31,'child1','country_of_residence','country'),
 (425,'main_applicant',43,'spouse',43,'child2','country_of_residence','country'),
 (426,'spouse',43,'main_applicant',43,'child2','country_of_residence','country'),
 (427,'main_applicant',55,'spouse',55,'child3','country_of_residence','country'),
 (428,'spouse',55,'main_applicant',55,'child3','country_of_residence','country'),
 (429,'main_applicant',67,'spouse',67,'child4','country_of_residence','country'),
 (430,'spouse',67,'main_applicant',67,'child4','country_of_residence','country'),
 (431,'main_applicant',79,'spouse',79,'child5','country_of_residence','country'),
 (432,'spouse',79,'main_applicant',79,'child5','country_of_residence','country'),
 (433,'main_applicant',32,'spouse',32,'child1','country_of_citizenship','country'),
 (434,'spouse',32,'main_applicant',32,'child1','country_of_citizenship','country'),
 (435,'main_applicant',44,'spouse',44,'child2','country_of_citizenship','country'),
 (436,'spouse',44,'main_applicant',44,'child2','country_of_citizenship','country'),
 (437,'main_applicant',56,'spouse',56,'child3','country_of_citizenship','country'),
 (438,'spouse',56,'main_applicant',56,'child3','country_of_citizenship','country'),
 (439,'main_applicant',68,'spouse',68,'child4','country_of_citizenship','country'),
 (440,'spouse',68,'main_applicant',68,'child4','country_of_citizenship','country'),
 (441,'main_applicant',80,'spouse',80,'child5','country_of_citizenship','country'),
 (442,'spouse',80,'main_applicant',80,'child5','country_of_citizenship','country'),
 (443,'spouse',15,'main_applicant',3,'main_applicant','DOB','date_year'),
 (444,'spouse',16,'main_applicant',4,'main_applicant','DOB','date_month'),
 (445,'spouse',17,'main_applicant',5,'main_applicant','DOB','date_day');
 


DROP TABLE IF EXISTS `FormSynField`;
CREATE TABLE `FormSynField` (
  `SynFieldId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FieldName` varchar(255) NOT NULL,
  PRIMARY KEY  (`SynFieldId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `FormSynField` VALUES (1, 'syncA_family_name');
INSERT INTO `FormSynField` VALUES (2, 'syncA_given_name');
INSERT INTO `FormSynField` VALUES (3, 'syncA_DOB_year');
INSERT INTO `FormSynField` VALUES (4, 'syncA_DOB_month');
INSERT INTO `FormSynField` VALUES (5, 'syncA_DOB_day');
INSERT INTO `FormSynField` VALUES (6, 'syncA_POB_country');
INSERT INTO `FormSynField` VALUES (7, 'syncA_country_of_citinzenship');
INSERT INTO `FormSynField` VALUES (8, 'syncA_country_of_residence');
INSERT INTO `FormSynField` VALUES (9, 'syncA_passport_number');
INSERT INTO `FormSynField` VALUES (10, 'syncA_passport_expiry_date_year');
INSERT INTO `FormSynField` VALUES (11, 'syncA_passport_expiry_date_month');
INSERT INTO `FormSynField` VALUES (12, 'syncA_passport_expiry_date_day');

INSERT INTO `FormSynField` VALUES (13, 'syncA_family_name_s');
INSERT INTO `FormSynField` VALUES (14, 'syncA_given_name_s');
INSERT INTO `FormSynField` VALUES (15, 'syncA_DOB_year_s');
INSERT INTO `FormSynField` VALUES (16, 'syncA_DOB_month_s');
INSERT INTO `FormSynField` VALUES (17, 'syncA_DOB_day_s');
INSERT INTO `FormSynField` VALUES (18, 'syncA_POB_country_s');
INSERT INTO `FormSynField` VALUES (19, 'syncA_country_of_citinzenship_s');
INSERT INTO `FormSynField` VALUES (20, 'syncA_country_of_residence_s');
INSERT INTO `FormSynField` VALUES (21, 'syncA_passport_number_s');
INSERT INTO `FormSynField` VALUES (22, 'syncA_passport_expiry_date_year_s');
INSERT INTO `FormSynField` VALUES (23, 'syncA_passport_expiry_date_month_s');
INSERT INTO `FormSynField` VALUES (24, 'syncA_passport_expiry_date_day_s');

INSERT INTO `FormSynField` VALUES (25, 'syncA_family_name_c1');
INSERT INTO `FormSynField` VALUES (26, 'syncA_given_name_c1');
INSERT INTO `FormSynField` VALUES (27, 'syncA_DOB_year_c1');
INSERT INTO `FormSynField` VALUES (28, 'syncA_DOB_month_c1');
INSERT INTO `FormSynField` VALUES (29, 'syncA_DOB_day_c1');
INSERT INTO `FormSynField` VALUES (30, 'syncA_POB_country_c1');
INSERT INTO `FormSynField` VALUES (31, 'syncA_country_of_citinzenship_c1');
INSERT INTO `FormSynField` VALUES (32, 'syncA_country_of_residence_c1');
INSERT INTO `FormSynField` VALUES (33, 'syncA_passport_number_c1');
INSERT INTO `FormSynField` VALUES (34, 'syncA_passport_expiry_date_year_c1');
INSERT INTO `FormSynField` VALUES (35, 'syncA_passport_expiry_date_month_c1');
INSERT INTO `FormSynField` VALUES (36, 'syncA_passport_expiry_date_day_c1');

INSERT INTO `FormSynField` VALUES (37, 'syncA_family_name_c2');
INSERT INTO `FormSynField` VALUES (38, 'syncA_given_name_c2');
INSERT INTO `FormSynField` VALUES (39, 'syncA_DOB_year_c2');
INSERT INTO `FormSynField` VALUES (40, 'syncA_DOB_month_c2');
INSERT INTO `FormSynField` VALUES (41, 'syncA_DOB_day_c2');
INSERT INTO `FormSynField` VALUES (42, 'syncA_POB_country_c2');
INSERT INTO `FormSynField` VALUES (43, 'syncA_country_of_citinzenship_c2');
INSERT INTO `FormSynField` VALUES (44, 'syncA_country_of_residence_c2');
INSERT INTO `FormSynField` VALUES (45, 'syncA_passport_number_c2');
INSERT INTO `FormSynField` VALUES (46, 'syncA_passport_expiry_date_year_c2');
INSERT INTO `FormSynField` VALUES (47, 'syncA_passport_expiry_date_month_c2');
INSERT INTO `FormSynField` VALUES (48, 'syncA_passport_expiry_date_day_c2');

INSERT INTO `FormSynField` VALUES (49, 'syncA_family_name_c3');
INSERT INTO `FormSynField` VALUES (50, 'syncA_given_name_c3');
INSERT INTO `FormSynField` VALUES (51, 'syncA_DOB_year_c3');
INSERT INTO `FormSynField` VALUES (52, 'syncA_DOB_month_c3');
INSERT INTO `FormSynField` VALUES (53, 'syncA_DOB_day_c3');
INSERT INTO `FormSynField` VALUES (54, 'syncA_POB_country_c3');
INSERT INTO `FormSynField` VALUES (55, 'syncA_country_of_citinzenship_c3');
INSERT INTO `FormSynField` VALUES (56, 'syncA_country_of_residence_c3');
INSERT INTO `FormSynField` VALUES (57, 'syncA_passport_number_c3');
INSERT INTO `FormSynField` VALUES (58, 'syncA_passport_expiry_date_year_c3');
INSERT INTO `FormSynField` VALUES (59, 'syncA_passport_expiry_date_month_c3');
INSERT INTO `FormSynField` VALUES (60, 'syncA_passport_expiry_date_day_c3');

INSERT INTO `FormSynField` VALUES (61, 'syncA_family_name_c4');
INSERT INTO `FormSynField` VALUES (62, 'syncA_given_name_c4');
INSERT INTO `FormSynField` VALUES (63, 'syncA_DOB_year_c4');
INSERT INTO `FormSynField` VALUES (64, 'syncA_DOB_month_c4');
INSERT INTO `FormSynField` VALUES (65, 'syncA_DOB_day_c4');
INSERT INTO `FormSynField` VALUES (66, 'syncA_POB_country_c4');
INSERT INTO `FormSynField` VALUES (67, 'syncA_country_of_citinzenship_c4');
INSERT INTO `FormSynField` VALUES (68, 'syncA_country_of_residence_c4');
INSERT INTO `FormSynField` VALUES (69, 'syncA_passport_number_c4');
INSERT INTO `FormSynField` VALUES (70, 'syncA_passport_expiry_date_year_c4');
INSERT INTO `FormSynField` VALUES (71, 'syncA_passport_expiry_date_month_c4');
INSERT INTO `FormSynField` VALUES (72, 'syncA_passport_expiry_date_day_c4');

INSERT INTO `FormSynField` VALUES (73, 'syncA_family_name_c5');
INSERT INTO `FormSynField` VALUES (74, 'syncA_given_name_c5');
INSERT INTO `FormSynField` VALUES (75, 'syncA_DOB_year_c5');
INSERT INTO `FormSynField` VALUES (76, 'syncA_DOB_month_c5');
INSERT INTO `FormSynField` VALUES (77, 'syncA_DOB_day_c5');
INSERT INTO `FormSynField` VALUES (78, 'syncA_POB_country_c5');
INSERT INTO `FormSynField` VALUES (79, 'syncA_country_of_citinzenship_c5');
INSERT INTO `FormSynField` VALUES (80, 'syncA_country_of_residence_c5');
INSERT INTO `FormSynField` VALUES (81, 'syncA_passport_number_c5');
INSERT INTO `FormSynField` VALUES (82, 'syncA_passport_expiry_date_year_c5');
INSERT INTO `FormSynField` VALUES (83, 'syncA_passport_expiry_date_month_c5');
INSERT INTO `FormSynField` VALUES (84, 'syncA_passport_expiry_date_day_c5');


DROP TABLE IF EXISTS `FormAssigned`;
CREATE TABLE `FormAssigned` (
  `FormAssignedId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,

  /* Which client is assigned to (his/her member id)*/
  `ClientMemberId` bigint(20) NOT NULL,

  /* Which family member is assigned to */
  `FamilyMemberId` enum('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') default 'main_applicant',

  /* Which form is assigned to */
  `FormVersionId` int(10) UNSIGNED NOT NULL,

  /* Y if revisions will be used for this form */
  UseRevision ENUM('Y','N') NOT NULL DEFAULT 'N',
  
  /* When form was assigned to */
  `AssignDate` datetime NOT NULL,

  /* Member Id who assigned or created this form */
  `AssignBy` bigint(20) NOT NULL,

  /* When form was marked as completed */
  `CompletedDate` datetime DEFAULT NULL,
  
  /* When form was marked as finalized */
  `FinalizedDate` datetime DEFAULT NULL,

  /* Member Id who updated this form last time */
  `UpdatedBy` bigint(20) DEFAULT NULL,

  /* Not used right now */
  `Status` tinyint(1) NOT NULL default '0',

   `AssignAlias` varchar(255) DEFAULT NULL,
   `LastUpdateDate` datetime default NULL,

  PRIMARY KEY  (`FormAssignedId`),
  CONSTRAINT `FK_FormAssigned_members` FOREIGN KEY (`ClientMemberId`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_formassigned_formversion` FOREIGN KEY (`FormVersionId`) REFERENCES `FormVersion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



DROP TABLE IF EXISTS `FormRevision`;
CREATE TABLE `FormRevision` (
	FormRevisionId INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

	/* Form id it is related to */
	FormAssignedId INT(10) UNSIGNED NOT NULL,

	/* Revision number */
	FormRevisionNumber INT(3) UNSIGNED NOT NULL,

	/* Member Id who uploaded this form */
	UploadedBy BIGINT(20) NOT NULL,

	/* When form was uploaded */
	UploadedOn DATETIME NOT NULL,

	PRIMARY KEY (FormRevisionId),
	INDEX FK_formrevision_formassigned (FormAssignedId),
	INDEX FK_formrevision_members (UploadedBy),
	CONSTRAINT `FK_formrevision_formassigned` FOREIGN KEY (`FormAssignedId`) REFERENCES `FormAssigned` (`FormAssignedId`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `AssignForm`;
DROP TABLE IF EXISTS `ClientForm`;
DROP TABLE IF EXISTS `ClientFormStatus`;


/* Sample insert data*/
DELETE FROM `FormFolder`;
INSERT INTO `FormFolder` (`FolderId`,`ParentId`,`FolderName`) VALUES
  (1, 0, 'English'),
  (2, 0, 'French'),
  (3, 1, 'Citizenship'),
  (4, 1, 'HRSDC'),
  (5, 4, 'E-LMO'),
  (6, 1, 'In-land'),
  (7, 1, 'Miscellaneous'),
  (8, 1, 'Overseas'),
  (9, 1, 'PNP'),
  (10, 9, 'AB'),
  (11, 9, 'BC'),
  (12, 9, 'MB'),
  (13, 9, 'NB'),
  (14, 9, 'NF'),
  (15, 9, 'NS'),
  (16, 9, 'ON'),
  (17, 9, 'PEI'),
  (18, 9, 'SK'),
  (19, 9, 'YN'),
  (20, 1, 'Quebec'),
  (21, 1, 'Refugee'),
  (22, 1, 'Sponsorship'),
  (23, 2, 'Federal'),
  (24, 2, 'Quebec')
;