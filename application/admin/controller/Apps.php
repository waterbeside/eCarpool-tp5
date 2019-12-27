<?php

namespace app\admin\controller;

use app\admin\controller\AdminBase;
use app\common\model\Apps as AppsModel;
use think\Db;

/**
 * APP管理
 * Class Apps
 * @package app\admin\controller
 */
class Apps extends AdminBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * app列表
     * @return mixed
     */
    public function index()
    {
        $lists = AppsModel::select();
        return $this->fetch('index', ['lists' => $lists]);
    }



    /**
     * 编辑
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $validate_result = $this->validate($data, 'Apps');
            if ($validate_result !== true) {
                $this->error($validate_result);
            } else {
                $AppsModel = new AppsModel();
                if ($AppsModel->allowField(true)->save($data, $id) !== false) {
                    $AppsModel->delItemCache($id);
                    $this->jsonReturn(0, '更新成功');
                } else {
                    $this->jsonReturn(-1, '更新失败');
                }
            }
        } else {
            $data = AppsModel::find($id);
            return $this->fetch('edit', ['data' => $data]);
        }
    }
}
