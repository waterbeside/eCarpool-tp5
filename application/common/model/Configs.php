<?php

namespace app\common\model;

use think\Model;
use think\facade\Cache;

class Configs extends Model
{


    public function getConfig($name)
    {
        $value = self::where("name", $name)->value('value');
        return $value;
    }

    public function getConfigs($group = null)
    {
        $where = [];
        if ($group) {
            $where[] = ["group", "=", $group];
        } else {
            $cache = Cache::get('configs');
            if ($cache) {
                return $cache;
            }
        }
        $res = self::where($where)->select();
        if (!$res) {
            return false;
        }
        $configs = [];
        foreach ($res as $key => $value) {
            $configs[$value['name']] = $value['value'];
        }
        if (!$group) {
            Cache::set('configs', $configs, 3600 * 24);
            return $configs;
        }
        return $configs;
    }
}
