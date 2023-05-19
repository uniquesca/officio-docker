<?php

use Phinx\Migration\AbstractMigration;

class fixProspectsJobOrder extends AbstractMigration
{
    public function up()
    {
        $arrAllJobs = $this->fetchAll('SELECT * FROM company_prospects_job ORDER BY prospect_id, qf_job_order, qf_job_id');

        // Group records by prospect id and by main applicant/spouse
        $arrMainApplicantJobsSorted = array();
        $arrSpouseJobsSorted        = array();
        foreach ($arrAllJobs as $arrJobRecord) {
            if ($arrJobRecord['prospect_type'] === 'main') {
                $arrMainApplicantJobsSorted[$arrJobRecord['prospect_id']][] = $arrJobRecord['qf_job_id'];
            } else {
                $arrSpouseJobsSorted[$arrJobRecord['prospect_id']][] = $arrJobRecord['qf_job_id'];
            }
        }

        // Group records, so we'll run only several queries
        $arrUpdatedOrder = array();
        foreach ($arrMainApplicantJobsSorted as $arrProspectJobs) {
            if (count($arrProspectJobs) > 1) {
                foreach ($arrProspectJobs as $i => $jobId) {
                    $arrUpdatedOrder[$i][] = $jobId;
                }
            }
        }

        foreach ($arrSpouseJobsSorted as $arrProspectJobs) {
            if (count($arrProspectJobs) > 1) {
                foreach ($arrProspectJobs as $i => $jobId) {
                    $arrUpdatedOrder[$i][] = $jobId;
                }
            }
        }

        foreach ($arrUpdatedOrder as $order => $arrJobIds) {
            $sql = sprintf(
                "UPDATE `company_prospects_job` SET `qf_job_order`=%d WHERE `qf_job_id` IN (%s)",
                $order,
                implode(',', $arrJobIds)
            );

            $this->execute($sql);
        }
    }

    public function down()
    {
    }
}
