<?php

namespace app\admin\controller\npd;

use app\admin\controller\AdminBase;
use app\admin\behavior\CheckNpdSiteAuth;
use think\facade\Hook;
use think\Db;

/**
 * 拼车站点管理
 * Class Address
 * @package app\admin\controller
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
