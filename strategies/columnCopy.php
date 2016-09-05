<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */

include_once('base.php');

/**
 * Class columnCopy
 */
class columnCopy extends StrategyBase{
    const COLUMN_CONNECTOR = '|'; // 进行列属性对比时的连接符

    /**
     * @param DB $sourceDB
     * @param DB $targetDB
     * @return int
     */
    public function excecute(&$sourceDB, &$targetDB, $extend = null){
        $this->objLog->trace('columnCopy: start');
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

        $bothExistTables = array(); // 两边数据库都存在的表（待进行列对比的表）
        foreach($sourceTables as $table){
            if (!empty($targetTableMap[$table])){
                $bothExistTables[] = $table;
            }
        }

        if (count($bothExistTables) > 0){
            $this->objLog->trace('columnCopy: find both exist tables', $bothExistTables);
        }else{
            $this->objLog->trace('columnCopy: no extra table need to add');
        }

        $totalNum = count($bothExistTables);
        $idx = 1;
        foreach($bothExistTables as $table){
            $this->objLog->trace("columnCopy: column copying ($idx/$totalNum) - $table");
            $idx++;

            $targetColumns = $targetDB->showColumns($table);
            $sourceColumns = $sourceDB->showColumns($table);

            $columnsOnlyInSource = array();
            $columnsTheSame = array();
            foreach($sourceColumns as $columnName => &$sColumn){
                // 以下字段不加入对比之中
                unset($sColumn['Privileges']);
                unset($sColumn['Collation']);

                if (empty($targetColumns[$columnName])){
                    $columnsOnlyInSource[] = $columnName;
                }else{
                    $columnsTheSame[] = $columnName;
                }
            }

            $columnsOnlyInTarget = array();
            foreach($targetColumns as $columnName => &$tColumn){
                // 以下字段不加入对比之中
                unset($tColumn['Privileges']);
                unset($tColumn['Collation']);

                if (empty($sourceColumns[$columnName])){
                    $columnsOnlyInTarget[] = $columnName;
                }
            }

            $this->objLog->trace('columnCopy: only in source columns' . json_encode($columnsOnlyInSource));
            $this->objLog->trace('columnCopy: only in target columns' . json_encode($columnsOnlyInTarget));

            $this->dropColumns($targetDB, $table, $columnsOnlyInTarget);
            $this->addColumns($targetDB, $table, $columnsOnlyInSource, $sourceColumns);
            $this->rectifyColumns($targetDB, $table, $columnsTheSame, $sourceColumns, $targetColumns);
        }
    }

    /**
     * @param $db
     * @param $table
     * @param $columnNames
     * @param $sourceColumnInfo
     * @param $targetColumnInfo
     */
    private function rectifyColumns($db, $table, $columnNames, $sourceColumnInfo, $targetColumnInfo){
        $totalNum = count($columnNames);
        $idx = 1;
        foreach($columnNames as $columnName){
            $this->objLog->trace("comparing column ($idx/$totalNum) - $columnName");
            $idx++;

            $sourceColumn = $sourceColumnInfo[$columnName];
            $targetColumn = $targetColumnInfo[$columnName];

            $strSourceColumn = implode(self::COLUMN_CONNECTOR, $sourceColumn);
            $strTargetColumn = implode(self::COLUMN_CONNECTOR, $targetColumn);

            if ($strSourceColumn !== $strTargetColumn){
                $this->objLog->trace('diff column: ' . $strSourceColumn . " VS " . $strTargetColumn);

                if (!$this->action){
                    continue;
                }

                $ret = $db->modifyColumn($table, $sourceColumn);
                if (true === $ret){
                    $this->objLog->trace('columnCopy: success modified column: ' . $columnName);
                }else{
                    $this->objLog->trace('columnCopy: failed to modify column: ' . $columnName);
                    $this->objLog->warning('failed to drop column', $sourceColumn, $db->showDBInfo());
                }
            }
        }
    }

    /**
     * @param $db
     * @param $table
     * @param $columns
     */
    private function dropColumns($db, $table, $columns){
        $totalNum = count($columns);
        $idx = 1;
        foreach($columns as $columnName){
            $this->objLog->trace("dropping column ($idx/$totalNum)");
            $idx++;

            if (!$this->action){
                continue;
            }

            $ret = $db->dropColumn($table, $columnName);
            if (true === $ret){
                $this->objLog->trace('columnCopy: success dropped column: ' . $columnName);
            }else{
                $this->objLog->trace('columnCopy: failed to drop column: ' . $columnName);
                $this->objLog->warning('failed to drop column', $columnName, $db->showDBInfo());
            }
        }
    }

    /**
     * @param $db
     * @param $table
     * @param $columnNames
     * @param $columnInfo
     */
    private function addColumns($db, $table, $columnNames, $columnInfo){
        $totalNum = count($columnNames);
        $idx = 1;
        foreach($columnNames as $columnName){
            $this->objLog->trace("adding column ($idx/$totalNum)");
            $idx++;

            if (!$this->action){
                continue;
            }

            $ret = $db->addColumn($table, $columnInfo[$columnName]);

            if (true === $ret){
                $this->objLog->trace('columnCopy: success add column: ' . $columnName);
            }else{
                $this->objLog->trace('columnCopy: failed to add column: ' . $columnName);
                $this->objLog->warning('failed to add column', $columnName, $db->showDBInfo());
            }
        }
    }
}