<?php

namespace app\npd\model;

use think\Db;
use app\common\model\BaseModel;

class CpaUser extends BaseModel
{
    protected $connection = 'database_npd';
    protected $table = 't_cpa_user';
    protected $pk = 'id';

    protected $insert = ['create_time'];

    /**
     * 自动生成时间
     * @return bool|string
     */
    protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * 取得单项数据缓存Key设置
     *
     * @param integer $id 主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "npd:cpaUser:pk_$id";
    }

    /**
     * 取得单项数据缓存Key设置
     *
     * @param integer $id 用户uid
     * @return string
     */
    public function getItemUidCacheKey($id)
    {
        return "npd:cpaUser:u_$id";
    }

    public function getItemByUid($uid, $ex = 60 * 5)
    {
        $cacheKey = $this->getItemUidCacheKey($uid);
        $res = $this->redis()->cache($cacheKey);
        if ($res === false || !$ex) {
            $map = [
                ['uid','=', $uid],
                ['is_delete','=', Db::raw(0)],
            ];
            $res = self::where($map)->find();
            if (is_numeric($ex) && $ex > 0) {
                $this->redis()->cache($cacheKey, $res, $ex);
            }
        }
        return $res;
    }

    public function delItemUidCache($uid)
    {
        $cacheKey = $this->getItemUidCacheKey($uid);
        return $this->redis()->del($cacheKey);
    }

    /**
     * 检查是否允许登入
     *
     * @param integer $id 用户uid
     * @return boolean
     */
    public function checkAccess($uid)
    {
        $data = $this->getItemByUid($uid);
        if (empty($data) || $data['status'] == 0) {
            return $this->setError(10005, lang('Permission denied'), $data);
        }
        return $data;
    }

    /**
     * 添加授权用户
     *
     * @param array $dataList 用户数据列表
     * @return mixed
     */
    public function addByDataList($dataList)
    {
        $uidArray = [];
        $upData = [];
        foreach ($dataList as $key => $value) {
            $uidArray[] = $value['uid'];
            $itemUpData = [
                'uid' => $value['uid'],
                'loginname' => $value['loginname'],
                'nativename' => $value['nativename'],
                'email' => $value['mail'],
                'department_id' => $value['department_id'],
                'department_fullname' => $value['department_fullname'],
                'status' => 1,
                'is_delete' => 0,
            ];
            $upData[] = $itemUpData;
        }

        if (count($uidArray) < 1) {
            return false;
        }
        $connection = $this->connection;
        Db::connect($connection)->startTrans();
        try {
            $this->where([['uid', 'in', $uidArray]])->delete();
            $res = $this->insertAll($upData);
            Db::connect($connection)->commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::connect($connection)->rollback();
            $errorMsg = $e->getMessage();
            return $this->setError(-1, $errorMsg, []);
        }
        if ($res !== false) {
            foreach ($upData as $key => $value) {
                $this->delItemUidCache($value['uid']);
            }
        }
        return  $res;
    }
}
