<?php

namespace app\npd\model;

use think\Db;
use app\common\model\BaseModel;

class CpaDepartment extends BaseModel
{
    protected $connection = 'database_npd';
    protected $table = 't_cpa_department';
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
     * @param integer $did 部门id
     * @return string
     */
    public function getItemDidCacheKey($did)
    {
        return "npd:cpaDepartment:u_$did";
    }

    public function getItemByDid($did, $ex = 60 * 5)
    {
        $cacheKey = $this->getItemDidCacheKey($did);
        $res = $this->redis()->cache($cacheKey);
        if ($res === false || !$ex) {
            $map = [
                ['d_id','=', $did],
                ['is_delete','=', Db::raw(0)],
            ];
            $res = self::where($map)->find();
            if (is_numeric($ex) && $ex > 0) {
                $this->redis()->cache($cacheKey, $res, $ex);
            }
        }
        return $res;
    }

    /**
     * 检查是否允许登入
     *
     * @param mixed $path departmet_path string or array "0,1,10" or [0,1,10]
     * @return boolean
     */
    public function checkAccess($path)
    {
        $map = [
            ['d_id', 'in', $path],
            ['status','=', Db::raw(1)],
            ['is_delete','=', Db::raw(0)],
        ];
        $data = $this->where($map)->find();
        if (empty($data) || $data['is_delete'] == 1) {
            return $this->setError(10005, lang('Permission denied'));
        }
        return true;
    }

    /**
     * 添加授权部门
     *
     * @param array $dataList 用户数据列表
     * @return mixed
     */
    public function addByDataList($dataList)
    {
        $didArray = [];
        $upData = [];
        foreach ($dataList as $key => $value) {
            $didArray[] = $value['id'];
            $itemUpData = [
                'd_id' => $value['id'],
                'path' => $value['path'],
                'name' => $value['name'],
                'fullname' => $value['fullname'],
                'status' => 1,
                'is_delete' => 0,
            ];
            $upData[] = $itemUpData;
        }

        if (count($didArray) < 1) {
            return false;
        }
        $connection = $this->connection;
        Db::connect($connection)->startTrans();
        try {
            $this->where([['d_id', 'in', $didArray]])->delete();
            $res = $this->insertAll($upData);
            Db::connect($connection)->commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::connect($connection)->rollback();
            $errorMsg = $e->getMessage();
            return $this->setError(-1, $errorMsg, []);
        }
        return  $res;
    }
}
