<?php

namespace app\carpool\service;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\Configs as ConfigsModel;
use app\carpool\model\user as UserModel;
use app\common\model\PushMessage;
use app\user\model\Department as DepartmentModel;
use think\Db;

class Trips
{

    public $errorCode = 0;
    public $errorMsg = '';
    public $data = [];

    protected function error($code, $msg, $data = [])
    {
        $this->errorCode = $code;
        $this->errorMsg = $msg;
        $this->data = $data;
        return false;
    }

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
     */
    public function buildUserFields($a = "u", $fields = [])
    {
        $format_array = [];
        $fields = !empty($fields) ? $fields : ['uid', 'loginname', 'name','nativename', 'phone', 'mobile', 'Department', 'sex', 'company_id', 'department_id', 'companyname', 'imgpath', 'carnumber', 'carcolor', 'im_id'];

        foreach ($fields as $key => $value) {
            $format_array[$key] = $a . "." . $value . " as " . $a . "_" . mb_strtolower($value);
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
            'start_latlng'  => Db::raw("geomfromtext('point(" . $datas['start']['longitude'] . " " . $datas['start']['latitude'] . ")')"),
            'endname'  => $datas['end']['addressname'],
            'end_latlng'  => Db::raw("geomfromtext('point(" . $datas['end']['longitude'] . " " . $datas['end']['latitude'] . ")')"),

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


    public function buildCompanyMap($userData, $prefix = 't')
    {
        $company_id = $userData['company_id'];
        $ConfigsModel = new ConfigsModel();
        $groups = $ConfigsModel->getConfig('trip_company_group', true);
        if ($groups && is_array($groups)) {
            foreach ($groups as $k => $v) {
                if (is_array($v) && in_array($company_id, $v)) {
                    return [$prefix . '.company_id', 'in', $v];
                }
            }
        }
        return [$prefix . '.company_id', '=', $company_id];
    }
}
