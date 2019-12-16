<?php

namespace app\carpool\service;

use app\common\service\Service;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\Configs as ConfigsModel;
use app\carpool\model\user as UserModel;
use app\common\model\PushMessage;
use app\user\model\Department as DepartmentModel;
use think\Db;
use my\RedisData;

class Trips extends Service
{

    protected $cacheKey_myTrip = "carpool:trips:my:";
    protected $cacheKey_myInfo = "carpool:trips:my_info:";

    /**
     * 创件状态筛选map
     */
    public function buildStatusMap($status, $alias = "t")
    {
        $statusExp = '=';
        if (is_string($status) && strpos($status, '|')) {
            $statusArray = explode('|', $status);
            if (count($statusArray) > 1) {
                $statusExp = $statusArray[0];
                $status = $statusArray[1];
            } else {
                $status = $status[0];
            }
        }
        if (is_string($status) && strpos($status, ',')) {
            $status = explode(',', $status);
        }
        if (is_array($status) && $statusExp == "=") {
            $statusExp = "in";
        }
        if (in_array(mb_strtolower($statusExp), ['=', '<=', '>=', '<', '>', '<>', 'in', 'eq', 'neq', 'not in', 'lt', 'gt', 'egt', 'elt'])) {
            return [$alias . '.status', $statusExp, $status];
        } else {
            return [$alias . '.status', "=", $status];
        }
    }


    /**
     * 创件要select的用户字段
     *
     * @param mixed $a 别名
     * @param array $fields 要显示的字段
     */
    public function buildUserFields($a = "u", $fields = [])
    {
        $format_array = [];
        $fields = !empty($fields) ? $fields : ['uid', 'loginname', 'name','nativename', 'phone', 'mobile', 'Department', 'sex', 'company_id', 'department_id', 'companyname', 'imgpath', 'carnumber', 'carcolor', 'im_id'];
        if (is_array($a)) {
            $aa = $a;
            $a = $aa[0];
            $pf = isset($aa[1]) ? $aa[1].'_' : '';
        } else {
            $pf = $a.'_';
        }
        foreach ($fields as $key => $value) {
            $format_array[$key] = $a . "." . $value . " as " . $pf . mb_strtolower($value);
        }
        return join(",", $format_array);
    }

    /**
     * 创件要select的地址字段
     */
    public function buildAddressFields($fields = "", $alias = 't')
    {
        $fields .= ",{$alias}.startpid, {$alias}.endpid";
        $fields .= ", x({$alias}.start_latlng) as start_lng, y({$alias}.start_latlng) as start_lat";
        $fields .= ", x({$alias}.end_latlng) as end_lng, y({$alias}.end_latlng) as end_lat";
        $fields .= ", {$alias}.startname , {$alias}.start_gid ";
        $fields .= ", {$alias}.endname , {$alias}.end_gid ";
        $fields .= ',s.addressname as start_addressname, s.latitude as start_latitude, s.longtitude as start_longitude';
        $fields .= ',e.addressname as end_addressname, e.latitude as end_latitude, e.longtitude as end_longitude';
        return $fields;
    }


    /**
     * 创健要join的表的数缓
     * @param  string|array $filter
     * @return array
     */
    public function buildTripJoins($filter = "d,p,s,e,department", $alias = 't')
    {
        if (is_string($filter)) {
            $filter = explode(",", $filter);
        }
        $join = [];
        if (is_array($filter)) {
            foreach ($filter as $key => $value) {
                $filter[$key] = mb_strtolower($value);
            }
            if (in_array('s', $filter) || in_array('start', $filter)) {
                $join[] = ['address s', "s.addressid = {$alias}.startpid", 'left'];
            }
            if (in_array('e', $filter) || in_array('end', $filter)) {
                $join[] = ['address e', "e.addressid = {$alias}.endpid", 'left'];
            }
            if (in_array('d', $filter) || in_array('driver', $filter)) {
                $join[] = ['user d', "d.uid = {$alias}.carownid", 'left'];
                if (in_array('department', $filter)) {
                    $join[] = ['t_department dd', 'dd.id = d.department_id', 'left'];
                }
            }
            if (in_array('u', $filter) || in_array('driver', $filter)) {
                $join[] = ['user u', "u.uid = {$alias}.userid", 'left'];
                if (in_array('department', $filter)) {
                    $join[] = ['t_department ud', 'ud.id = u.department_id', 'left'];
                }
            }
            if (in_array('p', $filter) || in_array('passenger', $filter)) {
                $join[] = ['user p', "p.uid = {$alias}.passengerid", 'left'];
                if (in_array('department', $filter)) {
                    $join[] = ['t_department pd', 'pd.id = p.department_id', 'left'];
                }
            }
        }
        return $join;
    }


