<?php
namespace app\admin\controller;

use app\score\model\History as HistoryModel;
use app\score\model\AccountMix as AccountMixModel;
use app\score\model\Account as ScoreAccountModel;
use app\common\model\Docs as DocsModel;
use app\common\model\DocsCategory as DocsCategoryModel;
use my\RedisData;

use app\common\controller\AdminBase;
use think\Db;

/**
 * 积分操作
 * Class Link
 * @package app\admin\controller
 */
class Score extends AdminBase
{

  /**
   * 变更积分
   * @return mixed
   */
  public function change()
  {
    if ($this->request->isPost()) {
      $datas         = $this->request->post('');

      if(!$datas['operand'] || !$datas['reason']){
        $this->jsonReturn(-1,'参数错误');
      }else{
        $datas['operand'] = abs(intval($datas['operand']));
      }
      //根据accountType查出account字段
      if($this->updateScore($datas)){
        $this->jsonReturn(0,'改分成功');
      }else{
        $this->jsonReturn(-1,'改分失败');
      };

    }else{
      $type         = input('param.type/s');
      $account      = input('param.account/s');
      $account_id   = input('param.account_id/d',0);
      $accountModel = new AccountMixModel();
      if( ($type=='0' || $type=="score") && $account_id ){ //直接从积分帐号取
        $accountInfo = $accountModel->getDetailById($account_id);
      }else{
        $accountInfo = $accountModel->getDetailByAccount($type,$account);
      }

      $reasons = config('score.reason');
      $reasons_operable = config('score.reason_operable');
      $reasonsArray=[];
      foreach ($reasons as $key => $value) {
        if(in_array($key,$reasons_operable)){
          $reasonsArray[] = ['code'=>$key,'title'=>$value];
        }
      }

      $returnData = [
        'accountInfo'=>$accountInfo,
        'reasons'=>$reasons,
        'reasonsArray'=>$reasonsArray,
        'type'=>$type,
        'account'=>$account,
        'account_id'=>$account_id,
      ];
      return $this->fetch('change',$returnData);
    }

  }

  /**
   * 更新积分
   * @param  Array $datas 更新的数据
   */
  public function updateScore($datas){

    $type         = $datas['type'];
    $account_id   = isset($datas['account_id']) ? $datas['account_id'] : NULL;
    $account      = isset($datas['account']) ? $datas['account'] : NULL;
    $isadd        = $datas['isadd'];

    $data['operand']      = $datas['operand'];
    $data['reason']       = $datas['reason'];

    $accountType = config("score.account_type");
    $accountField = '';
    foreach ($accountType as $key => $value) {

      if( (is_numeric($type) && $key ==  $type ) || $value['name'] == $type ){
        $accountField = $value['field'];
        break;
      }
    }

    if(!$accountField && !$account_id  && !$account){
      // $this->jsonReturn(-1,'参数错误');
      return false;
    }

    try{
      //查找是否已开通拼车帐号，拼整理data
      $accountDetial = null ;
      if( ($type=='0' || $type=="score")  &&  $account_id ){ //直接从积分帐号取
        $accountDetial = ScoreAccountModel::where(['id'=>$account_id,['is_delete','<>', 1]])->lock(true)->find();
      }elseif($accountField){
        $accountDetial = ScoreAccountModel::where([$accountField=>$account,['is_delete','<>', 1]])->lock(true)->find();
      }
      if($accountDetial && $accountDetial['id']){
        $data['account_id'] = $accountDetial['id'];
        $updateAccountMap = [
          'id'=>$accountDetial['id'],
          'update_time'=>$accountDetial['update_time']
        ];
        if($isadd && $data['reason'] > 0 ){
          $data['result']    = intval($accountDetial['balance']) +  $data['operand'];
          $upAccountStatus = ScoreAccountModel::where($updateAccountMap)->setInc('balance', $data['operand']);
        }
        if(!$isadd && $data['reason'] < 0 ){
          $data['result']    = intval($accountDetial['balance']) -  $data['operand'];
          $upAccountStatus = ScoreAccountModel::where($updateAccountMap)->setDec('balance', $data['operand']);
        }
        if(!$upAccountStatus){
          throw new \Exception("更新分数失败");
        }
      }else{
        $data[$accountField] = $account;
        $data['result'] = 0;
      }
      $data['extra_info'] = '{}';
      $data['is_delete'] = 0;
      $data['time'] =  date('Y-m-d H:i:s');
      $historyModel =   new HistoryModel;
      $upHistoryStatus = $historyModel->save($data);
      if(!$upHistoryStatus){
        throw new \Exception("更新分数失败");
      }
      // 提交事务
      Db::commit();
    } catch (\Exception $e) {
        // 回滚事务
        Db::rollback();
        $logMsg = '改分失败，请稍候再试'.json_encode($this->request->post());
        $this->log($logMsg,-1);
        return false;

    }
    $this->log('改分成功，'.json_encode($this->request->post()),0);
    return true;

  }




