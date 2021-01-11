<?php

namespace app\admin\controller\mis\tvshow;

use app\admin\controller\AdminBase;
use app\mis\service\tvshow\Sql as SqlService;
use think\Db;

/**
 * tvshow/Config
 * Class Digital
 * @package app\admin\controller
 */

class Config extends AdminBase
{

    public function sql()
    {
        $key = input("param.key", '', 'trim');
        $SqlService = new SqlService();
        $checkKey = $SqlService->checkKey($key);
        $sql = '';
        $msg = '';
        if ($checkKey) {
            $sql =  $SqlService->getSql($key);
        } else {
            $msg = '该key不存在';
        }

        $returnData = [
            'sql' => $sql,
            'key' => $key,
            'msg' => $msg
        ];
        return $this->fetch('', $returnData);
    }

    public function sql_update()
    {
        $SqlService = new SqlService();

        if ($this->request->isPost()) {
            $sql = input("post.sql", '', 'trim');
            $key = input("post.key", '', 'trim');
            if (empty($key)) {
                return $this->jsonReturn(-1, '请填写key');
            }
            $checkKey = $SqlService->checkKey($key);
            if (!$checkKey) {
                return $this->jsonReturn(-1, 'KEY不存在');
            }
            if (empty($sql)) {
                return $this->jsonReturn(-1, '请填写sql');
            }
            $authRuleActionAll = "admin/mis.tvshow.config/sql_update__all";
            $authRuleAction = "admin/mis.tvshow.config/sql_update__$key";
            if (!($this->checkActionAuth($authRuleActionAll) || $this->checkActionAuth($authRuleAction))) {
                return $this->jsonReturn(-1, '你没有权限');
            }
            $SqlService->updateSql($key, $sql);
            return $this->jsonReturn(0, '更新成功');
        } else {
            return false;
        }
    }
}
