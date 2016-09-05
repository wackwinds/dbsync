<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */
include_once('log.php');

// strategies
include_once('addTable.php');
include_once('columnCopy.php');
include_once('dataCopy.php');

abstract class StrategyBase{
    protected $objLog = null;
    protected $action = true; // 控制策略是否真正进行应用，取值为false时就只统计不应用

    /**
     * @param $isDebug
     */
    public function __construct($isDebug){
        $this->objLog = new Log();

        if ($isDebug){
            $this->action = false;
        }

        if ($this->action){
            $this->objLog->trace('--------------------- action mode -------------------');
        }else{
            $this->objLog->trace('--------------------- statistics mode -------------------');
        }
    }

    /**
     * @param $instanceName
     * @param bool $isDebug
     * @return mixed
     */
    public function getInstance($instanceName, $isDebug = false){
        return new $instanceName($isDebug);
    }

    /**
     * @param DB $sourceDB
     * @param DB $targetDB
     */
    abstract function excecute(&$sourceDB, &$targetDB, $extend = null);
}