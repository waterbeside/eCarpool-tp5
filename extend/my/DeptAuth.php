<?php

namespace my;

use think\Db;
use think\facade\Config;
use think\facade\Session;
use think\facade\Request;
use think\Loader;
use my\RedisData;

class DeptAuth
{

    //默认配置
    protected $config = [
        'auth_on'           => 1, // 权限开关
        'auth_dept_group'        => 'dept_group', // 用户组数据表名
        'auth_group_access' => 'auth_group_access', // 用户-用户组关系表
        'auth_user'         => 'member', // 用户信息表
    ];


    /**
     * 根据用户id获取用户组,返回值为数组
     *
     * @param integer $uid 用户id
     */
    public function getGroup($uid)
    {
        static $groups = [];
        if (isset($groups[$uid])) {
            return $groups[$uid];
        }
        $redis = new RedisData();
        $cacheKey = "carpool_management:deptGroup:adminUser:$uid";
        $user_groups = $redis->cache($cacheKey);
        if ($user_groups) {
            return $user_groups;
        }
        // 转换表名
        $auth_group_access = Loader::parseName($this->config['auth_group_access'], 1);
        $auth_dept_group    = Loader::parseName($this->config['auth_dept_group'], 1);

        // 执行查询
        $user_groups  = Db::view($auth_group_access, 'uid,dept_group_id')
            ->view($auth_dept_group, 'title,depts', "{$auth_group_access}.dept_group_id={$auth_dept_group}.id", 'LEFT')
            ->where("{$auth_group_access}.uid='{$uid}' and {$auth_dept_group}.status='1'")
            ->find();
        $groups[$uid] = $user_groups ?: [];
        $redis->cache($cacheKey, $user_groups, 3600);
        return $groups[$uid];
    }
}
