<?php

namespace app\admin\controller\mis\tech;

use app\mis\model\Digital as DigitalModel;
use app\mis\model\LogSync;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 数码印花列表管理
 * Class Digital
 * @package app\admin\controller
 */

class Digital extends AdminBase
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
        $map[] = ['t.is_deleted', '=', Db::raw(0)];
        $field = 't.*';
        $pagesize = 20;
        $DigitalModel = new DigitalModel();

        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['remark|progress|customer_name', 'like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['keyword2']) && $filter['keyword2']) {
            $map[] = ['model_no|order_id|name', 'like', "%{$filter['keyword2']}%"];
        }
        if (isset($filter['batch_no']) && $filter['batch_no']) {
            $map[] = ['batch_no|digital_id', 'like', "%{$filter['batch_no']}%"];
        }

        if (isset($filter['bulk_sample']) && $filter['bulk_sample']) {
            $map[] = ['bulk_sample', '=', $filter['bulk_sample']];
        }


        if (isset($filter['status'])  && is_numeric($filter['status'])) {
            $map[] = ['status', '=', $filter['status']];
        }

        $lists  = DigitalModel::field($field)->alias('t')->where($map)->order('t.id DESC , t.create_date DESC  ')
            // ->fetchSql()->select();
            ->paginate($pagesize, false, ['query' => request()->param()]);

        if (!empty($lists)) {
            foreach ($lists as $key => $value) {
                $lists[$key]['thumb_fullpath'] = $DigitalModel->getFullThumbPath($value['thumb_data']);
            }
        }

        $syncModel = new LogSync();
        $lastSyncTime = $syncModel->getLastTime('mis:sync:digitalList');

        $returnData = [
            'lists' => $lists,
            'cid' => $cid,
            'filter' => $filter,
            'pagesize' => $pagesize,
            'lastSyncTime' => $lastSyncTime,
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
            // $validate_result = $this->validate($data, 'app\mis\validate\Digital');
            // if ($validate_result !== true) {
            //     $this->jsonReturn(-1, $validate_result);
            // }
            $data['creater_id'] = $this->userBaseInfo['uid'] ?: 0;

            Db::connect('database_mis')->startTrans();
            try {
                /******** 处理主表 ********/
                $DigitalModel = new DigitalModel();
                $DigitalModel->allowField(true)->save($data);
                $id = $DigitalModel->id;
                if (!$id) {
                    throw new \Exception("创建数据失败");
                }
                // 提交事务
                Db::connect('database_mis')->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('database_mis')->rollback();
                $errorMsg = $e->getMessage();
                $this->log('添加数据失败 title=' . $data['name'], 0);
                return $this->jsonReturn(-1, $errorMsg);
            }
            $this->log('添加数据成功 id=' . $id, -1);
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
        $DigitalModel = new DigitalModel();
        $dataDetail     = $DigitalModel->find($id);
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $data['modifier_id'] = $this->userBaseInfo['uid'] ?: 0;
            // dump($data);
            Db::connect('database_mis')->startTrans();
            try {

                /******** 处理主表 ********/
                $res = $DigitalModel->allowField(true)->save($data, $id);
                if ($res === 'false') {
                    throw new \Exception("创建数据失败");
                }
                // 提交事务
                Db::connect('database_mis')->commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('database_mis')->rollback();
                $errorMsg = $e->getMessage();
                $this->log('更新数据失败 id=' . $id, -1);
                return $this->jsonReturn(-1, $errorMsg);
            }
            $this->log('更新数据成功 id=' . $id, 0);
            return $this->jsonReturn(0, '更新成功');
        } else {
            $dataDetail['thumb_fullpath'] = $DigitalModel->getFullThumbPath($dataDetail['thumb_data']);
            return $this->fetch('edit', ['data' => $dataDetail]);
        }
    }



    /**
     * 删除数据
     * @param $id
     */
    public function delete($id)
    {
        if (DigitalModel::where('id', $id)->update(['is_deleted' => 1])) {
            $this->log('删除数据成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除数据失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }

    /**
     * 改变状态
     *
     * @return void
     */
    public function change_status($id)
    {
        $oldData = DigitalModel::where('id', $id)->find();
        if (empty($oldData)) {
            $this->jsonReturn(-1, '找不到数据，修改失败');
        }
        $upData = [
            'status' => $oldData['status'] == 1 ? 0 : 1,
        ];
        if (DigitalModel::where('id', $id)->update($upData)) {
            $this->jsonReturn(0, ['status'=>$upData['status']], '状态修改成功');
        } else {
            $this->jsonReturn(-1, '状态修改失败');
        }
    }
}
