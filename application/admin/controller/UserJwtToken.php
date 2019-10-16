<?php

namespace app\admin\controller;

use app\carpool\model\User as UserModel;
use app\user\model\JwtToken;
use app\user\model\Department;
use app\admin\controller\AdminBase;
use think\facade\Validate;
use think\Db;

/**
 * JWt管理
 * Class UserJwtToken
 * @package app\admin\controller
 */
class UserJwtToken extends AdminBase
{


    public $check_dept_setting = [
        "action" => ['index', 'kick_out']
    ];

    /**
     * 用户JWT管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter = [], $pagesize = 50)
    {
        $fields = "t.id, t.uid, t.client, t.iss, t.token, t.iat, t.exp, t.create_type, t.create_time , t.invalid_type, t.invalid_time, t.is_delete,
    u.loginname, u.name, u.nativename, u.phone, u.companyname, d.fullname as full_department";
        $join = [
            ['user u', 't.uid = u.uid', 'left'],
            ['t_department d', 'u.department_id = d.id', 'left'],
        ];
        $map = [];
        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['region_map'])) {
            $map[] = $authDeptData['region_map'];
        }
        //筛选信息
        if (isset($filter['is_delete']) && is_numeric($filter['is_delete'])) {
            $is_delete = $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
            $map[] = ['t.is_delete', '=', $is_delete];
        }
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['u.uid|u.loginname|u.phone|u.name|nativename', 'like', "%{$filter['keyword']}%"];
        }
        $JwtToken = new JwtToken();
        $lists = $JwtToken->alias('t')->field($fields)
            ->join($join)
            ->where($map)
            ->order('create_time DESC')
            ->paginate($pagesize, false, ['query' => request()->param()]);
        foreach ($lists as $key => $value) {
            $lists[$key]['invalid_type_str'] = $JwtToken->parseInvalidType($value['invalid_type']);
        }

        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'pagesize' => $pagesize,
            'now' => time(),
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 踢出登入
     */
    public function kick_out($id)
    {
        if (!$id) {
            $this->jsonReturn(992, 'ERROR ID');
        }
        $JwtToken = new JwtToken();
        $map = [['id', '=', $id]];
        $res = $JwtToken->invalidateByMap($map, -99);
        if ($res !== false) {
            $this->log('踢出用户JWT成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('踢出用户失败，id=' . $id, 0);
            return $this->jsonReturn(-1, '踢出失败');
        }
    }
}
