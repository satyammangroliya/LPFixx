<?php

namespace minervis\plugins\LPFixx\Log;


use ilDateTime;
use ilDBConstants;
use ilLPFixxPlugin;
use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use stdClass;

class Repository
{

    use LPFixxTrait;

    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var Repository
     */
    protected static $instance = null;
    /**
     * @var \ilDBInterface
     */
    protected $db;

    /**
     * @return Repository
     */
    public static function getInstance() : Repository
    {
        if (self::$instance === null) {
            self::setInstance(new self());
        }

        return self::$instance;
    }

    /**
     * @param Repository $instance
     */
    public static function setInstance(Repository $instance)/*: void*/
    {
        self::$instance = $instance;
    }

    /**
     * Additional data which should appear in all logs. E.g. something like
     * ID of datajunk of delivering system etc.
     *
     * @var stdClass
     */
    protected $global_additional_data;
    /**
     * @var Log[][][]
     */
    protected $kept_logs = [];

    /**
     * Repository constructor
     */
    private function __construct()
    {
        global $DIC;
        $this->withGlobalAdditionalData(new stdClass());
        $this->db = $DIC->database();
    }


    public function deleteLog(ILog $log)/*: void*/
    {
        $this->db->manipulateF(
            'DELETE FROM ' . $this->db->quoteIdentifier(Log::TABLE_NAME)
            . " WHERE log_id=%s", [ilDBConstants::T_INTEGER], [$log->getLogId()]
        );
    }

    public function deleteOldLogs(int $keep_old_logs_time) : int
    {
        $time = time();
        $keep_old_logs_time_timestamp = ($time - ($keep_old_logs_time * 24 * 60 * 60));
        $keep_old_logs_time_date = new ilDateTime($keep_old_logs_time_timestamp, IL_CAL_UNIX);

        $keep_log_ids = [];
        $result = $this->db->query(
            'SELECT MAX(log_id) AS log_id FROM '
            . $this->db->quoteIdentifier(Log::TABLE_NAME)
            . ' GROUP BY job_id'
        );

        while ($row = $result->fetchAssoc()) {
            $keep_log_ids[] = intval($row["log_id"]);
        }
        // $keep_log_ids = [];
        $count = $this->db->manipulateF(
            'DELETE FROM '
            . $this->db->quoteIdentifier(Log::TABLE_NAME)
            . ' WHERE date<%s AND '
            . $this->db->in(
                "log_id",
                $keep_log_ids,
                true,
                ilDBConstants::T_INTEGER
            ),
            [
                ilDBConstants::T_TEXT
            ], [
                $keep_old_logs_time_date->get(IL_CAL_DATETIME)
            ]
        );

        return $count;
    }


    public function factory() : Factory
    {
        return Factory::getInstance();
    }

    /**
     * @inheritdoc
     */
    public function getLogs(
        string $sort_by = null,
        string $sort_by_direction = null,
        int $limit_start = null,
        int $limit_end = null,
        string $title = null,
        string $message = null,
        ilDateTime $date_start = null,
        ilDateTime $date_end = null,
        int $level = null,
        int $job_id = null,
        string $job_object_type = null,
        int $object_id = null,
        int $user_id = null,
        string $additional_data = null,
        int $status = null
    ) : array {

        $sql = 'SELECT *';

        $sql .= $this->getLogsQuery(
            $sort_by,
            $sort_by_direction,
            $limit_start,
            $limit_end,
            $title,
            $message,
            $date_start,
            $date_end,
            $level,
            $job_id,
            $job_object_type,
            $object_id,
            $user_id,
            $additional_data,
            $status
        );

        /**
         * @var ILog[] $logs
         */
        $stm = $this->db->query($sql);
        $logs = [];
        while ($d = $this->db->fetchObject($stm)) {
            $logs[] = $d;
        }

        $logs = array_map([$this->factory(), "fromDB"], $logs);


        return $logs;
    }

    /**
     * @inheritdoc
     */
    public function getLogsCount(
        string $title = null,
        string $message = null,
        ilDateTime $date_start = null,
        ilDateTime $date_end = null,
        int $level = null,
        int $job_id = null,
        string $job_object_type = null,
        int $object_id = null,
        int $user_id = null,
        string $additional_data = null,
        int $status = null
    ) : int {

        $sql = 'SELECT COUNT(log_id) AS count';

        $sql .= $this->getLogsQuery(
            null,
            null,
            null,
            null,
            $title,
            $message,
            $date_start,
            $date_end,
            $level,
            $job_id,
            $job_object_type,
            $object_id,
            $user_id,
            $additional_data,
            $status
        );

        $result = $this->db->query($sql);

        if ($row = $result->fetchAssoc()) {
            return intval($row["count"]);
        }

        return 0;
    }


