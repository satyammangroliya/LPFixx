<?php

namespace minervis\plugins\LPFixx\Log;

use ilDateTime;
use stdClass;

/**
 * Interface ILog
 * @package minervis\plugins\LPFixx\Log
 * @author  Jephte Abijuru <jephte.abijuru@minervis.com>
 */
interface ILog
{

    // @see ilLogLevel

    /**
     * @var int
     */
    const LEVEL_INFO = 200;
    /**
     * @var int
     */
    const LEVEL_WARNING = 300;
    /**
     * @var int
     */
    const LEVEL_EXCEPTION = 400;
    /**
     * @var int
     */
    const LEVEL_CRITICAL = 500;

    /**
     * @return int
     */
    public function getLogId() : int;

    /**
     * @param int $log_id
     * @return self
     */
    public function withLogId(int $log_id) : self;

    /**
     * @return string
     */
    public function getTitle() : string;

    /**
     * @param string $title
     * @return self
     */
    public function withTitle(string $title) : self;

    /**
     * @return string
     */
    public function getMessage() : string;

    /**
     * @param string $message
     * @return self
     */
    public function withMessage(string $message) : self;

    /**
     * @return int
     */
    public function getStatus() : int;

    /**
     * @param int $status
     * @return $this
     */
    public function withStatus(int $status) : self;

    /**
     * @return ilDateTime
     */
    public function getDate() : ilDateTime;

    /**
     * @param ilDateTime $date
     * @return self
     */
    public function withDate(ilDateTime $date) : self;

    /**
     * @return int
     */
    public function getLevel() : int;

    /**
     * @param int $level
     * @return self
     */
    public function withLevel(int $level) : self;

    /**
     * @return stdClass
     */
    public function getAdditionalData() : stdClass;

    /**
     * @param stdClass $additional_data
     * @return self
     */
    public function withAdditionalData(stdClass $additional_data) : self;

    /**
     * @param string $key
     * @param mixed  $value
     * @return self
     */
    public function addAdditionalData(string $key, $value) : self;

    /**
     * @return int
     */
    public function getJobId() : int;

    /**
     * @param int $origin_id
     * @return self
     */
    public function withJobId(int $origin_id) : self;

    /**
     * @return string
     */
    public function getJobObjectType() : string;

    /**
     * @param string $origin_object_type
     * @return self
     */
    public function withJobObjectType(string $origin_object_type) : self;


    /**
     * Syntactic sugar for self::logs()->storeLog($log->withMessage()->withLevel());
     * @param string $message
     * @param int    $level
     */
    public function write(string $message, int $level = self::LEVEL_INFO)/*: void*/
    ;
}