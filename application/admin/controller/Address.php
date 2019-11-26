<?php

namespace app\admin\controller;

use app\admin\controller\AdminBase;
use app\carpool\model\Address as AddressModel;
use think\Db;

/**
 * 拼车站点管理
 * Class Address
 * @package app\admin\controller
 */
class Address extends AdminBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 站点列表
     * @return mixed
     */
    public function index($filter = [], $pagesize = 40, $type = 0)
    {

        if ($type) {
            $map = [];
            if (isset($filter['keyword']) && $filter['keyword']) {
                $keyword = $filter['keyword'];
                $map[] = ['', 'exp', Db::raw("addressname like '%{$keyword}%' or city = '{$keyword}' ")];
            }

            $results = AddressModel::where($map)->order('create_time DESC , addressid DESC ')
                ->paginate($pagesize, false, ['query' => request()->param()]);

            $datas = $results->toArray();
            $page =  [
                'total' => $datas['total'],
                'pageSize' => $datas['per_page'],
                'lastPage' => $datas['last_page'],
                'currentPage' => intval($datas['current_page']),
            ];

            $returnData = [
                'lists' => $datas['data'],
                'page' => $page,
            ];
            return $this->jsonReturn(0, $returnData, 'Successful');
        }
        return $this->fetch('index');
    }

    /**
     * 校正站点城市
     *
     * @param integer $id addressid
     */
    public function city_revise($id)
    {
        $AddressModel = new AddressModel();
        $data = AddressModel::get($id);
        if (!$data) {
            $this->jsonReturn(20002, 'No data');
        }
        $lnglat = $data->longtitude.','.$data->latitude;
        $res = $AddressModel->regeo($lnglat);
        if ($res && $res['info'] === 'OK' && $res['regeocode']) {
            $regeocode = $res['regeocode'];
            $addressComponent = $regeocode['addressComponent'];
            $city = $regeocode['addressComponent']['city'];
            $city = $city ? $city : $regeocode['addressComponent']['province'];
            if (empty($city)) {
                return $this->jsonReturn(-1, $regeocode, '逆地理编码查询失败');
            }
            
            if ($data->city !== $city || empty($data->address) || empty($data->district)) {
                $data->city = $city;
                // $saveRes = $data->save();
                $map = [
                    'longtitude' =>  $data->longtitude,
                    'latitude' =>  $data->latitude,
                ];
                $upData = [
                    'city' => $city,
                    'district' => $addressComponent['province'].$addressComponent['city'].$addressComponent['district'],
                    'address' => $regeocode['formatted_address'],
                    'status' => 2,
                ];
                $saveRes = AddressModel::where($map)->update($upData);
                if ($saveRes === false) {
                    return $this->jsonReturn(-1, '校正失败');
                }
            }
        } else {
            return $this->jsonReturn(-1, $regeocode, '逆地理编码查询失败');
        }
        return $this->jsonReturn(0, $regeocode, '校正城市信息成功');
    }

    /**
     * 编辑
     *
     * @param integer $id addressid
     */
    public function edit($id)
    {
        $data = $this->request->param();
        $fd = isset($data['fd']) ? $data['fd'] : false;
        if ($fd  && in_array($fd, ['addressname'])) {
            if (!isset($data[$fd]) && empty($data[$fd])) {
                $this->jsonReturn(-1, 'Error param');
            }
            $upData = [];
            $upData[$fd] = $data[$fd];
            if (AddressModel::where('addressid', $id)->update($upData) !== false) {
                $this->log('修改地址成功', 0);
                $this->jsonReturn(0, '修改成功');
            } else {
                $this->log('修改地址失败', -1);
                $this->jsonReturn(-1, '修改失败');
            }
        }
        $this->jsonReturn(-1, 'Error param');
    }
}
