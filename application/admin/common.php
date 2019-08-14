<?php

use org\Auth;
use think\Loader;
use think\Response;
use think\Db;
use think\facade\Session;
use app\admin\service\Admin;

function checkAuth($rule)
{
    $Admin = new Admin();
    $admin_id = $Admin->getAdminID();
    if (!$admin_id) {
        return false;
    }
    if ($admin_id === 1) {
        return true;
    }
    $auth = new Auth();
    return $auth->check($rule, $admin_id);
}
