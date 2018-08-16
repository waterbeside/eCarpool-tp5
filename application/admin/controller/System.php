<?php
namespace app\admin\controller;

use think\facade\Env;
use app\common\controller\AdminBase;
use think\facade\Cache;
use think\Db;

/**
 * 系统配置
 * Class System
 * @package app\admin\controller
 */
class System extends AdminBase
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 站点配置
     */
    public function siteConfig()
    {
        $site_config = Db::name('system')->field('value')->where('name', 'site_config')->find();
        $site_config = unserialize($site_config['value']);

        return $this->fetch('site_config', ['site_config' => $site_config]);
    }

    /**
     * 更新配置
     */
    public function updateSiteConfig()
    {
        if ($this->request->isPost()) {
            $site_config                = $this->request->post('site_config/a');
            $site_config['site_tongji'] = htmlspecialchars_decode($site_config['site_tongji']);
            $data['value']              = serialize($site_config);
            if (Db::name('system')->where('name', 'site_config')->update($data) !== false) {
              $this->jsonReturn(0,'提交成功');
            } else {
              $this->jsonReturn(-1,'提交失败');
            }
        }
    }

    /**
     * 清除缓存
     */
    public function clear()
    {
        if (delete_dir_file(Env::get('runtime_path') . 'cache/') || delete_dir_file(Env::get('runtime_path'). 'temp/')) {
            $this->jsonReturn(0,'清除缓存成功');
        } else {
            $this->jsonReturn(-1,'清除缓存失败');

        }
    }
}
