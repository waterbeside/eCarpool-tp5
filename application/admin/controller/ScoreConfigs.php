<?php
namespace app\admin\controller;


use app\score\model\Configs as ScoreConfigsModel;
use app\admin\controller\AdminBase;
use app\common\model\Configs as ConfigsModel;
use my\RedisData;
use think\Db;


/**
 * 积分配置设置
 * Class ScoreConfigs
 * @package app\admin\controller
 */
class ScoreConfigs extends AdminBase
{

  /**
   * 转盘抽奖奖项
   * @return mixed
   */
  public function awards()
  {
    if ($this->request->isPost()) {
      $value = $this->request->post('value');
      $value_array = json_decode($value,true);

      $rule_number = $this->request->post('rule_number');
      $data_used  = [];
      $data_used_count = 0;
      $data_used_keys = [];
      $total_rate = 0;

      if(!is_numeric($rule_number) ){
        $this->error("请选择地区");
      }



      foreach ($value_array as $key => $v) {
        $v['rate'] = strval($v['rate']);
        $value_array[$key]['rate'] = strval($v['rate']);
        if(isset($v['status']) && $v['status'] == 1){
          if(!in_array($v['grade'],$data_used_keys)){
            $data_used[strval($v['grade'])] = $v;
            $data_used_keys[] = $v['grade'];
            $data_used_count ++;
          }
          $total_rate = $total_rate + $v['rate'];
        }
        // code...
      }
      if($total_rate > 1 ){
        $this->error("总概率不得大于1");
      }

      array_multisort(array_column($data_used,'grade'),SORT_ASC,$data_used);
      $data_used_keys = [];
      $data_used_kv = [];
      foreach ($data_used as $key => $v) {
        $v['level'] = $key+1;
        $data_used_keys[] = $v['grade'];
        $data_used_kv[$v['grade']] = $v;
      }
      foreach ($value_array as $key => $v) {
        $value_array[$key]['full_desc'] = !isset($v['full_desc']) || empty(trim($v['full_desc'])) ? $v['desc'] : trim($v['full_desc']);
        if(isset($v['status']) && $v['status'] == 1 && isset($data_used_kv[$v['grade']])){
          $value_array[$key]['level'] = $data_used_kv[$v['grade']]['level'];
        }else{
          if(isset($value_array[$key]['level'])){
            unset($value_array[$key]['level']);
          }
          $value_array[$key]['status']  = 0;
        }
        $value_array[$key]['bulletin_count'] = isset($v['bulletin_count']) && $v['bulletin_count'] > 0 ? $v['bulletin_count'] : 0;
        $value_array[$key]['is_bulletin'] = isset($v['is_bulletin'])  && $v['is_bulletin'] ? $v['is_bulletin'] : 0;
        $value_array[$key]['is_disused'] = isset($v['status'])  && $v['status'] ? 0 : 1;
      }
      $value = json_encode($value_array);
      $value_public = json_encode($data_used_kv);
      // dump($value);


      // $value = json_encode();
      $map = [
        ['name','=','awards'],
        ['rule_number','=',$rule_number],
      ];
      $res = ScoreConfigsModel::where($map)->setField('value', $value);
      if($res!==false){
        $redis = new RedisData();
        $redis->delete("score:configs:awards:".$rule_number);
        // $redis->set("score:configs:awards",$value_public); 不由后台生成
        $this->log('更新转盘抽奖奖项成功',0);
        $this->success("更新成功");
      }else{
        $this->log('更新转盘抽奖奖项失败',-1);
        $this->error("更新失败");
      }


    }else{
      $rule_number = $this->request->param('rule_number',0);
      $map = [
        ['name','=','awards'],
        ['rule_number','=',$rule_number],
      ];
      $res = ScoreConfigsModel::where($map)->column('value');
      $lists = [];
      if($res){
        $lists = json_decode($res[0],true);
      }
      foreach ($lists as $key => $value) {
        if(isset($lists[$key]['level'])){
          unset($lists[$key]['level']);
        }
      }

      $returnData =  [
        'lists' => $lists,
        'rule_number' => $rule_number,
        'rule_number_lists'=> config("score.rule_number")
      ];

      return $this->fetch('awards',$returnData);

    }


  }







}
