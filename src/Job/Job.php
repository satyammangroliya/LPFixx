<?php

namespace minervis\plugins\LPFixx\Job;

use ILIAS\DI\Exceptions\Exception;
use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ilLPFixxPlugin;
use ilCronJob;
use ilCronJobResult;
use ilObjectFactory;


/**
 * Class Job
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class Job extends ilCronJob
{

    use LPFixxTrait;

    const CRON_JOB_ID = ilLPFixxPlugin::PLUGIN_ID . "_cron";
    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var \ILIAS\DI\Container|mixed
     */
    private $dic;


    /**
     * Job constructor
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;

    }


    /**
     * @inheritDoc
     */
    public function getDefaultScheduleType() : int
    {
        return self::SCHEDULE_TYPE_DAILY;
    }


    /**
     * @inheritDoc
     */
    public function getDefaultScheduleValue() : ?int
    {
        return null;
    }


    /**
     * @inheritDoc
     */
    public function getDescription() : string
    {
        return "";
    }


    /**
     * @inheritDoc
     */
    public function getId() : string
    {
        return self::CRON_JOB_ID;
    }


    /**
     * @inheritDoc
     */
    public function getTitle() : string
    {
        return ilLPFixxPlugin::PLUGIN_NAME;
    }


    /**
     * @inheritDoc
     */
    public function hasAutoActivation() : bool
    {
        return true;
    }


    /**
     * @inheritDoc
     */
    public function hasFlexibleSchedule() : bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function run() : ilCronJobResult
    {
        $result = new ilCronJobResult();
        try {
            $status_fixer = new ilLFStatusLP();
            $iterator = $status_fixer->getObjectsPerRule();
            $summary = [
                'passed_updated' => 0,
                'certs_generated' => 0
            ];
            foreach ($iterator as $obj_id => $usr_id){
                $p_info = (ilObjectFactory::getInstanceByObjId($obj_id))->getMembersObject()->getPassedInfo($usr_id);
                $status_fixer->updateStatus($obj_id, $usr_id, $p_info, $summary);
            }
            $message = $summary['passed_updated'] . " set to passed. And " . $summary['certs_generated'] . " certificated regenerated";
            $result->setMessage($message);
            $result->setStatus(ilCronJobResult::STATUS_OK);

        }catch (Exception $e){
            $result->setMessage($e->getMessage());
            $result->setStatus(ilCronJobResult::STATUS_FAIL);

        }
        return $result;
    }
}
