<?php

namespace app\admin\controller;

use app\npd\model\Product as ProductModel;
use app\npd\model\Gallery as GalleryModel;

use app\admin\controller\AdminBase;
use think\Db;

/**
 * 产品图册管理
 * Class NpdProductGallery
 * @package app\admin\controller
 */

class NpdProductGallery extends AdminBase
{


    /**
     * 产品图册管理
     * @param int    $pid    产品id
     * @param array $filter  筛选
     * @return mixed
     */
    public function index($pid = -1, $filter = ['keyword' => ''], $pagesize = 20)
    {
        $map   = [];
        $map[] = ['t.is_delete', '=', Db::raw(0)];

        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['title|title_en', 'like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $map[] = ['status', '=', $filter['status']];
        }
        $map[] = ['model', '=', 'product'];
        if ($pid) {
            $map[] = ['aid', '=', $pid];
        }
        $listBef  = GalleryModel::alias('t')->where($map)->order('t.sort DESC , t.create_time DESC , t.id DESC ');
        if ($pagesize > 0 && $pid < 1) {
            $list =  $listBef->paginate($pagesize, false, ['query' => request()->param()]);
            $total = $list->total();
        } else {
            $list =  $listBef->select();
            $total = count($list);
        }
        
        $returnData = [
            'pid' => $pid,
            'filter' => $filter,
            'pagesize' => $pagesize,
            'lists' => $list,
            'total' => $total,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加图片
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data          = $this->request->param();
            $data['model'] = 'product';
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Gallery');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            $GalleryModel = new GalleryModel();
            if ($GalleryModel->allowField(true)->save($data)) {
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $pid = input('param.pid');
            $this->assign('model', 'product');
            $this->assign('aid', $pid);
            $this->assign('form_action', url('admin/NpdProductGallery/add', ['pid'=>$pid]));
            return $this->fetch('npd_gallery/add');
        }
    }



    /**
     * 编辑图片
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data          = $this->request->param();
            $data['model'] = 'product';
            $data['status'] = isset($data['status']) ? isset($data['status']) : 0;
            $validate_result = $this->validate($data, 'app\npd\validate\Gallery');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }
            $GalleryModel = new GalleryModel();
            if ($GalleryModel->allowField(true)->save($data, $id)) {
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $data     = GalleryModel::find($id);
            $this->assign('form_action', url('admin/NpdProductGallery/edit'));
            $this->assign('id', $id);
            return $this->fetch('npd_gallery/edit', ['data' => $data]);
        }
    }


    /**
     * 删除图片
     * @param $id
     */
    public function delete($id)
    {
        if (GalleryModel::where('id', $id)->update(['is_delete' => 1])) {
            $this->log('删除产品图片成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除产品图片失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }
}
