<?php

namespace app\carpool\model;

use my\RedisData;
use app\common\model\BaseModel;
use app\user\model\Department as DepartmentModel;
use think\Db;

class ShuttleLineDepartment extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_shuttle_line_department';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'id';

    protected $insert = [];
    protected $update = [];

    
    /**
     * 取得允许相互拼车的部门路线
     *
     * @param integer $departmentIdOrData 部门id或部门data
     * @param boolean $buildSql     是否生成sql，否则返回line_id array
     * @return mixed
     */
    public function getIdsByDepartmentId($departmentIdOrData, $buildSql = false)
    {
        $DepartmentModel = new DepartmentModel();
        
        $departmentData = is_numeric($departmentIdOrData) ? $DepartmentModel->getItem($departmentIdOrData) : $departmentIdOrData;
        if (!$departmentData) {
            return $this->setError(20002, lang('No data'));
        }
        $departmentId = $departmentData['id'];
        $departmentPath = $departmentData['path'].','.$departmentId;
        $map_lineDept = [
            ['department_id','in', $departmentPath],
        ];
        if ($buildSql) {
            return $this->field('line_id')->distinct(true)->where($map_lineDept)->buildSql();
        } else {
            $cacheKey = "carpool:shuttle:getLineIds:did_$departmentId";
            $redis = $this->redis();
            $result = $redis->cache($cacheKey);
            $exp = 30;
            if (!$result || !is_array($result)) {
                $result = $this->field('line_id')->distinct(true)->where($map_lineDept)->column();
                $redis->cache($cacheKey, $result, $exp);
            }
            return $result;
        }
    }
}
