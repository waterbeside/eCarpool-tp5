<?php

namespace app\admin\controller;

use app\score\model\Prize as PrizeModel;
use app\admin\controller\AdminBase;
use app\common\model\Configs;
use think\Db;

/**
 * 抽奖管理
 * Class ScorePrize
 * @package app\admin\controller
 */
class ScorePrize extends AdminBase
{
    /**
     * 抽奖列表
     * @return mixed
     */
    public function index($keyword = "", $filter = ['status' => '0,1,2', 'is_hidden' => ''], $page = 1, $pagesize = 20)
    {
        $map = [];
        $fields = "t.*, d.fullname, d.path";
        $join = [
            ['carpool.t_department d', 't.p_region_id = d.id', 'left']
        ];

        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['region_map'])) {
            $map[] = $authDeptData['region_map'];
        }
        // if($region_id){
        //   if(is_numeric($region_id)){
        //     $regionData = $this->getDepartmentById($region_id);
        //   }
        //   $region_map_sql = $this->buildRegionMapSql($region_id);
        //   $map[] = ['','exp', Db::raw($region_map_sql)];
        // }

        $status = input("param.status");
        if ($status !== null) {
            $filter['status'] = $status;
        }

        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $map[] = ['t.status', '=', $filter['status']];
        } else {
            if (strpos($filter['status'], ',') > 0) {
                $map[] = ['t.status', 'in', $filter['status']];
            }
        }
        if (is_numeric($filter['is_hidden']) && $filter['is_hidden'] !== 0) {
            $is_delete = $filter['is_hidden'] ? 1 : 0;
            $map[] = ['is_delete', '=', $filter['is_hidden']];
        }

        if ($keyword) {
            $map[] = ['name|desc', 'like', "%{$keyword}%"];
        }

        $lists = PrizeModel::alias('t')->field($fields)->json(['images'])
            ->join($join)->where($map)
            ->order('id DESC')->paginate($pagesize, false, ['query' => request()->param()]);

        // $lists = PrizeModel::where($map)->json(['images'])->order('id DESC')->fetchSql()->select();
        // dump($lists);exit;
        foreach ($lists as $key => $value) {
            $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "";
        }

        $scoreConfigs = (new Configs())->getConfigs("score");
        $statusList = config('score.prize_status');
        $auth['admin/ScorePrize/add'] = $this->checkActionAuth('admin/ScorePrize/add');
        $returnData =  [
            'lists' => $lists,
            'keyword' => $keyword,
            'pagesize' => $pagesize,
            'statusList' => $statusList,
            'filter' => $filter,
            'scoreConfigs' => $scoreConfigs,
            'auth' => $auth,
        ];


        return $this->fetch('index', $returnData);
    }


    /**
     * 添加抽奖
     */
    public function add()
    {

        if ($this->request->isPost()) {
            $data               = $this->request->post();


            //开始验证字段
            $validate = new \app\score\validate\Prize;
            if (!$validate->check($data)) {
                return $this->jsonReturn(-1, $validate->getError());
            }
            $data['publication_number'] = 1;
            if ($data['identity']) {
                $maxData = $this->checkMaxData($data['identity']);
                $data['publication_number'] = $maxData['max(publication_number)'] ? $maxData['max(publication_number)'] + 1 : 1;
            } else {
                $data['identity'] = uuid_create();
            }
            if (!isset($data['p_region_id']) || !is_numeric($data['p_region_id'])) {
                $this->jsonReturn(-1, "error p_region_id");
            }
            $upData = [
                'p_region_id' => $data['p_region_id'],
                'name' => $data['name'],
                'desc' => $data['desc'],
                'price' => $data['price'],
                'amount' => $data['amount'],
                'level' => $data['level'],
                'identity' => $data['identity'],
                'publication_number' => $data['publication_number'],
                'real_count' => 0,
                'total_count' => $data['total_count'],
                'is_shelves' => isset($data['un_shelves']) &&  isset($data['un_shelves']) == 1 ? 0 : 1,
                'status' =>  in_array($data['status'], [-1, 0, 1, 2]) ? $data['status'] : -1,
                'is_delete' => 0,
                'update_time' => date('Y-m-d H:i:s'),
            ];
            if ($data['thumb'] && trim($data['thumb'])) {
                $upData['images'][0] =  $data['thumb'];
            }
            $id = PrizeModel::json(['images'])->insertGetId($upData);
            if ($id) {
                $this->log('添加抽奖成功，id=' . $id, 0);
                $url = url('admin/ScorePrize/index', ['status' => 'all']);
                if ($data['status'] > -1) {
                    $url = url('admin/ScorePrize/index', ['status' => '0,1,2']);
                } elseif ($data['status'] == -1) {
                    $url = url('admin/ScorePrize/index', ['status' => -1]);
                }
                return $this->success(lang('Save successfully'), $url);
            } else {
                $this->log('添加抽奖失败', -1);
                return $this->jsonReturn(-1, lang('Save failed'));
            }
        } else {
            $prize_status = config('score.prize_status');
            $id           = $this->request->param('id/d', 0);
            $data = [];
            if ($id) {
                $fields = "t.*, d.fullname";
                $join = [
                    ['carpool.t_department d', 't.p_region_id = d.id', 'left']
                ];
                $data = PrizeModel::alias('t')->field($fields)->json(['images'])->join($join)->where("t.id", $id)->find();
                if (!$data) {
                    $this->error(lang('Data does not exist'));
                }
                $this->checkDeptAuthByDid($data['p_region_id'], 1); //检查地区权限
                $maxData = $this->checkMaxData($data['identity']);
                $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "";

                $data['publication_number'] = $maxData['max(publication_number)'] ? $maxData['max(publication_number)'] + 1 : 1;
                // $data['publication_number'] = (PrizeModel::where('identity',$data['identity'])->max('publication_number')) + 1  ;
            }
            return $this->fetch('add', ['data' => $data, 'id' => $id, 'prize_status' => $prize_status]);
        }
    }




    /**
     * 编辑
     * @param  [int] $id 抽奖id
     */
    public function edit($id)
    {
        if (!$id) {
            $this->error("Lost id");
        }


        if ($this->request->isPost()) {
            $prize_data = PrizeModel::where("id", $id)->json(['images'])->find();
            if (!$prize_data) {
                $this->error(lang('Data does not exist'));
            }
            $data               = $this->request->post();
            if (isset($data['p_region_id']) && !is_numeric($data['p_region_id'])) {
                $this->jsonReturn(-1, "error p_region_id");
            }
            if ($prize_data['status'] < -1) {
                $this->error(lang('The lottory has been closed or removed and cannot be modified'));
            }
            if (in_array($prize_data['status'], [-1])) {
                //开始验证字段
                $validate = new \app\score\validate\Prize;
                if (!$validate->check($data)) {
                    return $this->jsonReturn(-1, $validate->getError());
                }
            }

            $upData = [
                'desc' => $data['desc'],
                'amount' => $data['amount'],
                'level' => $data['level'],
                'update_time' => date('Y-m-d H:i:s'),
                'is_shelves' => isset($data['un_shelves']) && $data['un_shelves'] == 1 ? 0 : 1,
            ];
            if (isset($data['p_region_id'])) {
                $upData['p_region_id']  = $data['p_region_id'];
            }

            if ($prize_data['status'] > -1) {
                $inArrayStatus = $prize_data['real_count'] > 0 ? [0, 1, 2] : [-1, 0, 1, 2];
                $upData['status'] = in_array($data['status'], $inArrayStatus) ? $data['status'] : 0;
            } elseif ($prize_data['status'] == -1) {
                $upData['status'] = in_array($data['status'], [-2, -1, 0, 1, 2]) ? $data['status'] : -1;
                $upData['name'] = $data['name'];
                $upData['price'] = $data['price'];
                $upData['total_count'] = $data['total_count'];
            }
            if (in_array($prize_data['status'], [-1, -2])) {
                $upData['is_delete'] = isset($data['is_show']) && $data['is_show'] == 1 ? 0 : 1;
            }
            if (isset($upData['total_count']) && $upData['total_count'] < 1) {
                $this->error(lang('Trigger the lottery ticket value can not be less than 1'));
            }
            if (isset($upData['price']) && !is_float($upData['price']) && !is_numeric($upData['price'])) {
                $this->error(lang('The required points for the lucky draw must be a number'));
            }


            if ($data['thumb'] && trim($data['thumb'])) {
                $upData['images'][0] =  $data['thumb'];
            }

            if (PrizeModel::json(['images'])->where('id', $id)->update($upData) !== false) {
                $this->log('保存抽奖成功，id=' . $id, 0);
                return $this->jsonReturn(0, lang('Save successfully'));
            } else {
                $this->log('保存抽奖失败，id=' . $id, -1);
                return $this->jsonReturn(-1, lang('Save failed'));
            }
        } else {
            $fields = "t.*, d.fullname";
            $join = [
                ['carpool.t_department d', 't.p_region_id = d.id', 'left']
            ];
            $data = PrizeModel::alias('t')->field($fields)->json(['images'])->join($join)->where("t.id", $id)->find();
            if (!$data) {
                $this->error(lang('Data does not exist'));
            }
            $this->checkDeptAuthByDid($data['p_region_id'], 1); //检查地区权限

            $data['is_show'] = $data['is_delete'] ? 0 : 1;
            $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "";

            // $auth['admin/ScorePrize/edit'] = $this->checkActionAuth('admin/ScorePrize/edit');
            $prize_status = config('score.prize_status');

            return $this->fetch('edit', ['data' => $data, 'prize_status' => $prize_status]);
        }
    }

    /**
     */
    public function prizes_unq($page = 1, $keyword = '')
    {
        $pagesize = 20;
        $map = [];
        if ($keyword) {
            $map[] = ['name|desc', 'like', "%{$keyword}%"];
        }
        $lists = PrizeModel::field('identity,max(id)')->group('identity')->where($map)->order('status Desc, id DESC')->select();
    }

    /**
     * 查询奖品最大期数等信息
     * @param  String $identity 奖品标识
     */
    public function checkMaxData($identity)
    {
        if (!$identity) {
            return false;
        }
        $maxData = PrizeModel::field('max(publication_number),max(id),max(status) as max_status,min(status) as min_status ,identity')
            ->group('identity')
            ->where('identity', $identity)
            ->find();
        if ($maxData && $maxData['max_status'] > -2) {
            return $this->error(lang('The latest issue is not over, and the next issue cannot be created'));
        }
        return $maxData;
    }
}
