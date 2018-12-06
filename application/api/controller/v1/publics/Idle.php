<?php
namespace app\api\controller\v1\publics;

use app\api\controller\ApiBase;
use app\content\model\Idle as IdleModel;
use my\RedisData;


use think\Db;

/**
 * 二手市场公开信息
 * Class Banners
 * @package app\api\controller
 */
class Idle extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }


    public function index($pagesize=20){
      $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,u.name,u.phone,u.loginname";
      $map = [
        ["is_delete","<>",1],
        // ["show_level",">",0],
      ];
      $join = [
        ['user u','u.uid = t.user_id', 'left']
      ];
      $orderby = "t.status DESC, post_time DESC";
      $results = IdleModel::alias('t')->field($fields)->json(['t.images'])->where($map)->join($join)->order($orderby)->paginate($pagesize, false,  ['query'=>request()->param()])->toArray();

      $datas = $results['data'];
      $pageData = [
        'total'=>$results['total'],
        'pageSize'=>$results['per_page'],
        'lastPage'=>$results['last_page'],
        'currentPage'=>$results['current_page'],
      ];
      $returnData = [
        'lists'=>$datas,
        'page' =>$pageData,
      ];
      $this->jsonReturn(0,$returnData,'success');
    }




}
