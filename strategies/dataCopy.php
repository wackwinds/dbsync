<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */

include_once('base.php');

class dataCopy extends StrategyBase{
    const SPLITER_CONKEYS = ',';
    const SPLITER_CONTABLES = ',';

    /**
     * @param DB $sourceDB
     * @param DB $targetDB
     * @return int
     */
    public function excecute(&$sourceDB, &$targetDB, $extend = null){
        $this->objLog->trace('dataCopy: start');

        $targetTables = $extend['strategy'];
        foreach($targetTables as $table => $tableStrategy){
            if (is_string($tableStrategy)){
                switch($tableStrategy){
                    case 'all':
                        $this->copyAllData($sourceDB, $targetDB, $table);
                        break;
                    default:
                        ;
                }
            }else{
                foreach($tableStrategy as $strategyName => $strategyContent){
                    switch($strategyName){
                        case 'latestRand':
                            $this->copyLastRandData($sourceDB, $targetDB, $table, $strategyContent, $extend['dbInfo']);
                            break;
                        default:
                            ;
                    }
                }
            }
        }
    }

    /**
     * @param $sourceDB
     * @param $targetDB
     * @param $table
     * @param $strategy
     * @param $dbInfo
     */
    private function copyLastRandData(&$sourceDB, &$targetDB, $table, $strategy, $dbInfo){
        $this->objLog->trace('dataCopy: copy last rand data: ' . $table);

        $maxId = $sourceDB->getMaxId($table);
        if ($maxId <= 0){
            $this->objLog->warning('failed to get max id', $sourceDB->showDBInfo(), $table);
            return;
        }

        $sourceNum = $strategy['sourceNum'];
        $targetNum = $strategy['targetNum'];

        if ($sourceNum > $maxId){
            $sourceNum = $maxId;
        }
        if ($targetNum > $sourceNum){
            $targetNum = $sourceNum;
        }

        $this->objLog->trace("dataCopy: start to copy rand ($targetNum in $sourceNum)");

        $arrRandId = array();
        for($i = 0; $i < $targetNum; $i++){
            $randId = rand($maxId, $maxId - $sourceNum);
            // 如果id重复，则进行重新生成
            if (in_array($randId, $arrRandId)){
                $i--;
                continue;
            }

            $arrRandId[] = $randId;
        }

        $totalNum = count($arrRandId);
        $idx = 1;
        if ($totalNum > 0){
            $arrData = array();
            foreach($arrRandId as $key => $value){
                $this->objLog->trace("dataCopy: copying($idx/$totalNum)");
                $idx++;

                $conds['id='] = $value;
                $data = $sourceDB->select($table, '*', $conds);

                if (count($data) > 1){
                    $logInput['table'] = $table;
                    $logInput['conds'] = $conds;
                    $this->objLog->warning('invalid data count', $logInput, $data);
                    continue;
                }

                $data = $data[0];
                if (empty($data)){
                    continue;
                }

                if ($this->action){
                    $ret = $targetDB->setData($data, $table);
                    if (true == $ret){
                        $arrData[] = $data;
                        $this->objLog->trace("dataCopy: copy successed");
                    }else{
                        $this->objLog->trace("dataCopy: copy failed");

                        $logInput['data'] = $data;
                        $logInput['table'] = $table;
                        $this->objLog->warning('data copy failed', $logInput, $targetDB->showDBInfo);
                    }
                }
            }

            // copy data chain
            if (!empty($strategy['dataChain'])){
                $this->copyDataChain($arrData, $strategy['dataChain'], $dbInfo);
            }
        }
    }

