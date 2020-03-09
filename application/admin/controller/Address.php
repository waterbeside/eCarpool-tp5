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
                $keywordSplit = explode('&&', $keyword);
                foreach ($keywordSplit as $key => $value) {
                    $keywordSplit_2 = explode(':', $value);
                    if (count($keywordSplit_2) < 2) {
                        $map[] = ['', 'exp', Db::raw("addressname like '%{$keyword}%' or city = '{$keyword}' ")];
                    } else {
                        $fields = $keywordSplit_2[0];
                        $fieldsSplit = explode('|', $fields);
                        $fieldsArray = array_intersect($fieldsSplit, ['id','addressname','city','address','district','status']);
                        $field = implode('|', $fieldsArray);
                        $v = $keywordSplit_2[1];
                        // if (in_array($fields[0], ['addressname','city','address','dist'])) {
                        // }
                        if ($v == 'null') {
                            $map[] = ['', 'exp', Db::raw("$field is null ")];
                        } elseif (count($fieldsArray) === 1 && in_array($field, ['id', 'status'])) {
                            $map[] = [$field, '=', $v];
                        } else {
                            $map[] = [$field, 'like', "%{$v}%"];
                        }
                    }
                }
            }
            $AddressModel = new AddressModel();
            $queryFields = $AddressModel->getCommonFields(['create_time']);
            $results = AddressModel::field($queryFields)->where($map)->order('create_time DESC , addressid DESC ')
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

            $city = $addressComponent['city'];
            $district = $addressComponent['district'];
            $province = $addressComponent['province'];
            $city = empty($city) ? '' : ( is_array($city) ? join(',', $city) : $city ) ;
            $district = empty($district) ? '' : ( is_array($district) ? join(',', $district) : $district ) ;
            $city_x = $city ? $city : $regeocode['addressComponent']['province'];
            if (empty($city_x)) {
                return $this->jsonReturn(-1, $regeocode, '逆地理编码查询失败');
            }
            
            if ($data->city !== $city_x || empty($data->address) || empty($data->district)) {
                $data->city = $city_x;
                // $saveRes = $data->save();
                $map = [
                    'longtitude' =>  $data->longtitude,
                    'latitude' =>  $data->latitude,
                ];
                $upData = [
                    'city' => $city,
                    'district' => $province.$city.$district,
                    'address' => $regeocode['formatted_address'],
                    'status' => 2,
                ];
                $saveRes = AddressModel::where($map)->update($upData);
                if ($saveRes === false) {
                    return $this->jsonReturn(-1, '校正失败');
                }
            }
            return $this->jsonReturn(0, $regeocode, '校正城市信息成功');
        } elseif (isset($res['info'])) {
            return $this->jsonReturn(-1, $res['info'], '逆地理编码查询失败');
        } else {
            return $this->jsonReturn(-1, '逆地理编码查询失败');
        }
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