    /**
     * 格式化结果字段
     */
    public function formatResultValue($value, $merge_ids = [], $unDo = [])
    {
        $value_format = $value;
        $value_format['subtime'] = intval(strtotime($value['subtime']));
        // $value_format['go_time'] = $value['go_time'] ?  $value['go_time'] : strtotime($value['time']."00");
        // $value_format['time'] = date('Y-m-d H:i',strtotime($value['time'].'00'));
        // $value_format['time'] = $value_format['go_time'];
        $value_format['time'] = intval(strtotime($value['time'] . '00'));
        //整理指定字段为整型
        $int_field_array = [
            'p_uid', 'p_sex', 'p_company_id', 'p_department_id',
            'd_uid', 'd_sex', 'd_company_id', 'd_department_id',
            'infoid', 'love_wall_ID',
            'startpid', 'endpid',
            'seat_count', 'trip_type'
        ];
        $value = json_decode(json_encode($value), true);
        foreach ($value as $key => $v) {
            if (in_array($key, $int_field_array)) {
                $value_format[$key] = intval($v);
            }
        }
        // if (!empty($merge_ids)) {
        //     if (!in_array('p', $unDo) && isset($value['p_uid']) &&  in_array($value['p_uid'], $merge_ids)) {
        //         $value_format['p_uid'] = $uid;
        //         $value_format['passengerid'] = $uid;
        //         $value_format['p_name'] = $userData['name'];
        //     }
        //     if (!in_array('d', $unDo) && isset($value['d_uid']) && in_array($value['d_uid'], $merge_ids)) {
        //         $value_format['d_uid'] = $uid;
        //         $value_format['carownid'] = $uid;
        //         $value_format['d_name'] = $userData['name'];
        //     }
        // }

        if (!is_numeric($value['startpid']) || $value['startpid'] < 1) {
            $value_format['start_addressid'] = $value['startpid'];
            $value_format['start_addressname'] = $value['startname'];
            $value_format['start_longitude'] = $value['start_lng'];
            $value_format['start_latitude'] = $value['start_lat'];
        }
        if (!is_numeric($value['endpid']) || $value['endpid'] < 1) {
            $value_format['end_addressid'] = $value['endpid'];
            $value_format['end_addressname'] = $value['endname'];
            $value_format['end_longitude'] = $value['end_lng'];
            $value_format['end_latitude'] = $value['end_lat'];
        }

        if (isset($value['d_full_department']) || isset($value['p_full_department'])) {
            $DepartmentModel = new DepartmentModel();
        }
        if (isset($value['d_full_department'])) {
            $value_format['d_department'] = $DepartmentModel->formatFullName($value['d_full_department'], 4);
        }
        if (isset($value['p_full_department'])) {
            $value_format['p_department'] = $DepartmentModel->formatFullName($value['p_full_department'], 4);
        }
        if (isset($value['d_imgpath']) && trim($value['d_imgpath']) == "") {
            $value_format['d_imgpath'] = 'default/avatar.png';
        }
        if (isset($value['p_imgpath']) && trim($value['p_imgpath']) == "") {
            $value_format['p_imgpath'] = 'default/avatar.png';
        }
        return $value_format;
    }


    /**
     * 取消显示的字段
     * @param array        $data         数据
     * @param string|array $unsetFields  要取消的字段
     * @param array        $unsetFields2 要取消的字段
     */
    public function unsetResultValue($data, $unsetFields = "", $unsetFields2 = [])
    {
        $unsetFields_default = [
            'start_lat', 'start_lng', 'start_gid', 'startname', 'startpid', 'end_lat', 'end_lng', 'end_gid',
            'endname', 'endpid', 'passengerid', 'carownid', 'passenger_id', 'driver_id'
        ];
        if (is_string($unsetFields) && $unsetFields == "list") {
            $unsetFields = [
                'p_companyname', 'd_companyname', 'p_company_id', 'd_company_id', 'p_department_id',
                'd_department_id', 'like_count'
                // ,'d_im_id','p_im_id'
                // ,'start_longitude','start_latitude'
                , 'start_addressid'
                // ,'end_longitude','end_latitude'
                , 'end_addressid'
            ];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_string($unsetFields) && ($unsetFields == "" || $unsetFields == "detail")) {
            $unsetFields = [];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_string($unsetFields) && $unsetFields == "detail_pb") {
            $unsetFields = [
                'p_companyname', 'd_companyname', 'd_im_id', 'p_im_id', 'd_phone', 'd_mobile', 'd_full_department', 'd_company_id', 'd_department_id', 'd_department', 'p_phone', 'p_mobile', 'p_full_department', 'p_company_id', 'p_department_id', 'p_department', 'start_addressid', 'end_addressid'
            ];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_array($unsetFields)) {
            foreach ($unsetFields as $key => $value) {
                unset($data[$value]);
            }
        }
        return $data;
    }


