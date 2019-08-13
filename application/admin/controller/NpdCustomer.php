<?php
namespace app\admin\controller;

use app\npd\model\Customer as CustomerModel;
use app\admin\controller\AdminBase;
use app\user\model\Department;
use think\Db;
use my\RedisData;

/**
 * 客户管理
 * Class NpdCustomer
 * @package app\admin\controller\NpdCustomer
 */

class NpdCustomer extends AdminBase
{
    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 客户管理
     * @return mixed
     */
    public function index($filter = ['keyword' => ''], $page = 1, $pagesize = 20)
    {
        $map  = [];
        $map[]  = ['is_delete', "=", 0];
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['name', 'like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['is_recommend'])  && is_numeric($filter['is_recommend'])) {
            $map[] = ['is_recommend', '=', $filter['is_recommend']];
        }
        if (isset($filter['r_group'])  && $filter['r_group'] != '') {
            $map[] = ['r_group', '=', $filter['r_group']];
        }

        $lists  = CustomerModel::where($map)->order(['sort' => 'DESC', 'id' => 'DESC'])->paginate($pagesize, false, ['page' => $page]);
        $this->assign('lists', $lists);
        $this->assign('filter', $filter);
        $this->assign('pagesize', $pagesize);
        $this->assign('groups', config('npd.customer_group'));
        return $this->fetch();
    }

    /**
     * 添加客户
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();

            if (!$data['thumb']) {
                $this->jsonReturn(-1, '请上传缩略图');
            }
            if (!$data['name']) {
                $this->jsonReturn(-1, '名称不能为空');
            }

            $upData = [
                'sort' => $data['sort'] ? $data['sort'] : 0,
                'remark' =>   iconv_substr($data['remark'], 0, 250),
                'name' => iconv_substr($data['name'], 0, 250),
                'thumb' => $data['thumb'],
                'is_recommend' => isset($data['is_recommend']) ? $data['is_recommend'] : 0,
                'r_group' => $data['r_group'],
            ];


            $CustomerModel = new CustomerModel();

            $id = $CustomerModel->insertGetId($upData);
            if ($id) {
                $CustomerModel->deleteListCache();
                $this->log('添加客户成功', 0);
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('添加客户失败', -1);
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $this->assign('groups', config('npd.customer_group'));
            return $this->fetch();
        }
    }



    /**
     * 编辑客户
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();

            if (!$data['thumb']) {
                $this->jsonReturn(-1, '请上传缩略图');
            }
            if (!$data['name']) {
                $this->jsonReturn(-1, '名称不能为空');
            }

            $upData = [
                'sort' => $data['sort'] ? $data['sort'] : 0,
                'remark' =>   iconv_substr($data['remark'], 0, 250),
                'name' => iconv_substr($data['name'], 0, 250),
                'thumb' => $data['thumb'],
                'r_group' => $data['r_group'],
            ];

            if (isset($data['is_recommend'])) {
                $upData['is_recommend'] = $data['is_recommend'];
            }

            $CustomerModel = new CustomerModel();

            if ($CustomerModel->where('id', $id)->update($upData) !== false) {
                $CustomerModel->deleteListCache();
                $this->log('编辑客户成功', 0);
                $this->jsonReturn(0, '修改成功');
            } else {
                $this->log('编辑客户失败', -1);
                $this->jsonReturn(-1, '修改失败');
            }
        } else {
            $data = CustomerModel::find($id);
            $this->assign('groups', config('npd.customer_group'));
            return $this->fetch('edit', ['data' => $data]);
        }
    }


    /**
     * 删除产品客户
     * @param $id
     */
    public function delete($id)
    {
        $CustomerModel = new CustomerModel();
        $oldData = $CustomerModel->get($id);
        if (!$oldData) {
            $this->jsonReturn(0, '删除成功');
        }
        $oldData->is_delete = 1;
        if ($oldData->save()) {
            $CustomerModel->deleteListCache();
            $this->log('删除产品客户成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除产品客户失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }

    /**
     * 用于选择列表
     */
    public function public_lists()
    {

        $CustomerModel = new CustomerModel();
        $lists = $CustomerModel->getList();
        $this->jsonReturn(0, ['lists' => $lists], 'success');
    }
}
