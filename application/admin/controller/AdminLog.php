<?php
namespace app\admin\controller;

use app\common\model\AdminLog as AdminLogModel;
use app\admin\controller\AdminBase;
use think\facade\Validate;
use think\facade\Config;
use think\Db;

/**
 * 公司管理
 * Class Department
 * @package app\admin\controller
 */
class AdminLog extends AdminBase
{
    protected $company_model;

    protected function initialize()
    {
        parent::initialize();
        $this->log_model = new AdminLogModel();
    }

    /**
     * 后台日志
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($keyword = '', $page = 1)
    {
        $map = [];
        if ($keyword) {
            $map[] = ['route|description|ip','like', "%{$keyword}%"];
        }
        $join = [
          ['admin_user u','l.uid = u.id'],
        ];
        $fields = 'l.*, u.username, u.nickname ';

        $lists = $this->log_model->alias('l')->join($join)->where($map)->order('time DESC , id DESC ')->field($fields)->paginate(50, false,['query'=>['keyword'=>$keyword]]);

        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword]);
    }



}
