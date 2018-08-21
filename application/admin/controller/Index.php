<?php
namespace app\admin\controller;
use think\facade\Config;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 后台首页
 * Class Index
 * @package app\admin\controller
 */
class Index extends AdminBase
{
    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 首页
     * @return mixed
     */
    public function main()
    {

        $version = Db::query('SELECT VERSION() AS ver');
        $config  = [
            'url'             => $_SERVER['HTTP_HOST'],
            'document_root'   => $_SERVER['DOCUMENT_ROOT'],
            'server_os'       => PHP_OS,
            'server_port'     => $_SERVER['SERVER_PORT'],
            'server_soft'     => $_SERVER['SERVER_SOFTWARE'],
            'php_version'     => PHP_VERSION,
            'mysql_version'   => $version[0]['ver'],
            'max_upload_size' => ini_get('upload_max_filesize')
        ];
        // dump(Config::get());
        return $this->fetch('main', ['config' => $config]);
    }

    /**
     * 首页
     * @return mixed
     */
    public function index()
    {
        $this->getMenu();
        $admin_user = $this->userBaseInfo;
        return $this->fetch('index',['admin_user'=>$admin_user]);
    }
}
