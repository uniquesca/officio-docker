<?php

use Phinx\Migration\AbstractMigration;

class AddNewQnrFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("SET NAMES UTF8");

        $this->execute("INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_use_in_search`, `q_field_order`) VALUES
        (131, 'qf_area_of_interest', 1, 'checkbox', 'N', 'Y', 'N',  'N', 13)
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 131, 'Area of Interest', 'Area of Interest' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options` (`q_field_option_id`, `q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
        (574, 131, 'immigrate', 'N', 0),
        (575, 131, 'work', 'N', 1),
        (576, 131, 'study', 'N', 2),
        (577, 131, 'invest', 'N', 3),
        (578, 131, 'not_sure', 'N', 4)
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 574, 'Immigrate To Canada', 'Y' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 575, 'Work in Canada', 'Y' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 576, 'Study in Canada', 'Y' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 577, 'Invest in Canada', 'Y' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 578, 'Not sure', 'Y' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_sections` (`q_section_id`, `q_section_step`, `q_section_order`) VALUES
        (13, 2, 7)
        ");

        $this->execute("INSERT INTO `company_questionnaires_sections_templates` (`q_id`, `q_section_id`, `q_section_template_name`, `q_section_prospect_profile`)
        SELECT q_id, 13, 'Previous and the Future Visit(s)', 'Previous and the Future Visit(s)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_use_in_search`, `q_field_order`) VALUES
        (132, 'qf_visit_previously_visited', 13, 'combo', 'N', 'Y', 'Y',  'Y', 0),
        (133, 'qf_visit_previously_applied', 13, 'combo', 'N', 'Y', 'Y',  'Y', 1),
        (134, 'qf_visit_preferred_destination', 13, 'combo', 'N', 'Y', 'Y',  'Y', 2),
        (135, 'qf_visit_previously_submitted_express_entry', 13, 'combo', 'N', 'Y', 'Y',  'Y', 3);
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 132, 'Have you or your spouse previously visited Canada for work, travel, or study?', 'Have you or your spouse previously visited Canada for work, travel, or study?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 133, 'Have you or your spouse previously applied for immigration or visa to Canada?', 'Have you or your spouse previously applied for immigration or visa to Canada?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 134, 'Where is your preferred destination in Canada?', 'Where is your preferred destination in Canada?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 135, 'Have you previously submitted an Express Entry application? application?', 'Have you previously submitted an Express Entry application? application?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options` (`q_field_option_id`, `q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
        (579, 132, 'yes', 'N', 0),
        (580, 132, 'no', 'N', 1),
                
        (581, 133, 'yes', 'N', 0),
        (582, 133, 'no', 'N', 1),
        
        (583, 134, 'any', 'N', 0),
        (584, 134, 'ab', 'N', 1),
        (585, 134, 'bc', 'N', 2),
        (586, 134, 'mb', 'N', 3),
        (587, 134, 'nb', 'N', 4),
        (588, 134, 'nl', 'N', 5),
        (589, 134, 'nt', 'N', 6),
        (590, 134, 'ns', 'N', 7),
        (591, 134, 'nu', 'N', 8),
        (592, 134, 'on', 'N', 9),
        (593, 134, 'pe', 'N', 10),
        (594, 134, 'qc', 'N', 11),
        (595, 134, 'sk', 'N', 12),
        (596, 134, 'yt', 'N', 13),
        
        (597, 135, 'yes', 'N', 0),
        (598, 135, 'no', 'N', 1),
        (599, 135, 'not_sure', 'N', 2)
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 579, 'Yes', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 580, 'No', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 581, 'Yes', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 582, 'No', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 583, 'Any', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 584, 'Alberta (AB)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 585, 'British Columbia (BC)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 586, 'Manitoba (MB)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 587, 'New Brunswick (NB)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 588, 'Newfoundland and Labrador (NL)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 589, 'Northwest Territories (NT)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 590, 'Nova Scotia (NS)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 591, 'Nunavut (NU)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 592, 'Ontario (ON)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 593, 'Prince Edward Island (PE)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 594, 'Quebec (QC)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 595, 'Saskatchewan (SK)', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 596, 'Yukon (YT)', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 597, 'Yes', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 598, 'No', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 599, 'I am not sure what it is', 'Y' FROM company_questionnaires;");


        $this->execute("INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_use_in_search`, `q_field_order`) VALUES
        (136, 'qf_language_english_done', 5, 'combo', 'N', 'Y', 'Y',  'Y', 1),
        (137, 'qf_language_english_ielts_scores_label', 5, 'label', 'N', 'Y', 'Y',  'N', 2),
        (138, 'qf_language_english_ielts_score_speak', 5, 'number', 'N', 'Y', 'Y',  'Y', 3),
        (139, 'qf_language_english_ielts_score_read', 5, 'number', 'N', 'Y', 'Y',  'Y', 4),
        (140, 'qf_language_english_ielts_score_write', 5, 'number', 'N', 'Y', 'Y',  'Y', 5),
        (141, 'qf_language_english_ielts_score_listen', 5, 'number', 'N', 'Y', 'Y',  'Y', 6),
        (142, 'qf_language_english_general_label', 5, 'label', 'N', 'Y', 'Y',  'N', 7),
        (143, 'qf_language_english_general_score_speak', 5, 'combo', 'N', 'Y', 'Y',  'Y', 8),
        (144, 'qf_language_english_general_score_read', 5, 'combo', 'N', 'Y', 'Y',  'Y', 9),
        (145, 'qf_language_english_general_score_write', 5, 'combo', 'N', 'Y', 'Y',  'Y', 10),
        (146, 'qf_language_english_general_score_listen', 5, 'combo', 'N', 'Y', 'Y',  'Y', 11),

        (147, 'qf_language_french_done', 5, 'combo', 'N', 'Y', 'Y',  'Y', 12),
        (148, 'qf_language_french_tef_scores_label', 5, 'label', 'N', 'Y', 'Y',  'N', 13),
        (149, 'qf_language_french_tef_score_speak', 5, 'number', 'N', 'Y', 'Y',  'Y', 14),
        (150, 'qf_language_french_tef_score_read', 5, 'number', 'N', 'Y', 'Y',  'Y', 15),
        (151, 'qf_language_french_tef_score_write', 5, 'number', 'N', 'Y', 'Y',  'Y', 16),
        (152, 'qf_language_french_tef_score_listen', 5, 'number', 'N', 'Y', 'Y',  'Y', 17),
        (153, 'qf_language_french_general_label', 5, 'label', 'N', 'Y', 'Y',  'N', 18),
        (154, 'qf_language_french_general_score_speak', 5, 'combo', 'N', 'Y', 'Y',  'Y', 19),
        (155, 'qf_language_french_general_score_read', 5, 'combo', 'N', 'Y', 'Y',  'Y', 20),
        (156, 'qf_language_french_general_score_write', 5, 'combo', 'N', 'Y', 'Y',  'Y', 21),
        (157, 'qf_language_french_general_score_listen', 5, 'combo', 'N', 'Y', 'Y',  'Y', 22),        
        
        (158, 'qf_language_spouse_english_done', 5, 'combo', 'N', 'Y', 'Y',  'Y', 23),
        (159, 'qf_language_spouse_english_ielts_scores_label', 5, 'label', 'N', 'Y', 'Y',  'N', 24),
        (160, 'qf_language_spouse_english_ielts_score_speak', 5, 'number', 'N', 'Y', 'Y',  'Y', 25),
        (161, 'qf_language_spouse_english_ielts_score_read', 5, 'number', 'N', 'Y', 'Y',  'Y', 26),
        (162, 'qf_language_spouse_english_ielts_score_write', 5, 'number', 'N', 'Y', 'Y',  'Y', 27),
        (163, 'qf_language_spouse_english_ielts_score_listen', 5, 'number', 'N', 'Y', 'Y',  'Y', 28),
        (164, 'qf_language_spouse_english_general_label', 5, 'label', 'N', 'Y', 'Y',  'N', 29),
        (165, 'qf_language_spouse_english_general_score_speak', 5, 'combo', 'N', 'Y', 'Y',  'Y', 30),
        (166, 'qf_language_spouse_english_general_score_read', 5, 'combo', 'N', 'Y', 'Y',  'Y', 31),
        (167, 'qf_language_spouse_english_general_score_write', 5, 'combo', 'N', 'Y', 'Y',  'Y', 32),
        (168, 'qf_language_spouse_english_general_score_listen', 5, 'combo', 'N', 'Y', 'Y',  'Y', 33),

        (169, 'qf_language_spouse_french_done', 5, 'combo', 'N', 'Y', 'Y',  'Y', 34),
        (170, 'qf_language_spouse_french_tef_scores_label', 5, 'label', 'N', 'Y', 'Y',  'N', 35),
        (171, 'qf_language_spouse_french_tef_score_speak', 5, 'number', 'N', 'Y', 'Y',  'Y', 36),
        (172, 'qf_language_spouse_french_tef_score_read', 5, 'number', 'N', 'Y', 'Y',  'Y', 37),
        (173, 'qf_language_spouse_french_tef_score_write', 5, 'number', 'N', 'Y', 'Y',  'Y', 38),
        (174, 'qf_language_spouse_french_tef_score_listen', 5, 'number', 'N', 'Y', 'Y',  'Y', 39),
        (175, 'qf_language_spouse_french_general_label', 5, 'label', 'N', 'Y', 'Y',  'N', 40),
        (176, 'qf_language_spouse_french_general_score_speak', 5, 'combo', 'N', 'Y', 'Y',  'Y', 41),
        (177, 'qf_language_spouse_french_general_score_read', 5, 'combo', 'N', 'Y', 'Y',  'Y', 42),
        (178, 'qf_language_spouse_french_general_score_write', 5, 'combo', 'N', 'Y', 'Y',  'Y', 43),
        (179, 'qf_language_spouse_french_general_score_listen', 5, 'combo', 'N', 'Y', 'Y',  'Y', 44)
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 136, 'Have you done IELTS (International English Language Testing System)?', 'Have you done IELTS (International English Language Testing System)?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 137, 'English (IELTS scores)', 'English (IELTS scores)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 138, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 139, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 140, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 141, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 142, 'English (general proficiency)', 'English (general proficiency)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 143, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 144, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 145, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 146, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 147, 'Have you done TEF (Test d\'évaluation de français)?', 'Have you done TEF (Test d\'évaluation de français)?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 148, 'French (TEF scores)', 'French (TEF scores)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 149, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 150, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 151, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 152, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 153, 'French (general proficiency)', 'French (general proficiency)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 154, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 155, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 156, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 157, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 158, 'Has your spouse/common-law parter done IELTS (International English Language Testing System)?', 'Has your spouse/common-law parter done IELTS (International English Language Testing System)?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 159, 'English (IELTS scores)', 'English (IELTS scores)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 160, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 161, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 162, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 163, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 164, 'English (general proficiency)', 'English (general proficiency)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 165, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 166, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 167, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 168, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 169, 'Has your spouse done TEF (Test d\'évaluation de français)?', 'Has your spouse done TEF (Test d\'évaluation de français)?' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 170, 'French (TEF scores)', 'French (TEF scores)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 171, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 172, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 173, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 174, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 175, 'French (general proficiency)', 'French (general proficiency)' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 176, 'Speak', 'Speak' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 177, 'Read', 'Read' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 178, 'Write', 'Write' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 179, 'Listen', 'Listen' FROM company_questionnaires;
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options` (`q_field_option_id`, `q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
        (600, 136, 'yes', 'N', 0),
        (601, 136, 'no', 'N', 1),
        (602, 136, 'not_sure', 'N', 2),
                
        (603, 143, 'elementary', 'N', 0),
        (604, 143, 'limited_working', 'N', 1),
        (605, 143, 'professional_working', 'N', 2),
        (606, 143, 'full_professional', 'N', 3),
        (607, 144, 'elementary', 'N', 0),
        (608, 144, 'limited_working', 'N', 1),
        (609, 144, 'professional_working', 'N', 2),
        (610, 144, 'full_professional', 'N', 3),
        (611, 145, 'elementary', 'N', 0),
        (612, 145, 'limited_working', 'N', 1),
        (613, 145, 'professional_working', 'N', 2),
        (614, 145, 'full_professional', 'N', 3),
        (615, 146, 'elementary', 'N', 0),
        (616, 146, 'limited_working', 'N', 1),
        (617, 146, 'professional_working', 'N', 2),
        (618, 146, 'full_professional', 'N', 3),
        
        (619, 147, 'yes', 'N', 0),
        (620, 147, 'no', 'N', 1),
        (621, 147, 'not_sure', 'N', 2),
        
        (622, 154, 'elementary', 'N', 0),
        (623, 154, 'limited_working', 'N', 1),
        (624, 154, 'professional_working', 'N', 2),
        (625, 154, 'full_professional', 'N', 3),
        (626, 155, 'elementary', 'N', 0),
        (627, 155, 'limited_working', 'N', 1),
        (628, 155, 'professional_working', 'N', 2),
        (629, 155, 'full_professional', 'N', 3),
        (630, 156, 'elementary', 'N', 0),
        (631, 156, 'limited_working', 'N', 1),
        (632, 156, 'professional_working', 'N', 2),
        (633, 156, 'full_professional', 'N', 3),
        (634, 157, 'elementary', 'N', 0),
        (635, 157, 'limited_working', 'N', 1),
        (636, 157, 'professional_working', 'N', 2),
        (637, 157, 'full_professional', 'N', 3), 
               
        (638, 158, 'yes', 'N', 0),
        (639, 158, 'no', 'N', 1),
        (640, 158, 'not_sure', 'N', 2),
               
        (641, 165, 'elementary', 'N', 0),
        (642, 165, 'limited_working', 'N', 1),
        (643, 165, 'professional_working', 'N', 2),
        (644, 165, 'full_professional', 'N', 3),
        (645, 166, 'elementary', 'N', 0),
        (646, 166, 'limited_working', 'N', 1),
        (647, 166, 'professional_working', 'N', 2),
        (648, 166, 'full_professional', 'N', 3),
        (649, 167, 'elementary', 'N', 0),
        (650, 167, 'limited_working', 'N', 1),
        (651, 167, 'professional_working', 'N', 2),
        (652, 167, 'full_professional', 'N', 3),
        (653, 168, 'elementary', 'N', 0),
        (654, 168, 'limited_working', 'N', 1),
        (655, 168, 'professional_working', 'N', 2),
        (656, 168, 'full_professional', 'N', 3),
        
        (657, 169, 'yes', 'N', 0),
        (658, 169, 'no', 'N', 1),
        (659, 169, 'not_sure', 'N', 2),
        
        (660, 176, 'elementary', 'N', 0),
        (661, 176, 'limited_working', 'N', 1),
        (662, 176, 'professional_working', 'N', 2),
        (663, 176, 'full_professional', 'N', 3),
        (664, 177, 'elementary', 'N', 0),
        (665, 177, 'limited_working', 'N', 1),
        (666, 177, 'professional_working', 'N', 2),
        (667, 177, 'full_professional', 'N', 3),
        (668, 178, 'elementary', 'N', 0),
        (669, 178, 'limited_working', 'N', 1),
        (670, 178, 'professional_working', 'N', 2),
        (671, 178, 'full_professional', 'N', 3),
        (672, 179, 'elementary', 'N', 0),
        (673, 179, 'limited_working', 'N', 1),
        (674, 179, 'professional_working', 'N', 2),
        (675, 179, 'full_professional', 'N', 3);
        ");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 600, 'Yes', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 601, 'No', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 602, 'Not sure', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 603, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 604, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 605, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 606, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 607, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 608, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 609, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 610, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 611, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 612, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 613, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 614, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 615, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 616, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 617, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 618, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 619, 'Yes', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 620, 'No', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 621, 'Not sure', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 622, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 623, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 624, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 625, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 626, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 627, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 628, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 629, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 630, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 631, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 632, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 633, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 634, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 635, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 636, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 637, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 638, 'Yes', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 639, 'No', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 640, 'Not sure', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 641, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 642, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 643, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 644, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 645, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 646, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 647, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 648, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 649, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 650, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 651, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 652, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 653, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 654, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 655, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 656, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 657, 'Yes', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 658, 'No', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 659, 'Not sure', 'Y' FROM company_questionnaires;");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 660, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 661, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 662, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 663, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 664, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 665, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 666, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 667, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 668, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 669, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 670, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 671, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 672, 'Elementary or no proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 673, 'Limited working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 674, 'Professional working proficiency', 'Y' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 675, 'Full professional/native proficiency', 'Y' FROM company_questionnaires;");

        // Automatically check "Not sure" for all 4 combos
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 136, 602 FROM company_prospects;");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 147, 621 FROM company_prospects;");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 158, 640 FROM company_prospects;");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 169, 659 FROM company_prospects;");

        // Convert values for "English Speak"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 143, 606 FROM company_prospects_data WHERE q_field_id = 73 AND q_value IN (373, 374, 375);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 143, 605 FROM company_prospects_data WHERE q_field_id = 73 AND q_value IN (376, 377, 378);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 143, 604 FROM company_prospects_data WHERE q_field_id = 73 AND q_value IN (379, 380, 381);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 143, 603 FROM company_prospects_data WHERE q_field_id = 73 AND q_value IN (382, 383, 384);");

        // Convert values for "English Read"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 144, 610 FROM company_prospects_data WHERE q_field_id = 79 AND q_value IN (421, 422, 423);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 144, 609 FROM company_prospects_data WHERE q_field_id = 79 AND q_value IN (424, 425, 426);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 144, 608 FROM company_prospects_data WHERE q_field_id = 79 AND q_value IN (427, 428, 429);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 144, 607 FROM company_prospects_data WHERE q_field_id = 79 AND q_value IN (430, 431, 432);");

        // Convert values for "English Write"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 145, 614 FROM company_prospects_data WHERE q_field_id = 85 AND q_value IN (469, 470, 471);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 145, 613 FROM company_prospects_data WHERE q_field_id = 85 AND q_value IN (472, 473, 474);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 145, 612 FROM company_prospects_data WHERE q_field_id = 85 AND q_value IN (475, 476, 477);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 145, 611 FROM company_prospects_data WHERE q_field_id = 85 AND q_value IN (478, 479, 480);");

        // Convert values for "English Listen"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 146, 618 FROM company_prospects_data WHERE q_field_id = 91 AND q_value IN (517, 518, 519);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 146, 617 FROM company_prospects_data WHERE q_field_id = 91 AND q_value IN (520, 521, 522);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 146, 616 FROM company_prospects_data WHERE q_field_id = 91 AND q_value IN (523, 524, 525);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 146, 615 FROM company_prospects_data WHERE q_field_id = 91 AND q_value IN (526, 527, 528);");

        // **********************************
        // Convert values for "French Speak"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 154, 625 FROM company_prospects_data WHERE q_field_id = 74 AND q_value IN (385, 386, 387);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 154, 624 FROM company_prospects_data WHERE q_field_id = 74 AND q_value IN (388, 389, 390);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 154, 623 FROM company_prospects_data WHERE q_field_id = 74 AND q_value IN (391, 392, 393);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 154, 622 FROM company_prospects_data WHERE q_field_id = 74 AND q_value IN (394, 395, 396);");

        // Convert values for "French Read"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 155, 629 FROM company_prospects_data WHERE q_field_id = 80 AND q_value IN (433, 434, 435);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 155, 628 FROM company_prospects_data WHERE q_field_id = 80 AND q_value IN (436, 437, 438);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 155, 627 FROM company_prospects_data WHERE q_field_id = 80 AND q_value IN (439, 440, 441);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 155, 626 FROM company_prospects_data WHERE q_field_id = 80 AND q_value IN (442, 443, 444);");

        // Convert values for "French Write"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 156, 633 FROM company_prospects_data WHERE q_field_id = 86 AND q_value IN (481, 482, 483);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 156, 632 FROM company_prospects_data WHERE q_field_id = 86 AND q_value IN (484, 485, 486);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 156, 631 FROM company_prospects_data WHERE q_field_id = 86 AND q_value IN (487, 488, 489);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 156, 630 FROM company_prospects_data WHERE q_field_id = 86 AND q_value IN (490, 491, 492);");

        // Convert values for "French Listen"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 157, 637 FROM company_prospects_data WHERE q_field_id = 92 AND q_value IN (529, 530, 531);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 157, 636 FROM company_prospects_data WHERE q_field_id = 92 AND q_value IN (532, 533, 534);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 157, 635 FROM company_prospects_data WHERE q_field_id = 92 AND q_value IN (535, 536, 537);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 157, 634 FROM company_prospects_data WHERE q_field_id = 92 AND q_value IN (538, 539, 540);");

        // **********************************
        // Convert values for "SPOUSE English Speak"
        // **********************************
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 165, 644 FROM company_prospects_data WHERE q_field_id = 76 AND q_value IN (397, 398, 399);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 165, 643 FROM company_prospects_data WHERE q_field_id = 76 AND q_value IN (400, 401, 402);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 165, 642 FROM company_prospects_data WHERE q_field_id = 76 AND q_value IN (403, 404, 405);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 165, 641 FROM company_prospects_data WHERE q_field_id = 76 AND q_value IN (406, 407, 408);");

        // Convert values for "SPOUSE English Read"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 166, 648 FROM company_prospects_data WHERE q_field_id = 82 AND q_value IN (445, 446, 447);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 166, 647 FROM company_prospects_data WHERE q_field_id = 82 AND q_value IN (448, 449, 450);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 166, 646 FROM company_prospects_data WHERE q_field_id = 82 AND q_value IN (451, 452, 453);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 166, 645 FROM company_prospects_data WHERE q_field_id = 82 AND q_value IN (454, 455, 456);");

        // Convert values for "SPOUSE English Write"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 167, 652 FROM company_prospects_data WHERE q_field_id = 88 AND q_value IN (493, 494, 495);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 167, 651 FROM company_prospects_data WHERE q_field_id = 88 AND q_value IN (496, 497, 498);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 167, 650 FROM company_prospects_data WHERE q_field_id = 88 AND q_value IN (499, 500, 501);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 167, 649 FROM company_prospects_data WHERE q_field_id = 88 AND q_value IN (502, 503, 504);");

        // Convert values for "SPOUSE English Listen"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 168, 656 FROM company_prospects_data WHERE q_field_id = 94 AND q_value IN (541, 542, 543);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 168, 655 FROM company_prospects_data WHERE q_field_id = 94 AND q_value IN (544, 545, 546);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 168, 654 FROM company_prospects_data WHERE q_field_id = 94 AND q_value IN (547, 548, 549);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 168, 653 FROM company_prospects_data WHERE q_field_id = 94 AND q_value IN (550, 551, 552);");

        // **********************************
        // Convert values for "SPOUSE French Speak"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 176, 663 FROM company_prospects_data WHERE q_field_id = 77 AND q_value IN (409, 410, 411);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 176, 662 FROM company_prospects_data WHERE q_field_id = 77 AND q_value IN (412, 413, 414);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 176, 661 FROM company_prospects_data WHERE q_field_id = 77 AND q_value IN (415, 416, 417);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 176, 660 FROM company_prospects_data WHERE q_field_id = 77 AND q_value IN (418, 419, 420);");

        // Convert values for "SPOUSE French Read"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 177, 667 FROM company_prospects_data WHERE q_field_id = 83 AND q_value IN (457, 458, 459);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 177, 666 FROM company_prospects_data WHERE q_field_id = 83 AND q_value IN (460, 461, 462);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 177, 665 FROM company_prospects_data WHERE q_field_id = 83 AND q_value IN (463, 464, 465);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 177, 664 FROM company_prospects_data WHERE q_field_id = 83 AND q_value IN (466, 467, 468);");

        // Convert values for "SPOUSE French Write"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 178, 671 FROM company_prospects_data WHERE q_field_id = 89 AND q_value IN (505, 506, 507);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 178, 670 FROM company_prospects_data WHERE q_field_id = 89 AND q_value IN (508, 509, 510);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 178, 669 FROM company_prospects_data WHERE q_field_id = 89 AND q_value IN (511, 512, 513);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 178, 668 FROM company_prospects_data WHERE q_field_id = 89 AND q_value IN (514, 515, 516);");

        // Convert values for "SPOUSE French Listen"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 179, 675 FROM company_prospects_data WHERE q_field_id = 95 AND q_value IN (553, 554, 555);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 179, 674 FROM company_prospects_data WHERE q_field_id = 95 AND q_value IN (556, 557, 558);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 179, 673 FROM company_prospects_data WHERE q_field_id = 95 AND q_value IN (559, 560, 561);");

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, 179, 672 FROM company_prospects_data WHERE q_field_id = 95 AND q_value IN (562, 563, 564);");

        // Delete old fields
        $this->execute('ALTER TABLE `company_questionnaires_fields_options` DROP FOREIGN KEY `FK_company_questionnaires_fields_options_1`;');
        $this->execute('ALTER TABLE `company_questionnaires_fields_options` ADD CONSTRAINT `FK_company_questionnaires_fields_options_1` FOREIGN KEY (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute('DELETE FROM company_prospects_data WHERE q_field_id >= 65 AND q_field_id <= 95;');
        $this->execute('DELETE FROM company_questionnaires_fields_templates WHERE q_field_id >= 65 AND q_field_id <= 95;');
        $this->execute('DELETE FROM company_questionnaires_fields WHERE q_field_id >= 65 AND q_field_id <= 95;');

        $this->execute("INSERT INTO `company_questionnaires_fields_options` (`q_field_option_id`, `q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
        (676, 48, 'prefer_not_to_disclose', 'N', 10)");

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, 676, 'Prefer not to disclose', 'Y' FROM company_questionnaires;");

        $this->execute("UPDATE `company_questionnaires_fields_options` SET `q_field_option_unique_id`='public' WHERE  `q_field_option_id`=363;");
        $this->execute("UPDATE `company_questionnaires_fields_options_templates` SET `q_field_option_label`='Public' WHERE  `q_field_option_id`=363;");
        $this->execute("UPDATE `company_questionnaires_fields_options` SET `q_field_option_unique_id`='public' WHERE  `q_field_option_id`=365;");
        $this->execute("UPDATE `company_questionnaires_fields_options_templates` SET `q_field_option_label`='Public' WHERE  `q_field_option_id`=365;");
    }

    public function down()
    {
        $this->execute("UPDATE `company_questionnaires_fields_options` SET `q_field_option_unique_id`='governmental' WHERE  `q_field_option_id`=363;");
        $this->execute("UPDATE `company_questionnaires_fields_options_templates` SET `q_field_option_label`='Governmental' WHERE  `q_field_option_id`=363;");
        $this->execute("UPDATE `company_questionnaires_fields_options` SET `q_field_option_unique_id`='governmental' WHERE  `q_field_option_id`=365;");
        $this->execute("UPDATE `company_questionnaires_fields_options_templates` SET `q_field_option_label`='Governmental' WHERE  `q_field_option_id`=365;");
        $this->execute('DELETE FROM company_questionnaires_fields_options_templates WHERE q_field_option_id >= 574;');
        $this->execute('DELETE FROM company_questionnaires_fields_options WHERE q_field_option_id IN >= 574;');
        $this->execute('DELETE FROM company_questionnaires_fields_templates WHERE q_field_id >= 131;');
        $this->execute('DELETE FROM company_questionnaires_fields WHERE q_field_id >= 131;');
        $this->execute('DELETE FROM company_questionnaires_sections WHERE q_section_id IN (13);');
    }
}