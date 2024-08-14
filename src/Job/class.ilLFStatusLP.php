<?php

namespace minervis\plugins\LPFixx\Job;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use ilLoggerFactory;
use ilLPStatusWrapper;
use ilLPStatus;
use ilObject;
use ilDBConstants;


/**
 * Class ilLFStatusLP
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class ilLFStatusLP
{
    /**
     * @var \ILIAS\DI\Container|mixed
     */
    private $dic;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
    }

    public function updateStatus($a_obj_id, $a_usr_id, $passed_info, &$summary, $a_obj = null, $a_force_raise = true)
    {
        $log = ilLoggerFactory::getLogger('trac');
        $log->debug(sprintf(
            "obj_id: %d, user id: %d, object: %s",
            (int)$a_obj_id,
            (int)$a_usr_id,
            is_object($a_obj) ? get_class($a_obj) : 'null'
        ));

        $status = $this->determineStatus((int)$a_obj_id, (int)$a_usr_id, $a_obj);

        if (!$status || ($status && !empty($status['status_changed']) 
                && $status['status_changed'] == ilLPStatus::_lookupStatusChanged((int)$a_obj_id, (int)$a_usr_id))) {
            $summary['not_updated']++;
            return;
        }

        $this->updatePassed((int)$a_obj_id, (int)$a_usr_id, $status['status_changed'], $summary);
        
        $old_status = null;
        $changed = self::writeStatus((int)$a_obj_id, (int)$a_usr_id, $status, $passed_info, false, false, $old_status);
        if (!$changed && (bool)$a_force_raise) { // #15529
            self::raiseEvent((int)$a_obj_id, (int)$a_usr_id, (int)$status['status'], (int)$old_status, 0);
            $changed = true;
        }
    }

    protected static function raiseEvent($a_obj_id, $a_usr_id, $a_status, $a_old_status, $a_percentage)
    {
        global $DIC;

        $ilAppEventHandler = $DIC['ilAppEventHandler'];
        $log = ilLoggerFactory::getLogger('trac');

        $log->debug(sprintf(
            "obj_id: %d, user id: %d, status: %d, percentage: %d",
            (int)$a_obj_id,
            (int)$a_usr_id,
            (int)$a_status,
            (int)$a_percentage
        ));

        $ilAppEventHandler->raise("Services/Tracking", "updateStatus", array(
            "obj_id" => (int)$a_obj_id,
            "usr_id" => (int)$a_usr_id,
            "status" => (int)$a_status,
            "old_status" => (int)$a_old_status,
            "percentage" => (int)$a_percentage
        ));
    }

    public function determineStatus($obj_id, $usr_id, $obj)
    {
        $query = "WITH modules_lp AS (
                    SELECT uc.obj_id, ut.usr_id, uc.item_id as module_id, ut.status, ut.status_changed, ut.status_dirty, ut.percentage 
                    FROM ut_lp_collections uc 
                    INNER JOIN object_reference obr ON uc.item_id=obr.ref_id AND uc.obj_id=%s  
                    INNER JOIN ut_lp_marks ut ON ut.obj_id=obr.obj_id AND ut.usr_id=%s),
                preferred_status AS (
                    SELECT obj_id, usr_id, MAX(CASE WHEN status=2 THEN 1 ELSE 0 END) As has_status_2
                    FROM modules_lp
                    GROUP BY obj_id,usr_id
                ),
                max_status AS(
                    SELECT obj_id, usr_id, MAX(status) as max_status
                    FROM modules_lp
                    GROUP BY obj_id, usr_id
                )
                SELECT 
                    DISTINCT mp.* FROM modules_lp mp
                INNER JOIN preferred_status ps ON mp.obj_id = ps.obj_id AND mp.usr_id = ps.usr_id
                INNER JOIN max_status ms ON mp.obj_id = ms.obj_id AND mp.usr_id = ms.usr_id
                WHERE (ps.has_status_2 = 1 AND mp.status = 2) 
                OR (ps.has_status_2 = 0 AND mp.status = ms.max_status)";

        $res = $this->dic->database()->queryF($query, ['integer', 'integer'], [(int)$obj_id, (int)$usr_id]);
        if ($res->numRows() == 0) {
            return false;
        }
        $status = array();
        while ($row = $this->dic->database()->fetchAssoc($res)) {
            $status = $row;
        }
        return $status;
    }

    /**
     * Update passed status
     */
    public function updatePassed($a_obj_id, $a_usr_id, $status_changed, array &$summary)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $ilAppEventHandler = $DIC['ilAppEventHandler'];

        // #11600
        $origin = -1;

        $query = "SELECT passed FROM obj_members " .
            "WHERE obj_id = " . $ilDB->quote((int)$a_obj_id, 'integer') . " " .
            "AND usr_id = " . $ilDB->quote((int)$a_usr_id, 'integer');
        $res = $ilDB->query($query);
        $update_query = '';
        if ($res->numRows()) {
            $old = $ilDB->fetchAssoc($res);
            $update_query = "UPDATE obj_members SET " .
                "passed = " . $ilDB->quote(1, 'integer') . ", " .
                "origin = " . $ilDB->quote($origin, 'integer') . ", " .
                "origin_ts = " . $ilDB->quote(strtotime($status_changed), 'integer') . " " .
                "WHERE obj_id = " . $ilDB->quote((int)$a_obj_id, 'integer') . " " .
                "AND usr_id = " . $ilDB->quote((int)$a_usr_id, 'integer');
        }
        if (strlen($update_query)) {
            $ilDB->manipulate($update_query);
            $ilAppEventHandler->raise('Modules/Course', 'participantHasPassedCourse', array(
                "obj_id" => (int)$a_obj_id,
                "usr_id" => (int)$a_usr_id,
            ));
            $summary['passed_updated']++;
        }
        return true;
    }

    public static function writeStatus($a_obj_id, $a_user_id, $a_status, $passed_info = null, $a_percentage = false, $a_force_per = false, &$a_old_status = ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM)
    {
        global $DIC;

        $ilDB = $DIC->database();
        $log = $DIC->logger()->trac();

        $log->debug(sprintf('Write status for: obj_id: %d, user id: %d, status: %d, percentage: %d, force: %s',
            (int)$a_obj_id,
            (int)$a_user_id,
            (int)$a_status['status'],
            (int)$a_percentage,
            (string)$a_force_per
        ));
        $update_dependencies = false;

        $a_old_status = ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM;

        // Get status in DB
        $set = $ilDB->query(
            "SELECT usr_id, status, status_dirty, status_changed FROM ut_lp_marks WHERE " .
            " obj_id = " . $ilDB->quote((int)$a_obj_id, "integer") . " AND " .
            " usr_id = " . $ilDB->quote((int)$a_user_id, "integer")
        );
        $rec = $ilDB->fetchAssoc($set);

        // Update status
        if ($rec) {
            $a_old_status = (int)$rec["status"];

            if ($rec["status"] != (int)$a_status['status'] || $rec['status_changed'] != $a_status['status_changed'] || $rec['status_changed'] != $passed_info['timestamp']) {
                $ret = $ilDB->manipulate(
                    "UPDATE ut_lp_marks SET " .
                    " status = " . $ilDB->quote((int)$a_status['status'], "integer") . "," .
                    " status_changed = " . $ilDB->quote($a_status['status_changed'], "datetime") . "," .
                    " status_dirty = " . $ilDB->quote(0, "integer") .
                    " WHERE usr_id = " . $ilDB->quote((int)$a_user_id, "integer") .
                    " AND obj_id = " . $ilDB->quote((int)$a_obj_id, "integer")
                );
                if ($ret != 0) {
                    //$update_dependencies = true;
                }
            } elseif ($rec["status_dirty"]) {
                // Status has not changed: reset dirty flag
                $ilDB->manipulate(
                    "UPDATE ut_lp_marks SET " .
                    " status_dirty = " . $ilDB->quote(0, "integer") .
                    " WHERE usr_id = " . $ilDB->quote((int)$a_user_id, "integer") .
                    " AND obj_id = " . $ilDB->quote((int)$a_obj_id, "integer")
                );
            }
        }

        $log->debug('Update dependencies is ' . ($update_dependencies ? 'true' : 'false'));

        // Update collections if needed
        if ($update_dependencies) {
            $log->debug('Update dependencies');

            include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
            ilLPStatusWrapper::_removeStatusCache((int)$a_obj_id, (int)$a_user_id);

            $set = $ilDB->query("SELECT ut_lp_collections.obj_id obj_id FROM " .
                "object_reference JOIN ut_lp_collections ON " .
                "(object_reference.obj_id = " . $ilDB->quote((int)$a_obj_id, "integer") .
                " AND object_reference.ref_id = ut_lp_collections.item_id)");
            while ($rec = $ilDB->fetchAssoc($set)) {
                if (in_array(ilObject::_lookupType($rec["obj_id"]), ["crs", "grp", "fold"])) {
                    $log->debug('Calling update status for collection obj_id: ' . $rec['obj_id']);
                    ilLPStatusWrapper::_removeStatusCache((int)$rec["obj_id"], (int)$a_user_id);
                    ilLPStatusWrapper::_updateStatus((int)$rec["obj_id"], (int)$a_user_id);
                }
            }

            // Find all course references
            if (ilObject::_lookupType((int)$a_obj_id) == 'crs') {
                $log->debug('Update references');

                $query = 'SELECT obj_id FROM container_reference ' .
                    'WHERE target_obj_id = ' . $ilDB->quote((int)$a_obj_id, ilDBConstants::T_INTEGER);
                $res = $ilDB->query($query);
                while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
                    $log->debug('Calling update status for reference obj_id: ' . $row->obj_id);
                    \ilLPStatusWrapper::_removeStatusCache($row->obj_id, $a_user_id);
                    \ilLPStatusWrapper::_updateStatus($row->obj_id, $a_user_id);
                }
            }

            self::raiseEvent($a_obj_id, $a_user_id, (int)$a_status['status'], $a_old_status, (int)$a_percentage);
        }

        return $update_dependencies;
    }

    public function hasUserCertificate($usr_id, $obj_id)
    {
        $sql = "SELECT id FROM il_cert_user_cert WHERE user_id = %d AND obj_id = %d";
        $res = $this->dic->database()->queryF($sql, ['integer', 'integer'], [(int)$usr_id, (int)$obj_id]);
        return ($res->numRows() > 0);
    }

    public function getCertificateInfo($usr_id, $obj_id)
    {
        $sql = "SELECT template_values FROM il_cert_user_cert WHERE user_id = %d AND obj_id = %d ORDER BY id LIMIT 1";
        $res = $this->dic->database()->queryF($sql, ['integer', 'integer'], [(int)$usr_id, (int)$obj_id]);
        $row = $this->dic->database()->fetchAssoc($res);
        $GLOBALS['DIC']->logger()->root()->dump($row['template_values']);
        return json_decode($row['template_values'], true);
    }

    public static function generateCertificates()
    {
        // Implementation needed
    }

    public function getObjectsPerRule($rule = null)
    {
        if ($rule) {
            // Implement rule-based fetching if needed
        } else {
            $query = "SELECT DISTINCT uc.obj_id, utl.usr_id 
                      FROM ut_lp_collections uc 
                      INNER JOIN ut_lp_marks utl ON uc.obj_id = utl.obj_id 
                      INNER JOIN object_data obd ON obd.obj_id = uc.obj_id 
                      WHERE uc.grouping_id > 0 AND (utl.status = %d OR utl.status = %d) 
                      AND obd.type= %s";
            $res = $this->dic->database()->queryF($query, ['integer', 'integer', 'text'], [ilLPStatus::LP_STATUS_COMPLETED_NUM, ilLPStatus::LP_STATUS_FAILED_NUM, 'crs']);
            $members = [];
            while ($r = $this->dic->database()->fetchAssoc($res)) {
                $members[] = [$r['obj_id'] => $r['usr_id']];
            }
            return new RecursiveIteratorIterator(new RecursiveArrayIterator($members));
        }
    }
}
