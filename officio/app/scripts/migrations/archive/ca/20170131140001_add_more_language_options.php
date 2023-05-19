<?php

use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddMoreLanguageOptions extends AbstractMigration
{
    public function updateProspectsFieldData($arrProspectIds, $newFieldId, $newFieldOption, $oldFieldId, $oldFieldOptions)
    {
        if (count($arrProspectIds)) {
            try {
                /** @var $db Zend_Db_Adapter_Abstract */
                $db = Zend_Registry::get('serviceManager')->get('db');

                /** @var array $arrSettings */
                $arrSettings                     = Zend_Registry::get('serviceManager')->get('config')['db'];
                $arrSettings['params']['dbname'] = 'Officio_CA_Tmp';

                $arrTmpDbConfig = new Zend_Config($arrSettings);


                /** @var $dbTmp Zend_Db_Adapter_Abstract */
                $dbTmp = Zend_Db::factory($arrTmpDbConfig);

                $select = $dbTmp->select()
                    ->from('company_prospects_data', 'prospect_id')
                    ->where('q_field_id = ?', $oldFieldId, 'INT')
                    ->where('q_value IN (?)', $oldFieldOptions);

                $arrThisOptionProspectIds = array_unique($dbTmp->fetchCol($select));
                $arrUpdateProspectIds     = array_intersect($arrProspectIds, $arrThisOptionProspectIds);
                if (count($arrUpdateProspectIds)) {
                    $db->delete(
                        'company_prospects_data',
                        $db->quoteInto('prospect_id IN (?)', $arrUpdateProspectIds, 'INT') .
                        $db->quoteInto(' AND q_field_id = ?', $newFieldId, 'INT')
                    );

                    $arrInsertValues = array();
                    foreach ($arrUpdateProspectIds as $prospectId) {
                        $arrInsertValues[] = sprintf(
                            '(%d, %d, %d)',
                            $prospectId,
                            $newFieldId,
                            $newFieldOption
                        );
                    }

                    if (count($arrInsertValues)) {
                        $db->query(sprintf("INSERT INTO company_prospects_data (`prospect_id`, `q_field_id`, `q_value`) VALUES %s", implode(',', $arrInsertValues)));
                    }
                }
            } catch (Zend_Db_Adapter_Exception $e) {
                // Do nothing
                /** @var Log $log */
                $log = Zend_Registry::get('serviceManager')->get('log');
                $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            } catch (\Exception $e) {
                /** @var Log $log */
                $log = Zend_Registry::get('serviceManager')->get('log');
                $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }
    }

    private function getFieldsMapping()
    {
        $arrFieldsMapping = array(
            array(
                'new_field_id'       => 'qf_language_english_general_score_speak',
                'check_field_id'     => 'qf_language_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 73,
                    'olddb_field_options' => array(373, 374, 375, 376),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 73,
                    'olddb_field_options' => array(377),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 73,
                    'olddb_field_options' => array(378),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 73,
                    'olddb_field_options' => array(379, 380),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 73,
                    'olddb_field_options' => array(381, 382),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 73,
                    'olddb_field_options' => array(383, 384),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_english_general_score_read',
                'check_field_id'     => 'qf_language_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 79,
                    'olddb_field_options' => array(421, 422, 423, 424),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 79,
                    'olddb_field_options' => array(425),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 79,
                    'olddb_field_options' => array(426),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 79,
                    'olddb_field_options' => array(427, 428),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 79,
                    'olddb_field_options' => array(429, 430),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 79,
                    'olddb_field_options' => array(731, 432),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_english_general_score_write',
                'check_field_id'     => 'qf_language_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 85,
                    'olddb_field_options' => array(469, 470, 471, 472),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 85,
                    'olddb_field_options' => array(473),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 85,
                    'olddb_field_options' => array(474),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 85,
                    'olddb_field_options' => array(475, 476),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 85,
                    'olddb_field_options' => array(477, 478),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 85,
                    'olddb_field_options' => array(479, 480),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_english_general_score_listen',
                'check_field_id'     => 'qf_language_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 91,
                    'olddb_field_options' => array(517, 518, 519, 520),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 91,
                    'olddb_field_options' => array(521),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 91,
                    'olddb_field_options' => array(522),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 91,
                    'olddb_field_options' => array(523, 524),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 91,
                    'olddb_field_options' => array(525, 526),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 91,
                    'olddb_field_options' => array(527, 528),
                ),

            ),

            array(
                'new_field_id'       => 'qf_language_french_general_score_speak',
                'check_field_id'     => 'qf_language_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 74,
                    'olddb_field_options' => array(385, 386, 387, 388),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 74,
                    'olddb_field_options' => array(389),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 74,
                    'olddb_field_options' => array(390),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 74,
                    'olddb_field_options' => array(391, 392),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 74,
                    'olddb_field_options' => array(393, 394),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 74,
                    'olddb_field_options' => array(395, 396),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_french_general_score_read',
                'check_field_id'     => 'qf_language_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 80,
                    'olddb_field_options' => array(433, 434, 435, 436),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 80,
                    'olddb_field_options' => array(437),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 80,
                    'olddb_field_options' => array(438),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 80,
                    'olddb_field_options' => array(439, 440),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 80,
                    'olddb_field_options' => array(441, 442),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 80,
                    'olddb_field_options' => array(443, 444),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_french_general_score_write',
                'check_field_id'     => 'qf_language_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 86,
                    'olddb_field_options' => array(481, 482, 483, 484),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 86,
                    'olddb_field_options' => array(485),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 86,
                    'olddb_field_options' => array(486),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 86,
                    'olddb_field_options' => array(487, 488),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 86,
                    'olddb_field_options' => array(489, 490),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 86,
                    'olddb_field_options' => array(491, 492),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_french_general_score_listen',
                'check_field_id'     => 'qf_language_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 92,
                    'olddb_field_options' => array(529, 530, 531, 532),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 92,
                    'olddb_field_options' => array(533),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 92,
                    'olddb_field_options' => array(534),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 92,
                    'olddb_field_options' => array(535, 536),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 92,
                    'olddb_field_options' => array(537, 538),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 92,
                    'olddb_field_options' => array(539, 540),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_english_general_score_speak',
                'check_field_id'     => 'qf_language_spouse_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 76,
                    'olddb_field_options' => array(397, 398, 399, 400),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 76,
                    'olddb_field_options' => array(401),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 76,
                    'olddb_field_options' => array(402),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 76,
                    'olddb_field_options' => array(403, 404),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 76,
                    'olddb_field_options' => array(405, 406),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 76,
                    'olddb_field_options' => array(407, 408),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_english_general_score_read',
                'check_field_id'     => 'qf_language_spouse_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 82,
                    'olddb_field_options' => array(445, 446, 447, 448),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 82,
                    'olddb_field_options' => array(449),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 82,
                    'olddb_field_options' => array(450),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 82,
                    'olddb_field_options' => array(451, 452),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 82,
                    'olddb_field_options' => array(453, 454),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 82,
                    'olddb_field_options' => array(455, 456),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_english_general_score_write',
                'check_field_id'     => 'qf_language_spouse_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 88,
                    'olddb_field_options' => array(493, 494, 495, 496),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 88,
                    'olddb_field_options' => array(497),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 88,
                    'olddb_field_options' => array(498),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 88,
                    'olddb_field_options' => array(499, 500),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 88,
                    'olddb_field_options' => array(501, 502),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 88,
                    'olddb_field_options' => array(503, 504),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_english_general_score_listen',
                'check_field_id'     => 'qf_language_spouse_english_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 94,
                    'olddb_field_options' => array(541, 542, 543, 544),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 94,
                    'olddb_field_options' => array(545),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 94,
                    'olddb_field_options' => array(546),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 94,
                    'olddb_field_options' => array(547, 548),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 94,
                    'olddb_field_options' => array(549, 550),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 94,
                    'olddb_field_options' => array(551, 552),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_french_general_score_speak',
                'check_field_id'     => 'qf_language_spouse_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 77,
                    'olddb_field_options' => array(409, 410, 411, 412),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 77,
                    'olddb_field_options' => array(413),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 77,
                    'olddb_field_options' => array(414),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 77,
                    'olddb_field_options' => array(415, 416),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 77,
                    'olddb_field_options' => array(417, 418),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 77,
                    'olddb_field_options' => array(419, 420),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_french_general_score_read',
                'check_field_id'     => 'qf_language_spouse_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 83,
                    'olddb_field_options' => array(457, 458, 459, 460),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 83,
                    'olddb_field_options' => array(461),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 83,
                    'olddb_field_options' => array(462),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 83,
                    'olddb_field_options' => array(463, 464),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 83,
                    'olddb_field_options' => array(465, 466),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 83,
                    'olddb_field_options' => array(467, 468),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_french_general_score_write',
                'check_field_id'     => 'qf_language_spouse_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 89,
                    'olddb_field_options' => array(505, 506, 507, 508),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 89,
                    'olddb_field_options' => array(509),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 89,
                    'olddb_field_options' => array(510),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 89,
                    'olddb_field_options' => array(511, 512),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 89,
                    'olddb_field_options' => array(513, 514),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 89,
                    'olddb_field_options' => array(515, 516),
                ),
            ),

            array(
                'new_field_id'       => 'qf_language_spouse_french_general_score_listen',
                'check_field_id'     => 'qf_language_spouse_french_done',
                'native_proficiency' => array(
                    'olddb_field_id'      => 95,
                    'olddb_field_options' => array(553, 554, 555, 556),
                ),
                'upper_intermediate' => array(
                    'olddb_field_id'      => 95,
                    'olddb_field_options' => array(557),
                ),
                'intermediate'       => array(
                    'olddb_field_id'      => 95,
                    'olddb_field_options' => array(558),
                ),
                'lower_intermediate' => array(
                    'olddb_field_id'      => 95,
                    'olddb_field_options' => array(559, 560),
                ),
                'basic'              => array(
                    'olddb_field_id'      => 95,
                    'olddb_field_options' => array(561, 562),
                ),
                'not_at_all'         => array(
                    'olddb_field_id'      => 95,
                    'olddb_field_options' => array(563, 564),
                ),
            ),
        );

        return $arrFieldsMapping;
    }


    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $arrFieldsMapping = $this->getFieldsMapping();

        foreach ($arrFieldsMapping as $arrFieldMapping) {
            // Search prospects with "not sure" set in the field
            $select = $db->select()
                ->from('company_questionnaires_fields', 'q_field_id')
                ->where('q_field_unique_id = ?', $arrFieldMapping['check_field_id']);

            $fieldId = $db->fetchOne($select);

            $select = $db->select()
                ->from('company_questionnaires_fields_options', 'q_field_option_id')
                ->where('q_field_id = ?', $fieldId, 'INT')
                ->where('q_field_option_unique_id = ?', 'not_sure');

            $notSureOptionId = $db->fetchOne($select);

            $select = $db->select()
                ->from('company_prospects_data', 'prospect_id')
                ->where('q_field_id = ?', $fieldId, 'INT')
                ->where('q_value = ?', $notSureOptionId);

            $arrProspectIds = array_unique($db->fetchCol($select));


            // Update "Advanced/Native Proficiency" option
            $select = $db->select()
                ->from('company_questionnaires_fields', 'q_field_id')
                ->where('q_field_unique_id = ?', $arrFieldMapping['new_field_id']);

            $fieldId = $db->fetchOne($select);

            $select = $db->select()
                ->from('company_questionnaires_fields_options', 'q_field_option_id')
                ->where('q_field_id = ?', $fieldId, 'INT')
                ->where('q_field_option_unique_id = ?', 'full_professional');

            $fieldOptionId = $db->fetchOne($select);

            if (!empty($fieldOptionId)) {
                $db->update(
                    'company_questionnaires_fields_options',
                    array(
                        'q_field_option_order'     => 0,
                        'q_field_option_unique_id' => 'native_proficiency'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );

                $db->update(
                    'company_questionnaires_fields_options_templates',
                    array(
                        'q_field_option_label' => 'Advanced/Native Proficiency'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );
            }

            $this->updateProspectsFieldData(
                $arrProspectIds,
                $fieldId,
                $fieldOptionId,
                $arrFieldMapping['native_proficiency']['olddb_field_id'],
                $arrFieldMapping['native_proficiency']['olddb_field_options']
            );


            // Update "Upper Intermediate" option
            $select = $db->select()
                ->from('company_questionnaires_fields_options', 'q_field_option_id')
                ->where('q_field_id = ?', $fieldId, 'INT')
                ->where('q_field_option_unique_id = ?', 'professional_working');

            $fieldOptionId = $db->fetchOne($select);

            if (!empty($fieldOptionId)) {
                $db->update(
                    'company_questionnaires_fields_options',
                    array(
                        'q_field_option_order'     => 1,
                        'q_field_option_unique_id' => 'upper_intermediate'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );

                $db->update(
                    'company_questionnaires_fields_options_templates',
                    array(
                        'q_field_option_label' => 'Upper Intermediate'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );
            }

            $this->updateProspectsFieldData(
                $arrProspectIds,
                $fieldId,
                $fieldOptionId,
                $arrFieldMapping['upper_intermediate']['olddb_field_id'],
                $arrFieldMapping['upper_intermediate']['olddb_field_options']
            );


            // Update "Intermediate" option
            $select = $db->select()
                ->from('company_questionnaires_fields_options', 'q_field_option_id')
                ->where('q_field_id = ?', $fieldId, 'INT')
                ->where('q_field_option_unique_id = ?', 'limited_working');

            $fieldOptionId = $db->fetchOne($select);

            if (!empty($fieldOptionId)) {
                $db->update(
                    'company_questionnaires_fields_options',
                    array(
                        'q_field_option_order'     => 2,
                        'q_field_option_unique_id' => 'intermediate'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );

                $db->update(
                    'company_questionnaires_fields_options_templates',
                    array(
                        'q_field_option_label' => 'Intermediate'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );
            }

            $this->updateProspectsFieldData(
                $arrProspectIds,
                $fieldId,
                $fieldOptionId,
                $arrFieldMapping['intermediate']['olddb_field_id'],
                $arrFieldMapping['intermediate']['olddb_field_options']
            );


            // Update "Basic" option
            $select = $db->select()
                ->from('company_questionnaires_fields_options', 'q_field_option_id')
                ->where('q_field_id = ?', $fieldId, 'INT')
                ->where('q_field_option_unique_id = ?', 'elementary');

            $fieldOptionId = $db->fetchOne($select);

            if (!empty($fieldOptionId)) {
                $db->update(
                    'company_questionnaires_fields_options',
                    array(
                        'q_field_option_order'     => 4,
                        'q_field_option_unique_id' => 'basic'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );

                $db->update(
                    'company_questionnaires_fields_options_templates',
                    array(
                        'q_field_option_label' => 'Basic'
                    ),
                    $db->quoteInto('q_field_option_id = ?', $fieldOptionId)
                );
            }

            $this->updateProspectsFieldData(
                $arrProspectIds,
                $fieldId,
                $fieldOptionId,
                $arrFieldMapping['basic']['olddb_field_id'],
                $arrFieldMapping['basic']['olddb_field_options']
            );


            // New "Lower Intermediate" option
            $db->insert(
                'company_questionnaires_fields_options',
                array(
                    'q_field_id'               => $fieldId,
                    'q_field_option_unique_id' => 'lower_intermediate',
                    'q_field_option_selected'  => 'N',
                    'q_field_option_order'     => 3
                )
            );
            $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

            $this->execute(
                "INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
            SELECT q_id, " . $fieldOptionId . ", 'Lower Intermediate', 'Y' FROM company_questionnaires;"
            );

            $this->updateProspectsFieldData(
                $arrProspectIds,
                $fieldId,
                $fieldOptionId,
                $arrFieldMapping['lower_intermediate']['olddb_field_id'],
                $arrFieldMapping['lower_intermediate']['olddb_field_options']
            );

            // New "Not at all" option
            $db->insert(
                'company_questionnaires_fields_options',
                array(
                    'q_field_id'               => $fieldId,
                    'q_field_option_unique_id' => 'not_at_all',
                    'q_field_option_selected'  => 'N',
                    'q_field_option_order'     => 5
                )
            );
            $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

            $this->execute(
                "INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
            SELECT q_id, " . $fieldOptionId . ", 'Not at all', 'Y' FROM company_questionnaires;"
            );

            $this->updateProspectsFieldData(
                $arrProspectIds,
                $fieldId,
                $fieldOptionId,
                $arrFieldMapping['not_at_all']['olddb_field_id'],
                $arrFieldMapping['not_at_all']['olddb_field_options']
            );
        }
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $arrFieldsMapping = $this->getFieldsMapping();

        $arrOptionsToDelete = array();
        foreach ($arrFieldsMapping as $arrFieldMapping) {
            $select = $db->select()
                ->from('company_questionnaires_fields', 'q_field_id')
                ->where('q_field_unique_id = ?', $arrFieldMapping['new_field_id']);

            $fieldId = $db->fetchOne($select);

            $select = $db->select()
                ->from('company_questionnaires_fields_options', 'q_field_option_id')
                ->where('q_field_id = ?', $fieldId, 'INT')
                ->where('q_field_option_unique_id IN (?)', array('lower_intermediate', 'not_at_all'));

            $arrOptionsToDelete[$fieldId] = $db->fetchCol($select);

            foreach ($arrOptionsToDelete[$fieldId] as $optionId) {
                $db->delete(
                    'company_prospects_data',
                    $db->quoteInto('q_field_id = ? ', $fieldId, 'INT') .
                    $db->quoteInto(' AND q_value = ?', $optionId, 'INT')
                );
            }

            $db->delete(
                'company_questionnaires_fields_options_templates',
                $db->quoteInto('q_field_option_id IN (?)', $arrOptionsToDelete)
            );

            $db->delete(
                'company_questionnaires_fields_options',
                $db->quoteInto('q_field_option_id IN (?)', $arrOptionsToDelete)
            );
        }

        $db->query("UPDATE company_questionnaires_fields_options SET q_field_option_unique_id = 'elementary' WHERE q_field_option_unique_id = 'basic';");
        $db->query("UPDATE company_questionnaires_fields_options SET q_field_option_unique_id = 'limited_working' WHERE q_field_option_unique_id = 'intermediate';");
        $db->query("UPDATE company_questionnaires_fields_options SET q_field_option_unique_id = 'professional_working' WHERE q_field_option_unique_id = 'upper_intermediate';");
        $db->query("UPDATE company_questionnaires_fields_options SET q_field_option_unique_id = 'full_professional' WHERE q_field_option_unique_id = 'native_proficiency';");

        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Elementary or no proficiency' WHERE q_field_option_label = 'Basic';");
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Limited working proficiency' WHERE q_field_option_label = 'Intermediate';");
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Professional working proficiency' WHERE q_field_option_label = 'Upper Intermediate';");
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Full professional/native proficiency' WHERE q_field_option_label = 'Advanced/Native Proficiency';");
    }
}