    /**
     * 推送消息
     * @param  Integer $uid      [对方id]
     * @param  String  $message  [发送的内容]
     * @param  Array  $content   [要透传的数据]
     */
    public function pushMsg($uid, $message, $content = null)
    {
        if (!$uid || !$message) {
            return false;
        }
        $PushMessage = new PushMessage();
        if (is_array($uid)) {
            $res = [];
            foreach ($uid as $key => $value) {
                if (is_numeric($value)) {
                    $res[] = $PushMessage->add($value, $message, "拼车", $content, 101, 101, 0);
                    // $res[] = $PushMessage->add($value, $message, lang("Car pooling"), 101,101, 0);
                    // $PushMessage->push($value,$message,lang("Car pooling"),2);
                }
            }
        } elseif (is_numeric($uid)) {
            $res = $PushMessage->add($uid, $message, "拼车", $content, 101, 101, 0);
            // $res = $PushMessage->add($uid, $message, lang("Car pooling"), 101,101, 0);
            // $PushMessage->push($uid,$message,lang("Car pooling"),2);
        } else {
            return false;
        }
        // try {
        //     $pushRes = $PushMessage->push($uid, $message, lang("Car pooling"), $appid);
        //     $this->errorMsg = $pushRes;
        // } catch (\Exception $e) {
        //     $this->errorMsg = $e->getMessage();
        //     $pushRes =  $e->getMessage();
        // }
        return $res;
    }


    /**
     * 创建起终点
     */
    public function createAddress($datas, $userData)
    {
        $createAddress = [];
        //处理起点
        if ((!isset($datas['start']['addressid']) || !(is_numeric($datas['start']['addressid']) && $datas['start']['addressid'] > 0))) {
            $startDatas = $datas['start'];
            $startRes = $this->createOneAddress($startDatas, $userData);
            if (!$startRes) {
                return $this->error(-1, lang("The point of departure must not be empty"));
            }
            $createAddress['start'] = $startRes;
        }

        //处理终点
        if ((!isset($datas['end']['addressid']) || !(is_numeric($datas['end']['addressid']) && $datas['end']['addressid'] > 0))) {
            $endDatas = $datas['end'];
            $endRes = $this->createOneAddress($endDatas, $userData);
            if (!$endRes) {
                return $this->error(-1, lang("The destination cannot be empty"));
            }
            $createAddress['end'] = $endRes;
        }
        return $createAddress;
    }

    /**
     * 创建一个站点
     */
    public function createOneAddress($addressDatas, $userData)
    {
        $AddressModel = new Address();
        $addressDatas['company_id'] = $userData['company_id'];
        $addressDatas['create_uid'] = $userData['uid'];
        $addressRes = $AddressModel->addFromTrips($addressDatas);
        if (!$addressRes) {
            return $this->error(-1, lang("The adress must not be empty"));
        }
        // $addressDatas['addressid'] = $addressRes['addressid'];
        return $addressRes;
    }


    public function createTripBaseData($datas, $mapType)
    {
        $start_longitude = $datas['start']['longitude'];
        $start_latitude = $datas['start']['latitude'];
        $end_longitude = $datas['end']['longitude'];
        $end_latitude = $datas['end']['latitude'];
        $inputData = [
            'status'    => 0,
            'subtime'   => date('YmdHi'),
            'time'      => $datas['time'],
            'type'      => isset($datas['type']) ? $datas['type'] : 0,
            'map_type'  => $mapType,
            'distance'  => $datas['distance'],
            'startpid'  => $mapType ? (isset($datas['start']['gid']) &&  $datas['start']['gid'] ? -1 : 0) : $datas['start']['addressid'],
            'endpid'  => $mapType ? (isset($datas['end']['gid']) &&  $datas['end']['gid'] ? -1 : 0) : $datas['end']['addressid'],
            'startname'  => $datas['start']['addressname'],
            'start_latlng'  => Db::raw("geomfromtext('point(" . $start_longitude . " " . $start_latitude . ")')"),
            'endname'  => $datas['end']['addressname'],
            'end_latlng'  => Db::raw("geomfromtext('point(" . $end_longitude . " " . $end_latitude . ")')"),
        ];
        if ($mapType) {
            if (isset($datas['start']['gid']) &&  $datas['start']['gid']) {
                $inputData['start_gid'] = $datas['start']['gid'];
            }
            if (isset($datas['end']['gid']) &&  $datas['end']['gid']) {
                $inputData['end_gid'] = $datas['end']['gid'];
            }
        }
        return $inputData;
    }

