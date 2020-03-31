<?php

namespace app\admin\controller;

use app\carpool\model\Configs as ConfigsModel;
use app\carpool\model\User as UserModel;
use app\carpool\model\ContactsReporting;
use app\admin\controller\AdminBase;
use my\RedisData;
use think\Db;

/**
 * 通讯录管理
 * Class Contacts
 * @package app\admin\controller
 */
class Contacts extends AdminBase
{

    protected $block_list_cackeKey = "carpool:contacts:block_list";

    public $check_dept_setting = [
        // "action" => ['index']
    ];

    /**
     * 通讯录管理配置管理
     */
    public function configs()
    {
        if ($this->request->isPost()) {
            $name = input('name');
            $ConfigsModel = new ConfigsModel();
            switch ($name) {
                case 'contacts_tree_status':
                    $value = input('val');
                    if (!is_numeric($value)) {
                        $this->jsonReturn(992, '请输入数字');
                    }
                    $res = $ConfigsModel->where('name', 'contacts_tree_status')->update(['value' => $value]);
                    if ($res !== false) {
                        $ConfigsModel->deleteListCache(1);
                        $this->log('修改 contacts_tree_status 配置成功', 0);
                        $this->jsonReturn(0, '修改成功');
                    } else {
                        $this->log('修改 contacts_tree_status 配置失败', -1);
                        $this->jsonReturn(-1, '修改失败');
                    }
                    break;

                case 'contacts_rule':
                    $allow_cache_deep_list = input('allow_cache_deep_list');
                    $monitor_deep_list = input('monitor_deep_list');
                    $monitor_access_max_number = input('monitor_access_max_number/d');
                    $monitor_screenshots_max_number = input('monitor_screenshots_max_number/d');
                    $monitor_review_cycle = input('monitor_review_cycle/d');

                    $contacts_rule_old = $ConfigsModel->where('name', 'contacts_rule')->value('value');
                    $contacts_rule_old = json_decode($contacts_rule_old, true);

                    $allow_cache_deep_list_format = [];
                    foreach (explode(',', $allow_cache_deep_list) as $key => $value) {
                        if (is_numeric($value) && !in_array($value, $allow_cache_deep_list_format)) {
                            $allow_cache_deep_list_format[] = intval($value);
                        }
                    }

                    $monitor_deep_list_format = [];
                    foreach (explode(',', $monitor_deep_list) as $key => $value) {
                        if (is_numeric($value) && !in_array($value, $monitor_deep_list_format)) {
                            $monitor_deep_list_format[] = intval($value);
                        }
                    }
                    $upData = [
                        "allow_cache_deep_list" => $allow_cache_deep_list_format,
                        "monitor_deep_list" => $monitor_deep_list_format,
                        "monitor_access_max_number" => $monitor_access_max_number,
                        "monitor_screenshots_max_number" => $monitor_screenshots_max_number,
                        "monitor_review_cycle" => $monitor_review_cycle,
                    ];

                    $upData = is_array($contacts_rule_old) ? array_merge($contacts_rule_old, $upData) : $upData;
                    $value = json_encode($upData);

                    $res = $ConfigsModel->where('name', 'contacts_rule')->update(['value' => $value]);
                    if ($res !== false) {
                        $ConfigsModel->deleteListCache(0);
                        $this->log('修改 contacts_rule 配置成功', 0);
                        $this->jsonReturn(0, '修改成功');
                    } else {
                        $this->log('修改 contacts_rule 配置失败', -1);
                        $this->jsonReturn(-1, '修改失败');
                    }
                    break;
                default:
                    # code...
                    break;
            }
        } else {
            $contacts_tree_status = ConfigsModel::where('name', 'contacts_tree_status')->value('value');
            $contacts_rule = ConfigsModel::where('name', 'contacts_rule')->value('value');
            $contacts_rule = json_decode($contacts_rule, true);
            $returnData = [
                'contacts_tree_status' => $contacts_tree_status,
                'contacts_rule' => $contacts_rule,
            ];
            return $this->fetch('configs', $returnData);
        }
    }


    /**
     * 通讯录受限名单
     *
     */
    public function block_list()
    {
        $redis = RedisData::getInstance();
        $res = $redis->cache($this->block_list_cackeKey);
        $userlist = $res;
        // dump($res);
        // $userlist = json_decode($res, true);
        // dump(json_encode(json_encode($userlist)));
        $field = "uid,name,nativename,loginname,Department,sex,phone";
        $lists = is_array($userlist) ?  UserModel::field($field)->where([['loginname', 'in', $userlist]])->select() : [];
        $returnData = [
            "lists" => $lists,
        ];
        return $this->fetch(null, $returnData);
    }

    /**
     * 删除通讯录受限名单
     *
     */
    public function block_list_delete($loginname = null)
    {
        if (!$loginname) {
            return $this->jsonReturn(992, 'Error params');
        }
        $uid = UserModel::where([['loginname', '=', $loginname], ['is_delete', '=', Db::raw(0)]])->value('uid');
        if (!$uid) {
            return $this->jsonReturn(20002, '该用户不存在或已删除');
        }
        $res = ContactsReporting::where([['uid', '=', $uid]])->update(['is_delete' => 1]);
        if ($res === false) {
            return $this->jsonReturn(-1, '删除失败');
        }
        $redis = RedisData::getInstance();
        $userlist = $redis->cache($this->block_list_cackeKey);
        // $userlist = json_decode($res, true);
        $userList_new = [];
        foreach ($userlist as $key => $value) {
            if (strtolower($value) != strtolower($loginname)) {
                $userList_new[] = $value;
            }
        }
        // $userList_new_str = json_encode(json_encode($userList_new));
        // $redis->set($this->block_list_cackeKey, $userList_new_str);
        $redis->cache($this->block_list_cackeKey, $userList_new);
        $this->log("移除通讯录黑名单受限用户成功。loginname = $loginname", 0);
        return $this->jsonReturn(0, '删除成功');
    }
}
