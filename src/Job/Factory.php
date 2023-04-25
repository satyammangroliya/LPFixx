<?php

namespace minervis\plugins\LPFixx\Job;

use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ilCronJob;

/**
 * Class Factory
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
final class Factory
{

    use LPFixxTrait;

    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var self|null
     */
    protected static $instance = null;


    /**
     * Factory constructor
     */
    private function __construct()
    {

    }


    /**
     * @return self
     */
    public static function getInstance() : self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * @param string $job_id
     *
     * @return ilCronJob|null
     */
    public function newInstanceById(string $job_id) : ?ilCronJob
    {
        switch ($job_id) {
            case Job::CRON_JOB_ID:
                return $this->newJobInstance();

            default:
                return null;
        }
    }


    /**
     * @return ilCronJob[]
     */
    public function newInstances() : array
    {
        return [
            $this->newJobInstance()
        ];
    }


    /**
     * @return Job
     */
    public function newJobInstance() : Job
    {
        return new Job();
    }
}
