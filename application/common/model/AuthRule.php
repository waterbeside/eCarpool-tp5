<?php

namespace app\common\model;

use think\Db;
use think\Model;

class AuthRule extends Model
{

    /**
     * 取得所有子菜单id
     * @param  integer $pid  当前菜单id
     * @param  integer $deep  0 包抱当前菜单id， 1不包括
     */
    public function getChildrensId($pid = 0, $deep = 0)
    {
        $data = $this->where([['pid', '=', $pid]])->column('id');
        foreach ($data as $key => $value) {
            $children_next = $this->getChildrensId($value, $deep + 1);
            if ($children_next) {
                $data = array_merge($data, $children_next);
            }
        }
        return  $deep ? $data : array_merge($data, [intval($pid)]);
    }
}
