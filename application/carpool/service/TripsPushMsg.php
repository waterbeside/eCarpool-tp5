<?php
namespace app\carpool\service;

use app\common\service\Service;
use app\carpool\service\Trips as TripsService;
use think\Db;

class TripsPushMsg extends Service
{
    /**
     * pushMsg 操作后推送消息;
     */
    public function pushMsg($sendTarget, $data)
    {
        
        $runType = mb_strtolower($data['runType']);
        $from = mb_strtolower($data['from']);
        $id = $data['id'];
        $isDriver = $data['isDriver'] ?? 0;
        $userData = $data['userData'] ?? null;
        $tripData = $data['tripData'] ?? null;
        $id = $id ?: ($tripData && $tripData['id'] ? $tripData['id'] : 0);
        if (empty($id)) {
            return false;
        }
        $content = [
            'code' => 101,
            'data' => [
                'object_id' => $id,
                'from' => $from,
            ]
        ];

        if ($runType == "cancel") { // 取消行程
            if ($isDriver) { // 如果是司机，则推给乘客
                // $push_msg = lang("The driver {:name} cancelled the trip", ["name"=>$userData['name']]) ;
                $push_msg = "司机" . $userData['name'] . "取消了行程";
            } else { //如果乘客取消，则推送给司机
                // $push_msg = lang("The passenger {:name} cancelled the trip", ["name"=>$userData['name']]) ;
                $push_msg = "乘客" . $userData['name'] . "取消了行程";
            }
        } elseif ($runType == 'get_on') { // 上车 （暂没用）
            // $push_msg = lang("The passenger {:name} has got on your car", ["name"=>$userData['name']]) ;
            $push_msg = '乘客' . $userData['name'] . '上了你的车';
        } elseif ($runType == "riding" || $runType == "hitchhiking") { // 搭车
            // $push_msg = lang('{:name} took your car', ["name"=>$userData['name']]);
            $push_msg = $userData['name'] . '搭了你的车';
        } elseif ($runType == "pickup") { // 搭客
            // $push_msg = lang('{:name} accepted your ride requst', ["name"=>$userData['name']]);
            $push_msg = $userData['name'] . '接受了你的约车需求';
        } elseif ($runType == "pickup_partner") { // 搭同行者
            $push_msg = $userData['name'] . '接受了你的约车需求';
            // $push_msg = "你搭上了{$userData['name']}的车";
        } elseif ($runType == "partner_save") { // 添加同行者
            $push_msg = "您成功加入到{$userData['name']}的约车需求";
            // $push_msg = "你作为同行伙伴加入了{$userData['name']}的约车需求";
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
}
