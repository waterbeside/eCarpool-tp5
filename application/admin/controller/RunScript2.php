<?php

namespace app\admin\controller;

use app\carpool\model\User as UserModel;
use app\admin\controller\AdminBase;
use app\user\service\DepartmentIm as DepartmentImService;
use Firebase\JWT\JWT;
use my\RedisData;
use my\Queue;
use think\Db;

/**
 * RunScript
 * Class RunScript
 * @package app\admin\controller
 */
class RunScript2 extends AdminBase
{

    protected function initialize()
    {
        // exit;
        parent::initialize();
    }


    /**
     * 更正 GEK项目管理系统的NT账号信息
     * @return void
     */
    public function corr_user_info()
    {
        exit;
        $list =  Db::connect('database_project')->table('temp_user')->where('flag', Db::raw(0))->select();
        foreach ($list as $key => $value) {
            $dnArray = explode('/', $value['display_name']);
            $accountData = Db::connect('database_project')->table('project_user')->where('account', $value['account'])->find();
            if ($accountData) {
                $deparment = isset($dnArray[3]) ? trim($dnArray[3]) : ( isset($dnArray[2]) ? trim($dnArray[2]) : '');
                $upData = [
                    'account' => $value['account'],
                    'unit' => 'GEK',
                    'department' => $deparment,
                    'email' => $value['mail'],
                    'email_show' => $value['display_name'],
                    'post' => $value['title'],
                    'office_address' => 'GEK',
                    'nickname' => $dnArray[0],
                    'update_time' => time(),
                    'status' => 1,
                ];
                $res = Db::connect('database_project')->table('project_user')->where('account', $value['account'])->update($upData);
                $flag = $res !== false ? 1 : -1;
                Db::connect('database_project')->table('temp_user')->where('account', $value['account'])->update(['flag'=>$flag]);
                dump($value['account'].':'.$flag);
            }
        }
    }
}
