<?php

use Officio\Migration\AbstractMigration;

class addProspectStartedSignupTemplate extends AbstractMigration
{
    public function up()
    {
        $this->execute('INSERT INTO `system_templates` (`type`, `title`, `subject`, `from`, `to`, `cc`, `bcc`, `template`, `create_date`) VALUES 
("system", "New Company Signup - Started", "New Company Signup - Started", "support@officio.ca", "support@officio.ca", "", "", \'<h1>New Company Signup - Started</h1>
<div>Company name: {prospects: company}</div>
<div>Company phone (W): {prospects: company}</div>
<br>
<h2>Admin User Details</h2>
<div>First Name: {prospects: admin_first_name}</div>
<div>Last Name: {prospects: admin_last_name}</div>
<div>Email: {prospects: admin_email}</div>
<div>Username: {prospects: admin_username}</div>
<br>
<h3>You should receive a second email confirmation after they have successfully paid for the subscription.</h3>\', CURDATE());');

        $this->execute('INSERT INTO `system_templates` (`type`, `title`, `subject`, `from`, `to`, `cc`, `bcc`, `template`, `create_date`) VALUES 
("system", "New Company Signup - Failed (Payment Successful)", "New Company Signup - Failed (Payment Successful)", "support@officio.ca", "support@officio.ca", "", "", \'<h1>New Company Signup - Failed (Payment Successful)</h1>
<div>Company name: {prospects: company}</div>
<div>Company phone (W): {prospects: company}</div>
<br>
<h2>Admin User Details</h2>
<div>First Name: {prospects: admin_first_name}</div>
<div>Last Name: {prospects: admin_last_name}</div>
<div>Email: {prospects: admin_email}</div>
<div>Username: {prospects: admin_username}</div>
<br>
\', CURDATE());');

    }

    public function down()
    {
        $this->execute('DELETE FROM  `system_templates` WHERE `title` = "New Company Signup - Started"');
        $this->execute('DELETE FROM  `system_templates` WHERE `title` = "New Company Signup - Failed (Payment Successful)"');
    }
}
