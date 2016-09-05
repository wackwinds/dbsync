<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */
include_once('log.php');

class DB{
    const STATE_NORMAL = 1;
    const STATE_INVALID_DB_OBJ = 2;

    private $objLog = null;
    public $db = null;
    private $state = self::STATE_NORMAL;
    private $uname = null;
    private $host = null;
    private $dbname = null;
    private $port = null;

    /**
     * @param $db
     */
    public function __construct($db){
        $this->objLog = new Log();
        $this->db = $db;
        if (!is_object($this->db)){
            $this->objLog->warning('invalid obj db', $db);
            $this->state = self::STATE_INVALID_DB_OBJ;
        }
    }

    /**
     * @param $dbName
     * @return array|null
     */
    public function useDB($dbName){
        $sql = 'use ' . $dbName;
        $ret = $this->getSQLResult($sql);
        return $ret;
    }

    /**
     * @return null
     */
    public function getDBName(){
        return $this->dbname;
    }

    /**
     * @param $tableName
     * @param $fields
     * @param $conds
     * @return array|null
     */
    public function select($tableName, $fields, $conds){
        if ($fields != '*'){
            $strFields = implode("`,`", $fields);
        }else{
            $strFields = '*';
        }

        $strConds = null;
        $isFirst = true;
        foreach($conds as $key => $value){
            if ($isFirst){
                $isFirst = false;
                $strConds .= $key . $value;
            }else{
                $strConds .= "," . $key . $value;
            }
        }

        $sql = 'select ' . $strFields . ' from ' . '`' . $tableName . '`';

        if (!empty($strConds)){
            $sql .= " where " . $strConds;
        }

        $ret = $this->getSQLResult($sql, MYSQLI_ASSOC);
        return $ret;
    }

    /**
     * @param $tableName
     * @param string $columnName
     * @return array|null
     */
    public function getMaxId($tableName, $columnName = 'id'){
        $sql = "select max($columnName) from `" . $tableName . '`';
        $sqlResult = $this->getSQLResult($sql);
        $ret = $sqlResult[0][0];
        return $ret;
    }

    /**
     * @param $data
     * @param $tableName
     * @return array|null
     */
    public function setData($data, $tableName){
        $strColumn = '(`';
        $strValue = "('";
        $strUpdate = "";
        $arrColumns = array();
        $arrValues = array();
        $arrUpdate = array();
        foreach($data as $columnName => $value){
            $arrColumns[] = $columnName;
            $arrValues[] = $value;
            $arrUpdate[] = "`" . $columnName . "`" . " = " . "'" . $value . "'";
        }
        $strColumn .= implode("`,`", $arrColumns);
        $strValue .= implode("','", $arrValues);
        $strUpdate .= implode(',', $arrUpdate);
        $strColumn .= '`)';
        $strValue .= "')";

        $sql = 'insert into `' . $tableName . '` ' . $strColumn . ' values ' . $strValue . " ON DUPLICATE KEY UPDATE "  . $strUpdate;

        $ret = $this->getSQLResult($sql, MYSQLI_ASSOC);
        return $ret;
    }

    /**
     * @param $tableName
     * @return array|null
     */
    public function getAllData($tableName){
        $sql = 'select * from `' . $tableName . '`';
        $sqlResult = $this->getSQLResult($sql, MYSQLI_ASSOC);
        $ret = $sqlResult;
        return $ret;
    }

    /**
     * @param $tableName
     * @return mixed
     */
    public function showCreateTable($tableName){
        $sql = 'show create table `' . $tableName . '`';
        $sqlResult = $this->getSQLResult($sql);
        $ret = $sqlResult[0][1];
        return $ret;
    }

    /**
     * 从源数据库中复制一张相同的表到本数据库
     * @param $sourceDB
     * @param $tableName
     * @return array|null
     */
    public function tableCopy($sourceDB, $tableName){
        $createSQL = $sourceDB->showCreateTable($tableName);
        $createSQL = str_replace("\n", '', $createSQL);
        $sqlResult = $this->getSQLResult($createSQL);

        return $sqlResult;
    }

    /**
     * @param $table
     * @return null
     * 返回 columnName => array(column properties)
     */
    public function showColumns($table){
        $sql = 'show full fields from `' . $table . '`';
        $sqlResult = $this->getSQLResult($sql, MYSQLI_ASSOC);

        $ret = null;
        if (is_array($sqlResult)){
            foreach($sqlResult as $columnInfo){
                $ret[$columnInfo['Field']] = $columnInfo;
            }
        }

        return $ret;
    }