    /**
     * 创建查询wall表和info表联合的sql
     */
    public function buildUnionSql($userData, $statusSet, $type = 0)
    {
        $InfoModel = new InfoModel();
        $extra_info = json_decode($userData['extra_info'], true);
        $uid = $userData['uid'];
        $merge_ids  = isset($extra_info['merge_id']) && is_array($extra_info['merge_id']) && $type == 1  ? $extra_info['merge_id'] : [];
        $viewSql    = $InfoModel->buildUnionSql($uid, $merge_ids, $statusSet);
        return $viewSql;
    }

    /**
     * 创建查询的字段
     */
    public function buildQueryFields($type = 'all', $alias = 't')
    {
        $fields = "{$alias}.time, {$alias}.status , {$alias}.subtime";
        $fields .= $type == 'all' ? ", {$alias}.infoid , {$alias}.love_wall_ID ,  {$alias}.trip_type ,  {$alias}.passengerid, {$alias}.carownid ,  {$alias}.seat_count,   {$alias}.map_type " : '';

        $fields .= $type == 'wall_list'   ?   ", {$alias}.seat_count" : '';
        $fields .= $type == 'wall_list'   ?   ", {$alias}.love_wall_ID as id,   {$alias}.carownid as driver_id " : '';

        $fields .= $type == 'info_list'   ?   ", {$alias}.infoid as id, {$alias}.love_wall_ID ,  {$alias}.carownid as driver_id , {$alias}.passengerid as passenger_id " : '';

        $fields .= $type == 'wall_detail' ?   ", {$alias}.seat_count, {$alias}.map_type, {$alias}.im_tid, {$alias}.im_chat_tid" : '';
        $fields .= $type == 'wall_detail' ?   ", {$alias}.love_wall_ID as id ,{$alias}.love_wall_ID ,{$alias}.carownid as driver_id " : '';

        $fields .= $type == 'info_detail' ?   ", {$alias}.map_type" : '';
        $fields .= $type == 'info_detail' ?   ", {$alias}.infoid as id, {$alias}.infoid , {$alias}.love_wall_ID , {$alias}.subtime , {$alias}.carownid as driver_id, {$alias}.passengerid as passenger_id " : '';


        if (in_array($type, ['all', 'wall_list', 'wall_detail', 'info_detail'])) {
            $fields .= ',' . $this->buildUserFields('d');
            $fields .= ', dd.fullname as d_full_department';
        }

        if (in_array($type, ['all', 'info_list', 'info_detail'])) {
            $fields .= ',' . $this->buildUserFields('p');
            $fields .= ', pd.fullname as p_full_department';
        }

        $fields .=  $this->buildAddressFields();
        return $fields;
    }

    /**
     * 取得能相互拼车的Company_id
     */
    public function getCompanyIds($company_id)
    {
        $ConfigsModel = new ConfigsModel();
        $groups = $ConfigsModel->getConfig('trip_company_group', true);
        if ($groups && is_array($groups)) {
            foreach ($groups as $k => $v) {
                if (is_array($v) && in_array($company_id, $v)) {
                    return $v;
                }
            }
        }
        return $company_id;
    }
    /**
     * 创建拼车列表以公司id的筛选map
     *
     * @param Number||Array $userData 用户数据或者公司id
     * @param string $prefix
     * @return void
     */
    public function buildCompanyMap($userData, $prefix = 't')
    {
        $company_id = is_numeric($userData) ? $userData : $userData['company_id'];
        $company_id = $this->getCompanyIds($company_id);
        if (is_array($company_id)) {
            return [$prefix . '.company_id', 'in', $company_id];
        }
        return [$prefix . '.company_id', '=', $company_id];
    }

