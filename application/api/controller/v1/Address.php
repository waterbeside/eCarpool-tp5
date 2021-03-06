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

        $addressModel = new AddressModel();
        $result =  $addressModel->myCache($uid);
        if (!$result) {
            $res = Db::connect('database_carpool')->query('call get_my_address_ex(:uid)', [
                'uid' => $uid,
            ]);
            if (!$res) {
                return $this->jsonReturn(20002, "fail");
            }
            $result = $res[0];
            foreach ($result as $key => $value) {
                $result[$key]['longitude'] = $value['longtitude'];
                $result[$key]['addressid'] = intval($value['addressid']);
                unset($result[$key]['longtitude']);
            }
            $addressModel->myCache($uid, $result, 60);
        }
        // $resultSet = Db::query('call get_my_address('.$uid.')');
        $returnData  = array(
            'lists' => $result,
            'total' => count($result)
        );
        $this->jsonReturn(0, $returnData, "success");
        // $this->success('加载成功','',$returnData);
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
        $res = $AddressModel->addOne($data, 50);
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
        if (!in_array($type, [1, 2, 'wall', 'info'])) {
            return $this->jsonReturn(992, 'error type');
        }
        $type = $type == 'wall' ? 1 : ($type == 'info' ? 2 : $type);
        $AddressModel = new AddressModel();
        $cacheKey = $AddressModel->getCitysCacheKey($company_id, $type);
        $redis = RedisData::getInstance();
        $res =  $redis->cache($cacheKey);
        // $res = Cache::get($cacheKey);
        if (!$res) {
            if ($type) {
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
            } else {
                $res = AddressModel::group('city')->order('city Desc')->column('city');
            }
            $redis->cache($cacheKey, $res, 300);
        }
        if ($res) {
            $this->jsonReturn(0, $res, lang('Successfully'));
        } else {
            // $this->jsonReturn(20002, $res, lang('No data'));
            $this->jsonReturn(0, [], lang('Successfully'), [], false);
        }
    }
}
