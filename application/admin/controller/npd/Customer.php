<?php
namespace app\admin\controller\npd;

use app\npd\model\Customer as CustomerModel;
use app\admin\controller\npd\NpdAdminBase;
use app\user\model\Department;
use think\Db;
use my\RedisData;

/**
 * 客户管理
 * Class Customer
 * @package app\admin\controller\npd
 */

class Customer extends NpdAdminBase
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
        $where  = [];
        $where[]  = ['is_delete', "=", Db::raw(0)];

        $siteIdwhere = $this->authNpdSite['sql_site_map'];
        $siteListIdMap = $this->getSiteListIdMap();

        if (!empty($siteIdwhere)) {
            $where[] = $siteIdwhere;
        }


        if (isset($filter['keyword']) && $filter['keyword']) {
            $where[] = ['name', 'like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['is_recommend'])  && is_numeric($filter['is_recommend'])) {
            $where[] = ['is_recommend', '=', $filter['is_recommend']];
        }
        if (isset($filter['r_group'])  && $filter['r_group'] != '') {
            $where[] = ['r_group', '=', $filter['r_group']];
        }

        $lists  = CustomerModel::where($where)->order(['sort' => 'DESC', 'id' => 'DESC'])
            ->paginate($pagesize, false, ['query' => request()->param()])
            ->each(function ($item, $key) use ($siteListIdMap) {
                $siteData = $siteListIdMap[$item->site_id] ?? [];
                $item->site_name = $siteData['title'] ?? '';
            });

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
            $this->checkItemSiteAuth($data, 1); //检查权限

            if (empty($data['site_id'])) {
                return $this->jsonReturn(-1, '请选择站点');
            }

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
                'site_id' => $data['site_id'],
            ];


            $CustomerModel = new CustomerModel();

            $id = $CustomerModel->insertGetId($upData);
            if ($id) {
                $CustomerModel->deleteListCache($data['site_id']);
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
        $dataModel = new CustomerModel();

        if ($this->request->isPost()) {
            $itemRes = $this->getItemAndCheckAuthSite($dataModel, $id);
            if (!$itemRes['auth']) {
                $this->jsonReturn(-1, '没有权限');
            }
            $siteId = $itemRes['data']['site_id'] ?? 0;
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
                'is_recommend' => isset($data['is_recommend']) ? $data['is_recommend'] : 0,
            ];

            if ($dataModel->where('id', $id)->update($upData) !== false) {
                $dataModel->deleteListCache($siteId);
                $this->log('编辑客户成功', 0);
                $this->jsonReturn(0, '修改成功');
            } else {
                $this->log('编辑客户失败', -1);
                $this->jsonReturn(-1, '修改失败');
            }
        } else {
            $this->assign('groups', config('npd.customer_group'));
            return $this->editPage($dataModel, $id, null, true);
        }
    }


    /**
     * 删除产品客户
     * @param $id
     */
    public function delete($id)
    {
        $dataModel = new CustomerModel();
        return $this->checkAuthAndDelete($dataModel, $id, true, '删除产品客户', function ($res) use ($dataModel) {
            if ($res && $res['site_id']) {
                $dataModel->deleteListCache($res['site_id']);
            }
        });
    }

    /**
     * 用于选择列表
     */
    public function public_lists()
    {
        $site_id = $this->authNpdSite['site_id'];
        $CustomerModel = new CustomerModel();
        $lists = $CustomerModel->getList($site_id);
        $this->jsonReturn(0, ['lists' => $lists], 'success');
    }
}
