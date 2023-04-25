<?php

require_once __DIR__ . "/../vendor/autoload.php";

use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ILIAS\DI\Container;


/**
 * Class ilLPFixxPlugin
 *
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class ilLPFixxPlugin extends ilCronHookPlugin
{

    use LPFixxTrait;

    const PLUGIN_CLASS_NAME = self::class;
    const PLUGIN_ID = "lpfixx";
    const PLUGIN_NAME = "LPFixx";
    /**
     * @var self|null
     */
    protected static $instance = null;


    /**
     * ilLPFixxPlugin constructor
     */
    public function __construct()
    {
        parent::__construct();
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
     * @inheritDoc
     */
    public function getCronJobInstance(/*string*/ $a_job_id) : ?ilCronJob
    {
        return self::lPFixx()->jobs()->factory()->newInstanceById($a_job_id);
    }


    /**
     * @inheritDoc
     */
    public function getCronJobInstances() : array
    {
        return self::lPFixx()->jobs()->factory()->newInstances();
    }


    /**
     * @inheritDoc
     */
    public function getPluginName() : string
    {
        return self::PLUGIN_NAME;
    }


    /**
     * @inheritDoc
     */
    public function updateLanguages(/*?array*/ $a_lang_keys = null) : void
    {
        parent::updateLanguages($a_lang_keys);

        //$this->installRemovePluginDataConfirmLanguages();

        //DevToolsCtrl::installLanguages(self::plugin());
    }


    /**
     * @inheritDoc
     */
    protected function deleteData() : void
    {
        self::lPFixx()->dropTables();
    }


    /**
     * @inheritDoc
     */
    protected function shouldUseOneUpdateStepOnly() : bool
    {
        return false;
    }
}
