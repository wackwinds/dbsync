<?php
/**
 * @author 黄伟琦(huangweiqi@baidu.com)
 */

class Conf{
    static public $dbInfo = array(
        // key为db名称,value为具体的db信息
        // strategy中涉及的所有数据库都应该在这里能获取到信息
        'db_base' => array(
            // 源数据库连接信息
            // 线上库: db_base
            'sourceDB' => array(
                'host' => '10.114.72.17',
                'port' => '5104',
                'uname' => 'huangweiqi',
                'passwd' => 'iGd5558565~Xs',
            ),
            // QA库
            /*'sourceDB' => array(
                'host' => '10.38.41.58',
                'port' => '6274',
                'uname' => 'u2BSUsj6274',
                'passwd' => 'PrEUSxbPpj0M45HFk',
            ),*/
            // 将会进行数据修改的目标DB
            'targetDB' => array(
                'host' => '10.36.253.127',
                'port' => '6155',
                'uname' => 'u9eXz636155',
                'passwd' => 'PUQClbT27CHNYQaUe',
            ),
            // QA库
            /*'targetDB' => array(
                'host' => '10.38.41.58',
                'port' => '6274',
                'uname' => 'u2BSUsj6274',
                'passwd' => 'PrEUSxbPpj0M45HFk',
            ),*/
        ),

        // ----------++-------------------------++-------------------------++---------------

        'db_schedule' => array(
            // 源数据库连接信息
            // 线上库: db_base
            'sourceDB' => array(
                'host' => '10.114.72.17',
                'port' => '5104',
                'uname' => 'huangweiqi',
                'passwd' => 'iGd5558565~Xs',
            ),
            // QA库
            /*'sourceDB' => array(
                'host' => '10.38.41.58',
                'port' => '6274',
                'uname' => 'u2BSUsj6274',
                'passwd' => 'PrEUSxbPpj0M45HFk',
            ),*/
            // 将会进行数据修改的目标DB
            'targetDB' => array(
                'host' => '10.36.253.127',
                'port' => '6155',
                'uname' => 'u9eXz636155',
                'passwd' => 'PUQClbT27CHNYQaUe',
            ),
            // QA库
            /*'targetDB' => array(
                'host' => '10.38.41.58',
                'port' => '6274',
                'uname' => 'u2BSUsj6274',
                'passwd' => 'PrEUSxbPpj0M45HFk',
            ),*/
        ),
    );

    // diff策略
    static public $diffStrategy = array(
        // key为将要执行同步策略的db名称，与上面dbInfo里的第一层key取值需要相同
        // value为策略名，与代码中的类名相同
        // 目前支持：
        // addTable[根据源库添加表]
        // columnCopy[根据源库中的表同步每张表里的字段属性(包括增删改)]
        // dataCopy[复制数据表里的记录，支持链式复制（根据前面的复制结果把后续其他表里的关联数据也进行复制）]
        'db_base' => array(
            // 'addTable',
            // 'columnCopy',
            // 'indexCopy'
            // 策略名后可以跟具体的策略选项
            /*'dataCopy' => array(
                //-+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+---
                //    表名 => 复制方式
                //    复制方式:   'all' - 全表完全复制
                //                'latestRand' - 取源表最后sourceNum条数据，随机选择其中的targetNum条
                // -+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+---
                // 't_sph_counter' => 'all',
            ),*/
        ),
        'db_schedule' => array(
            'dataCopy' => array(
                //-+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+---
                //    表名 => 复制方式
                //    复制方式:   'all' - 全表完全复制
                //                'latestRand' - 取源表最后sourceNum条数据，随机选择其中的targetNum条
                //
                //                  dataLink - 根据关键字段链式复制相关表数据
                // -+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+----+---+---
//                't_carpool' => array(
//                    'latestRand' => array(
//                        'sourceNum' => 100,
//                        'targetNum' => 10,
//                        'dataChain' => array(
                                // key为用于在源表中进行检索的字段，支持以英文逗号分隔的多个条件and检索
//                            'departureregionid' => array(
                                    // 格式：db名称.表名:目标字段名（需要与key值里的逗号分隔字段一一对应）
//                                // dataChain可重复上面的包含过程，当前写法表示不再进行下一步复制
//                                'db_base.t_region:id' => 'dataChain',
//                            ),
//                            'arrivalregionid' => array(
//                                // dataChain可重复上面的包含过程，当前写法表示不再进行下一步复制
//                                'db_base.t_region:id' => 'dataChain',
//                            ),
//                        ),
//                    ),
//                ),
                't_schedule_201605' => array(
                    'latestRand' => array(
                        'sourceNum' => 100,
                        'targetNum' => 30,
                        'dataChain' => array(
                            'scheduleid' => array(
                                // dataChain可重复上面的包含过程，当前写法表示不再进行下一步复制
                                'db_schedule.t_schedule_stat_201605:scheduleid' => 'dataChain',
                            ),
                            'routeid' => array(
                                // dataChain可重复上面的包含过程，当前写法会进行下一步复制
                                'db_base.t_supplier_station:id' => array(
                                    'departurestationid' => array(
                                        'db_base.t_region_station:stationid' => 'dataChain',
                                    ),
                                    'arrivalstationid' => array(
                                        'db_base.t_region_station:stationid' => 'dataChain',
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ),
    );
}