<?php

namespace app\admin\controller\shuttle;

use app\carpool\model\User as UserModel;
use app\user\model\Department;
use app\carpool\model\ShuttleTime as ShuttleTimeModel;
use app\admin\controller\AdminBase;
use think\facade\Validate;
use think\Db;

/**
 * 班车可选时间管理
 * Class line
 * @package app\admin\controller
 */
class Time extends AdminBase
{
    /**
     *
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter = [], $page = 1, $pagesize = 50)
    {
        $fields = "*";
        $s_hours = isset($filter['s_hours']) && is_numeric($filter['s_hours']) ? $filter['s_hours'] : 0 ;
        $s_minutes = isset($filter['s_minutes']) && is_numeric($filter['s_minutes']) ? $filter['s_minutes'] : -59 ;
        $e_hours = isset($filter['e_hours']) && is_numeric($filter['e_hours']) ? $filter['e_hours'] : 23 ;
        $e_minutes = isset($filter['e_minutes']) && is_numeric($filter['e_minutes']) ? $filter['e_minutes'] : 59 ;
        $map = [
            ['hours', 'between', [intval($s_hours),intval($e_hours)]],
            ['minutes', 'between', [intval($s_minutes),intval($e_minutes)]],
        ];
        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $map[] = ['t.status', '=', $filter['status']];
        }

        if (isset($filter['type']) && is_numeric($filter['type'])) {
            $map[] = ['t.type', '=', $filter['type']];
        }
        //筛选是否被删的
        $is_delete = isset($filter['is_delete']) &&  $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
        $map[] = ['t.is_delete', '=', $is_delete];

        $list = ShuttleTimeModel::alias('t')->field($fields)
            ->where($map)
            ->order('t.hours ASC, t.minutes ASC')
            ->paginate($pagesize, false, ['query' => request()->param()]);
        
        $returnData = [
            'list' => $list,
            'filter' => $filter,
            'pagesize' => $pagesize,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加时刻
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $times = $data['times'];
            if (!is_array($times)) {
                return $this->jsonReturn(992, 'error time');
            }
            $updataList = [];
            foreach ($times as $key => $value) {
                $updata = [
                    'type' => intval($data['type']),
                    'status' => intval($data['status']),
                    'hours' => intval($value['hours']),
                    'minutes' => intval($value['minutes']),
                ];

                $validate_result = $this->validate($updata, 'app\carpool\validate\ShuttleTime');
                if ($validate_result !== true) {
                    return $this->jsonReturn(-1, $validate_result);
                }
                $updataList[] = $updata;
            }
            // dump($updataList);

            $ShuttleTime = new ShuttleTimeModel();
            $res = $ShuttleTime->saveAll($updataList);
            if (count($res) > 0) {
                return $this->jsonReturn(0, '保存成功');
            } else {
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $this->assign('shuttle_line_type', config('carpool.shuttle_line_type'));
            return $this->fetch('add');
        }
    }


    /**
     * 编辑时间
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $validate_result = $this->validate($data, 'app\carpool\validate\ShuttleTime');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }
            $ShuttleTime = new ShuttleTimeModel();
            $ShuttleTime->allowField(true)->save($data, ['id'=>$id]);
            return $this->jsonReturn(0, '保存成功');
        } else {
            $fields = "t.*";
            $data = ShuttleTimeModel::alias('t')->field($fields)->find($id);
            $this->assign('shuttle_line_type', config('carpool.shuttle_line_type'));
            $this->assign('data', $data);
            return $this->fetch('edit');
        }
    }



    /**
     * 删除时间
     * @param $id 数据id
     */
    public function delete($id)
    {
        if (ShuttleTimeModel::where('id', $id)->update(['is_delete' => 1]) !== false) {
            $this->log('删除班车时刻成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除班车时刻失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }
}
