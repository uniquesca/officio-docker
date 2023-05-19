<?php

use Officio\Migration\AbstractMigration;

class UpdateSystemTemplates extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `system_templates` SET `title`='Upgrade to OfficioSolo' WHERE `title`='Upgrade to Officio Solo'");

        $template = <<<EOD
        <div><font face="arial"><span style="FONT-SIZE: small">Dear {user: first name} {user: last name};</span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small"><br></span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small">As per your request, please find below your login details:</span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small"><br></span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small">User ID: <b>{user: username}</b></span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small">Link to reset password: <b><a href="{settings: officio url}/auth/recovery?hash={user: password hash}">click here</a></b></span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small"><br></span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small">For the security of your data, please ensure to always logout from Officio when you finish work.</span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small"><br></span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small">Regards,</span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small">Officio Support Team</span></font></div>
        <div><font face="arial"><span style="FONT-SIZE: small"><br></span></font></div><br>            
EOD;

        $this->getQueryBuilder()
            ->update('system_templates')
            ->set(['template' => $template])
            ->where(['title' => 'Forgotten Email'])
            ->execute();
    }

    public function down()
    {
        $this->execute("UPDATE `system_templates` SET `title`='Upgrade to Officio Solo' WHERE `title`='Upgrade to OfficioSolo'");
    }
}
