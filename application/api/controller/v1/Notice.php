<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\content\model\CommonNotice as NoticeModel;
use app\common\model\I18nLang as I18nLangModel;
use think\Db;

/**
 * 通知
 * Class Notice
 * @package app\api\controller
 */
class Notice extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 通知列表
     * @return mixed
     */
    public function index($type=1)
    {
        $lang = (new I18nLangModel())->formatLangCode($this->language);
        $field = 't.id,t.title,t.content,t.type,t.start_time,t.end_time,t.create_time,t.refresh_time,t.sort,t.status,t.lang';
        $map   = [];
        $map[] = ['status','=',1];
        $map[] = ['type','=',$type];
        $map[] = ['lang','=',$lang];
        $map[] = ['end_time','>=',date("Y-m-d H:i:s")];
        $map[] = ['start_time','<',date("Y-m-d H:i:s")];
        $lists  = NoticeModel::field($field)->alias('t')->where($map)->order('t.sort DESC , t.id DESC')->select();
        // dump($lists);exit;
        if(empty($lists)){
          return $this->jsonReturn(20002,$data,'暂无数据');
        }
        foreach ($lists as $key => $value) {
          $lists[$key]['token'] = md5(strtotime($value['refresh_time']));
        }
        $returnData = [
          'lists' => $lists,
        ];
        // dump($lists);
        return $this->jsonReturn(0,$returnData,'success');

    }


    /**
     *
     * @param  int  $id
     */
    public function read($id)
    {
      $field = 't.id,t.title,t.content,t.type,t.start_time,t.end_time,t.create_time,t.refresh_time,t.sort,t.status,t.lang';
      $map   = [];
      $map[] = ['status','=',1];
      $map[] = ['id','=',$id];

      $data  = NoticeModel::field($field)->alias('t')->where($map)->find();
      if(!$data){
        return $this->jsonReturn(20002,$data);
      }
      return $this->jsonReturn(0,$data);
    }

}
