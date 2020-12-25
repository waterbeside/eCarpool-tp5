<?php

namespace app\npd\controller\api\v1;

use app\npd\controller\api\NpdApiBase;
use app\npd\model\Category as CateModel;
use my\Tree;


use think\Db;

/**
 * Api Category
 * Class Category
 * @package app\npd\controller\api\v1
 */
class Category extends NpdApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 取得导航列表
     */
    public function index($model = "", $cid = 0)
    {
        $CateModel = new CateModel();
        $siteId = $this->siteId;
        $res = $CateModel->getListByModel($model, $siteId, 1, true);

        $list = [];
        if ($res) {
            foreach ($res as $key => $value) {
                $data = [
                    'id' => $value['id'],
                    'parent_id' => $value['parent_id'],
                    'name' => $value['name'],
                    'name_en' => $value['name_en'],
                    'type' => $value['type'],
                    'sort' => $value['sort'],
                    'path' => $value['path'],
                    'icon' => $value['icon'],
                    'thumb' => $value['thumb'],
                ];
                $list[] = $data;
            }
            $tree = new Tree();
            $tree->init($list);
            $tree->parentid_name = 'parent_id';
            $treeData = $tree->get_tree_array($cid, 'id');
        } else {
            $treeData = [];
        }
        $returnData = [
            'list' => $treeData,
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }
}
