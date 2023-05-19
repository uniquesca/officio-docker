<?php

use Officio\Migration\AbstractMigration;
use Cake\Database\Expression\QueryExpression;

class UpdateFieldsMapping extends AbstractMigration
{

    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->insert(['FieldName'])
            ->into('FormSynField')
            ->values(['FieldName' => 'syncA_english_test_scores'])
            ->execute();

        $newId = $statement->lastInsertId('FormSynField');

        $this->getQueryBuilder()
            ->insert(
                [
                    'FromFamilyMemberId',
                    'FromSynFieldId',
                    'ToFamilyMemberId',
                    'ToSynFieldId',
                    'ToProfileFamilyMemberId',
                    'ToProfileFieldId',
                    'form_map_type',
                    'parent_member_type'
                ]
            )
            ->into('FormMap')
            ->values(
                [
                    'FromFamilyMemberId'      => 'main_applicant',
                    'FromSynFieldId'          => $newId,
                    'ToFamilyMemberId'        => 'main_applicant',
                    'ToSynFieldId'            => $newId,
                    'ToProfileFamilyMemberId' => 'main_applicant',
                    'ToProfileFieldId'        => 'english_test_scores',
                    'form_map_type'           => new QueryExpression('NULL'),
                    'parent_member_type'      => 7
                ]
            )
            ->execute();

        $statement = $this->getQueryBuilder()
            ->insert(['FieldName'])
            ->into('FormSynField')
            ->values(['FieldName' => 'syncA_date_of_english_test'])
            ->execute();

        $newId = $statement->lastInsertId('FormSynField');

        $this->getQueryBuilder()
            ->insert(
                [
                    'FromFamilyMemberId',
                    'FromSynFieldId',
                    'ToFamilyMemberId',
                    'ToSynFieldId',
                    'ToProfileFamilyMemberId',
                    'ToProfileFieldId',
                    'form_map_type',
                    'parent_member_type'
                ]
            )
            ->into('FormMap')
            ->values(
                [
                    'FromFamilyMemberId'      => 'main_applicant',
                    'FromSynFieldId'          => $newId,
                    'ToFamilyMemberId'        => 'main_applicant',
                    'ToSynFieldId'            => $newId,
                    'ToProfileFamilyMemberId' => 'main_applicant',
                    'ToProfileFieldId'        => 'date_of_english_test',
                    'form_map_type'           => new QueryExpression('NULL'),
                    'parent_member_type'      => 7
                ]
            )
            ->execute();
    }

    public function down()
    {
        $this->execute("DELETE FROM `FormSynField` WHERE  `FieldName`='syncA_english_test_scores';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `FieldName`='syncA_date_of_english_test';");
    }
}
