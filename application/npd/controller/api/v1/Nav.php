<?php

namespace app\npd\controller\api\v1;

use app\npd\controller\api\NpdApiBase;
use app\npd\model\Nav as NavModel;
use my\RedisData;
use my\Tree;


use think\Db;

/**
 * Api Nav
 * Class Nav
 * @package app\npd\controller\api\v1
 */
class Nav extends NpdApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 取得导航列表
     */
    public function index()
    {
        $NavModel = new NavModel();
        $res = $NavModel->getList($this->siteId);
        $list = [];
        if ($res) {
            foreach ($res as $key => $value) {
                if ($value['status'] > 0) {
                    $data = [
                        'id' => $value['id'],
                        'pid' => $value['pid'],
                        'name' => $value['name'],
                        'name_en' => $value['name_en'],
                        'link' => $value['link'],
                        'link_type' => $value['link_type'],
                        'target' => $value['target'],
                    ];
                    $list[] = $data;
                }
            }
            $tree = new Tree();
            $tree->init($list);
            $tree->parentid_name = 'pid';
            $treeData = $tree->get_tree_array(0, 'id');
        }
        $returnData = [
            'list' => $treeData,
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }
}
