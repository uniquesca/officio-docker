<?php

use Officio\Migration\AbstractMigration;

class AddTestClients extends AbstractMigration
{
    private function getCompanyOffices($companyId, $divisionGroupId)
    {
        $statement = $this->getQueryBuilder()
            ->select('division_id')
            ->from('divisions')
            ->where(['company_id' => $companyId])
            ->where(['division_group_id' => $divisionGroupId])
            ->execute();

        $arrAllOffices = $statement->fetchAll('assoc');

        return array_column($arrAllOffices, 'division_id');
    }

    public function up()
    {
        exit('No!');
        $this->getAdapter()->commitTransaction();

        $companyId       = 1;
        $divisionGroupId = 2;
        $clientTypeId    = 2;
        $mainUserId      = 105;

        // Case defaults
        $arrCasePrograms = [122, 123, 124, 125, 126];
        $arrCaseQuebec   = [129, 130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140];

        // Dependants defaults
        $arrRelationship  = ['parent', 'spouse', 'sibling', 'child', 'other'];
        $arrSex           = ['M', 'F'];
        $arrYesNo         = ['Y', 'N'];
        $arrMaritalStatus = ['single', 'married', 'engaged', 'widowed', 'separated', 'divorced'];

        $statement = $this->getQueryBuilder()
            ->select('client_category_id')
            ->from('client_categories')
            ->where(['company_id' => $companyId])
            ->where(['client_type_id' => $clientTypeId])
            ->execute();

        $arrAllCategories  = $statement->fetchAll('assoc');
        $arrCaseCategories = array_column($arrAllCategories, 'client_category_id');


        $officesCount     = 200;
        $arrAllOfficesIds = $this->getCompanyOffices($companyId, $divisionGroupId);
        if (count($arrAllOfficesIds) < $officesCount) {
            // Create 200 offices
            for ($i = 0; $i < $officesCount; $i++) {
                $this->table('divisions')
                    ->insert([
                        'division_group_id' => $divisionGroupId,
                        'company_id'        => $companyId,
                        'name'              => 'Office ' . $i,
                    ])
                    ->save();
            }

            $arrAllOfficesIds = $this->getCompanyOffices($companyId, $divisionGroupId);
        }

        // Make sure that main user is assigned to all offices
        $statement = $this->getQueryBuilder()
            ->select('division_id')
            ->from('members_divisions')
            ->where(['member_id' => $mainUserId])
            ->where(['type' => 'access_to'])
            ->execute();

        $arrSavedDivisions = $statement->fetchAll('assoc');
        $arrSavedDivisions = array_column($arrSavedDivisions, 'division_id');
        foreach ($arrAllOfficesIds as $divisionId) {
            if (!in_array($divisionId, $arrSavedDivisions)) {
                $this->table('members_divisions')
                    ->insert([
                        'member_id'   => $mainUserId,
                        'division_id' => $divisionId,
                        'type'        => 'access_to',
                    ])
                    ->save();
            }
        }


        $statement = $this->getQueryBuilder()
            ->select('member_id')
            ->from('members')
            ->order('member_id DESC')
            ->limit(1)
            ->execute();

        $arrSavedMembers = $statement->fetchAll('assoc');
        $maxMemberId     = $arrSavedMembers[0]['member_id'];

        $arrMembers           = [];
        $arrClients           = [];
        $arrMembersRelations  = [];
        $arrApplicantFormData = [];
        $arrClientFormData    = [];
        $arrMembersDivisions  = [];
        $arrMembersRoles      = [];
        $arrDependents        = [];
        for ($i = 0; $i < 50000; $i++) {
            $rnd = rand(0, 100000);

            // Use only 5 random offices from all 200 + assign them to the client/case
            $arrOffices = array_rand(array_flip($arrAllOfficesIds), 5);

            $internalContactId = $maxMemberId + 1;
            $individualId      = $maxMemberId + 2;
            $caseId            = $maxMemberId + 3;

            $maxMemberId += 3;

            $arrMembers[] = [
                'member_id'         => $internalContactId,
                'company_id'        => $companyId,
                'division_group_id' => $divisionGroupId,
                'userType'          => 9,
                'emailAddress'      => null,
                'fName'             => null,
                'lName'             => '',
                'regTime'           => time(),
                'status'            => 1,
            ];
            $arrMembers[] = [
                'member_id'         => $individualId,
                'company_id'        => $companyId,
                'division_group_id' => $divisionGroupId,
                'userType'          => 8,
                'emailAddress'      => 'email' . $rnd . '@email.com',
                'fName'             => 'first ' . $rnd,
                'lName'             => 'last  ' . $rnd,
                'regTime'           => time(),
                'status'            => 1,
            ];
            $arrMembers[] = [
                'member_id'         => $caseId,
                'company_id'        => $companyId,
                'division_group_id' => $divisionGroupId,
                'userType'          => 3,
                'emailAddress'      => 'email' . $rnd . '@email.com',
                'fName'             => null,
                'lName'             => '',
                'regTime'           => time(),
                'status'            => 1,
            ];


            // Clients info
            $arrClients[] = [
                'member_id'          => $caseId,
                'client_type_id'     => $clientTypeId,
                'added_by_member_id' => $mainUserId,
                'fileNumber'         => 'fn' . $rnd,
            ];

            // Links
            $arrMembersRelations[] = [
                'parent_member_id'   => $individualId,
                'child_member_id'    => $internalContactId,
                'applicant_group_id' => 100,
                'row'                => 0,
            ];
            $arrMembersRelations[] = [
                'parent_member_id'   => $individualId,
                'child_member_id'    => $internalContactId,
                'applicant_group_id' => 101,
                'row'                => 0,
            ];
            $arrMembersRelations[] = [
                'parent_member_id'   => $individualId,
                'child_member_id'    => $caseId,
                'applicant_group_id' => null,
                'row'                => null,
            ];

            // Internal contact data
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 500,
                'value'              => "last $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 501,
                'value'              => "first $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 502,
                'value'              => "phone home $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 505,
                'value'              => "email1$rnd@email.com",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 506,
                'value'              => "email2$rnd@email.com",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 508,
                'value'              => '49',
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 509,
                'value'              => date('Y-m-d', mt_rand(1, time())),
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 510,
                'value'              => "passport number $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 511,
                'value'              => date('Y-m-d', mt_rand(1, time())),
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 512,
                'value'              => "Country of Birth $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 513,
                'value'              => "Andorra",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 514,
                'value'              => "Albania",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 519,
                'value'              => "address 1 $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 520,
                'value'              => "address 2 $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 521,
                'value'              => "city $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 522,
                'value'              => "state $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 523,
                'value'              => "country $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 524,
                'value'              => "zip $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 525,
                'value'              => "fax work $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 526,
                'value'              => "55",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 527,
                'value'              => "special\ninstructions",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 533,
                'value'              => "email2$rnd@email.com",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 534,
                'value'              => "fax home $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 535,
                'value'              => "fax others $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 543,
                'value'              => "Passport Issued Country $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 550,
                'value'              => "phone work $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 551,
                'value'              => "phone mobile $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 553,
                'value'              => "city of birth $rnd",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 555,
                'value'              => "email3$rnd@email.com",
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 556,
                'value'              => 'linkedin ' . $rnd,
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 557,
                'value'              => 'skype ' . $rnd,
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 558,
                'value'              => 'whatsapp ' . $rnd,
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 559,
                'value'              => 'wechat ' . $rnd,
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $internalContactId,
                'applicant_field_id' => 563,
                'value'              => implode(',', $arrOffices),
                'row'                => 0,
                'row_id'             => null
            ];

            // Individual data
            $arrApplicantFormData[] = [
                'applicant_id'       => $individualId,
                'applicant_field_id' => 563,
                'value'              => implode(',', $arrOffices),
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $individualId,
                'applicant_field_id' => 564,
                'value'              => 'tmplogin ' . $rnd,
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $individualId,
                'applicant_field_id' => 565,
                'value'              => '*****',
                'row'                => 0,
                'row_id'             => null
            ];
            $arrApplicantFormData[] = [
                'applicant_id'       => $individualId,
                'applicant_field_id' => 566,
                'value'              => '65',
                'row'                => 0,
                'row_id'             => '00xxPHWw8XCyIlr6z9ZIeLJe9WpAOtOq',
            ];

            // Case data
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 67,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 69,
                'value'     => '81'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 82,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 83,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 84,
                'value'     => 'time'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 85,
                'value'     => 'location ' . $rnd
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 86,
                'value'     => '111'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 87,
                'value'     => $arrCaseCategories[array_rand($arrCaseCategories)]
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 89,
                'value'     => 'dependants $rnd'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 108,
                'value'     => 'coordinator $rnd'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 109,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 110,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 112,
                'value'     => $arrCasePrograms[array_rand($arrCasePrograms)]
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 114,
                'value'     => $arrCaseQuebec[array_rand($arrCaseQuebec)]
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 115,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 116,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 117,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 118,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 119,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 120,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 121,
                'value'     => 'description $rnd'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 122,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 123,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 124,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 125,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 126,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 127,
                'value'     => date('Y-m-d', mt_rand(1, time()))
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 128,
                'value'     => 'user:all'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 129,
                'value'     => 'user:all'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 130,
                'value'     => 'user:all'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 146,
                'value'     => '164'
            ];
            $arrClientFormData[] = [
                'member_id' => $caseId,
                'field_id'  => 210768,
                'value'     => 'Active'
            ];

