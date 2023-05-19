<?php

use Officio\Migration\AbstractMigration;

class AddOauthFields extends AbstractMigration
{
    public function up()
    {
        $path = 'var/log/CMS_IDIR_active_users.csv';
        if (!file_exists($path)) {
            throw new Exception($path . ' file does not exist');
        }

        // BOM as a string for comparison.
        $bom = "\xef\xbb\xbf";

        // Read file from beginning.
        $fp = fopen($path, 'r');

        // Progress file pointer and get first 3 characters to compare to the BOM string.
        if (fgets($fp, 4) !== $bom) {
            // BOM not found - rewind pointer to start of file.
            rewind($fp);
        }

        // Read CSV into an array.
        $arrListOfIDIRs = array();

        $line         = 0;
        $arrUsernames = [];
        while (!feof($fp) && ($row = fgetcsv($fp)) !== false) {
            if (!empty($line)) {
                $arrListOfIDIRs[$line] = $row;

                $arrUsernames[] = $row[0];
            }
            $line++;
        }

        if (empty($arrUsernames)) {
            throw new Exception('The list is empty');
        }

        $arrUniqueUsernames = array_unique($arrUsernames);
        if (count($arrUniqueUsernames) != count($arrUsernames)) {
            throw new Exception('There are duplicates');
        }

        $statement = $this->getQueryBuilder()
            ->select(array('member_id', 'username'))
            ->from('members')
            ->whereInList('username', $arrUniqueUsernames)
            ->execute();

        $arrMembers = $statement->fetchAll('assoc');

        $arrGroupedMembers = [];
        foreach ($arrMembers as $arrMemberInfo) {
            $arrGroupedMembers[$arrMemberInfo['username']] = $arrMemberInfo['member_id'];
        }
        $arrSavedUsernames = array_keys($arrGroupedMembers);

        if (count($arrUniqueUsernames) != count($arrSavedUsernames)) {
            throw new Exception('These users were not found in the DB: ' . implode(', ', array_diff($arrUniqueUsernames, $arrSavedUsernames)));
        }


        $this->execute("ALTER TABLE `members`
            ADD COLUMN `oauth_idir` VARCHAR(255) NULL DEFAULT NULL AFTER `password`,
            ADD COLUMN `oauth_guid` VARCHAR(255) NULL DEFAULT NULL AFTER `oauth_idir`;");

        foreach ($arrListOfIDIRs as $idirPair) {
            $this->getQueryBuilder()
                ->update('members')
                ->set(['oauth_idir' => $idirPair[1]])
                ->where(['member_id' => $arrGroupedMembers[$idirPair[0]]])
                ->execute();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members` DROP COLUMN `oauth_idir`, DROP COLUMN `oauth_guid`;");
    }
}
