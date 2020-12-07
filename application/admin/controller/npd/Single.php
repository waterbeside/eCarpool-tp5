<?php

namespace app\admin\controller\npd;

use app\npd\model\Single as SingleModel;
use app\npd\model\Category as CategoryModel;

use app\admin\controller\npd\NpdAdminBase;
use think\Db;

/**
 * Single 单页文章管理
 * Class Single
 * @package app\admin\controller\npd
 */

class Single extends NpdAdminBase
{


    protected function initialize()
    {
        parent::initialize();
    }

    public function index($cid = 0, $keyword = '', $page = 1)
    {
        $where   = [];
        $where[] = ['t.is_delete', '=', Db::raw(0)];
        $siteIdwhere = $this->authNpdSite['sql_site_map'];
        $siteListIdMap = $this->getSiteListIdMap();
        if (!empty($siteIdwhere)) {
            $siteIdwhere[0] = 't.site_id';
            $where[] = $siteIdwhere;
        }

        $field = 't.*,c.name as c_name';
        $CategoryModel = new CategoryModel();
        if ($cid > 0) {
            $cids = $CategoryModel->getChildrensId($cid);
            $where[] = ['cid', 'in', $cids];
        }

        if (!empty($keyword)) {
            $where[] = ['title', 'like', "%{$keyword}%"];
        }

        $join = [
            ['t_category c', 't.cid = c.id', 'left'],
        ];
        $lists  = SingleModel::field($field)->alias('t')->join($join)->where($where)->order('t.sort DESC , t.cid DESC , t.create_time DESC')
            ->paginate(15, false, ['query' => request()->param()])
            ->each(function ($item, $key) use ($siteListIdMap) {
                $siteData = $siteListIdMap[$item->site_id] ?? [];
                $item->site_name = $siteData['title'] ?? '';
            });
        
        $this->getCateLevelList('single', $this->authNpdSite['site_id']);

        return $this->fetch('index', ['lists' => $lists, 'cid' => $cid, 'keyword' => $keyword]);
    }



    /**
     * 添加文档
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $this->checkItemSiteAuth($data, 1); //检查权限
            $validate_result = $this->validate($data, 'app\npd\validate\Single');
            $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            } else {
                $Single_model = new SingleModel();
                if ($Single_model->allowField(true)->save($data)) {
                    $this->log('添加NPD单页文档成功', 0);
                    $this->jsonReturn(0, '保存成功');
                } else {
                    $this->log('添加NPD单页文档失败', -1);
                    $this->jsonReturn(-1, '保存失败');
                }
            }
        } else {
            return $this->addPage('single', true);
        }
    }

    /**
     * 编辑文档
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        $dataModel = new SingleModel();
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $itemRes = $this->getItemAndCheckAuthSite($dataModel, $id);
            if (!$itemRes['auth']) {
                $this->jsonReturn(-1, '没有权限');
            }
            $validate_result = $this->validate($data, 'app\npd\validate\Single.edit');
            $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            } else {
                if ($dataModel->allowField(true)->save($data, $id) !== false) {
                    $this->log('编辑NPD单页文档成功', 0);
                    $this->jsonReturn(0, '修改成功');
                } else {
                    $this->log('编辑NPD单页文档失败', -1);
                    $this->jsonReturn(-1, '修改失败');
                }
            }
        } else {
            return $this->editPage($dataModel, $id, 'single', true);
        }
    }

    /**
     * 删除文档
     * @param int   $id
     * @param array $ids
     */
    public function delete($id = 0, $ids = [])
    {
        $id = $ids ? $ids : $id;
        $dataModel = new SingleModel();
        return $this->checkAuthAndDelete($dataModel, $id, true, '删除NPD单页文章');
    }
}
