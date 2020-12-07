<?php

namespace app\admin\controller\npd;

use app\npd\model\Article as ArticleModel;
use app\npd\model\Category as CategoryModel;
use app\admin\controller\npd\NpdAdminBase;
use think\Db;

/**
 * Npd文章管理
 * Class Article
 * @package app\admin\controller\npd
 */
class Article extends NpdAdminBase
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
        $lists  = ArticleModel::field($field)->alias('t')->join($join)->where($where)->order('t.sort DESC , t.cid DESC , t.create_time DESC')
            ->paginate(15, false, ['query' => request()->param()])
            ->each(function ($item, $key) use ($siteListIdMap) {
                $siteData = $siteListIdMap[$item->site_id] ?? [];
                $item->site_name = $siteData['title'] ?? '';
            });
        // ->fetchSql()->select();
        // dump($lists);exit;

        
        $this->getCateLevelList('article', $this->authNpdSite['site_id']);

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
            $data = $this->request->param();
            $this->checkItemSiteAuth($data, 1); //检查权限
            $validate_result = $this->validate($data, 'app\npd\validate\Article');
            $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            } else {
                $article_model = new ArticleModel();
                if ($article_model->allowField(true)->save($data)) {
                    $this->log('添加NPD文档成功', 0);
                    $this->jsonReturn(0, '保存成功');
                } else {
                    $this->log('添加NPD文档失败', -1);
                    $this->jsonReturn(-1, '保存失败');
                }
            }
        } else {
            return $this->addPage('article', true);
        }
    }



    /**
     * 编辑文档
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        $dataModel = new ArticleModel();
        if ($this->request->isPost()) {
            $itemRes = $this->getItemAndCheckAuthSite($dataModel, $id);
            if (!$itemRes['auth']) {
                $this->jsonReturn(-1, '没有权限');
            }
            $data            = $this->request->param();
            $validate_result = $this->validate($data, 'app\npd\validate\Article.edit');
            $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            } else {
                if ($dataModel->allowField(true)->save($data, $id) !== false) {
                    $this->log('编辑NPD文档成功', 0);
                    $this->jsonReturn(0, '修改成功');
                } else {
                    $this->log('编辑NPD文档失败', -1);
                    $this->jsonReturn(-1, '修改失败');
                }
            }
        } else {
            return $this->editPage($dataModel, $id, 'article', true);
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
        $dataModel = new ArticleModel();
        return $this->checkAuthAndDelete($dataModel, $id, true, '删除NPD文章');
    }
}
