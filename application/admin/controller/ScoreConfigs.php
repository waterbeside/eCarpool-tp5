<?php

namespace app\admin\controller;

use app\score\model\Configs as ScoreConfigsModel;
use app\admin\controller\AdminBase;
use app\common\model\Configs as ConfigsModel;
use my\RedisData;
use think\Db;

/**
 * 积分配置设置
 * Class ScoreConfigs
 * @package app\admin\controller
 */
class ScoreConfigs extends AdminBase
{

    public $check_dept_setting = [
        "action" => ['index', 'awards']
    ];

    /**
     *
     */

    public function index($filter = null)
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


        if (isset($filter['name']) && $filter['name']) {
            $map[] = ['t.name', '=', $filter['name']];
        }
        if (isset($filter['is_hidden']) && is_numeric($filter['is_hidden']) && $filter['is_hidden'] !== 0) {
            $is_delete = $filter['is_hidden'] ? Db::raw(1) : Db::raw(0);
            $map[] = ['t.is_delete', '=', $is_delete];
        }

        $resList = ScoreConfigsModel::alias('t')->field($fields)->join($join)->where($map)->order('d.id')->select();

        $returnData =  [
            'lists' => $resList,
            'filter' => $filter,
            'authDoc' => $this->checkActionAuth('admin/Score/doc')
        ];

        return $this->fetch('index', $returnData);
    }

    /**
     * 转盘抽奖奖项
     * @return mixed
     */
    public function awards($region_id = 0)
    {
        if ($this->request->isPost()) {
            $value = $this->request->post('value');
            $value_array = json_decode($value, true);
            if (!is_numeric($region_id)) {
                $this->error(lang("Please select a region or department"));
            }

            $data_used  = [];
            $data_used_count = 0;
            $data_used_keys = [];
            $total_rate = 0;


            foreach ($value_array as $key => $v) {
                $v['rate'] = strval($v['rate']);
                $value_array[$key]['rate'] = strval($v['rate']);
                if (isset($v['status']) && $v['status'] == 1) {
                    if (!in_array($v['grade'], $data_used_keys)) {
                        $data_used[strval($v['grade'])] = $v;
                        $data_used_keys[] = $v['grade'];
                        $data_used_count++;
                    }
                    $total_rate = $total_rate + $v['rate'];
                }
                // code...
            }
            if ($total_rate > 1) {
                $this->error("总概率不得大于1");
            }

            array_multisort(array_column($data_used, 'grade'), SORT_ASC, $data_used);
            $data_used_keys = [];
            $data_used_kv = [];
            foreach ($data_used as $key => $v) {
                $v['level'] = $key + 1;
                $data_used_keys[] = $v['grade'];
                $data_used_kv[$v['grade']] = $v;
            }
            foreach ($value_array as $key => $v) {
                $value_array[$key]['full_desc'] = !isset($v['full_desc']) || empty(trim($v['full_desc'])) ? $v['desc'] : trim($v['full_desc']);
                if (isset($v['status']) && $v['status'] == 1 && isset($data_used_kv[$v['grade']])) {
                    $value_array[$key]['level'] = $data_used_kv[$v['grade']]['level'];
                } else {
                    if (isset($value_array[$key]['level'])) {
                        unset($value_array[$key]['level']);
                    }
                    $value_array[$key]['status']  = 0;
                }
                $value_array[$key]['bulletin_count'] = isset($v['bulletin_count']) && $v['bulletin_count'] > 0 ? $v['bulletin_count'] : 0;
                $value_array[$key]['is_bulletin'] = isset($v['is_bulletin'])  && $v['is_bulletin'] ? $v['is_bulletin'] : 0;
                $value_array[$key]['is_disused'] = isset($v['status'])  && $v['status'] ? 0 : 1;
            }
            $value = json_encode($value_array);
            $value_public = json_encode($data_used_kv);
            // dump($value);


            // $value = json_encode();

            $map = [
                ['name', '=', 'awards'],
                ['p_region_id', '=', $region_id],
            ];
            $ScoreConfigsModel = new ScoreConfigsModel();
            $res = $ScoreConfigsModel->where($map)->find();
            $updataData = [
                'p_region_id' => $region_id,
                'name' => 'awards',
                'value' => $value,
                'title' => '积分抽奖奖项列表',
            ];
            if ($res && $res['id']) {
                $map[] = ['id', "=", $res['id']];
                $updateRes = $ScoreConfigsModel->where($map)->update($updataData);
                $updateid =  $updateRes !== false ? $res['id'] : false;
            } else {
                $updateid = $ScoreConfigsModel->insertGetId($updataData);
            }
            // $res = ScoreConfigsModel::where($map)->setField('value', $value);

            if ($res !== false) {
                $redis = RedisData::getInstance();
                $redis->del("score:configs:awards:" . $region_id);
                // $redis->set("score:configs:awards",$value_public); 不由后台生成
                $this->log('更新转盘抽奖奖项成功', 0);
                $this->success("更新成功");
            } else {
                $this->log('更新转盘抽奖奖项失败', -1);
                $this->error("更新失败");
            }
        } else {
            $region_id =  $this->request->param('region_id', 1);

            //地区排查 检查管理员管辖的地区部门
            $authDeptData = $this->authDeptData;
            // dump($authDeptData);exit;
            if (empty($authDeptData['allow_region_ids']) && !$this->userBaseInfo['auth_depts_isAll']) {
                $region_id = $authDeptData['filter_region_ids'][0];
            }
            $regionData = isset($authDeptData['filter_region_datas'][0]) ? $authDeptData['filter_region_datas'][0] : null;
            // if(is_numeric($region_id)){
            //   $regionData = $this->getDepartmentById($region_id);
            // }
            $map = [
                ['name', '=', 'awards'],
                ['p_region_id', '=', $region_id],
            ];
            $res = ScoreConfigsModel::where($map)->column('value');
            $lists = [];
            if ($res) {
                $lists = json_decode($res[0], true);
            }
            foreach ($lists as $key => $value) {
                if (isset($lists[$key]['level'])) {
                    unset($lists[$key]['level']);
                }
            }

            $returnData =  [
                'regionData' => isset($regionData) ? $regionData : null,
                'region_id' => $region_id,
                'lists' => $lists,
            ];

            return $this->fetch('awards', $returnData);
        }
    }

    /**
     * 彻底删除用户
     * @param $id
     */
    public function detail($id)
    {

        $ScoreConfigsModel = new ScoreConfigsModel();
        $res = $ScoreConfigsModel->find($id);
        if (!$res) {
            return $this->error('查无数据');
        }
        $returnData =  [
            'data' => $res,
        ];
        return $this->fetch('detail', $returnData);
    }

    /**
     * 彻底删除用户
     * @param $id
     */
    public function delete($id)
    {

        $ScoreConfigsModel = new ScoreConfigsModel();
        $res = $ScoreConfigsModel->find($id);
        if (!$res) {
            return $this->jsonReturn(-1, '删除失败，数据不存在或已删除');
        }

        if (is_numeric($res['p_region_id'])  && in_array($res['p_region_id'], [0, 1])) {
            return $this->jsonReturn(-1, '总地区配置项不能被删除');
        }

        $this->checkDeptAuthByDid($res['p_region_id'], 1); //检查地区权限


        if ($ScoreConfigsModel->destroy($id)) {
            $this->log('删除积分配置成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除积分配置失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }
}