    /**
     * 积分配置
     */
    public function config(){
      # 控制积分兑换&&积分抽奖&&实物抽奖开关
                // "order_switch" : 1,
                // "lottery_integral_switch" : 1,
                // "lottery_material_switch" : 1,

      $configs = $this->systemConfig;
      $redis = new RedisData();
      $soreSettingData=json_decode($redis->get("CONFIG_SETTING"),true);
      // dump($soreSettingData);
      if ($this->request->isPost()){
        $datas          = $this->request->post('');
        $order_date     = explode(',',$datas['order_date']);
        $exchange_date  = explode(',',$datas['exchange_date']);
        $data['order_date'] = $order_date;
        $data['exchange_date'] = $exchange_date;
        $soreSettingData['order_date'] = [];
        $soreSettingData['exchange_date'] = [];

        foreach ($order_date as $key => $value) {

          if(is_numeric($value)){
            $soreSettingData['order_date'][]= intval($value);
          }
        }
        foreach ($exchange_date as $key => $value) {
          if(is_numeric($value)){
            $soreSettingData['exchange_date'][]=intval($value);
          }
        }

        $soreSettingData['order_switch']              = isset($datas['order_switch'])            ? $datas['order_switch']            : 0 ;
        $soreSettingData['lottery_integral_switch']   = isset($datas['lottery_integral_switch']) ? $datas['lottery_integral_switch'] : 0 ;
        $soreSettingData['lottery_material_switch']   = isset($datas['lottery_material_switch']) ? $datas['lottery_material_switch'] : 0 ;
        $soreSettingData['lottery_integral_price']    = isset($datas['lottery_integral_price'])  ? $datas['lottery_integral_price']  : 0 ;
        $soreSettingDataStr = json_encode($soreSettingData);
        $redis->set('CONFIG_SETTING', $soreSettingDataStr);
        $this->log('修改积分配置成功',0);
        $this->success("修改成功");


      }else{

        $data = $soreSettingData;
        $data['order_date_str']           =  !isset($data['order_date'])  ? '' : (is_array($data['order_date']) ? join(",",$data['order_date']) : $data['order_date']);
        $data['exchange_date_str']        =  !isset($data['exchange_date'])  ? '' : (is_array($data['exchange_date']) ? join(",",$data['exchange_date']) : $data['exchange_date']);
        /*$data['(order_switch)']             =  isset($data['order_switch'])             ? $data['order_switch']            : 0  ;
        $data['lottery_integral_switch']  =  isset($data['lottery_integral_switch'])  ? $data['lottery_integral_switch'] : 0  ;
        $data['lottery_material_switch']  =  isset($data['lottery_material_switch'])  ? $data['lottery_material_switch'] : 0  ;
        $data['lottery_integral_price']   =  isset($data['lottery_integral_price'])   ? $data['lottery_integral_price']  : 0  ;*/


        return $this->fetch('config',['configs'=>$configs,'data'=>$data]);

      }

    }


