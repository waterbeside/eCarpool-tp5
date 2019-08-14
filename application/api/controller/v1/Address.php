<?php

namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\Address as AddressModel;
use app\carpool\service\Trips as TripsService;
use think\facade\Cache;
use think\Db;
use my\RedisData;

/**
 * 地址相关
 * Class Address
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
    public function my()
    {
        $this->checkPassport(1);
        $uid = $this->userBaseInfo['uid'];
        // $resultSet = Db::query('call get_my_address('.$uid.')');
        $res = Db::connect('database_carpool')->query('call get_my_address(:uid)', [
            'uid' => $uid,
        ]);

        if ($res) {
            $result = $res[0];
            foreach ($result as $key => $value) {
                $result[$key]['longitude'] = $value['longtitude'];
                $result[$key]['addressid'] = intval($value['addressid']);
                unset($result[$key]['longtitude']);
            }
            $returnData  = array(
                'lists' => $result,
                'total' => count($result)
            );
            $this->jsonReturn(0, $returnData, "success");
            // $this->success('加载成功','',$returnData);
        } else {
            $this->jsonReturn(-1, "", "fail");
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
        if (empty($data['addressname'])) {
            $this->jsonReturn(-10001, [], lang('Address name cannot be empty'));
        }
        if (empty($data['latitude']) || (empty($data['longitude']) && empty($data['longtitude']))) {
            // $this->error('网络出错');
            $this->jsonReturn(-10001, [], lang('Parameter error'));
        }
        $userData = $this->getUserData(1);
        $data['company_id'] = intval($userData['company_id']);
        $data['create_uid'] = intval($userData['uid']);
        $AddressModel = new AddressModel();
        $res = $AddressModel->addFromTrips($data);
        if (!$res) {
            $this->jsonReturn(-1, [], lang('Fail'));
        }
        return $this->jsonReturn(0, $res, 'success');
    }

    /**
     * GET 通过id取详情
     * @return \think\Response
     */
    public function read($id)
    {
        if (!$id) {
            return $this->my();
        }
        $res = AddressModel::field('addressid,addressname,ordernum,company_id,city,latitude,longtitude')->find($id);
        if (!$res) {
            $this->jsonReturn(20002, [], lang('No data'));
        }
        $res['longitude'] = $res['longtitude'];
        unset($res['longtitude']);
        if (isset($res['company_id'])) {
            $res['company_id'] = intval($res['company_id']);
        }
        if (isset($res['ordernum'])) {
            $res['ordernum'] = intval($res['ordernum']);
        }
        return $this->jsonReturn(0, $res, 'success');
    }


    /**
     *  GET 取得城市列表 group by
     * @param  integer $type 0 ，所有，1 空坐位上的，2约车需求上的
     * @return [type]        [description]
     */
    public function citys($type = 0)
    {
        $userData = $this->getUserData(1);
        $company_id = $userData['company_id'];
        if ($type) {
            if (!in_array($type, [1, 2, 'wall', 'info'])) {
                return $this->jsonReturn(992, 'error type');
            }
            $cacheKey = "carpool:citys:company_id_$company_id:type_$type";
            $redis = new RedisData();
            $res_str =  $redis->get($cacheKey);
            $res =  $res_str ? json_decode($res_str, true) : false;
            // $res = Cache::get($cacheKey);
            if (!$res) {
                $join = [];
                if ($type == 1 || $type == "wall") {
                    $join[] = ['love_wall s', 't.addressid = s.startpid', 'left'];
                } elseif ($type == 2 || $type == "info") {
                    $join[] = ['info s', 't.addressid = s.startpid', 'left'];
                }
                $map = [
                    ["s.time", ">", date('YmdHi')],
                    ["s.status", "<>", '2'],
                    // ["t.company_id", "=", $company_id]
                ];
                $TripsService = new TripsService();
                $map[] = $TripsService->buildCompanyMap($userData, 't');
                $resList = AddressModel::alias('t')->field('t.city , count(t.city) as num')->join($join)
                    ->where($map)->group('t.city')
                    ->order('num DESC')->select();
                $res = [];
                $hasNull = false;
                $hasMin = false;
                foreach ($resList as $key => $value) {
                    if ($value['city'] == "(null)") {
                        $hasNull = true;
                    } elseif ($value['city'] == "--") {
                        $hasMin = true;
                    } else {
                        $res[] = $value['city'];
                    }
                }
                if ($hasMin) {
                    $res[] = "--";
                }
                if ($hasNull) {
                    // $res[] = "(null)";
                }
                // Cache::set($cacheKey,$res,300);
                $redis->setex($cacheKey, 300, json_encode($res));
            }
        } else {
            $res = AddressModel::group('city')->order('city Desc')->cache(30)->column('city');
        }
        if ($res) {
            $this->jsonReturn(0, $res, lang('Successfully'));
        } else {
            $this->jsonReturn(20002, $res, lang('No data'));
        }
    }
}
