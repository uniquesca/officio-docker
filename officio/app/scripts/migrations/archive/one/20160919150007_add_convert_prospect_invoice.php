<?php

use Phinx\Migration\AbstractMigration;

class AddConvertProspectInvoice extends AbstractMigration
{
    public function up()
    {
        $this->execute('INSERT INTO `system_templates` (`type`, `title`, `subject`, `from`, `to`, `cc`, `bcc`, `template`, `create_date`) VALUES ("system", "Marketplace Prospect to Client Fee", "Marketplace prospect to client conversion", "support@uniques.ca", "", "", "support@uniques.ca", \'<table border="1" cellpadding="5" cellspacing="0">\r\n    <tbody>\r\n    <tr>\r\n        <td>BILL TO</td>\r\n    </tr>\r\n    <tr>\r\n        <td>{admin first name} {admin last name}<br>{company name}<br>{company address}<br>{company city} {company province/state} {company postal code/zip}<br>{company country}\r\n        </td>\r\n    </tr>\r\n    </tbody>\r\n</table><br><br><br>\r\n<table border="1" cellpadding="5" cellspacing="0">\r\n    <tbody>\r\n    <tr>\r\n        <td align="middle">INVOICE NO.</td>\r\n        <td align="middle">DATE</td>\r\n        <td align="middle">TERMS</td>\r\n    </tr>\r\n    <tr>\r\n        <td align="left">{invoice: number}</td>\r\n        <td align="middle">{invoice: date}</td>\r\n        <td><br></td>\r\n    </tr>\r\n    </tbody>\r\n</table><br><br>\r\n<table border="1" cellpadding="5" cellspacing="0">\r\n    <tbody>\r\n    <tr>\r\n        <td align="middle" width="50%">DESCRIPTION</td>\r\n        <td align="middle" width="20%">RATE</td>\r\n        <td align="middle" width="15%">QTY</td>\r\n        <td align="middle" width="15%">AMOUNT</td>\r\n    </tr>\r\n    <tr>\r\n        <td width="50%">Fee for the Marketplace prospect lead<br></td>\r\n        <td align="middle" width="20%">${company prospect: conversion rate}</td>\r\n        <td align="middle" width="15%">{company prospect: conversion quantity}</td>\r\n        <td align="right" width="15%">${invoice: subtotal}</td>\r\n    </tr>\r\n    <tr>\r\n        <td colspan="3" width="85%"><br></td>\r\n        <td align="right" width="15%"><b>${invoice: subtotal}</b></td>\r\n    </tr>\r\n    <tr>\r\n        <td colspan="4" width="100%"><br></td>\r\n    </tr>\r\n    <tr>\r\n        <td colspan="3" align="left" width="85%">GST/HST (Registration No. 896191244)</td>\r\n        <td align="right" width="15%">${invoice: gst/hst fee}</td>\r\n    </tr>\r\n    <tr>\r\n        <td colspan="4" width="100%"><br></td>\r\n    </tr>\r\n    <tr>\r\n        <td colspan="3" align="right" width="85%"><b>TOTAL</b></td>\r\n        <td align="right" width="15%"><b>${invoice: total}</b></td>\r\n    </tr>\r\n    </tbody>\r\n</table><br>\r\n<p></p>\r\n<center>\r\n    <b>Thank you for using our services. Please do not hesitate to contact us, if we can be of further assistance in the future.</b>\r\n</center>\r\n<p></p>\', CURDATE());
');

    }

    public function down()
    {
        $this->execute('DELETE FROM  `system_templates` WHERE `title` = "Marketplace Prospect to Client Fee"');
    }
}