<?php
namespace app\api\controller\v1\publics;

use app\api\controller\ApiBase;
use my\RedisData;


use think\Db;

/**
 * 报表相关
 * Class Reports
 * @package app\api\controller
 */
class Reports extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 列表
     * @param  integer $pagesize  每页条数
     */
    public function trips_summary(){
        $sql = "select sum(sumqty) as sumtrip,sum(1) as sumdriver from carpool.reputation";
        $res  =  Db::connect('database_score')->query($sql);
        if(!$res){
            return $this->jsonReturn(20002,'No data');
        }
        $returnData = $res[0];
        $this->jsonReturn(0,$returnData,'success');
    }





}
