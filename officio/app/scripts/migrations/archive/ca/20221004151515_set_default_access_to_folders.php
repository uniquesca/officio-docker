<?php

use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;
use Officio\Migration\AbstractMigration;

class SetDefaultAccessToFolders extends AbstractMigration
{
    public function up()
    {
        $arrFoldersToSet = [
            [
                'folder_name' => 'Client Uploads',
                'type'        => 'CD',
                'access'      => 'RW',
            ],
            [
                'folder_name' => 'Submissions',
                'type'        => 'F',
                'access'      => 'R',
            ],
        ];


        $statement = $this->getQueryBuilder()
            ->select(array('folder_id', 'company_id', 'folder_name'))
            ->from(array('f' => 'u_folders'))
            ->where(function (QueryExpression $exp, Query $query) use ($arrFoldersToSet) {
                $arrConditions = [];
                foreach ($arrFoldersToSet as $arrFoldersToSetInfo) {
                    $arrConditions[] = $query->newExpr()
                        ->eq('folder_name', $arrFoldersToSetInfo['folder_name'])
                        ->eq('type', $arrFoldersToSetInfo['type']);
                }

                return $exp->or($arrConditions);
            })
            ->andWhere(function (QueryExpression $exp) {
                return $exp->notEq('company_id', 0);
            })
            ->execute();

        $arrAllFolders = $statement->fetchAll('assoc');

        $arrAllFoldersGrouped = [];
        foreach ($arrAllFolders as $arrAllFolderInfo) {
            $companyId = empty($arrAllFolderInfo['company_id']) ? 0 : $arrAllFolderInfo['company_id'];

            $arrAllFoldersGrouped[$companyId][$arrAllFolderInfo['folder_name']] = $arrAllFolderInfo['folder_id'];
        }

        $arrAllCompaniesRoles = $this->fetchAll("SELECT * FROM acl_roles WHERE role_type = 'employer_client'");

        $arrFoldersAccess = [];
        foreach ($arrAllCompaniesRoles as $arrCompanyRole) {
            foreach ($arrFoldersToSet as $arrFoldersToSetInfo) {
                if (isset($arrAllFoldersGrouped[$arrCompanyRole['company_id']][$arrFoldersToSetInfo['folder_name']])) {
                    $arrFoldersAccess[] = [
                        'folder_id' => $arrAllFoldersGrouped[$arrCompanyRole['company_id']][$arrFoldersToSetInfo['folder_name']],
                        'role_id'   => $arrCompanyRole['role_id'],
                        'access'    => $arrFoldersToSetInfo['access'],
                    ];
                }
            }
        }

        $this->table('folder_access')
            ->insert($arrFoldersAccess)
            ->save();
    }

    public function down()
    {
    }
}