    /**
     * 文档管理
     * @param int    $cid     分类ID
     * @param string $keyword 关键词
     * @param int    $page
     * @return mixed
     */
    public function doc($cate = 'score', $keyword = '', $page = 1)
    {

        $map   = [];
        $field = 't.id,t.title,t.cid,t.update_time,t.create_time,t.listorder,t.status,t.lang,c.name as cate, c.title as cate_title';

        if ($cate) {
            $map[] = ['c.name','=',$cate];
        }

        if (!empty($keyword)) {
            $map[] = ['title','like', "%{$keyword}%"];
        }

        $join = [
          ['docs_category c','t.cid = c.id', 'left'],
        ];
        $lists  = DocsModel::field($field)->alias('t')->join($join)->where($map)->order('t.cid DESC , t.create_time DESC')->paginate(15, false, ['page' => $page]);
        // $category_list = $this->category_model->field('id,name,title')->where([['is_delete','=',0]])->select();
        $category_list = DocsCategoryModel::column('title', 'name');
        return $this->fetch('doc', ['lists' => $lists, 'category_list' => $category_list, 'cate' => $cate, 'keyword' => $keyword]);
    }


    /**
     * 添加文档
     * @return mixed
     */
    public function doc_add($cate = 'score')
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'Docs');
          $data['description'] = $data['description'] ? iconv_substr($data['description'],0,250) : '' ;
          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          } else {
              $docsModel = new DocsModel();
              if ($docsModel->allowField(true)->save($data)) {
                  $this->log('保存文档成功',0);
                  $this->jsonReturn(0,'保存成功');
              } else {
                  $this->log('保存文档失败',-1);
                  $this->jsonReturn(-1,'保存失败');
              }
          }
      }else{
        $category_list = DocsCategoryModel::where([['is_delete','=',0]])->select();
        $cate_data = '';
        foreach ($category_list as $key => $value) {
          if($value['name'] == $cate){
            $cate_data = $value;
          }
        }

        return $this->assign('category_list',$category_list)
             ->assign('cate_data',$cate_data)
             ->assign('cate',$cate)
             ->fetch();

      }
    }



    /**
     * 编辑文档
     * @param $id
     * @return mixed
     */
    public function doc_edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'Docs');
          $data['description'] = $data['description'] ? iconv_substr($data['description'],0,250) : '' ;
          if ($validate_result !== true) {
              $this->jsonReturn(-1,$validate_result);
          } else {
              unset($data['cid']);
              $docsModel = new DocsModel();
              if ($docsModel->allowField(true)->save($data, $id) !== false) {
                  $this->log('编辑文档成功',0);
                  $this->jsonReturn(0,'修改成功');
              } else {
                  $this->log('编辑文档失败',-1);
                  $this->jsonReturn(-1,'修改失败');
              }
          }
      }else{
        $field = 't.*,c.name as cate, c.title as cate_title';
        $join = [
          ['docs_category c','t.cid = c.id', 'left'],
        ];
        $data = DocsModel::field($field)->alias('t')->join($join)->find($id);
        $category_list = DocsCategoryModel::where([['is_delete','=',0]])->select();

        return $this->fetch('doc_edit', ['data' => $data,'category_list'=>$category_list]);
      }

    }




    /**
     * 积分导入
     * @param  integer $page [description]
     * @return [type]        [description]
     */
      public function test_multi_balance($page=1){
        $lists = Db::connect('database_carpool')->table('temp_carpool_score')->page($page,1)->select();
        exit;
        if(count($lists)>0){
          $msg =   "";
          foreach ($lists as $key => $value) {
            $data = [
              'type' => 'carpool',
              'account' => $value['loginname'],
              'operand' =>  $value['balance'],
              'reason' => 1 ,
              'isadd' =>1 ,
            ];
            if($value['status']>0){
              $msg .=  "id:".$value['id'].";"."account:".$data['account'].";"."operand:".$data['operand'].";"."   Has finished <br />";
              continue;
            }
            // dump($data);
            $is_ok = $this->updateScore($data);
            // dump($is_ok);exit;
            if($is_ok){
              $st = Db::connect('database_carpool')->table('temp_carpool_score')->where("id",$value['id'])->update(['status'=>1]);
              $msg .=  "id:".$value['id'].";"."account:".$data['account'].";"."operand:".$data['operand'].";"."   OK   ";
            }else{
              $msg .=  "id:".$value['id'].";"."account:".$data['account'].";"."operand:".$data['operand'].";"."   fail ";
            }
          }
          $page = $page+1;
          $url  = url('admin/Score/test_multi_balance',['page'=>$page]);
        }else{
          $url  = '';
          $msg = "完成全部操作";

        }

        return $this->fetch('index/multi_jump',['url'=>$url,'msg'=>$msg]);

      }



}
