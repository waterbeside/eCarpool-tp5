<?php

namespace app\common\model;

use think\Db;
use think\Model;

class AdminLog extends Model
{
    public function add($desc = '', $status = 2, $uid = 0)
    {
        $request = request();
        $data['uid'] = $uid;
        $data['ip'] = $request->ip();
        // $data['path'] = $request->path();
        $isAjaxShow =  $request->isAjax() ? " (Ajax)" : "";
        $data['type'] = $request->method() . "$isAjaxShow";
        $data['route'] = $request->module() . '/' . $request->controller() . '/' . $request->action();
        $data['query_string'] = $request->query();
        $data['description'] = $desc;
        $data['status'] = $status;
        $data['time'] = time();
        $this->insert($data);
    }
}