    private function getLogsQuery(
        string $sort_by = null,
        string $sort_by_direction = null,
        int $limit_start = null,
        int $limit_end = null,
        string $title = null,
        string $message = null,
        ilDateTime $date_start = null,
        ilDateTime $date_end = null,
        int $level = null,
        int $job_id = null,
        string $job_object_type = null,
        int $object_id = null,
        int $user_id = null,
        string $additional_data = null,
        int $status = null
    ) : string {

        $sql = ' FROM ' . $this->db->quoteIdentifier(Log::TABLE_NAME);

        $wheres = [];

        if (!empty($title)) {
            $wheres[] = $this->db->like("title", ilDBConstants::T_TEXT, '%' . $title . '%');
        }

        if (!empty($message)) {
            $wheres[] = $this->db->like("message", ilDBConstants::T_TEXT, '%' . $message . '%');
        }

        if (!empty($date_start)) {
            $wheres[] = 'date>=' . $this->db->quote(
                    $date_start->get(IL_CAL_DATETIME),
                    ilDBConstants::T_TEXT
                );
        }

        if (!empty($date_end)) {
            $wheres[] = 'date<=' . $this->db->quote(
                    $date_start->get(IL_CAL_DATETIME),
                    ilDBConstants::T_TEXT
                );
        }

        if (!empty($level)) {
            $wheres[] = 'level=' . $this->db->quote($level, ilDBConstants::T_INTEGER);
        }

        if (!empty($job_id)) {
            $wheres[] = 'job_id=' . $this->db->quote($job_id, ilDBConstants::T_INTEGER);
        }

        if (!empty($job_object_type)) {
            $wheres[] = 'job_object_type=' . $this->db->quote(
                    $job_object_type,
                    ilDBConstants::T_TEXT
                );
        }

        if (!empty($object_id)) {
            $wheres[] = 'object_id=' . $this->db->quote($object_id, ilDBConstants::T_INTEGER);
        }

        if (!empty($additional_data)) {
            $wheres[] = $this->db->like(
                "additional_data",
                ilDBConstants::T_TEXT,
                '%' . $additional_data . '%'
            );
        }

        if (!empty($status)) {
            $wheres[] = 'status=' . $this->db->quote($status, ilDBConstants::T_INTEGER);
        }

        if (count($wheres) > 0) {
            $sql .= ' WHERE ' . implode(" AND ", $wheres);
        }

        if ($sort_by !== null && $sort_by_direction !== null) {
            $sql .= ' ORDER BY ' . $this->db->quoteIdentifier($sort_by) . ' ' . $sort_by_direction;
        }

        if ($limit_start !== null && $limit_end !== null) {
            $sql .= ' LIMIT ' . $this->db->quote(
                    $limit_start,
                    ilDBConstants::T_INTEGER
                ) . ',' . $this->db->quote(
                    $limit_end,
                    ilDBConstants::T_INTEGER
                );
        }

        return $sql;
    }


    public function getLogById(int $log_id)/*: ?ILog*/
    {
        /**
         * @var Log|null $log
         */
        $log = $this->db->fetchObjectCallback(
            $this->db->queryF(
                'SELECT * FROM ' . $this->db->quoteIdentifier(Log::TABLE_NAME)
                . ' WHERE log_id=%s', [ilDBConstants::T_INTEGER], [$log_id]
            ), [$this->factory(), "fromDB"]
        );

        return $log;
    }


    public function getGlobalAdditionalData() : stdClass
    {
        return $this->global_additional_data;
    }


    public function withGlobalAdditionalData(stdClass $global_additional_data) : Repository
    {
        $this->global_additional_data = $global_additional_data;

        return $this;
    }


    public function keepLog(ILog $log)/*:void*/
    {
        if (!isset($this->kept_logs[$log->getJobId()])) {
            $this->kept_logs[$log->getJobId()] = [];
        }

        if (!isset($this->kept_logs[$log->getJobId()][$log->getLevel()])) {
            $this->kept_logs[$log->getJobId()][$log->getLevel()] = [];
        }

        $this->kept_logs[$log->getJobId()][$log->getLevel()][] = $log;

        //GlobalHook::getInstance()->handleLog($log);
    }

    /**
     * @inheritdoc
     */
    public function getKeptLogs($job,/*?*/ int $level = null) : array
    {
        if (!isset($this->kept_logs[$job])) {
            return [];
        }

        if ($level === null) {
            return array_reduce(
                $this->kept_logs[$job], function (array $logs1, array $logs2) : array {
                return array_merge($logs1, $logs2);
            }, []
            );
        }

        if (isset($this->kept_logs[$job][$level])) {
            return $this->kept_logs[$job][$level];
        } else {
            return [];
        }
    }

    public function storeLog(ILog $log)/*: void*/
    {
        $date = new ilDateTime(time(), IL_CAL_UNIX);

        if (empty($log->getLogId())) {
            $log->withDate($date);
        }

        $json_encode = json_encode($log->getAdditionalData()) ?? '{}';
        $log->withLogId(
            $this->store(
                Log::TABLE_NAME,
                [
                    "title" => [ilDBConstants::T_TEXT, $log->getTitle()],
                    "message" => [ilDBConstants::T_TEXT, $log->getMessage()],
                    "date" => [ilDBConstants::T_TEXT, $log->getDate()->get(IL_CAL_DATETIME)],
                    "level" => [ilDBConstants::T_INTEGER, $log->getLevel()],
                    "additional_data" => [ilDBConstants::T_TEXT, $json_encode],
                    "job_id" => [ilDBConstants::T_INTEGER, $log->getJobId()],
                    "job_object_type" => [ilDBConstants::T_TEXT, $log->getJobObjectType()],
                    "object_ilias_id" => [ilDBConstants::T_INTEGER, $log->getObjectId()],
                    "object_id" => [ilDBConstants::T_INTEGER, $log->getUserId()],
                    "status" => [ilDBConstants::T_INTEGER, $log->getStatus()],
                ],
                "log_id",
                $log->getLogId()
            )
        );

        $this->keepLog($log);
    }

    private function store(
        string $table_name,
        array $values,
        string $primary_key_field,/*?*/
        int $primary_key_value = 0
    ) : int {
        if (empty($primary_key_value)) {
            $this->db->insert($table_name, $values);

            return $this->db->getLastInsertId();
        } else {
            $this->db->update($table_name, $values, [
                $primary_key_field => [ilDBConstants::T_INTEGER, $primary_key_value]
            ]);

            return $primary_key_value;
        }
    }
}