    /**
     * @param $tableName
     * @param $columnInfo
     * @return array|null
     */
    public function addColumn($tableName, $columnInfo){
        $strNotNull = '';
        if ($columnInfo['Null'] === 'NO'){
            $strNotNull = 'NOT NULL ';
        }

        $default = '';
        if (!empty($columnInfo['Default'])){
            $default = 'default ' . $columnInfo['Default'] . " ";
        }

        $extra = '';
        if (!empty($columnInfo['Extra'])){
            $extra = $columnInfo['Extra'] . " ";
        }

        $comment = '';
        if (!empty($columnInfo['Comment'])){
            $comment = "COMMENT '" . $columnInfo['Comment'] . "' ";
        }

        $sql = "alter table `" . $tableName . "` add column `" . $columnInfo['Field'] . "` " . $columnInfo['Type'] . " " . $strNotNull . $default . $extra . $default . $comment;
        $ret = $this->getSQLResult($sql);
        return $ret;
    }

    /**
     * @param $tableName
     * @param $columnInfo
     * @return array|null
     */
    public function modifyColumn($tableName, $columnInfo){
        $strNotNull = '';
        if ($columnInfo['Null'] === 'NO'){
            $strNotNull = 'NOT NULL ';
        }

        $default = '';

        if (!empty($columnInfo['Default'])){
            $default = 'default ' . $columnInfo['Default'] . " ";
        }

        $extra = '';
        if (!empty($columnInfo['Extra'])){
            $extra = $columnInfo['Extra'] . " ";
        }

        $comment = '';
        if (!empty($columnInfo['Comment'])){
            $comment = "COMMENT '" . $columnInfo['Comment'] . "' ";
        }

        $sql = "alter table `" . $tableName . "` modify column `" . $columnInfo['Field'] . "` " . $columnInfo['Type'] . " " . $strNotNull . $default . $extra . $comment;

        $ret = $this->getSQLResult($sql);
        return $ret;
    }

    /**
     * @param $tableName
     * @param $columnName
     * @return array|null
     */
    public function dropColumn($tableName, $columnName){
        $sql = "alter table `" . $tableName . "` drop column `" . $columnName . "`";
        $ret = $this->getSQLResult($sql);
        return $ret;
    }

    /**
     * @return array|null
     */
    public function getTables(){
        $sql = 'show tables';
        $tables = $this->getSQLResult($sql);
        $ret = null;
        if (is_array($tables)){
            foreach($tables as $tb){
                $ret[] = $tb[0];
            }
        }

        return $ret;
    }

    /**
     * @param $sql
     * @return array|null
     */
    public function getSQLResult($sql, $fetchType = MYSQLI_NUM){
        $result = null;
        if (!is_string($sql)){
            $this->objLog->warning('invalid sql', $this->showDBInfo(), $sql);
            return null;
        }

        $sqlResult = $this->db->query($sql);

        if ($sqlResult){
            if (!is_object($sqlResult)){
                return $sqlResult;
            }

            while(true){
                if ($fetchType == MYSQLI_NUM){
                    $row = $sqlResult->fetch_row();
                }else{
                    $row = $sqlResult->fetch_assoc();
                }

                if (empty($row)){
                    break;
                }

                $result[] = $row;
            }
        }else{
            $this->objLog->warning('failed to excecute SQL', $this->showDBInfo(), $sql);
            return null;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function showDBInfo(){
        $info = array();
        $info['db_name'] = $this->dbname;
        $info['uname'] = $this->uname;
        $info['host'] = $this->host;
        $info['port'] = $this->port;

        /*var_dump(
            "db_name: " . $this->dbname, "uname: " . $this->uname,
            "host: " . $this->host, "port: " . $this->port
        );*/

        return $info;
    }

    /**
     * @param $host
     * @param $uname
     * @param $passwd
     * @param $dbname
     * @param $port
     * @return mixed
     */
    public function connect($host, $uname, $passwd, $dbname, $port){
        $this->dbname = $dbname;
        $this->host = $host;
        $this->uname = $uname;
        $this->port = $port;

        return $this->db->real_connect($host, $uname, $passwd, $dbname, $port);
    }

    /**
     * @return mixed
     */
    public function close(){
        return $this->db->close();
    }
}