<?php

namespace app\admin\controller\npd;

use app\admin\controller\AdminBase;
use app\admin\behavior\CheckNpdSiteAuth;
use think\facade\Hook;
use think\Db;

/**
 * NPD后台管理基础类
 * Class NpdAdminBase
 * @package app\admin\controller\npd
 */
class NpdAdminBase extends AdminBase
{

    public $authNpdSite = [];

    protected function initialize()
    {
        parent::initialize();
        $res = (new Hook())->listen("check_npd_site_auth", $this, [], true);
    }
}
