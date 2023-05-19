<?php

use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;
use Officio\Migration\AbstractMigration;

class CreateSummaryInvoices extends AbstractMigration
{
    public function up()
    {
        /**
         * what we want to do:
         * 1. find all invoices created before Oct 8 or that are linked to fees created before Oct 8.
         * 2. find all invoice payments for the invoices (from #1).
         * 3. find all fees before Oct 8.
         * 4. create a summary invoice for each case that have:
         * - name: Statement (as invoice number)
         * - date: is set to the max from invoices
         * - amount (and tax): a sum of amounts (and taxes) of all fees found in #3.
         * - note: contains a description/note for all linked invoices that will be deleted
         * 5. link to all fees found in #3.
         * 6. for each invoice payment add Legacy Invoice #<a num of linked invoice that will be deleted>
         * 7. link invoice payments of invoices that were deleted to the new summary invoice.
         * 8. delete invoices found in #1 (don't delete the invoice(s) if there are no fees created before Oct 8 (not found in #3)).
         */

        // 1. find all invoices created before Oct 8 or if they are lined to fees created before Oct 8.
        $statement = $this->getQueryBuilder()
            ->select(['i.*', 'p.payment_id'])
            ->from(['i' => 'u_invoice'])
            ->leftJoin(['p' => 'u_payment'], ['p.invoice_id = i.invoice_id'])
            ->where(function (QueryExpression $exp, Query $query) {
                $firstCondition = $query->newExpr()
                    ->lt('i.date_of_invoice', '2022-10-08');

                $secondCondition = $query->newExpr()
                    ->isNotNull('p.invoice_id')
                    ->lt('p.date_of_event', '2022-10-08 00:00:00');

                return $exp->or([
                    $firstCondition,
                    $secondCondition
                ]);
            })
            ->order(['i.date_of_invoice' => 'ASC'])
            ->execute();

        $arrInvoices = $statement->fetchAll('assoc');

        $arrGroupedInvoices     = [];
        $arrGroupedInvoicesIds  = [];
        $arrGroupedInvoicesFees = [];
        foreach ($arrInvoices as $arrInvoiceInfo) {
            $arrGroupedInvoices[$arrInvoiceInfo['member_id']][$arrInvoiceInfo['company_ta_id']][] = $arrInvoiceInfo;

            $arrGroupedInvoicesIds[] = $arrInvoiceInfo['invoice_id'];
            if (!empty($arrInvoiceInfo['payment_id'])) {
                $arrGroupedInvoicesFees[] = $arrInvoiceInfo['payment_id'];
            }
        }

        // 2. find all invoice payments for the invoices (from #1).
        $arrGroupedInvoicePayments = [];
        if (!empty($arrGroupedInvoicesIds)) {
            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('u_invoice_payments')
                ->where([
                    'invoice_id IN ' => $arrGroupedInvoicesIds,
                ])
                ->execute();

            $arrInvoicesPayments = $statement->fetchAll('assoc');

            foreach ($arrInvoicesPayments as $arrInvoicesPayment) {
                $arrGroupedInvoicePayments[$arrInvoicesPayment['invoice_id']][] = $arrInvoicesPayment;
            }
        }

        // 3. find all fees before Oct 8 or that were linked to invoices found in #1
        $arrGroupedInvoicesFees = empty($arrGroupedInvoicesFees) ? [0] : $arrGroupedInvoicesFees;
        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('u_payment')
            ->where(function (QueryExpression $exp, Query $query) use ($arrGroupedInvoicesFees) {
                $firstCondition = $query->newExpr()
                    ->lt('date_of_event', '2022-10-08 00:00:00');

                $secondCondition = $query->newExpr()
                    ->in('payment_id', $arrGroupedInvoicesFees);

                return $exp->or([
                    $firstCondition,
                    $secondCondition
                ]);
            })
            ->order(['date_of_event' => 'ASC'])
            ->execute();

        $arrFees = $statement->fetchAll('assoc');

        $arrGroupedFees = [];
        foreach ($arrFees as $arrFeeInfo) {
            $arrGroupedFees[$arrFeeInfo['member_id']][$arrFeeInfo['company_ta_id']][] = $arrFeeInfo;
        }

        $arrInvoicesIdsToDelete = [];
        foreach ($arrGroupedFees as $memberId => $arrGroupedMemberFees) {
            foreach ($arrGroupedMemberFees as $companyTAId => $arrMemberFees) {
                $fee              = 0;
                $tax              = 0;
                $authorId         = 0;
                $dateOfInvoice    = '';
                $dateOfCreation   = '';
                $arrMemberFeesIds = [];
                foreach ($arrMemberFees as $arrMemberFeeInfo) {
                    $arrMemberFeesIds[] = $arrMemberFeeInfo['payment_id'];

                    $fee += round($arrMemberFeeInfo['withdrawal'], 2);

                    if (!empty($arrMemberFeeInfo['gst_province_id'])) {
                        $tax += round(round($arrMemberFeeInfo['withdrawal'], 2) * $arrMemberFeeInfo['gst'] / 100, 2);
                    }

                    // If no invoices are for this T/A -> we'll use details from the last Fee record
                    $authorId       = $arrMemberFeeInfo['author_id'];
                    $dateOfInvoice  = $arrMemberFeeInfo['date_of_event'];
                    $dateOfCreation = $arrMemberFeeInfo['date_of_event'];
                }

                $arrNotes = [];
                if (isset($arrGroupedInvoices[$memberId][$companyTAId])) {
                    foreach ($arrGroupedInvoices[$memberId][$companyTAId] as $arrMemberInvoiceInfo) {
                        // Use author, date of invoice and date of creation from the last invoice record
                        if (!empty($arrMemberInvoiceInfo['author_id'])) {
                            $authorId = $arrMemberInvoiceInfo['author_id'];
                        }

                        $dateOfInvoice  = $arrMemberInvoiceInfo['date_of_invoice'];
                        $dateOfCreation = $arrMemberInvoiceInfo['date_of_creation'];

                        $thisDescription = '';
                        if (strlen($arrMemberInvoiceInfo['description'])) {
                            $thisDescription = trim('Description: ' . $arrMemberInvoiceInfo['description']);
                        }

                        $thisNote = '';
                        if (strlen($arrMemberInvoiceInfo['notes'])) {
                            $thisNote = trim('Notes: ' . $arrMemberInvoiceInfo['notes']);
                        }

                        if (strlen($thisDescription) || strlen($thisNote)) {
                            $label = 'Invoice# ' . $arrMemberInvoiceInfo['invoice_num'] . ' ';
                            $note  = $label;

                            if (strlen($thisDescription)) {
                                $note .= $thisDescription;
                            }

                            if (strlen($thisNote)) {
                                if (strlen($thisDescription)) {
                                    $note .= PHP_EOL . str_pad(' ', strlen($label));
                                }

                                $note .= $thisNote;
                            }

                            $arrNotes[] = $note;
                        }

                        $arrInvoicesIdsToDelete[] = $arrMemberInvoiceInfo['invoice_id'];
                    }
                }

                /*
                 * 4. create a summary invoice for each case that have:
                 * - name: Statement (as invoice number)
                 * - date: is set to the max from invoices
                 * - amount (and tax): a sum of amounts (and taxes) of all fees found in #3.
                 * - note: contains a description/note for all linked invoices that will be deleted
                 */
                $arrNewInvoice = [
                    'member_id'        => $memberId,
                    'company_ta_id'    => $companyTAId,
                    'author_id'        => empty($authorId) ? null : $authorId,
                    'invoice_num'      => 'Statement',
                    'amount'           => $fee + $tax,
                    'fee'              => $fee,
                    'tax'              => $tax,
                    'date_of_invoice'  => $dateOfInvoice,
                    'date_of_creation' => $dateOfCreation,
                    'notes'            => implode(PHP_EOL, $arrNotes),
                ];

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrNewInvoice))
                    ->into('u_invoice')
                    ->values($arrNewInvoice)
                    ->execute();

                $newInvoiceId = $statement->lastInsertId('u_invoice');

                // 5. link to all fees found in #3.
                $this->getQueryBuilder()
                    ->update('u_payment')
                    ->set([
                        'invoice_id' => $newInvoiceId,
                    ])
                    ->where(['payment_id IN ' => $arrMemberFeesIds])
                    ->execute();

                // 6. for each invoice payment add Legacy Invoice #<a num of linked invoice that will be deleted>
                // 7. link invoice payments of invoices that were deleted to the new summary invoice.
                if (isset($arrGroupedInvoices[$memberId][$companyTAId])) {
                    foreach ($arrGroupedInvoices[$memberId][$companyTAId] as $arrMemberInvoiceInfo) {
                        if (isset($arrGroupedInvoicePayments[$arrMemberInvoiceInfo['invoice_id']])) {
                            foreach ($arrGroupedInvoicePayments[$arrMemberInvoiceInfo['invoice_id']] as $arrInvoicePayment) {
                                $this->getQueryBuilder()
                                    ->update('u_invoice_payments')
                                    ->set([
                                        'invoice_id'                 => $newInvoiceId,
                                        'invoice_payment_cheque_num' => trim($arrInvoicePayment['invoice_payment_cheque_num'] . ' Legacy Invoice #' . $arrMemberInvoiceInfo['invoice_num'])
                                    ])
                                    ->where(['invoice_payment_id' => (int)$arrInvoicePayment['invoice_payment_id']])
                                    ->execute();
                            }
                        }
                    }
                }
            }
        }

        // 8. delete invoices found in #1 (don't delete the invoice(s) if there are no fees created before Oct 8 (not found in #3)).
        if (!empty($arrInvoicesIdsToDelete)) {
            $this->execute(sprintf('DELETE FROM u_invoice WHERE invoice_id IN (%s)', implode(',', $arrInvoicesIdsToDelete)));
        }
    }

    public function down()
    {
    }
}
