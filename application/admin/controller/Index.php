<?php

namespace app\admin\controller;

use think\facade\Config;
use app\admin\controller\AdminBase;
use app\admin\service\Server;
use think\Db;
use my\RedisData;

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
// phpinfo();
        $redis = RedisData::getInstance();
        $version = Db::query('SELECT VERSION() AS ver');
        $Server = new Server();
        try {
            $CPU1= $Server->GetCPUUse();
            sleep(1);
            $CPU2= $Server->GetCPUUse();
        } catch (\Exception $e) {
        }

        $domains = ['cm.gitsite.net', 'h5.gitsite.net', 'esqueler.com', 'm.carpool.gitsite.net', 'gitsite.net'];
        // $res = $Server->getCertInfo('cm.gitsite.net');
        $domainsValidity = [];
        $cacheKey_domains = 'carpool_admin:domains_validity';
        $domainsValidity = $redis->cache($cacheKey_domains);
        if (empty($domainsValidity)) {
            foreach ($domains as $key => $value) {
                $res = $Server->getCertInfo($value);
                $data = [
                    'domain' => $domains[$key],
                    'validTo_t' => $res ? $res['validTo_time_t'] : 0,
                    'validFrom_t' => $res ? $res['validFrom_time_t'] : 0,
                    'validTo' => $res ? date('Y-m-d H:i', $res['validTo_time_t']) : '',
                    'validFrom' => $res ? date('Y-m-d H:i', $res['validFrom_time_t']) : '',
                ];
                $domainsValidity[] = $data;
            }
            $redis->cache($cacheKey_domains, $domainsValidity, 60 * 60 * 24 * 3);
        }
        foreach ($domainsValidity as $key => $value) {
            $surplus_t = $value['validTo_t'] - time();
            $domainsValidity[$key]['surplus_t'] = $surplus_t;
            $domainsValidity[$key]['surplus']  = floor($surplus_t / (60 * 60 * 24));
        }

        
        $config  = [
            'url'             => $_SERVER['HTTP_HOST'],
            'document_root'   => $_SERVER['DOCUMENT_ROOT'],
            'server_os'       => PHP_OS,
            'server_port'     => $_SERVER['SERVER_PORT'],
            'server_soft'     => $_SERVER['SERVER_SOFTWARE'],
            'php_version'     => PHP_VERSION,
            'mysql_version'   => $version[0]['ver'],
            'max_upload_size' => ini_get('upload_max_filesize'),
            'disk' => $Server->getDisk(),
            'memory' => $Server->getMemory(),
            'cpu' => isset($CPU1) && isset($CPU2) ? $Server->getCPUPercent($CPU1, $CPU2) : [],
            'domains_validity' => $domainsValidity,
        ];
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
        return $this->fetch('index', ['admin_user' => $admin_user]);
    }
}
