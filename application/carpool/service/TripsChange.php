<?php

namespace app\carpool\service;

use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\User as UserModel;
use app\common\model\PushMessage;
use app\user\model\Department as DepartmentModel;
use app\carpool\service\Trips as TripsService;
use think\Db;

class TripsChange
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
     * 验证参数数据
     */
    public function checkParamData($data)
    {
        $type = mb_strtolower($data['type']);
        $from = mb_strtolower($data['from']);
        $id = mb_strtolower($data['id']);
        //验证type是否正确
        if (!in_array($type, ['cancel', 'finish', 'riding', 'hitchhiking', 'pickup', 'get_on', 'startaddress', 'endaddress']) || !$id) {
            return $this->error(992, lang('Parameter error'));
        }
        //验证from是否正确
        if (!in_array($from, ['wall', 'info'])) {
            return $this->error(992, lang('Parameter error'));
        }
        return true;
    }

    /**
     * 取得行程数据
     */
    public function getData($from, $id)
    {
        if ($from == "wall") {
            $Model    = new WallModel();
        } elseif ($from == "info") {
            $Model    = new InfoModel();
        } else {
            return $this->error(992, lang('Parameter error'));
        }
        $fields = "*,x(start_latlng) as start_lng , y(start_latlng) as start_lat,x(end_latlng) as end_lng,y(end_latlng) as end_lat";
        $datas = $Model->field($fields)->get($id);
        return $datas;
    }

    /**
     * 取得行程数据后再验证
     */
    public function checkAfterData($data)
    {
        $type = mb_strtolower($data['type']);
        $from = mb_strtolower($data['from']);
        $id = mb_strtolower($data['id']);
        $uid = mb_strtolower($data['uid']);
        $step = $data['step'];
        $tripData = $data['tripData'];
        $map_type = $tripData->map_type;
        $appid      = $map_type ? 2 : 1;
        $isDriver    = $tripData->carownid == $uid ? true : false; //是否司机操作
        $InfoModel    = new InfoModel();
        $WallModel    = new WallModel();

        /*********** 完成或取消或上车 ***********/
        if (in_array($type, ["cancel", "finish", "get_on"])) {
            //检查是否已取消或完成
            if (in_array($tripData->status, [2, 3])) {
                return $this->error(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
            }
            //检查时间
            if ($type == "finish" && strtotime($tripData->time . '00')  > time()) {
                return $this->error(-1, lang('The trip not started, unable to operate'));
            }
            //检查是否允许操作
            if ($from == "info" && !$isDriver && $tripData->passengerid != $uid) {
                return $this->error(-1, lang('No permission'));
            }
            // 断定是否自己上自己车
            if ($type == "get_on" && $isDriver) {
                return $this->error(-1, lang('You can`t take your own'));
            }

            //如果是乘客从空座位操作, 则查出infoid，递归到from = info操作。
            if ($from == "wall" && !$isDriver) {
                $infoDatas = InfoModel::where([["love_wall_ID", '=', $id], ['passengerid', "=", $uid], ['status', 'in', [0, 1, 4]]])
                    ->order("status")
                    ->find();
                if (!$infoDatas) {
                    $checkCount = InfoModel::where([["love_wall_ID", '=', $id], ['passengerid', "=", $uid]])->order("status")->count();
                    if (!$checkCount) {
                        return $this->error(-1, lang('No permission'));
                    } else {
                        return $this->error(-1, lang('The trip has been completed or cancelled. Operation is not allowed'));
                    }
                }
                return $this->error(1, 'Do with info', $infoDatas);
            }
        }

        /*********** riding 搭车  ***********/
        if ($type == "riding" || $type == "hitchhiking") {
            if ($from != "wall") {
                return $this->error(992, lang('Parameter error'));
            }

            if ($tripData->status == 2) {
                return $this->error(-1, lang('Failed, the owner has cancelled the trip'));
                // return $this->error('或车主或已取消空座位，<br />请选择其它司机。');
            }
            if ($tripData->status == 3) {
                return $this->error(-1, lang('Failed, the trip has ended'));
            }
            // 断定是否自己搭自己
            if ($isDriver) {
                return $this->error(-1, lang('You can`t take your own'));
            }
            $TripsService = new TripsService();
            if ($TripsService->checkRepetition(strtotime($tripData->time . '00'), $uid)) {
                return $this->error(30007, $TripsService->errorMsg);
            }

            // //计算前后范围内有没有重复行程
            // if ($InfoModel->checkRepetition(strtotime($tripData->time . '00'), $uid, 60 * 5)) {
            //     return $this->error(30007, $InfoModel->errorMsg);
            // }
            // //计算前后范围内有没有重复行程
            // if ($WallModel->checkRepetition(strtotime($tripData->time . '00'), $uid, 60 * 5)) {
            //     return $this->error(30007, $WallModel->errorMsg);
            // }


            $seat_count = $tripData->seat_count;
            $checkInfoMap = [['love_wall_ID', '=', $id], ['status', '<>', 2]];
            $took_count = InfoModel::where($checkInfoMap)->count();

            if ($took_count >= $seat_count) {
                return $this->error(-1, lang('Failed, seat is full'), [$seat_count, $took_count]);
            }
            $checkInfoMap[] = ['passengerid', '=', $uid];
            $checkHasTake = InfoModel::where($checkInfoMap)->count();
            if ($checkHasTake > 0) {
                return $this->error(-1, lang('You have already taken this trip'));
            }
        }

        /*********** pickup 接受需求  ***********/
        if ($type == "pickup") {
            if ($from != "info") {
                return $this->error(992, lang('Parameter error'));
            }
            // 断定是否自己搭自己
            if ($tripData->passengerid == $uid) {
                return $this->error(-1, lang('You can`t take your own'));
            }
            if ($tripData->status > 0) {
                return $this->error(-1, lang("This requirement has been picked up or cancelled"));
            }
        }

        /*********** startaddress|endaddress 修改起点终点  ***********/
        if (in_array($type, ["startaddress", "endaddress"]) && $from == "info") {
            if ($tripData->passengerid != $uid) {
                return $this->error(-1, lang('No permission'));
            }
        }
        return true;
    }


    /**
     * pushMsg 操作后推送消息;
     */

    public function pushMsg($data)
    {
        $type = mb_strtolower($data['type']);
        $from = mb_strtolower($data['from']);
        $id = mb_strtolower($data['id']);
        $uid = mb_strtolower($data['uid']);
        $userData = $data['userData'];
        $tripData = $data['tripData'];
        $driver_id   = $data['driver_id'];
        $isDriver    = $driver_id == $uid ? true : false; //是否司机操作
        $map_type = $tripData->map_type;
        // $appid      = $map_type ? 2 : 1;
        
        $cnt_object_id = $id;
        $cnt_from = $from;
        if (isset($tripData->love_wall_ID) && $tripData->love_wall_ID > 0) {
            $cnt_object_id = $tripData->love_wall_ID;
            $cnt_from = 'wall';
        }
        
        $content = [
            'code' => 101,
            'data' => [
                'object_id' => $cnt_object_id,
                'from' => $cnt_from,
            ]
        ];

        if ($type == "cancel") {
            if ($isDriver) { // 如果是司机，则推给乘客
                // $push_msg = lang("The driver {:name} cancelled the trip", ["name"=>$userData['name']]) ;
                $push_msg = "司机" . $userData['name'] . "取消了行程";
                $passengerids = $from == "wall" ?
                    InfoModel::where([["love_wall_ID", '=', $id], ["status", "in", [0, 1, 4]]])->column('passengerid') : $tripData->passengerid;
                $sendTarget = $passengerids;
            } else { //如果乘客取消，则推送给司机
                // $push_msg = lang("The passenger {:name} cancelled the trip", ["name"=>$userData['name']]) ;
                if ($driver_id > 0) {
                    $push_msg = "乘客" . $userData['name'] . "取消了行程";
                    $sendTarget = $driver_id;
                }
            }
        } elseif ($type == 'get_on') {
            // $push_msg = lang("The passenger {:name} has got on your car", ["name"=>$userData['name']]) ;
            $push_msg = '乘客' . $userData['name'] . '上了你的车';
            $sendTarget = $driver_id;
        } elseif ($type == "riding" || $type == "hitchhiking") {
            // $push_msg = lang('{:name} took your car', ["name"=>$userData['name']]);
            $push_msg = $userData['name'] . '搭了你的车';
            $sendTarget = $driver_id;
        } elseif ($type == "pickup") {
            // $push_msg = lang('{:name} accepted your ride requst', ["name"=>$userData['name']]);
            $push_msg = $userData['name'] . '接受了你的约车需求';
            $sendTarget = $tripData->passengerid;
        } else {
            return true;
        }

        //执行推送
        if (!isset($sendTarget)) {
            return true;
        }
        $TripsService = new TripsService();
        $TripsService->pushMsg($sendTarget, $push_msg, $content);
        if ($TripsService->errorMsg) {
            return $this->error(-1, $TripsService->errorMsg);
        }
        $this->errorCode = 0;
        $this->errorMsg = '';
        $this->data = ['sendTarget' => $sendTarget];
        return true;
    }

    /**
     * 乘客搭车 执行入表
     */
    public function riding($datas, $uid)
    {
        $setInfoDatas = array(
            'passengerid'   => $uid,
            'carownid'      => $datas->carownid,
            'love_wall_ID'  => $datas->love_wall_ID,
            'subtime'       => date('YmdHi', time()),
            'time'          => $datas->time,
            'startpid'      => $datas->startpid,
            'startname'     => $datas->startname,
            'start_gid'     => $datas->start_gid,
            'endpid'        => $datas->endpid,
            'endname'       => $datas->endname,
            'end_gid'       => $datas->end_gid,
            'type'          => $datas->type,
            'map_type'      => $datas->map_type,
            'status'        => 1,
        );
        if ($datas->start_lat && $datas->start_lng) {
            $setInfoDatas['start_latlng'] = Db::raw("geomfromtext('point(" . $datas->start_lng . " " . $datas->start_lat . ")')");
            $setInfoDatas['end_latlng'] = Db::raw("geomfromtext('point(" . $datas->end_lng . " " . $datas->end_lat . ")')");
        }
        return InfoModel::insertGetId($setInfoDatas);
    }
}