    public function buildListCacheKey($data)
    {
        $from = $data['from'] ?? '';
        $company_ids = $data['company_ids'] ?? false;
        $company_id = $data['company_id'] ?? false;
        $page = $data['page'] ?? 1;
        $pagesize = $data['pagesize'] ?? 0;
        $city = $data['city'] ?? 'all';
        $map_type = $data['map_type'];

        if (!$map_type) {
            return false;
        }
        if (empty($company_ids)) {
            if (!$company_id) {
                return false;
            }
            $company_ids = $this->getCompanyIds($company_id);
            $company_ids = is_array($company_ids) ? implode(',', $company_ids) : $company_ids;
        }
        $cacheKey = "carpool:trips:{$from}_list:companyId_{$company_ids}:city_{$city}:pz_{$pagesize}:p_{$page}";
        return $cacheKey;
    }

    /**
     * 删除我的缓存
     *
     * @param integer|array $uid 用户uid
     * @return void
     */
    public function removeCache($uid, $userData = null, $dataStart = null)
    {
        if (is_array($uid)) {
            $uid = array_unique($uid);
            foreach ($uid as $v) {
                $this->removeCache($v);
            }
        } elseif (is_numeric($uid)) {
            $redis = new RedisData();
            // $userData = $this->getUserData(1);
            $userModel = new UserModel();
            $userData = $userModel->findByUid($uid);
            $company_id = $userData['company_id'];
            $company_ids = $this->getCompanyIds($company_id);
            $company_ids = is_array($company_ids) ? implode(',', $company_ids) : $company_ids;

            $cacheKey_01 = $this->cacheKey_myInfo . "u{$uid}";
            $cacheKey_02 = $this->cacheKey_myTrip . "u{$uid}";
            $cacheKey_03 = "carpool:citys:company_id_$company_id:type_1";
            $cacheKey_04 = "carpool:citys:company_id_$company_id:type_2";

            $redis->del($cacheKey_01, $cacheKey_02, $cacheKey_03, $cacheKey_04);

            /** 删除空座位列表缓存 */
            $cacheKeyData_n = [
                'from' => 'wall',
                'page' => 1,
                'pagesize' => 20,
                'company_id' => $company_id,
                'map_type' => -1,
                'city' => $dataStart['city'] ?? 'all',
            ];
            
            $cacheKeyData_0 = $cacheKeyData_n;
            $cacheKeyData_0['map_type'] = 0;
            $cacheKeyData_1 = $cacheKeyData_n;
            $cacheKeyData_1['map_type'] = 1;
            $cacheKeyData_n_1 = $cacheKeyData_n;
            $cacheKeyData_n_1['city'] = 'all';
            $cacheKeyData_0_1 = $cacheKeyData_0;
            $cacheKeyData_0_1['city'] = 'all';
            $cacheKeyData_1_1 = $cacheKeyData_1;
            $cacheKeyData_1_1['city'] = 'all';
            $listCacheKey_n = $this->buildListCacheKey($cacheKeyData_n);
            $listCacheKey_0 = $this->buildListCacheKey($cacheKeyData_0);
            $listCacheKey_1 = $this->buildListCacheKey($cacheKeyData_1);
            $listCacheKey_n_1 = $this->buildListCacheKey($cacheKeyData_n_1);
            $listCacheKey_0_1 = $this->buildListCacheKey($cacheKeyData_0_1);
            $listCacheKey_1_1 = $this->buildListCacheKey($cacheKeyData_1_1);
            $redis->del($listCacheKey_n, $listCacheKey_0, $listCacheKey_1, $listCacheKey_0_1, $listCacheKey_1_1, $listCacheKey_n_1);

            $cacheKey_mapcars_n = "carpool:trips:mapCars:mapType_-1:company_{$company_ids}";
            $cacheKey_mapcars_0 = "carpool:trips:mapCars:mapType_0:company_{$company_ids}";
            $cacheKey_mapcars_1 = "carpool:trips:mapCars:mapType_1:company_{$company_ids}";
            $redis->del($cacheKey_mapcars_n, $cacheKey_mapcars_0, $cacheKey_mapcars_1);
        }
    }

