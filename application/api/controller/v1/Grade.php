<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Grade as GradeModel;


use think\Db;

/**
 * 评分Grade
 * Class Banners
 * @package app\api\controller
 */
class Grade extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }


    protected function typeCheck($oid=0,$type=0,$action="")
    {
      $this->checkPassport(1);
      $uid = $this->userBaseInfo['uid'];
      $type = intval($type);
      switch ($type) {
        case 0:
          $this->jsonReturn(992,"Error type");
          break;
        case 1:
          $oData = InfoModel::find($oid);
          if(!$oData){
            $this->jsonReturn(992,"Error object_id");
          }
          if($action == "save" && ($oData['passengerid'] != $uid || $oData['carownid'] != $uid) ){
            $this->jsonReturn(30001,lang("You are not the driver or passenger of this trip").lang('.').lang("You can't rate this"));
          }
        case 2:
          $oData = WallModel::find($oid);
          if(!$oData){
            $this->jsonReturn(992,"Error object_id");
          }
          if($action == "save" &&  $oData['carownid'] != $uid){
            $this->jsonReturn(30001,lang("You are not the driver of this trip").lang('.').lang("You can't rate this"));
          }
          break;
        default:
          // code...
          break;
      }
      return $oData;
    }

    /**
     * 取得是否已评分
     * @param  integer $oid  [description]
     * @param  integer $type [description]
     * @return [type]        [description]
     */
    public function index($oid=0,$type=0){
      $this->checkPassport(1);
      $uid = $this->userBaseInfo['uid'];
      if( !is_numeric($oid)){
        $this->jsonReturn(992,lang('Parameter error'));
      }
      // $objectData = $this->typeCheck($oid,$type);

      $map = [
        'object_id' =>$oid,
        'type' => $type,
        'uid' => $uid,
      ];
      $data = GradeModel::where($map)->find();
      if($data){
        $returnData = [
          "type"=>$data['type'],
          "uid"=>$data['uid'],
          "object_id"=>$data['object_id'],
          "grade"=>$data['grade'],
          "create_time"=>strtotime($data['create_time']),
        ];
        $this->jsonReturn(0,$returnData,"Success");
      }else{
        $this->jsonReturn(20002,lang('No data'));
      }
    }

    /**
     * 评分
     * @param  integer $oid  [description]
     * @param  integer $type [description]
     * @return [type]        [description]
     */
    public function save($oid=0,$type=0){
      $this->checkPassport(1);
      $uid = $this->userBaseInfo['uid'];
      $grade = input('post.grade');
      $remark = input('post.remark');
      if(!is_numeric($grade) || !is_numeric($oid)){
        $this->jsonReturn(992,lang('Parameter error'));
      }
      $objectData = $this->typeCheck($oid,$type,'save');
      $map = [
        'object_id' =>$oid,
        'type' => $type,
        'uid' => $uid,
      ];
      $GradeModel = new GradeModel();
      $data = $GradeModel->field('type,uid,object_id,create_time,grade')->where($map)->find();
      if($data){
        $data['create_time'] = strtotime($data['create_time']) ? strtotime($data['create_time']) : 0;
        $this->jsonReturn(30006,$data,lang("You are not the driver of this trip"));
      }else{
        $setData = [
          'type' => intval($type),
          'object_id' => intval($oid),
          'uid' => intval($uid),
          'grade' => intval($grade),
          'create_time'=> date('Y-m-d H:i:s'),
        ];
        if($remark){
          $setData['remark'] = $remark;
        }

        $res = $GradeModel->insertGetId($setData);
        if(!$res){
          $this->jsonReturn(-1,lang("Failed"));
        }
        $returnData = $setData;
        $returnData['id'] = $res;
        $returnData['create_time'] = strtotime($setData['create_time']) ? strtotime($setData['create_time']) : 0;
        $this->jsonReturn(0,$returnData,lang("Successfully"));
      }


    }







}
