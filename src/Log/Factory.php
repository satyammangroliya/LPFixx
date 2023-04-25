<?php

namespace minervis\plugins\LPFixx\Log;

use Exception;
use ilLPFixxPlugin;
use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ilDateTime;
use stdClass;
use Throwable;

final class Factory
{

    use LPFixxTrait;
    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;

    protected static $instance = null;



    public static function getInstance() : Factory
    {
        if (self::$instance === null) {
            self::setInstance(new self());
        }

        return self::$instance;
    }



    public static function setInstance(Factory $instance)/*: void*/
    {
        self::$instance = $instance;
    }


    /**
     * Factory constructor
     */
    private function __construct()
    {

    }


    /**
     * @inheritdoc
     */
    public function log() : ILog
    {
        return (new Log())->withAdditionalData(clone self::logs()->getGlobalAdditionalData());
    }



    public function jobLog($job = null,  $object_id = null, $user_id = null, $status = null, $object_type = null, $additional_data = []) : ILog
    {
        $log = $this->log()->withJobId($job)->withJobObjectType($object_type);

        $log->withObjectId($object_id)
            ->withUserId($user_id)
            ->withStatus(intval($status))
            ->withAdditionalData((object) $additional_data);

        return $log;
    }



    public function exceptionLog(Exception $ex, $job, $object_id = null, $user_id = null, $object_type = null) : ILog
    {
        $log = $this->jobLog($job, $object_id, $user_id, $object_type);

        $log->withLevel(ILog::LEVEL_EXCEPTION);
        $log->withMessage($ex->getMessage());
        $additional       = new stdClass();
        $additional->file = $ex->getFile();
        $additional->line = $ex->getLine();
        $log->withAdditionalData($additional);

        return $log;
    }


    /**
     * @throws \ilDateTimeException
     */
    public function fromDB(stdClass $data) : ILog
    {
        return $this->log()->withLogId($data->log_id)->withTitle($data->title)->withMessage($data->message)
            ->withDate(new ilDateTime($data->date, IL_CAL_DATETIME))->withLevel($data->level)->withAdditionalData(json_decode($data->additional_data, false) ?? new stdClass())
            ->withJobId($data->origin_id)->withJobObjectType($data->origin_object_type)
            ->withObjectId($data->object_id)
            ->withUserId($data->user_id)
            ->withStatus(intval($data->status));
    }
}