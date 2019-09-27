<?php

namespace app\admin\controller;

use app\npd\model\Banner as BannerModel;
use app\admin\controller\AdminBase;
use app\user\model\Department;
use think\Db;

// use my\RedisData;

/**
 * Npd Banner管理
 * Class NpdBanner
 * @package app\admin\controller
 */
class NpdBanner extends AdminBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * Banner列表
     * @return mixed
     */
    public function index($filter = ['keyword' => ''], $page = 1, $pagesize = 20)
    {


        $map  = [];
        $map[]  = ['is_delete', "=", 0];
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['title', 'like', "%{$filter['keyword']}%"];
        }


        $lists  = BannerModel::where($map)->order(['sort' => 'DESC', 'id' => 'DESC'])->paginate($pagesize, false, ['page' => $page]);
        $DepartmentModel = new Department();

        foreach ($lists as $key => $value) {
            $lists[$key]['thumb'] = $value["image"];
        }
        $typeList = config('npd.banner_type');
        $this->assign('typeList', $typeList);

        $this->assign('filter', $filter);
        $this->assign('lists', $lists);
        $this->assign('pagesize', $pagesize);

        return $this->fetch();
    }

    /**
     * 添加轮播图
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();


            if ($data['link_type'] > 0 && !trim($data['link'])) {
                return $this->jsonReturn(-1, '你选择了跳转，请填写跳转连接');
            }
            if (is_numeric($data['lang']) && intval($data['lang']) === 0) {
                $data['lang'] = '';
            }
            if ($data['lang'] == '-1') {
                $data['lang'] = $data['lang_input'];
            }
            $validate_result = $this->validate($data, 'app\npd\validate\Banner');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }

            $upData = [
                'title' =>   iconv_substr($data['title'], 0, 100),
                'status' => $data['status'],
                'type' => $data['type'],
                'sort' => $data['sort'],
                'create_time' => date('Y-m-d H:i:s'),
                'link_type' => $data['link_type'],
                'link' => $data['link'],
                'lang' => $data['lang'],
            ];
            if ($data['thumb'] && trim($data['thumb'])) {
                $upData['image'] =  $data['thumb'];
            }

            $id = BannerModel::insertGetId($upData);
            if ($id) {
                $this->log('保存NPD Banner成功', 0);
                $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('保存NPD Banner失败', -1);
                $this->jsonReturn(-1, '保存失败');
            }
        } else {
            $typeList = config('npd.banner_type');
            $this->assign('typeList', $typeList);
            return $this->fetch();
        }
    }



    /**
     * 编辑轮播图
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();

            if ($data['link_type'] > 0 && !trim($data['link'])) {
                return $this->jsonReturn(-1, '你选择了跳转，请填写跳转连接');
            }
            if (is_numeric($data['lang']) && intval($data['lang']) === 0) {
                $data['lang'] = '';
            }
            if ($data['lang'] == '-1') {
                $data['lang'] = $data['lang_input'];
            }
            $validate_result = $this->validate($data, 'app\npd\validate\Banner');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }
            $upData = [
                'title' =>   iconv_substr($data['title'], 0, 100),
                'status' => $data['status'],
                'type' => $data['type'],
                'sort' => $data['sort'],
                'link_type' => $data['link_type'],
                'link' => $data['link'],
                'lang' => $data['lang'],
            ];
            if ($data['thumb'] && trim($data['thumb'])) {
                $upData['image'] =  $data['thumb'];
            }

            if (BannerModel::where('id', $id)->update($upData) !== false) {
                $this->log('编辑NPD Banner成功', 0);
                $this->jsonReturn(0, '修改成功');
            } else {
                $this->log('编辑NPD Banner失败', -1);
                $this->jsonReturn(-1, '修改失败');
            }
        } else {
            $data = BannerModel::find($id);
            // $deptsArray = explode(',',$data['region_id']);
            $typeList = config('npd.banner_type');
            $data['thumb'] = $data["image"] ? $data["image"] : "";
            $this->assign('typeList', $typeList);
            return $this->fetch('edit', ['data' => $data]);
        }
    }


    /**
     * 删除轮播图
     * @param $id
     */
    public function delete($id)
    {
        $oldData = BannerModel::get($id);
        if (!$oldData) {
            $this->jsonReturn(0, '删除成功');
        }
        $oldData->is_delete = 1;
        if ($oldData->save()) {
            $this->log('删除NPD banner图成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除NPD banner图失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }
}