    /**
     * @param $sourceDB
     * @param $arrRawData
     * @param $arrChainInfo
     * @param $dbInfo
     */
    private function copyDataChain($arrRawData, $arrChainInfo, $dbInfo){
        $this->objLog->trace('dataCopy: start copy data chain: ' . json_encode($arrChainInfo));

        foreach($arrChainInfo as $conKeys => $conInfo){
            $arrConKeys = $this->getArrKeys($conKeys);
            $this->objLog->trace('dataCopy: copy according to ' . $conKeys);

            foreach($conInfo as $rawTableInfo => $dataChain){
                $this->objLog->trace('dataCopy: copy target ' . $rawTableInfo);
                $arrTableInfo = $this->parseTableInfoInDataChain($rawTableInfo);

                foreach($arrTableInfo as $tableInfo){
                    $targetDBRaw = mysqli_init();
                    $targetDB = new DB($targetDBRaw);
                    $targetDBInfo = $dbInfo[$tableInfo['db']]['targetDB'];
                    $isConnected = $targetDB->connect($targetDBInfo['host'], $targetDBInfo['uname'], $targetDBInfo['passwd'], $tableInfo['db'], $targetDBInfo['port']);
                    if (!$isConnected){
                        $this->objLog->warning('failed to connect targetDB in copyDataChain', $targetDBInfo, $isConnected);
                        continue;
                    }

                    $newRawSourceDB = mysqli_init();
                    $newSourceDB = new DB($newRawSourceDB);
                    $newSourceDBInfo = $dbInfo[$tableInfo['db']]['sourceDB'];
                    $isConnected = $newSourceDB->connect($newSourceDBInfo['host'], $newSourceDBInfo['uname'], $newSourceDBInfo['passwd'], $tableInfo['db'], $newSourceDBInfo['port']);
                    if (!$isConnected){
                        $this->objLog->warning('failed to connect newSourceDB in copyDataChain', $newSourceDBInfo, $isConnected);
                        continue;
                    }

                    $targetData = array();
                    foreach($arrRawData as $data){
                        $sql = $this->genDataCopySQL($data, $arrConKeys, $tableInfo['columns'], $tableInfo['table']);
                        $this->objLog->trace('target sql: ' . $sql);
                        $nextData = $newSourceDB->getSQLResult($sql, MYSQLI_ASSOC);

                        if (count($nextData) > 1){
                            $this->objLog->warning('invalid data count', $sql, $nextData);
                            continue;
                        }

                        $nextData = $nextData[0];

                        if (!empty($nextData) && count($nextData) > 0){
                            $targetData[] = $nextData;
                            if ($this->action){
                                $dbSetRet = $targetDB->setData($nextData, $tableInfo['table']);
                                if (true == $dbSetRet){
                                    $this->objLog->trace("dataCopy: copy successed");
                                }else{
                                    $this->objLog->trace("dataCopy: copy failed");

                                    $logInput['data'] = $nextData;
                                    $logInput['table'] = $tableInfo['table'];
                                    $this->objLog->warning('data copy failed', $logInput, $targetDB->showDBInfo);
                                }
                            }
                        }
                    }

                    if (is_array($dataChain) && count($dataChain) > 0){
                        // 递归拷贝
                        $this->copyDataChain($targetData, $dataChain, $dbInfo);
                    }

                    $targetDB->close();
                    $newSourceDB->close();
                }
            }
        }
    }

    /**
     * @param $data
     * @param $arrConKeys // 原始表中的列名，与targetColumns一一对应
     * @param $targetColumns
     * @param $table
     * @return string
     */
    private function genDataCopySQL($data, $arrConKeys, $targetColumns, $table){
        $sql = 'select * from `' . $table . '` where ';
        $isFirst = true;
        foreach($targetColumns as $idx => $column){
            if (!$isFirst){
                $sql .= ' and ';
            }else{
                $isFirst = false;
            }
            $sql .= $column . "='" . $data[$arrConKeys[$idx]] . "'";
        }
        return $sql;
    }

    /**
     * @param $conKeys
     * @return array
     */
    private function getArrKeys($conKeys){
        return explode(self::SPLITER_CONKEYS, $conKeys);
    }

    /**
     * @param $tableInfo
     * @return array
     */
    private function parseTableInfoInDataChain($tableInfo){
        $arrTableInfo = explode(self::SPLITER_CONTABLES, $tableInfo);
        $arrResult = array();
        foreach($arrTableInfo as $dbTable){
            $split = explode('.', $dbTable);
            $temp['db'] = $split[0];
            $split = explode(':', $split[1]);
            $temp['table'] = $split[0];
            $columns = $split[1];
            $temp['columns'] = explode(';', $columns);
            $arrResult[] = $temp;
        }
        return $arrResult;
    }

    /**
     * @param $sourceDB
     * @param $targetDB
     * @param $table
     */
    private function copyAllData(&$sourceDB, &$targetDB, $table){
        $this->objLog->trace('dataCopy: copy all data: ' . $table);

        // update: 分批获取数据
        $sourceData = $sourceDB->getAllData($table);

        $totalNum = count($sourceData);
        $idx = 1;
        foreach($sourceData as $data){
            $this->objLog->trace("dataCopy: copying($idx/$totalNum)");
            $idx++;
            $ret = $targetDB->setData($data, $table);
            if (true == $ret){
                $this->objLog->trace("dataCopy: copy successed");
            }else{
                $this->objLog->trace("dataCopy: copy failed");

                $logInput['data'] = $data;
                $logInput['table'] = $table;
                $this->objLog->warning('data copy failed', $logInput, $targetDB->showDBInfo);
            }
        }
    }

    /**
     * 递归进行字符转码
     * @param $arrData
     * @param $in_charset
     * @param $out_charset
     * @return mixed
     */
    private function recursiveIconv($arrData, $in_charset, $out_charset){
        $newData = array();
        foreach($arrData as $key => $value){
            if (is_array($value)){
                $value = $this->recursiveIconv($value, $in_charset, $out_charset);
            }else{
                $value = iconv($in_charset, $out_charset, $value);
            }
            $key = iconv($in_charset, $out_charset, $key);

            $newData[$key] = $value;
        }

        return $newData;
    }
}