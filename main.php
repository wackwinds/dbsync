<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */

ini_set('memory_limit', '6144M');
ini_set('max_execution_time', 0);

/**
 * Class Job
 * @property Bd_DB $db
 */
include_once('conf.php');
include_once('log.php');
include_once('DB.php');
include_once('strategies/base.php');

class Job{
    public $objLogResult = null;
    public $objLogProcess = null;
    private $diffStrategy = null;
    private $dbInfo = null;
    private $sourceDB = null;
    private $targetDB = null;
    private $env = null; // 运行环境，表示可以调用特定环境的函数

    const FOLDER_NAME_RESULT = 'logs';
    const LOG_NAME_RESULT = 'result.log';
    const LOG_NAME_PROCESS = 'process.log';

    const COMMAND_DEBUG = '-d';
    const COMMAND_HELP = '-h';
    const COMMAND_SET_ENV = '-env';
    const COMMAND_VALUE_ENV_ODP = Log::ENV_ODP;

    const ERRNO_INVALID_DIFF_STRATEGY = 1001;
    const ERRNO_INVALID_DB_INFO = 1002;

    /**
     *
     */
    public function __construct()
    {
        $this->objLogResult = new Log($this->env);
        $this->objLogResult->setLogPath('logs/result.log');

        $this->objLogProcess = new Log($this->env);
        $this->objLogProcess->setLogPath('logs/process.log');
    }

    /**
     * @param array $input
     * @return mixed|void
     */
    public function run()
    {
        $errno = $this->init();
        if (0 != $errno){
            return $errno;
        }

        $this->objLogProcess->trace('start to apply strategies');

        $strategies = $this->diffStrategy;
        foreach($strategies as $dbName => $strategy){
            $this->objLogProcess->trace('start to deal with ' . $dbName);
            $dbInfo = $this->dbInfo[$dbName];
            if (empty($dbInfo) || !is_array($dbInfo)){
                $this->objLogProcess->warning('empty db info: ' . $dbName);
                continue;
            }

            $sourceDBInfo = $dbInfo['sourceDB'];
            $isConnected = $this->sourceDB->connect($sourceDBInfo['host'], $sourceDBInfo['uname'], $sourceDBInfo['passwd'], $dbName, $sourceDBInfo['port']);
            if (!$isConnected){
                $this->objLogProcess->warning('failed to connect source DB', $sourceDBInfo, $isConnected);
                continue;
            }
            $this->sourceDB->useDB($dbName);

            $targetDBInfo = $dbInfo['targetDB'];
            $isConnected = $this->targetDB->connect($targetDBInfo['host'], $targetDBInfo['uname'], $targetDBInfo['passwd'], $dbName, $targetDBInfo['port']);
            if (!$isConnected){
                $this->objLogProcess->warning('failed to connect target DB', $targetDBInfo, $isConnected);
                continue;
            }
            $this->targetDB->useDB($dbName);

            $extend['dbInfo'] = $this->dbInfo;
            // key 跟 value 都有可能是策略名
            foreach($strategy as $key => $value){
                if (is_string($value)){
                    $objStrategy = StrategyBase::getInstance($value, $this->isDebug);
                }else{
                    $objStrategy = StrategyBase::getInstance($key, $this->isDebug);
                }

                $extend['strategy'] = $value;

                $objStrategy->excecute($this->sourceDB, $this->targetDB, $extend);
            }

            // finished dealing with db
            /*$this->sourceDB->close();
            $this->targetDB->close();*/
        }
    }

    /**
     * @return int
     */
    private function init(){
        $this->objLogProcess->trace('start to init');

        $this->dbInfo = Conf::$dbInfo;
        $this->diffStrategy = Conf::$diffStrategy;

        if (empty($this->diffStrategy) || !is_array($this->diffStrategy)){
            $this->objLogResult->warning('diff stategy is empty | not an array');
            return self::ERRNO_INVALID_DIFF_STRATEGY;
        }

        if (empty($this->dbInfo) || !is_array($this->dbInfo)){
            $this->objLogResult->warning('db info is empty | not an array');
            return self::ERRNO_INVALID_DB_INFO;
        }

        $sourceDB = mysqli_init();
        $this->sourceDB = new DB($sourceDB);
        $targetDB = mysqli_init();
        $this->targetDB = new DB($targetDB);

        $this->objLogProcess->trace('init finished');
    }

    /**
     *
     */
    private function usage(){
        echo "\n\n";
        echo "     ||       ||   ===     ===     ===     *******                          \n";
        echo "     ||       ||    ===   =====   ===     **     **                         \n";
        echo "     ||=======||     === === === ===     **       **                        \n";
        echo "     ||       ||      =====   =====       **     ***                        \n";
        echo "     ||       ||       ===     ===         ******* ***                      \n";
        echo "\n";
        echo "script writen by huangweiqi@baidu.com\n";
        echo "how to run : php {this script} {params}\n";
        echo "simplest sample: php ./thisScript.php\n";
        echo "supported params:\n";
        echo self::COMMAND_DEBUG . " : run in debug mode, thie mode will not write changes to target DB\n";
        echo self::COMMAND_HELP . " : show tips about this script\n";
        echo "\n\n";
    }

    /**
     *
     */
    public function parseArgs(){
        for ($i = 1; $i < $_SERVER['argc']; $i++) {
            switch ($_SERVER['argv'][$i]) {
                case self::COMMAND_DEBUG:
                    $this->isDebug = true;
                    break;
                case self::COMMAND_HELP:
                    $this->usage();
                    exit(0);
                    break;
                case self::COMMAND_SET_ENV:
                    $this->env = $_SERVER['argv'][$i + 1];
                    break;
            }
        }
    }
}

$startTime = microtime(true);

$job = new Job();
$job->parseArgs();
$errno = $job->run();

$endTime = microtime(true);

var_dump('run succ in ' . ($endTime - $startTime) . ' secs');
exit($errno);