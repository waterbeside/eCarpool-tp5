<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\Address as AddressModel;

use think\Db;

/**
 * 地址相关
 * Class Docs
 * @package app\api\controller
 */
class Address extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }


    /**
     *
     *
     */
    public function my(){
      $this->checkPassport(1);
      $uid = $this->userBaseInfo['uid'];
      // $resultSet = Db::query('call get_my_address('.$uid.')');
      $res = Db::connect('database_carpool')->query('call get_my_address(:uid)', [
        'uid' => $uid,
      ]);

      if($res){
        $result = $res[0];
        foreach ($result as $key => $value) {
          $result[$key]['longitude'] = $value['longtitude'];
          $result[$key]['addressid'] = intval($value['addressid']);
          unset($result[$key]['longtitude']);
        }
        $returnData  = array(
          'lists' => $result,
          'total'=> count($result)
        );
        $this->jsonReturn(0,$returnData,"success");
        // $this->success('加载成功','',$returnData);
      }else{
        $this->jsonReturn(-1,"","fail");
      }
    }

    /**
     * POST 创建地址
     *
     * @return \think\Response
     */
    public function save()
    {

      $data = $this->request->post();
      if(empty($data['addressname'])){
        $this->jsonReturn(-10001,[],lang('Address name cannot be empty'));
      }
      if(empty($data['latitude']) || (empty($data['longitude']) && empty($data['longtitude'])  )){
        // $this->error('网络出错');
        $this->jsonReturn(-10001,[],lang('Parameter error'));
      }
      $userData = $this->getUserData(1);
      $data['company_id'] = intval($userData['company_id']);
      $AddressModel = new AddressModel();
      $res = (new AddressModel())->addFromTrips($data);
      if(!$res){
        $this->jsonReturn(-1,[],lang('Fail'));
      }
      return $this->jsonReturn(0,$res,'success');
    }

}
