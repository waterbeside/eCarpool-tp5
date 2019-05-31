<?php
namespace app\carpool\service;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\user as UserModel;
use app\common\model\PushMessage;
use app\user\model\Department as DepartmentModel;

class Trips
{


     /**
     * 创件状态筛选map
     */
    public function buildStatusMap($status, $t="t")
    {
        $statusExp = '=';
        if (is_string($status) && strpos($status, '|')) {
            $statusArray = explode('|', $status);
            if (count($statusArray)>1) {
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
        if (in_array(mb_strtolower($statusExp), ['=','<=','>=','<','>','<>','in','eq','neq','not in','lt','gt','egt','elt'])) {
            return [$t.'.status',$statusExp,$status];
        } else {
            return [$t.'.status',"=",$status];
        }
    }


    /**
     * 创件要select的用户字段
     */
    public function buildUserFields($a="u", $fields=[])
    {
        $format_array = [];
        $fields = !empty($fields) ? $fields : ['uid','loginname','name','phone','mobile','Department','sex','company_id','department_id','companyname','imgpath','carnumber','carcolor','im_id'];

        foreach ($fields as $key => $value) {
            $format_array[$key] = $a.".".$value." as ".$a."_".mb_strtolower($value);
        }
        return join(",", $format_array);
    }

    /**
     * 创件要select的地址字段
     */
    public function buildAddressFields($fields="", $start_latlng = false)
    {
        $fields .= ',t.startpid, t.endpid';
        $fields .= ', x(t.start_latlng) as start_lng, y(t.start_latlng) as start_lat' ;
        $fields .= ', x(t.end_latlng) as end_lng, y(t.end_latlng) as end_lat' ;
        $fields .= ', t.startname , t.start_gid ';
        $fields .= ', t.endname , t.end_gid ';
        $fields .= ',s.addressname as start_addressname, s.latitude as start_latitude, s.longtitude as start_longitude';
        $fields .= ',e.addressname as end_addressname, e.latitude as end_latitude, e.longtitude as end_longitude';
        return $fields;
    }


    /**
     * 创健要join的表的数缓
     * @param  string|array $filter
     * @return array
     */
    public function buildTripJoins($filter="d,p,s,e,department")
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
                $join[] = ['address s','s.addressid = t.startpid', 'left'];
            }
            if (in_array('e', $filter) || in_array('end', $filter)) {
                $join[] = ['address e','e.addressid = t.endpid', 'left'];
            }
            if (in_array('d', $filter) || in_array('driver', $filter)) {
                $join[] = ['user d','d.uid = t.carownid', 'left'];
                if (in_array('department', $filter)) {
                    $join[] = ['t_department dd','dd.id = d.department_id', 'left'];
                }
            }
            if (in_array('p', $filter) || in_array('passenger', $filter)) {
                $join[] = ['user p','p.uid = t.passengerid', 'left'];
                if (in_array('department', $filter)) {
                    $join[] = ['t_department pd','pd.id = p.department_id', 'left'];
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
        $value_format['subtime'] = intval(strtotime($value['subtime'])) ;
        // $value_format['go_time'] = $value['go_time'] ?  $value['go_time'] : strtotime($value['time']."00");
        // $value_format['time'] = date('Y-m-d H:i',strtotime($value['time'].'00'));
        // $value_format['time'] = $value_format['go_time'];
        $value_format['time'] = intval(strtotime($value['time'].'00')) ;
        //整理指定字段为整型
        $int_field_array = [
          'p_uid','p_sex','p_company_id','p_department_id',
          'd_uid','d_sex','d_company_id','d_department_id',
          'infoid','love_wall_ID',
          'startpid','endpid',
          'seat_count','trip_type'
        ];
        $value = json_decode(json_encode($value),true);
        foreach ($value as $key => $v) {
           if(in_array($key,$int_field_array)){
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

        if (isset($value['d_full_department']) || isset($value['p_full_department']) ) {
            $DepartmentModel = new DepartmentModel();
        }
        if (isset($value['d_full_department'])) {
            $value_format['d_department'] = $DepartmentModel->formatFullName($value['d_full_department'], 3);
        }
        if (isset($value['p_full_department'])) {
            $value_format['p_department'] = $DepartmentModel->formatFullName($value['p_full_department'], 3);
        }
        if (isset($value['d_imgpath']) && trim($value['d_imgpath'])=="") {
            $value_format['d_imgpath'] = 'default/avatar.png';
        }
        if (isset($value['p_imgpath']) && trim($value['p_imgpath'])=="") {
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
           'start_lat','start_lng','start_gid','startname','startpid'
          , 'end_lat','end_lng','end_gid','endname','endpid'
          ,'passengerid','carownid','passenger_id','driver_id'
        ];
        if (is_string($unsetFields) && $unsetFields=="list") {
            $unsetFields = [
              'p_companyname','d_companyname'
              ,'p_company_id','d_company_id'
              ,'p_department_id','d_department_id'
              ,'p_sex','d_sex'
              ,'like_count'
              // ,'d_im_id','p_im_id'
              // ,'start_longitude','start_latitude'
              ,'start_addressid'
              // ,'end_longitude','end_latitude'
              ,'end_addressid'
            ];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_string($unsetFields) && ($unsetFields=="" ||$unsetFields=="detail")) {
            $unsetFields = [];
            $unsetFields = array_merge($unsetFields_default, $unsetFields);
        }
        if (is_string($unsetFields) && $unsetFields=="detail_pb") {
            $unsetFields = [
              'p_companyname','d_companyname','d_im_id','p_im_id'
              ,'d_phone','d_mobile','d_full_department','d_company_id','d_department_id','d_department'
              ,'p_phone','p_mobile','p_full_department','p_company_id','p_department_id','p_department'
              ,'start_addressid'
              ,'end_addressid'
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
     * 通过id取得用户姓名
     * @param  integer $uid      [对方id]
     * @param  string  $message  [发送的内容]
     */
    public function pushMsg($uid, $message, $appid = 1)
    {
        if (!$uid || !$message) {
            return false;
        }
        $PushMessage = new PushMessage();
        if (is_array($uid)) {
            $res = [];
            foreach ($uid as $key => $value) {
                if (is_numeric($value)) {
                    $res[] = $PushMessage->add($value, $message, "拼车", 101,101, 0);
                    // $res[] = $PushMessage->add($value, $message, lang("Car pooling"), 101,101, 0);
                    // $PushMessage->push($value,$message,lang("Car pooling"),2);
                }
            }
        } elseif (is_numeric($uid)) {
            $res = $PushMessage->add($uid, $message, "拼车", 101,101, 0);
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



}
