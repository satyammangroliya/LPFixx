<?php

namespace minervis\plugins\LPFixx\Log;

use ActiveRecord;
use arConnector;
use ilDateTime;
use ilLPFixxPlugin;
use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use stdClass;

class Log extends ActiveRecord implements ILog
{

    use LPFixxTrait;
    const TABLE_NAME = "lpfixx_log";
    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;

    /**
     * @return string
     */
    public final function getConnectorContainerName() : string
    {
        return static::TABLE_NAME;
    }

    /**
     * @return string
     * @deprecated
     */
    public final static function returnDbTableName() : string
    {
        return static::TABLE_NAME;
    }

    /**
     * @var array
     */
    public static $levels
        = [
            self::LEVEL_INFO => self::LEVEL_INFO,
            self::LEVEL_WARNING => self::LEVEL_WARNING,
            self::LEVEL_EXCEPTION => self::LEVEL_EXCEPTION,
            self::LEVEL_CRITICAL => self::LEVEL_CRITICAL,
        ];
    /**
     * @var int
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   true
     * @con_is_primary   true
     */
    protected $log_id = 0;
    /**
     * @var string
     * @con_has_field    true
     * @con_fieldtype    text
     * @con_is_notnull   true
     */
    protected $title = "";
    /**
     * @var string
     * @con_has_field    true
     * @con_fieldtype    text
     * @con_is_notnull   true
     */
    protected $message = "";
    /**
     * @var int
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     */
    protected $status = 0;
    /**
     * @var ilDateTime
     * @con_has_field    true
     * @con_fieldtype    timestamp
     * @con_is_notnull   true
     */
    protected $date = null;
    /**
     * @var int
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   true
     */
    protected $level = self::LEVEL_INFO;
    /**
     * @var stdClass
     * @con_has_field    true
     * @con_fieldtype    text
     * @con_is_notnull   true
     */
    protected $additional_data;
    /**
     * @var int
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   false
     */
    protected $job_id = null;
    /**
     * @var string
     * @con_has_field    true
     * @con_fieldtype    text
     * @con_is_notnull   true
     */
    protected $job_object_type = "";
    /**
     * @var string|null
     * @con_has_field    true
     * @con_fieldtype    text
     * @con_length       255
     * @con_is_notnull   false
     */
    protected $object_ext_id = null;
    /**
     * @var int|null
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   false
     */
    protected $object_id = null;

    /**
     * @var int|null
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   false
     */
    protected $user_id = null;

    /**
     * Log constructor
     * @param int              $primary_key_value
     * @param arConnector|null $connector
     */
    public final function __construct(/*int*/
        $primary_key_value = 0,
        arConnector $connector = null
    ) {
        $this->additional_data = new stdClass();
        //parent::__construct($primary_key_value, $connector);
    }

    /**
     * @inheritdoc
     */
    public function getLogId() : int
    {
        return $this->log_id;
    }

    /**
     * @inheritdoc
     */
    public function withLogId(int $log_id) : ILog
    {
        $this->log_id = $log_id;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() : string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function withTitle(string $title) : ILog
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMessage() : string
    {
        return $this->message;
    }

    /**
     * @inheritdoc
     */
    public function withMessage(string $message) : ILog
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDate() : ilDateTime
    {
        return $this->date;
    }

    /**
     * @inheritdoc
     */
    public function withDate(ilDateTime $date) : ILog
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return int
     */
    public function getStatus() : int
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return $this
     */
    public function withStatus(int $status) : ILog
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLevel() : int
    {
        return $this->level;
    }

    /**
     * @inheritdoc
     */
    public function withLevel(int $level) : ILog
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalData() : stdClass
    {
        return $this->additional_data;
    }

    /**
     * @inheritdoc
     */
    public function withAdditionalData(stdClass $additional_data) : ILog
    {
        $this->additional_data = $additional_data;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addAdditionalData(string $key, $value) : ILog
    {
        $this->additional_data->{$key} = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJobId() : int
    {
        return $this->job_id;
    }

    /**
     * @inheritdoc
     */
    public function withJobId(int $job_id) : ILog
    {
        $this->job_id = $job_id;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJobObjectType() : string
    {
        return $this->job_object_type;
    }

    /**
     * @inheritdoc
     */
    public function withJobObjectType(string $job_object_type) : ILog
    {
        $this->job_object_type = $job_object_type;

        return $this;
    }




    public function getObjectId(): ?int/*: ?int*/
    {
        return $this->object_id;
    }

    public function withObjectId(/*?*/
        int $object_id = null
    ) : ILog {
        $this->object_id = $object_id;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUserId()/*: ?int*/
    {
        return $this->user_id;
    }

    /**
     * @inheritdoc
     */
    public function withUserId(/*?*/
        int $user_id = null
    ) : ILog {
        $this->user_id = $user_id;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function write(string $message, int $level = self::LEVEL_INFO)/*: void*/
    {
        self::logs()->storeLog($this->withMessage($message)->withLevel($level));
    }
}
