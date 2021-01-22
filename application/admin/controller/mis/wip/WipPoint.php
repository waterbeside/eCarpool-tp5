<?php
namespace app\admin\controller\mis\wip;

use app\mis\model\WipPoint as WipPointModel;

use app\admin\controller\AdminBase;
use think\Db;

class WipPoint extends AdminBase
{
    /**
     * 数据管理
     * @param int    $cid     分类ID
     * @param string $keyword 关键词
     * @param int    $page
     * @return mixed
     */
    public function index($cid = 0, $filter = ['keyword' => ''], $page = 1)
    {
        $map   = [];
        $map[] = ['t.is_delete', '=', Db::raw(0)];
        $field = 't.*';
        $pagesize = 20;

        $DataModel = new WipPointModel();

        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['name', 'like', "%{$filter['keyword']}%"];
        }
        // /筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }



        $lists  = $DataModel::field($field)->alias('t')->where($map)->order(' t.listorder DESC, t.create_time ASC, t.id ASC ')
            // ->fetchSql()->select();
            ->paginate($pagesize, false, ['query' => request()->param()]);

        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'pagesize' => $pagesize,
        ];


        return $this->fetch('index', $returnData);
    }

    /**
     * 添加数据
     * @param string $pid
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();

            $data['creater_id'] = $this->userBaseInfo['uid'] ?: 0;
            $data['modifier_id'] = $this->userBaseInfo['uid'] ?: 0;

            $DataModel = new WipPointModel();
            $DataModel->allowField(true)->save($data);
            $id = $DataModel->id;

            $this->log('添加WIP控制点成功 id=' . $id, -1);
            return $this->jsonReturn(0, '添加成功');
        } else {
            return $this->fetch();
        }
    }


    /**
     * 编辑数据
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        $DataModel = new WipPointModel();
        $dataDetail     = $DataModel->find($id);
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $data['modifier_id'] = $this->userBaseInfo['uid'] ?: 0;
            $res = $dataDetail->allowField(true)->save($data, $id);
            if ($res === false) {
                $this->log('添加WIP控制点失败 id=' . $id, -1);
                return $this->jsonReturn(-1, '更新失败');
            }
            $this->log('添加WIP控制点成功 id=' . $id, 0);
            return $this->jsonReturn(0, '更新成功');
        } else {
            return $this->fetch('edit', ['data' => $dataDetail]);
        }
    }



    /**
     * 删除数据
     * @param $id
     */
    public function delete($id)
    {
        if (WipPointModel::where('id', $id)->update(['is_deleted' => 1])) {
            $this->log('删除WIP控制点成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除WIP控制点失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }


}