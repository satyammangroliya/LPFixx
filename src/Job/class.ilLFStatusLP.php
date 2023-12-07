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


    public function updateStatus($a_obj_id, $a_usr_id, $passed_info, &$summary, $a_obj = null,  $a_force_raise = true)
    {
        $log = ilLoggerFactory::getLogger('trac');
        $log->debug(sprintf(
            "obj_id: %s, user id: %s, object: %s",
            $a_obj_id,
            $a_usr_id,
            (is_object($a_obj) ? get_class($a_obj) : 'null')
        ));

        $status = $this->determineStatus($a_obj_id, $a_usr_id, $a_obj);
        $certs_info = $this->getCertificateInfo($a_usr_id, $a_obj_id);

        if ($status && !empty($status['status_changed']) && $status['status_changed'] == ilLPStatus::_lookupStatusChanged($a_obj_id, $a_usr_id)){
            $summary['not_updated'] ++;
            return ;
        }
        $this->updatePassed($a_obj_id, $a_usr_id, $status['status_changed'], $summary);
        
        $old_status = null;
        $changed = self::writeStatus($a_obj_id, $a_usr_id, $status, $passed_info,   false, false, $old_status);
        if (!$changed && (bool) $a_force_raise) { // #15529
            self::raiseEvent($a_obj_id, $a_usr_id, $status['status'], $old_status, false);
            $changed = true;
        }
        if ($changed) $summary['certs_generated'] ++;

    }



    protected static function raiseEvent($a_obj_id, $a_usr_id, $a_status, $a_old_status, $a_percentage)
    {
        global $DIC;

        $ilAppEventHandler = $DIC['ilAppEventHandler'];

        $log = ilLoggerFactory::getLogger('trac');
        $log->debug("obj_id: " . $a_obj_id . ", user id: " . $a_usr_id . ", status: " .
            $a_status . ", percentage: " . $a_percentage);

        $ilAppEventHandler->raise("Services/Tracking", "updateStatus", array(
            "obj_id" => $a_obj_id,
            "usr_id" => $a_usr_id,
            "status" => $a_status,
            "old_status" => $a_old_status,
            "percentage" => $a_percentage
        ));
    }



    public function determineStatus($obj_id, $usr_id, $obj)
    {

        $query = "SELECT  ut.usr_id, MAX(ut.status) AS status, MAX(ut.status_changed) AS status_changed, uc.obj_id,  uc.lpmode, obr.obj_id 
                    FROM ut_lp_collections uc 
                        INNER JOIN object_reference obr ON obr.ref_id= uc.item_id AND uc.obj_id = %s
                         INNER JOIN object_data obd ON obd.obj_id=obr.obj_id 
                         INNER JOIN ut_lp_marks ut ON obd.obj_id=ut.obj_id AND ut.usr_id = %s 
                         GROUP BY ut.usr_id, uc.obj_id";

        $res = $this->dic->database()->queryF($query, ['integer', 'integer'], [$obj_id, $usr_id] );
        $status = array();
        while($row = $this->dic->database()->fetchAssoc($res)){
            $status = $row;
        }
        return $status;

    }

    /**
     * Update passed status
     */
    public  function updatePassed($a_obj_id, $a_usr_id, $status_changed, array &$summary)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $ilAppEventHandler = $DIC['ilAppEventHandler'];


        // #11600
        $origin = -1;

        $query = "SELECT passed FROM obj_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " " .
            "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');
        $res = $ilDB->query($query);
        $update_query = '';
        if ($res->numRows()) {
            $old = $ilDB->fetchAssoc($res);
            $update_query = "UPDATE obj_members SET " .
                "passed = " . $ilDB->quote(1, 'integer') . ", " .
                "origin = " . $ilDB->quote($origin, 'integer') . ", " .
                "origin_ts = " . $ilDB->quote(strtotime($status_changed), 'integer') . " " .
                "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " " .
                "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');

        }
        if (strlen($update_query)) {
            $ilDB->manipulate($update_query);
            $ilAppEventHandler->raise('Modules/Course', 'participantHasPassedCourse', array(
                'obj_id' => $a_obj_id,
                'usr_id' => $a_usr_id,
            ));
            $summary['passed_updated'] ++;
        }
        return true;
    }


    public static function writeStatus($a_obj_id, $a_user_id, $a_status, $passed_info = null, $a_percentage = false, $a_force_per = false, &$a_old_status = ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM)
    {
        global $DIC;

        $ilDB = $DIC->database();
        $log = $DIC->logger()->trac();

        $log->debug('Write status for:  ' . "obj_id: " . $a_obj_id . ", user id: " . $a_user_id . ", status: " . $a_status['status'] . ", percentage: " . $a_percentage . ", force: " . $a_force_per);
        $update_dependencies = false;

        $a_old_status = ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM;

        // get status in DB
        $set = $ilDB->query(
            "SELECT usr_id,status,status_dirty, status_changed FROM ut_lp_marks WHERE " .
            " obj_id = " . $ilDB->quote($a_obj_id, "integer") . " AND " .
            " usr_id = " . $ilDB->quote($a_user_id, "integer")
        );
        $rec = $ilDB->fetchAssoc($set);

        // update
        if ($rec) {
            $a_old_status = $rec["status"];

            // status has changed: update
            if ($rec["status"] != $a_status['status'] || ($rec['status_changed'] != $a_status['status_changed']) || ($rec['status_changed'] != $passed_info['timestamp'])) {
                $ret = $ilDB->manipulate(
                    "UPDATE ut_lp_marks SET " .
                    " status = " . $ilDB->quote($a_status['status'], "integer") . "," .
                    " status_changed = " . $ilDB->quote($a_status['status_changed'], "datetime") . "," .
                    " status_dirty = " . $ilDB->quote(0, "integer") .
                    " WHERE usr_id = " . $ilDB->quote($a_user_id, "integer") .
                    " AND obj_id = " . $ilDB->quote($a_obj_id, "integer")
                );
                if ($ret != 0) {
                    //$update_dependencies = true;
                }
            }
            // status has not changed: reset dirty flag
            elseif ($rec["status_dirty"]) {
                $ilDB->manipulate(
                    "UPDATE ut_lp_marks SET " .
                    " status_dirty = " . $ilDB->quote(0, "integer") .
                    " WHERE usr_id = " . $ilDB->quote($a_user_id, "integer") .
                    " AND obj_id = " . $ilDB->quote($a_obj_id, "integer")
                );
            }
        }

        $log->debug('Update dependecies is ' . ($update_dependencies ? 'true' : 'false'));

        // update collections
        if ($update_dependencies) {
            $log->debug('update dependencies');


            include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
            ilLPStatusWrapper::_removeStatusCache($a_obj_id, $a_user_id);

            $set = $ilDB->query("SELECT ut_lp_collections.obj_id obj_id FROM " .
                "object_reference JOIN ut_lp_collections ON " .
                "(object_reference.obj_id = " . $ilDB->quote($a_obj_id, "integer") .
                " AND object_reference.ref_id = ut_lp_collections.item_id)");
            while ($rec = $ilDB->fetchAssoc($set)) {
                if (in_array(ilObject::_lookupType($rec["obj_id"]), array("crs", "grp", "fold"))) {
                    $log->debug('Calling update status for collection obj_id: ' . $rec['obj_id']);
                    // just to make sure - remove existing cache entry
                    ilLPStatusWrapper::_removeStatusCache($rec["obj_id"], $a_user_id);
                    ilLPStatusWrapper::_updateStatus($rec["obj_id"], $a_user_id);
                }
            }

            // find all course references
            if (ilObject::_lookupType($a_obj_id) == 'crs') {
                $log->debug('update references');

                $query = 'select obj_id from container_reference ' .
                    'where target_obj_id = ' . $ilDB->quote($a_obj_id, ilDBConstants::T_INTEGER);
                $res = $ilDB->query($query);
                while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
                    $log->debug('Calling update status for reference obj_id: ' . $row->obj_id);
                    \ilLPStatusWrapper::_removeStatusCache($row->obj_id, $a_user_id);
                    \ilLPStatusWrapper::_updateStatus($row->obj_id, $a_user_id);
                }
            }

            self::raiseEvent($a_obj_id, $a_user_id, $a_status['status'], $a_old_status, $a_percentage);
        }

        return $update_dependencies;
    }

    public  function hasUserCertificate($usr_id, $obj_id)
    {
        $sql = "SELECT id from il_cert_user_cert where user_id = %s AND obj_id =%s";
        $res = $this->dic->database()->queryF($sql, ['integer', 'integer'], [$usr_id, $obj_id]);
        return ($res->numRows() > 0);
    }
    public function getCertificateInfo($usr_id, $obj_id)
    {
        $sql = "SELECT template_values from il_cert_user_cert where user_id = %s AND obj_id =%s ORDER BY id LIMIT 1";
        $res = $this->dic->database()->queryF($sql, ['integer', 'integer'], [$usr_id, $obj_id]);
        $row = $this->dic->database()->fetchAssoc($res);
        $GLOBALS['DIC']->logger()->root()->dump($row['template_values']);
        return json_decode($row['template_values'], true);
    }
    public static function generateCertificates()
    {

    }

    public function  getObjectsPerRule($rule = null)
    {
        if($rule){}
        else{
            $query = "SELECT DISTINCT uc.obj_id, utl.usr_id FROM ut_lp_collections uc INNER JOIN ut_lp_marks utl ON uc.obj_id = utl.obj_id INNER JOIN object_data obd ON obd.obj_id = uc.obj_id WHERE uc.grouping_id > 0 AND utl.status = %s AND obd.type= %s";
            $res = $this->dic->database()->queryF($query, ['text', 'text'], [ilLPStatus::LP_STATUS_COMPLETED_NUM, 'crs']);
            $members = array();
            while($r = $this->dic->database()->fetchAssoc($res)){
                $members [] = [$r['obj_id'] => $r['usr_id']];
            }
            return new RecursiveIteratorIterator(new RecursiveArrayIterator($members));;
        }
    }

}