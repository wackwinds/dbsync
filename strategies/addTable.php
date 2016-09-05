<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */

include_once('base.php');

class addTable extends StrategyBase{
    /**
     * @param DB $sourceDB
     * @param DB $targetDB
     * @return int
     */
    public function excecute(&$sourceDB, &$targetDB, $extend = null){
        $this->objLog->trace('addTable: start');
        $sourceTables = $sourceDB->getTables();

        if (!is_array($sourceTables)){
            $this->objLog->warning('failed to get source tables', $sourceDB->showDBInfo());
            return -1;
        }

        $targetTables = $targetDB->getTables();
        if (!is_array($targetTables)){
            $this->objLog->warning('failed to get target tables', $targetDB->showDBInfo());
            return -1;
        }

        // get target table mapping
        $targetTableMap = array();
        foreach($targetTables as $table){
            $targetTableMap[$table] = 1;
        }

        $notExistTables = array(); // 源数据库中不存在于目标数据库的表（待添加的表）
        foreach($sourceTables as $table){
            if (empty($targetTableMap[$table])){
                $notExistTables[] = $table;
            }
        }

        if (count($notExistTables) > 0){
            $this->objLog->trace('addTable: find not exist tables', $notExistTables);
        }else{
            $this->objLog->trace('addTable: no extra table need to add');
        }

        $totalNum = count($notExistTables);
        $idx = 1;
        if ($this->action){
            foreach($notExistTables as $table){
                $this->objLog->trace("addTable: table copying ($idx/$totalNum)");
                $idx++;

                $sqlResult = $targetDB->tableCopy($sourceDB, $table);
                if (true !== $sqlResult){
                    $this->objLog->trace('addTable: failed to copy table: ' . $table);
                    $this->objLog->warning('failed to copy table', $table, $sourceDB->showDBInfo());
                }else{
                    $this->objLog->trace('addTable: success copy table: ' . $table);
                }
            }
        }
    }
}