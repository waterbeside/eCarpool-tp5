<?php
namespace app\index\controller;

use app\common\controller\HomeBase;
use think\Db;

class Index extends HomeBase
{
    public function index()
    {
      exit;
        return $this->fetch();
    }
}
