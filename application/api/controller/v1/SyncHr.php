<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\User as OldUserModel;
use app\user\model\User as NewUserModel;
use app\user\model\UserTemp ;
use Firebase\JWT\JWT;
use my\RedisData;
use think\Db;

/**
 * 同步hr系统
 * Class Passport
 * @package app\api\controller
 */
class SyncHr extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        $host = $this->request->host();
        if(strpos($host,'127.0.0.1') === false && strpos($host,'localhost') === false ){
          $this->error(lang('Illegal access'));
        }
    }

    /**
     * 验证登入
     */
    public function index($date = '')
    {

      $redis = new RedisData();
      $cacheKey = "carpool:user:sync_hr:$date";
      $cacheData = json_decode($redis->get($cacheKey),true);
      // dump($cacheData);
      if($cacheData){
        // $this->error('该日期数据已经同步过');
      }

      $client = new \GuzzleHttp\Client();


      $response = $client->request('POST', config('secret.HR_api.getUserlist'), [
          'form_params' => [
              'modiftytime' => $date,
          ]
      ]);
      $body = $response->getBody();
      $remainingBytes = $body->getContents();
      // dump($remainingBytes);
      $bodyObj = new \SimpleXMLElement($remainingBytes);
      unset($body);
      unset($remainingBytes);

      $bodyString = json_decode(json_encode($bodyObj),true)[0] ;
      $redis->set($cacheKey,$bodyString);

      unset($bodyObj);
      $dataArray = json_decode($bodyString,true);
      unset($bodyString);

      $success = [];
      $fail = [];
      foreach ($dataArray as $key => $value) {
        $oldData = UserTemp::where([["create_time",">=",$value['ModiftyTime']],['code',"=",$value['Code']]])->find();
        if($oldData){
          continue;
        }
        $data = [
          "code" => $value['Code'],
          "name" => $value['EmployeeName'],
          "modifty_time" => $value['ModiftyTime'],
          "department" => $value['OrgFullName'],
          "sex" => $value['Sex'],
        ];
        $returnId = UserTemp::insertGetId($data);
        $returnSuccess =  $returnId ? $returnId : 0;
        if($returnId){
          $success[] = $data['name'];
        }else{
          $fail[] = $data['name'];
        }
        // echo $data['name'].":".$returnSuccess,", ";
        // echo $key%10 === 0 ? "<br />" : "";

        // echo $value['EmployeeName'] . "<br />";
      }
      $this->jsonReturn(0,["success"=>$success,"fail"=>$fail],"入库成功");



exit;
      // try {
      //   // libxml_disable_entity_loader(false);
      //
      //     $client = new \SoapClient('https://mobile.esquel.cn:8001/HRPaySlipWebTest/carpool.asmx/GetEmployeeInfoForCarpoolbyModiftyTime');
      //     $result =  $client->__soapCall('greet', [
      //         ['modiftytime' => '2018-01-01']
      //     ]);
      //     dump($result);exit;
      //     // printf("Result = %s", $result->greetReturn);
      // } catch (Exception $e) {
      //   dump("Message = %s",$e->__toString());
      // }

    }






}