            // Assign all clients/internal clients/cases to all offices
            foreach ($arrOffices as $officeId) {
                $arrMembersDivisions[] = [
                    'member_id'   => $individualId,
                    'division_id' => $officeId,
                ];

                $arrMembersDivisions[] = [
                    'member_id'   => $internalContactId,
                    'division_id' => $officeId,
                ];

                $arrMembersDivisions[] = [
                    'member_id'   => $caseId,
                    'division_id' => $officeId,
                ];
            }

            $arrMembersRoles[] = [
                'member_id' => $individualId,
                'role_id'   => 6,
            ];

            // Create 2 dependents
            for ($j = 0; $j < 2; $j++) {
                $arrDependents[] = [
                    'member_id'                          => $caseId,
                    'relationship'                       => empty($j) ? 'parent' : $arrRelationship[array_rand($arrRelationship)],
                    'line'                               => $j,
                    'fName'                              => "depf $j $rnd",
                    'lName'                              => "depl $j $rnd",
                    'DOB'                                => date('Y-m-d', mt_rand(1, time())),
                    'spouse_name'                        => "spouse $rnd",
                    'sex'                                => $arrSex[array_rand($arrSex)],
                    'passport_num'                       => "num $rnd",
                    'passport_date'                      => date('Y-m-d', mt_rand(1, time())),
                    'uci'                                => "uci $rnd",
                    'canadian'                           => $arrYesNo[array_rand($arrYesNo)],
                    'country_of_birth'                   => "country of birth $rnd",
                    'place_of_birth'                     => "place of birth $rnd",
                    'country_of_citizenship'             => "country of citizenship $rnd",
                    'city_of_residence'                  => "city of residence $rnd",
                    'country_of_residence'               => "country of residence $rnd",
                    'migrating'                          => "migrating $rnd",
                    'nationality'                        => "nationality $rnd",
                    'medical_expiration_date'            => date('Y-m-d', mt_rand(1, time())),
                    'main_applicant_address_is_the_same' => $arrYesNo[array_rand($arrYesNo)],
                    'address'                            => "address $rnd",
                    'city'                               => "city $rnd",
                    'country'                            => "country $rnd",
                    'region'                             => "region $rnd",
                    'postal_code'                        => "postal code $rnd",
                    'profession'                         => "profession $rnd",
                    'marital_status'                     => $arrMaritalStatus[array_rand($arrMaritalStatus)],
                    'passport_issuing_country'           => "passport issuing country $rnd",
                    'third_country_visa'                 => $arrYesNo[array_rand($arrYesNo)],
                ];
            }
        }

        $this->table('members')
            ->insert($arrMembers)
            ->save();

        $this->table('clients')
            ->insert($arrClients)
            ->save();

        $this->table('members_relations')
            ->insert($arrMembersRelations)
            ->save();

        $this->table('applicant_form_data')
            ->insert($arrApplicantFormData)
            ->save();

        $this->table('client_form_data')
            ->insert($arrClientFormData)
            ->save();

        $this->table('members_divisions')
            ->insert($arrMembersDivisions)
            ->save();

        $this->table('members_roles')
            ->insert($arrMembersRoles)
            ->save();

        $this->table('client_form_dependents')
            ->insert($arrDependents)
            ->save();
    }

    public function down()
    {
    }
}