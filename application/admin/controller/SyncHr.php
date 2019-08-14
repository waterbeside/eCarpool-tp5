<?php

namespace app\admin\controller;

use app\admin\controller\AdminBase;
use app\user\model\UserTemp;

/**
 * 同步hr系统
 * Class SyncHr
 * @package app\api\controller
 */
class SyncHr extends AdminBase
{

    /**
     * 同步用户数据列表
     * @param array $filter 列表过滤参数
     * @param int    $page 页码
     * @param int    $pagesize 每页条数
     * @return mixed
     */
    public function index($filter = [], $page = 1, $pagesize = 50)
    {
        $map = [];
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['code|name', 'like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            $map[] = ['department', 'like', "%{$filter['keyword_dept']}%"];
        }
        //筛选部门
        if (isset($filter['status']) &&  is_numeric($filter['status'])) {
            $map[] = ['status', '=', $filter['status']];
        }
        $UserTemp = new UserTemp();
        $lastTime = $UserTemp->getLastExecuteTime();
        $lists = $UserTemp->alias('u')->where($map)->order('id DESC')->paginate($pagesize, false, ['query' => request()->param()]);

        // dump($lists);exit;
        //
        $port = $this->request->port();
        return $this->fetch('index', ['lastTime' => $lastTime, 'lists' => $lists, 'filter' => $filter, 'pagesize' => $pagesize, 'port' => $port]);
    }



    /**
     * 同步所有用户
     * @param  string  $date     拉取数据的起始日
     * @param  integer $type
     *  $type = 0 仅执行从Hr接口拉到本地临时表（t_user_temp）
     *  $type = 1 仅执行从本地临时表同步到正式表
     *  $type = 2 执行 type = 0后，并执行1. （拉取到临时库，并同步到正式库。数据量大时不建议使用。）
     * @param  integer $page     页码 当type ==1 时有效。设为page > 0时，分页从t_user_temp同步到正式表
     * @param  integer $pagesize 当设有page参数时，设置每页执行多少条数据。
     */
    public function sync_all($date = null, $type = 0, $page = 0, $pagesize = 10, $return = 1)
    {
        ini_set('memory_limit', '128M');
        ini_set('max_execution_time', '180');
        $url = config("others.local_hr_sync_api.all");
        $params = [
            'type' => $type,
            'page' => $page,
            'pagesize' => $pagesize,
        ];
        if ($date) {
            $params['date'] = $date;
        }

        try {
            $client = new \GuzzleHttp\Client(['verify' => false]);
            // $client->setDefaultOption('verify', false);
            $response = $client->request('get', $url, ['query' => $params]);
            $content = $response->getBody()->getContents();
            $res = json_decode($content, true);
        } catch (Exception $e) {
            // $this->errorMsg ='拉取失败';
            $this->error("同步失败");
        }
        if (!$res) {
            $this->error("同步失败");
        }
        if ($res && $res['code'] === 20002) {
            return  $type == 1 && $page > 0 && $return ?
                $this->fetch('index/multi_jump', ['url' => "", 'msg' => $res['desc']]) : $this->jsonReturn($res['code'], $res['data'], $res['desc']);
        }
        if ($res && $res['code'] !== 0) {
            return $this->jsonReturn($res['code'], $res['data'], $res['desc']);
        }

        $data = $res['data'];
        if ($type == 1 && $page > 0  && $return) {
            $jumpUrl  = url('sync_all', ['date' => $date, 'type' => $type, 'page' => $page + 1, 'pagesize' => $pagesize, 'return' => $return]);
            if ($data['total'] > 0) {
                $msg = "total:" . $data['total'] . "<br />";
                $successMsg = "success:<br />";
                foreach (explode(',', $data['success']) as $key => $value) {
                    $br = $key % 2 == 1 ? "<br />" : "";
                    $successMsg .= $value . "," . $br;
                }
                $failMsg = "fail:<br />";
                foreach (explode(',', $data['fail']) as $key => $value) {
                    $br = $key % 2 == 1 ? "<br />" : "";
                    $failMsg .= $value . "," . $br;
                }
                $msg .= $successMsg . "<br />" . $failMsg . "<br />";
                return $this->fetch('index/multi_jump', ['url' => $jumpUrl, 'msg' => $msg]);
            } else {
                return $this->success("同步完成");
            }
        } else {
            return $this->jsonReturn($res['code'], $res['data'], $res['desc']);
        }
    }




    /**
     * 同步单用户接口
     * @param  integer $code    员工号，使用该参数后，会先从HR拉取信息到临时表t_user_temp并马上执行同步到正式表
     * @param  integer $tid      t_user_temp表的行id，使用该参数后，直接从t_user_temp表执行同步到正式表。当使用参数code时，tid参数无效。
     */
    public function sync_single($code = 0, $tid = 0)
    {
        $url = config("others.local_hr_sync_api.single");
        $params = [
            'code' => $code,
            'tid' => $tid,
            'is_sync' => 1,
        ];

        try {
            $res = clientRequest($url, ['query' => $params], 'get');
        } catch (\Exception $e) {
            return  $this->jsonReturn(0, null, '同步失败', ['err' => $e->getMessage()]);
        }
        return $this->jsonReturn($res['code'], $res['data'], $res['desc'], $res['extra']);

        exit;
    }
}
