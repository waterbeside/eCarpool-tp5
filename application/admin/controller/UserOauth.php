<?php

namespace app\admin\controller;

use app\admin\controller\AdminBase;
use app\user\model\UserOauth as UserOauthModel;
use think\Db;

/**
 * 用户第三方账号
 * Class UserOauth
 * @package app\admin\controller
 */
class UserOauth extends AdminBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 第三方绑定列表
     * @return mixed
     */
    public function index($filter = [], $pagesize = 20)
    {
        $fields = "o.*, u.loginname, u.name,c.company_name,u.sex, d.fullname as full_department ";
        $map = [];
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['u.loginname|u.phone|u.name', 'like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            $map[] = ['d.fullname|u.companyname|c.company_name', 'like', "%{$filter['keyword_dept']}%"];
        }
        //是否已解绑
        $filter['is_delete'] = isset($filter['is_delete']) ? $filter['is_delete'] : 0;
        $map[] = ['o.is_delete', '=', $filter['is_delete']];


        //类型
        if (isset($filter['type']) && is_numeric($filter['type']) && $filter['type'] > 0) {
            $map[] = ['o.type', '=', $filter['type']];
        } else {
            $filter['type'] = 0;
        }
        $typeList = config('others.user_oauth_type');

        $join = [
            ['user u', 'u.uid = o.user_id', 'left'],
            ['company c', 'c.company_id = u.company_id', 'left'],
            ['t_department d', 'd.id = u.department_id', 'left'],
        ];
        $lists = UserOauthModel::alias('o')->field($fields)->join($join)
            ->where($map)
            ->order('uid DESC')
            ->paginate($pagesize, false, ['query' => request()->param()]);
        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'pagesize' => $pagesize,
            'typeList' => $typeList,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 删除绑定
     * @param $id
     */
    public function delete($id)
    {
        $UserOauthModel = new UserOauthModel();
        $userOauth = $UserOauthModel->where('id', $id)->find();
        if (!$userOauth) {
            return $this->jsonReturn(20002, '无此数据');
        }
        $res = $UserOauthModel->unbind([['id','=',$id]]);

        // $uid = $userOauth->user_id;
        // $res = $UserOauthModel->unbindByUid($uid);


        // if($this->user_model->where('uid', $id)->update(['is_delete' => 1])){
        if ($res !== false) {
            $this->log('解绑第三方登录成功，id=' . $id, 0);
            return $this->jsonReturn(0, '解绑成功');
        } else {
            $this->log('解绑第三方登录失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '解绑失败');
        }
    }
}