    /**
     * 通过时间范围取得合并的数据内容
     *
     * @param  integer     $time       出发时间的时间戳
     * @param  integer     $uid        发布者ID
     * @param  string      $offsetTime 时间偏差范围
     *
     */
    public function getUnionListByTimeOffset($time, $uid, $offsetTime = 60 * 30)
    {
        $startTime = $time - $offsetTime;
        $endTime =   $time + $offsetTime;
        $map = [
            ["status", "in", [0,1,4]],
            ["time", ">=", date('YmdHi', $startTime)],
            ["time", "<=", date('YmdHi', $endTime)],
            // ["go_time",">=",$startTime],
            // ["go_time","<=",$endTime],
        ];
        $map1 = $map;
        $map1[] = ["carownid", "=", $uid];
        $map2 = $map;
        $map2[] = ["carownid|passengerid", "=", $uid];

        $res_wall = WallModel::where($map1)->select();
        $res_info = InfoModel::where($map2)->select();
        $res = [];
        if ($res_wall) {
            foreach ($res_wall as $key => $value) {
                $data = [
                    'from'=>'wall',
                    'id'  => $value['love_wall_ID'],
                    'time'=>strtotime($value['time'] . '00'),
                    'user_type' => 1,
                    'love_wall_ID' => $value['love_wall_ID'],
                    'info_id' => 0,
                    'd_uid' => $value['carownid'],
                    'p_uid' => 0,
                ];
                $res[] = $data;
            }
        }
        
        if ($res_info) {
            foreach ($res_info as $key => $value) {
                $data = [
                    'from'=>'info',
                    'id' => $value['infoid'],
                    'time'=>strtotime($value['time'] . '00'),
                    'user_type' => $value['carownid'] > 0 && $value['carownid'] == $uid ? 1 : 0,
                    'love_wall_ID' => $value['love_wall_ID'],
                    'info_id' => $value['infoid'],
                    'd_uid' => $value['carownid'],
                    'p_uid' => $value['passengerid'],
                ];
                $res[] = $data;
            }
        }
        return $res;
    }

    public function checkRepetitionByList($list, $time, $itemOffset, $level = [[60*10,10],[60*30,20]])
    {
        $level_checked = [];
        $level_timeList = [];
        $level_dataList = [];
        $errorMsg = '';

        foreach ($level as $k => $levelItem) {
            $itemOffset = is_array($levelItem) ? $levelItem[0] : $levelItem;
            $existCount = 0;
            $timeList = [];
            $dataList = [];
            foreach ($list as $key => $value) {
                $time_s = $time - $itemOffset;
                $time_e = $time + $itemOffset;
                if ($value['time'] > $time_s && $value['time'] < $time_e) {
                    $existCount += 1;
                    $timeList[] = $value['time'];
                    $dataList[] = $value;
                }
            }
            rsort($timeList);
            $level_checked[$k] = $existCount;
            $level_timeList[$k] = $timeList;
            $level_dataList[$k] = $dataList;
            if ($existCount > $k) {
                if ($k > 0) {
                    $min = is_array($levelItem) && isset($levelItem[1]) ? $levelItem[1] : intval($itemOffset/60) ;
                    $errorMsg = lang("You have multiple trips in {:time} minutes, please do not post in a similar time", ["time" => $min]);
                } else {
                    $returnTime = date('Y-m-d H:i', $timeList[0]);
                    $errorMsg = lang("You have already made one trip at {:time}, please do not post in a similar time", ["time" => $returnTime]);
                }
                break;
            }
        }
        if (empty($errorMsg)) {
            return false;
        }
        $this->error(30007, $errorMsg);
        return $level_dataList;
    }

    /**
     * 发布行程时检查行程是否有重复
     * @param  integer     $time       出发时间的时间戳
     * @param  integer     $uid        发布者ID
     * @param  string      $offsetTime 时间偏差范围
     */
    public function checkRepetition($time, $uid, $offsetTime = 60 * 30, $level = [[60*10,10],[60*30,20]])
    {
        $res = $this->getUnionListByTimeOffset($time, $uid, $offsetTime);
        if (empty($res)) {
            return false;
        }
        return $this->checkRepetitionByList($res, $time, $offsetTime, $level);
    }

    /**
     * 发布行程时检查行程是否有重复 (包含ShuttleTrip, love_wall , info)
     * @param  integer     $time       timestamp 出发时间的时间戳
     * @param  integer     $uid        发布者ID
     * @param  string      $offsetTime 时间偏差范围
     */
    public function getRepetition($time, $uid, $offsetTime = 60 * 30, $level = [[60*10,10],[60*30,20]])
    {
        $ShuttleTripModel = new ShuttleTripModel();
        $list_trip = $ShuttleTripModel->getListByTimeOffset($time, $uid, $offsetTime);
        $list_wallinfo = $this->getUnionListByTimeOffset($time, $uid, $offsetTime);
        $list = array_merge($list_trip, $list_wallinfo);
        return $this->checkRepetitionByList($list, $time, $offsetTime, $level);
    }
}
