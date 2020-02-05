<?php
namespace app\carpool\service\nmtrip;

use app\common\service\Service;
use app\carpool\model\User as UserModel;
use app\carpool\service\Trips as TripsService;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\user\model\Department as DepartmentModel;
use my\RedisData;
use my\Utils;
use think\Db;

class Trip extends Service
{

    /**
     * 取得乘客列表
     *
     * @param integer $id 行程id
     * @param array $userFields 要读出的用户字段
     * @param array $tripFields 要读出的行程字段
     * @param string $as 字段的补充前缀
     * @param integer $getFullDepartment 是否取得完整部门数据
     *
     * @return array
     */
    public function passengers($id = 0, $userFields = [], $tripFields = [], $as = 'u', $getFullDepartment = 0)
    {
        if (!$id) {
            $this->error(992, lang('Error Param'));
            return [];
        }

        $InfoModel = new InfoModel();
        $Utils = new Utils();
        $res = $InfoModel->passengers($id);
        if (empty($res)) {
            $this->error(20002, 'No data');
            return [];
        }
        $tripFields = !empty($tripFields) && is_string($tripFields) ? array_map('trim', explode(',', $tripFields)) : $tripFields;
        if ($userFields !== false) {
            $userFields = $userFields ?: $this->defaultUserFields;
            $userFields = is_string($userFields) ? array_map('trim', explode(',', $userFields)) : $userFields;
            $UserModel = new UserModel();
            $DepartmentModel = new DepartmentModel();
            foreach ($res as $key => $value) {
                $userData = $UserModel->getItem($value['passengerid']);
                $departmentId = $userData['department_id'] ?: 0;
                if ($getFullDepartment > 0) {
                    $departmentData = $departmentId ? $DepartmentModel->getItem($departmentId) : null;
                    if ($getFullDepartment == 1) {
                        $userFields[] = 'full_department';
                        $userData['full_department'] = $departmentData ? $departmentData['fullname'] : '';
                    } else {
                        $userFields[] = 'department_data';
                        $userData['department_data'] = $departmentData;
                    }
                }
                $userData = $Utils->filterDataFields($userData, $userFields, false, "{$as}_", 0);

                $res[$key] = array_merge($value, $userData);
            }
            $userFields_fill = $Utils->arrayAddString($userFields, "{$as}_", '', -1);
            $filterFields = array_merge($tripFields, $userFields_fill ?: []);
        } else {
            $filterFields = $tripFields;
        }
        $res = $Utils->formatFieldType($res, ["{$as}_sex"=>'int', "{$as}_company_id"=>'int'], 'list');
        $res = $Utils->filterListFields($res, $filterFields, false, '', -1);
        return $res ?: [];
    }
}
