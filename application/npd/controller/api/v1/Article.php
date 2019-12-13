<?php

namespace app\npd\controller\api\v1;

use think\Db;
use app\npd\controller\api\NpdApiBase;
use app\npd\model\Article as ArticleModel;
use app\npd\model\Category;

/**
 * Api Article
 * Class Article
 * @package app\npd\controller\api\v1
 */
class Article extends NpdApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 取得列表
     */
    public function index($cid = 0, $pagesize = 30)
    {
        $map = [
            ['is_delete', '=', Db::raw(0)],
            ['status', '=', 1],
        ];
        $cate_data = null;
        $breadcrumd = null;
        if (is_numeric($cid) && $cid > 0) {
            $Category = new Category();
            $cate_data = $Category->getDetail($cid);
            $breadcrumd  = $Category->getCateBreadcrumb($cate_data, 'article');
            $cate_Ids  = $Category->getCateChildrenIds($cid, 'article');
            $map[] = ['cid', 'in', $cate_Ids];
        }

        $lang = $this->language;
        $map[] = ['lang', '=', ($lang !== "zh-cn" ? 'en' : 'zh-cn')];

        $lists_res = ArticleModel::where($map)->order('is_top DESC , sort DESC')->paginate($pagesize, false, ['query' => request()->param()]);

        $pagination = [
            'total' => $lists_res->total(),
            'page' => input('page', 1),
            'pagesize' => $pagesize,
            // 'render' => $lists_res->render(),
        ];
        $lists_to_array = $lists_res->toArray();
        $lists = $lists_to_array['data'];
        $returnData = [
            'list' => $this->replaceAttachmentDomain($lists, 'thumb'),
            'pagination' => $pagination,
            'category' => $cate_data,
            'breadcrumd' => $breadcrumd,
        ];
        $this->jsonReturn(0, $returnData, 'Successful');
    }

    /**
     * 取得文章详情
     *
     * @param integer $id
     */
    public function read($id = 0)
    {
        $this->checkPassport(true);

        if (!$id) {
            $this->jsonReturn(992, 'Error id');
        }
        $field = 'id, title, cid, update_time, create_time, publish_time , content, sort, status, lang';
        $map   = [];
        $map = [
            ['status', '=', 1],
            ['is_delete', '=', Db::raw(0)],
        ];
        if (is_numeric($id)) {
            $map[] = ['t.id', '=', $id];
        }

        // $lang = $this->language;
        // $whereLang = ['lang'=>$lang] ;

        $data  = ArticleModel::field($field)->alias('t')->where($map)->find();

        if (!$data) {
            return $this->jsonReturn(20002, 'No data');
        }

        $Category = new Category();
        $cate_data = $Category->getDetail($data['cid']);
        $breadcrumd = $Category->getCateBreadcrumb($cate_data, 'article');

        $data['content'] = $this->replaceAttachmentDomain($data["content"]);
        $returnData = [
            'data' => $data,
            'category' => $cate_data,
            'breadcrumd' => $breadcrumd,
        ];

        $this->jsonReturn(0, $returnData, 'Successful');
    }
}
