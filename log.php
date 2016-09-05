<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */

class Log{
    const ENV_ODP = 'odp';
    const ENV_DEFAULT = 'default';

    const LOG_LEVEL_DEBUG = 1;
    const LOG_LEVEL_TRACE = 2;
    const LOG_LEVEL_NOTICE = 3;
    const LOG_LEVEL_WARNING = 4;

    private $logLevelAndShowWords = array(
        self::LOG_LEVEL_DEBUG => 'debug',
        self::LOG_LEVEL_TRACE => 'trace',
        self::LOG_LEVEL_NOTICE => 'notice',
        self::LOG_LEVEL_WARNING => 'warning',
    );

    private $logPath = null;
    private $objLog = null;

    /**
     * @param string $env
     */
    public function __construct($env = 'default'){
        switch($env){
            case self::ENV_ODP:
                Bd_Init::init();
                $this->objLog = BdBus_Factory_Log::getInstance();
                break;
            default:
                ;
        }
    }

    /**
     * @param $path
     */
    public function setLogPath($path){
        $this->logPath = $path;
    }

    /**
     * @param $strMsg
     * @param null $arrInput
     * @param null $arrOutput
     * @param int $errno
     * @param bool $printStack
     */
    public function warning($strMsg, $arrInput = null, $arrOutput = null, $errno = 0, $printStack = true){
        $this->log(self::LOG_LEVEL_WARNING, $strMsg, $arrInput, $arrOutput, $errno, $printStack);
    }

    /**
     * @param $strMsg
     * @param null $arrInput
     * @param null $arrOutput
     * @param int $errno
     * @param bool $printStack
     */
    public function notice($strMsg, $arrInput = null, $arrOutput = null, $errno = 0, $printStack = false){
        $this->log(self::LOG_LEVEL_NOTICE, $strMsg, $arrInput, $arrOutput, $errno, $printStack);
    }

    /**
     * @param $strMsg
     * @param null $arrInput
     * @param null $arrOutput
     * @param int $errno
     * @param bool $printStack
     * @param bool $simpleOutput
     */
    public function trace($strMsg, $arrInput = null, $arrOutput = null, $errno = 0, $printStack = false, $simpleOutput = true){
        $this->log(self::LOG_LEVEL_TRACE, $strMsg, $arrInput, $arrOutput, $errno, $printStack, $simpleOutput);
    }

    /**
     * @param $strMsg
     * @param null $arrInput
     * @param null $arrOutput
     * @param int $errno
     * @param bool $printStack
     */
    public function debug($strMsg, $arrInput = null, $arrOutput = null, $errno = 0, $printStack = false){
        $this->log(self::LOG_LEVEL_DEBUG, $strMsg, $arrInput, $arrOutput, $errno, $printStack);
    }

    /**
     * @param $logLevel
     * @param $strMsg
     * @param null $arrInput
     * @param null $arrOutput
     * @param int $errno
     * @param bool $printStack
     * @param bool $simpleOutput
     */
    private function log($logLevel, $strMsg, $arrInput = null, $arrOutput = null, $errno = 0, $printStack = false, $simpleOutput = false){
        $newTraceStack = array();
        if ($printStack){
            $rawTraceStack = debug_backtrace();;
            foreach($rawTraceStack as $key => $value){
                // unset outer input params to make sure the log file will not be toooo large
                unset($value['args']);
                $newTraceStack[] = $value;
            }
        }

        $logWords = $this->logLevelAndShowWords[$logLevel];
        /*$msg = $logWords . ':';
        if (!empty($newTraceStack)){
            $msg .= ' callStack: ' . json_encode($newTraceStack);
        }
        if (!empty($arrInput)){
            $msg .= ' input: ' . json_encode($arrInput);
        }
        if (!empty($arrOutput)){
            $msg .= ' output: ' . json_encode($arrOutput);
        }*/

        $outputStr = json_encode($arrOutput);
        $inputStr = json_encode($arrInput);

        if ('null' == $outputStr){
            $outputStr = json_encode($this->array_iconv('gbk', 'utf8', $arrOutput));
        }
        if ('null' == $inputStr){
            $inputStr = json_encode($this->array_iconv('gbk', 'utf8', $arrInput));
        }

        if ($simpleOutput){
            var_dump($strMsg);
        }else{
            var_dump(
                $logWords . ': callStack: ' . json_encode($newTraceStack) . ' input: ' .
                $inputStr . ' output: ' . $outputStr . ' msg: ' . $strMsg . " errno: " .
                $errno
            );
        }
    }

    /**
     * @param $in_charset
     * @param $out_charset
     * @param $arr
     * @return mixed
     */
    private function array_iconv($in_charset,$out_charset,$arr){
        return eval('return '.iconv($in_charset,$out_charset,var_export($arr,true).';'));
    }
}