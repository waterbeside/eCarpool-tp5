<?php

namespace app\user\model;

use app\common\model\BaseModel;
use app\carpool\model\Company;
use my\RedisData;
use think\Db;

class Department extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_department';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    /**
     * 取得单项数据缓存key的默认值
     *
     * @param integer $id 表主键
     * @return string
     */
    public function getItemCacheKey($id)
    {
        return "carpool:department:{$id}";
    }
    
    /**
     * create_department_by_str 根据部门路径字符串添加部门到数据库，并返回最后部门id]
     * @param  string  $department_str 部门全称
     */
    public function create_department_by_str($department_str)
    {
        $company_id = 1;
        if (!$department_str) {
            return 0;
        }
        $rule_number_list = config('score.rule_number');
        //验证该部门是否已存在，是则直接返回；
        $department_str_path = str_replace('/', ',', $department_str);
        $checkPathName = $this->where([['fullname', '=', $department_str_path]])->find();
        if ($checkPathName) {
            return $checkPathName;
        }

        $array = explode('/', $department_str);

        //计算company_id;
        if (is_array($array) && count($array) > 1) {
            $CompanyModel = new Company();
            $company_id = $CompanyModel->bulidIdByRegion($array[1]);
            $company_id = $company_id ? $company_id : 1;
        }

        $lists = [];
        $ids = [];
        $integral_number = 0;
        foreach ($array as $key => $value) {
            $data = [
                'name' => $value,
                // 'name_en' => $value,
                'pid' => 0,
                'company_id' => $company_id,
                'status' => 1,
                'path' => '0',
                'fullname' => $value,
                // 'integral_number'=> $integral_number,
                'deep' => $key,
            ];
            $data_p = [];
            if ($key > 0) {
                $data_p = $lists[$key - 1];
                $data['pid']  = $data_p['id'];
                $data['path'] = $data_p['path'] . ',' . $data_p['id'];
                $data['fullname'] = $data_p['fullname'] . ',' . $value;
                // if($key === 1){
                //   foreach ($rule_number_list as $k => $v) {
                //     if(isset($v['region']) && $v['region'] && $v['region'] === $value){
                //       $integral_number = $k;
                //       break;
                //     }
                //   }
                // }
                // $data['integral_number'] = $integral_number;
            }
            $check  = $this->where([['name', '=', $value], ["pid", "=", $data['pid']]])->find();
            if (!$check) {
                $newId =   $this->insertGetId($data);
                if (!$newId) {
                    return false;
                }
                $data['id'] = $newId;
            } else {
                $data['id'] = $check['id'];
            }
            $ids[] = $data['id'];
            $lists[$key] = $data;
        }
        return end($lists);
    }

    /**
     * 格式化部门
     * @param  [type]  $fullNameStr [description]
     * @param  integer $type        0：返回数组， 1：返回分厂加公司名(string)，2：返回分厂加公司名缩写(string), 3: 返回分厂
     */
    public function formatFullName($fullNameStr, $type = 0)
    {
        if (!$fullNameStr) {
            return false;
        }
        if (is_numeric($fullNameStr)) {
            $fullName = $this->where("id", $fullNameStr)->cache(320)->value("fullname");
        } elseif (is_string($fullNameStr)) {
            $fullName = $fullNameStr;
        }
        if (!isset($fullName) || !$fullName) {
            return false;
        }
        $path_list = explode(',', $fullName);
        $departmentName_per = isset($path_list[3]) ?  $path_list[3] : "";
        $departmentName = isset($path_list[4]) ?  $path_list[4] : "";
        $region = isset($path_list[1]) ?  $path_list[1] : "";

        $returnData = [
            'region' => $region,
            'branch' => $departmentName_per,
            'department' => $departmentName,
            'format_name' => $departmentName_per . ',' . $departmentName,
            // 'fullname' => $fullName,
        ];
        if ($type == 1) {
            return $returnData['format_name'];
        }

        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $departmentName) > 0) {
            $returnData['short_name'] = $departmentName;
            return $returnData;
        }

        $departmentName_epl_1 = explode(" and ", $departmentName);

        $newName = "";
        foreach ($departmentName_epl_1 as $letters) {
            $departmentName_epl = explode(" ", $letters);
            $newName .= $newName ? ' and ' : '';
            if (count($departmentName_epl) > 1 && strlen($departmentName) > 16) {
                $shortName = "";
                foreach ($departmentName_epl as $key => $value) {
                    if (strpos($value, '(') === false && strlen($value) > 2) {
                        $shortName .= strtoupper($value{
                            0});
                    } else {
                        $shortName .= " " . $value;
                    }
                }
                $newName .= $shortName;
            } else {
                $newName .= $letters;
            }
        }
        $returnData['short_name'] = $newName;

        if ($type == 2) {
            return $returnData['branch'] . ',' . $returnData['short_name'];
        }
        if ($type == 3) {
            return $returnData['branch'];
        }
        if ($type == 4) {
            return $returnData['branch'] ? $returnData['branch'] : $path_list[0];
        }
        return $returnData;
    }

    public function itemChildrenCache($id, $value = false, $ex = 3600 * 24)
    {
        $cacheKey = "carpool:departmentChildrens:" . $id;
        return $this->redis()->cache($cacheKey, $value, $ex);
    }

    /**
     * 取单条数据
     */
    public function getItem($id, $fields = '*', $cache_time = 3600 * 24, $randomExOffset = [1,2,3], $hCache = false)
    {
        $res = parent::getItem($id, $fields, $cache_time, $randomExOffset, false);
        if ($res) {
            $res['department_format'] = $this->formatFullName($res['fullname']);
        }
        return $res;
    }

    /**
     * 取子部门id
     * @param  integer  $pid  父ID
     * @param  integer $cache_time 0时，从path字段截取，1时历遍取.
     * @return array        [id,id,id]
     */
    public function getChildrenIds($pid, $cache_time = 3600 * 12)
    {
        $ids = $this->itemChildrenCache($pid);
        if (!$ids || !$cache_time) {
            $map = [
                ['', 'exp', Db::raw("FIND_IN_SET($pid,path)")]
            ];
            $ids =  $this->where($map)->order('id asc')->column('id');
            if (!$ids) {
                return false;
            }
            $this->itemChildrenCache($pid, $ids, $cache_time);
        }
        return $ids;
    }

    public function excludeChildrens($ids)
    {
        $idsArray = explode(",", $ids);
        $idsArray = array_values(array_unique($idsArray));
        $childrenList = [];
        foreach ($idsArray as $key => $value) {
            $childrens = $this->getChildrenIds($value);
            $childrens = is_array($childrens) && $childrens ? $childrens : [];
            if (in_array($value, $childrenList)) {
                unset($idsArray[$key]);
            }
            $childrenList =  $childrens ? array_merge($childrenList, $childrens) : $childrenList;
        }
        foreach ($idsArray as $key => $value) {
            if (in_array($value, $childrenList)) {
                unset($idsArray[$key]);
            }
        }
        return $idsArray;
    }

    /**
     * 通过id 列表查找部门数据
     */
    public function getDeptDataIdList($ids, $field = null)
    {
        $deptsArray = is_array($ids) ? $ids : explode(',', $ids);
        $deptsData = [];
        foreach ($deptsArray as $key => $value) {
            $deptsItemData = $this->getItem($value);
            // field('id , path, fullname , name')->find($value);
            if ($field) {
                $fieldArray = explode(',', $field);
                $deptsItemData_n = [];
                foreach ($fieldArray as $f) {
                    $deptsItemData_n[$f] = isset($deptsItemData[$f]) ? $deptsItemData[$f] : null;
                }
                $deptsItemData  = $deptsItemData_n;
            }
            $deptsData[$value] = $deptsItemData ? $deptsItemData : [];
        }
        return $deptsData;
    }

    public function getDeptDataList($ids, $field = null)
    {
        $deptsArray = is_array($ids) ? $ids : explode(',', $ids);
        $deptsData = [];
        foreach ($deptsArray as $key => $value) {
            $deptsItemData = $this->getItem($value);
            // field('id , path, fullname , name')->find($value);
            if ($field) {
                $fieldArray = explode(',', $field);
                $deptsItemData_n = [];
                foreach ($fieldArray as $f) {
                    $deptsItemData_n[$f] = isset($deptsItemData[$f]) ? $deptsItemData[$f] : null;
                }
                $deptsItemData  = $deptsItemData_n;
            }
            $deptsData[] = $deptsItemData ? $deptsItemData : [];
        }
        return $deptsData;
    }

    /**
     * checkIsCheckLeave
     */
    public function checkIsCheckLeave($userData = [])
    {
        $department_id  = $userData['department_id'];
        $company_id     = $userData['company_id'];
        $department     = $userData['Department'];
        $check_companys = [1, 11, 12, 14, 15, 16, 17, 18];
        $check_Dpt = ['李宁', '高明常安花园', '高明一中', '佛山市政府', '中国联通', 'TEST'];
        if (!in_array($company_id, $check_companys) || in_array($department, $check_Dpt)) {
            return false;
        }
        //对部分Department_id 进行同步排除
        // $deptsItemData = $this->getItem($department_id);
        // if($deptsItemData){
        //   $path = $deptsItemData['path'];
        //   $path_arr = explode(',',$path);
        //   if(in_array('1740',$path_arr) || $department_id == '1740'){
        //     return false;
        //   }
        // }
        return true;
    }

    /**
     * 通过deep取得
     */
    public function getListByDeep($deep, $top_pid = 1)
    {
        $cacheKey = "carpool:department:list:php:deep_{$deep}_tpid_{$top_pid}";
        $redis = $this->redis();
        $cacheData = $redis->get($cacheKey);
        if ($cacheData) {
            $res = json_decode($cacheData, true);
            if ($res) {
                return $res;
            }
        }
        $map = [
            ['deep', '=', $deep],
            ['', 'exp', Db::raw("FIND_IN_SET($top_pid,path)")]
        ];
        $order = 'name ASC';
        $res = $this->where($map)->order($order)->select();
        if ($res) {
            $cacheData = $redis->setex($cacheKey, 3600, json_encode($res));
        }
        return $res;
    }

    /**
     * 取得指定深度的部门名
     *
     * @param array||integer $department 部门数据或id;
     * @return string
     */
    public function getDeepName($department, $deep = 3)
    {
        $data = null;
        if (is_array($department)) {
            $data = $department;
        } elseif (is_numeric($department)) {
            $data = $this->getItem($department);
        } else {
            return '';
        }
        if (!$data) {
            return '';
        }
        $fullname = $data['fullname'];
        $namePathArray = explode(',', $fullname);
        return isset($namePathArray[$deep]) ? $namePathArray[$deep] : '';
    }
}
