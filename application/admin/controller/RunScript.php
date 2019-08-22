<?php

namespace app\admin\controller;

use app\carpool\model\User as UserModel;
use app\admin\controller\AdminBase;
use Firebase\JWT\JWT;
use my\RedisData;
use my\Queue;
use think\Db;

/**
 * RunScript
 * Class RunScript
 * @package app\admin\controller
 */
class RunScript extends AdminBase
{

    protected function initialize()
    {
        // exit;
        parent::initialize();
    }


    /**
     * 更正 score.t_history表的地区部门字段
     *
     * @param integer $page 页码，当为0时，从数据库取出要处理的放到队列中
     * @param string $time 只操作这个时间之后的数据
     * @param integer $requeue 是否重新取数据到队列（1是，0否），仅page = 0时有效。
     * @return void
     */
    public function score_history_regionid_set($page = 0, $time = '2017-01-01', $requeue = 0)
    {
        $HistoryModel = app()->model('\app\score\model\History');
        $queueKey = "common:queue:score_history_regionid_0";
        $Queue = Queue::key($queueKey);

        $msg = "";
        $url = "";

        $startTime = date('Y-m-d H:i:s', strtotime($time));
        $map = [
            ['is_delete', '=', 0],
            ['region_id', '=', 0],
            ['time', '>=', $startTime],
        ];
        $len = $Queue->count();
        if ($page < 1) {
            if ($requeue || $len === 0) {
                $Queue->delete();
                $fields = 'region_id, account_id, carpool_account, min(time) as min_time , max(time) as max_time, count(id) as num';
                $lists = $HistoryModel->field($fields)->where($map)->group('region_id,account_id, carpool_account')->select()->toArray();
                $Queue->pushAll($lists, ['run_start_time' => $startTime]);
                $msg .= "共查" . count($lists) . "条数据入列";
            } else {
                $msg .= "共查" . $len . "条数据入列";
            }
            $url  = url('score_history_regionid_set', ['page' => 1, 'time' => $time]);
            return $this->fetch('index/multi_jump', ['url' => $url, 'msg' => $msg]);
        } else {
            $msg .= "队列剩余" . $len . "条数据";
            $lists = $Queue->pops(50);
        }


        if (count($lists) > 0) {
            $UserModel = new UserModel();
            $ScoreAccountModel = app()->model('\app\score\model\AccountMix');
            foreach ($lists as $key => $value) {
                $msg .= "<br />";
                $upMap = [
                    ['is_delete', '=', 0],
                    ['region_id', '=', 0],
                    ['time', '>=', $value['run_start_time'] ? $value['run_start_time'] : $startTime],
                ];
                if ($value['carpool_account']) {
                    $carpool_account = $value['carpool_account'];
                    $upMap[] = ['carpool_account', '=', $value['carpool_account']];
                } elseif ($value['account_id']) {
                    $carpool_account = $ScoreAccountModel->where('id', $value['account_id'])->value('carpool_account');
                    $upMap[] = ['account_id', '=', $value['account_id']];
                } else {
                    continue;
                }

                $user_data = $UserModel->getDetail($carpool_account);
                if (!$user_data) {
                    $msg .=  "carpool_account:" . $carpool_account . "   no data";
                    continue;
                }
                $upData = [
                    'region_id' => $user_data['department_id'],
                ];

                $res = $HistoryModel::where($upMap)->update($upData);
                if ($res !== false) {
                    $msg .=  "carpool_account:" . $carpool_account . "  OK";
                } else {
                    $msg .=  "carpool_account:" . $carpool_account . "  Failed";
                }
            }
            $page = $page + 1;
            $url  = url('score_history_regionid_set', ['page' => $page]);
        } else {
            $msg .= "完成全部操作";
        }
        return $this->fetch('index/multi_jump', ['url' => $url, 'msg' => $msg]);
    }


    /**
     * 重置jwt表的client字段
     *
     * @param integer $page
     * @param integer $requeue
     * @return void
     */
    public function reset_jwt_client($page = 0, $requeue = 0)
    {
        $JwtModel = app()->model('\app\user\model\JwtToken');

        $queueKey = "common:queue:reset_jwt_client";
        $Queue = Queue::key($queueKey);

        $msg = "";
        $url = "";

        $runTime = date('Y-m-d H:i:s');


        $len = $Queue->count();
        if ($page < 1) {
            if ($requeue || $len === 0) {
                $Queue->delete();
                $fields = 'id, uid, client, iss , token';
                $lists = $JwtModel->field($fields)->select()->toArray();
                $Queue->pushAll($lists, ['run_time' => $runTime]);
                $msg .= "共查" . count($lists) . "条数据入列";
            } else {
                $msg .= "共查" . $len . "条数据入列";
            }
            $url  = url('reset_jwt_client', ['page' => 1, 'time' => $runTime]);
            return $this->fetch('index/multi_jump', ['url' => $url, 'msg' => $msg]);
        } else {
            $msg .= "队列剩余" . $len . "条数据";
            $lists = $Queue->pops(50);
        }

        if (count($lists) > 0) {
            foreach ($lists as $key => $value) {
                $msg .= "<br />";
                $upMap = [
                    ['id', '=', $value['id']],
                ];
                $jwtData = null ;
                $id = $value['id'];
                try {
                    $jwtData = JWT::decode($value['token'], config('secret.front_setting')['jwt_key'], array('HS256'));
                } catch (\Firebase\JWT\SignatureInvalidException $e) {
                }
                
                if ($jwtData) {
                    $upData = [
                        'client' => $jwtData->client == 2 ? 'Android' : ( $jwtData->client == 1 ? 'iOS' : $jwtData->client),
                    ];
                    $res = $JwtModel::where($upMap)->update($upData);
                }
                

                if ($res !== false) {
                    $msg .=  "id:" . $id . "  OK";
                } else {
                    $msg .=  "id:" . $id . "  Failed";
                }
            }
            $page = $page + 1;
            $url  = url('reset_jwt_client', ['page' => $page]);
        } else {
            $msg .= "完成全部操作";
        }
        return $this->fetch('index/multi_jump', ['url' => $url, 'msg' => $msg]);
    }
}
