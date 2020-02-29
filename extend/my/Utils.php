<?php

namespace my;

/**
 * 小工具
 */
class Utils
{

    protected static $instance;

    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }


    /**
     * 为数组元素添加字符串
     *
     * @param array $array 要处理的数组
     * @param string $preStr 要添加在开头的字符串
     * @param string $endStr 要添加在尾部的字符串
     * @param integer $keyDo 负数为转小写，正数为转大写，0为不处理
     * @return array
     */
    public function arrayAddString($array, $preStr = '', $endStr = '', $keyDo = 0)
    {
        foreach ($array as $k => $v) {
            $newKey = $keyDo > 0 ? strtoupper($k) : ($keyDo < 0 ? strtolower($k) : $k);
            $array[$newKey] = $preStr.$v.$endStr;
        }
        return $array;
    }


    /**
     * 筛选数据字类
     *
     * @param array $data 数据
     * @param array $filterFields 筛选的字段
     * @param boolean $notSet 参数2设定的字段是否作为排除用
     * @param string $keyFill 为字段添加前缀
     * @param integer $keyDo 负数为转小写，正数为转大写，0为不处理
     * @return array
     */
    public function filterDataFields($data, $filterFields = [], $notSet = false, $keyFill = '', $keyDo = 0)
    {
        if (empty($data)) {
            return $data;
        }
        $filterFields = is_string($filterFields) ? array_map('trim', explode(',', $filterFields)) : $filterFields ;
        if (!empty($filterFields) && is_array($filterFields)) {
            $newData = [];
            foreach ($filterFields as $k => $field) {
                if ($notSet) {
                    unset($data[$field]);
                } else {
                    $newData[$field] = $data[$field] ?? null;
                }
            }
            $data = $notSet ? $data : $newData;
        }
        if (is_string($keyFill) && (!empty($keyFill) || $keyDo !== 0)) {
            foreach ($data as $k => $value) {
                $newKey = $keyDo > 0 ? strtoupper($k) : ($keyDo < 0 ? strtolower($k) : $k);
                $data[$keyFill.$newKey] = $value;
                if ($k != $keyFill.$newKey) {
                    unset($data[$k]);
                }
            }
        }
        return $data;
    }

    /**
     * 筛选列表字段
     *
     * @param array $data 数据
     * @param array $filterFields 筛选的字段
     * @param boolean $notSet 参数2设定的字段是否作为排除用
     * @param string $keyFill 为字段添加前缀
     * @param integer $keyDo 负数为转小写，正数为转大写，0为不处理
     * @return array
     */
    public function filterListFields($list, $filterFields = [], $notSet = false, $keyFill = '', $keyDo = 0)
    {
        foreach ($list as $key => $value) {
            $itemData = self::filterDataFields($value, $filterFields, $notSet, $keyFill, $keyDo);
            $list[$key] = $itemData;
        }
        return $list;
    }


    /**
     * 把 "1,2,3" 处理为 [1,2,3], 把1
     *
     * @param mixed $data string,integer,array
     * @param string $mapFun 处理元素的函数
     * @param boolean $unique 是否设置不重复，默认为是
     * @return array
     */
    public function stringSetToArray($data, $mapFun = null, $unique = true)
    {
        $data = is_string($data) ? array_map('trim', explode(',', $data)) :
        (
            is_numeric($data) ? [$data] :
            (is_array($data) ? array_map('trim', $data) : [])
        );
        $data = $unique ? array_unique($data) : $data;
        if ($mapFun) {
            $data = array_map($mapFun, $data);
        }
        return $data;
    }

    /**
     * 格式化时间字段
     *
     * @param array $data 要处理的数据
     * @param string $dataType 处理的数据格式类型 [list or item]
     * @param mixed $fields 要处理的字段 可为string||array
     * @return array
     */
    public function formatTimeFields($data, $dataType = 'item', $fields = ['updata_time', 'create_time'])
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        if ($dataType == 'list') {
            foreach ($data as $key => $value) {
                $data[$key] = $this->formatTimeFields($value, 'item', $fields);
            }
        } else {
            foreach ($fields as $key => $value) {
                if (isset($data[$value])) {
                    $data[$value] = is_numeric($data[$value]) ? $data[$value] : strtotime($data[$value]);
                }
            }
        }
        return $data;
    }

    /**
     * 从列表抽取某字段组成数组
     *
     * @param array $list 列表
     * @param string $field 字段名
     * @return array
     */
    public function getListColumn($list, $field)
    {
        $returnData = [];
        foreach ($list as $key => $value) {
            if (isset($value[$field])) {
                $returnData[] = $value[$field];
            }
        }
        return $returnData;
    }

    /**
     * list_sort_by()对查询结果集进行排序
     * @param array $list 查询结果
     * @param string $field 排序的字段名
     * @param array $sortby 排序类型
     * asc正向排序 desc逆向排序 nat自然排序
     * @return array
     */
    public function listSortBy($list, $field, $sortby = 'asc')
    {
        if (is_array($list)) {
            $refer = $resultSet = array();
            foreach ($list as $i => $data) {
                $refer[$i] = &$data[$field];
            }
            switch ($sortby) {
                case 'asc': // 正向排序
                    asort($refer);
                    break;
                case 'desc': // 逆向排序
                    arsort($refer);
                    break;
                case 'nat': // 自然排序
                    natcasesort($refer);
                    break;
            }
            foreach ($refer as $key => $val) {
                $resultSet[] = &$list[$key];
            }
            return $resultSet;
        }
        return $list;
    }

    /**
     * 列表数组排序
     *
     * @param array $list 列表
     * @param array $sortRule 排序规则 格式 ['field1'=>'ASC','field2'=>'DESC']
     * @return array 返回重新排序后的列表
     */
    public function listSort($list, $sortRule = [])
    {
        $fieldsRuleArray = []; //提取字段值
        foreach ($list as $key => $row) {
            foreach ($sortRule as $field => $sort) {
                $fieldsRuleArray[$field][$key] = $row[$field];
            }
        }
        $sortParams = [];
        $sortNames = [
            'ASC' => SORT_ASC,
            'DESC' => SORT_DESC,
        ];
        foreach ($fieldsRuleArray as $key => $value) {
            $sortParams[] = $fieldsRuleArray[$key];
            $sortName = $sortRule[$key] ?? SORT_ASC;
            if (!in_array($sortName, [SORT_ASC, SORT_DESC]) && is_string($sortName)) {
                $sortName =  strtoupper($sortRule[$key] ?? 'ASC');
                $sortName = $sortNames[$sortName] ?? SORT_ASC;
            }
            $sortParams[] = $sortName ;
        }
        $sortParams[] = &$list;
        if (count($sortParams) > 0) {
            call_user_func_array('array_multisort', $sortParams);
            // array_multisort(...$sortParams);
        }
        
        // array_multisort($fieldsRuleArray['line_sort'], SORT_ASC, $fieldsRuleArray['distance'], SORT_DESC, $list);

        // var_dump(...$sortParams);
        // array_multisort(...$sortParams);
        return $list;
    }




    /**
     * 通过数据构造器取数据
     */
    public function getListDataByCtor($ctor, $pagesize = 0)
    {
        if ($pagesize > 0) {
            $results =    $ctor->paginate($pagesize, false, ['query' => request()->param()])->toArray();
            $resData = $results['data'] ?? [];
            $pageData = $this->getPageData($results);
        } else {
            $resData =    $ctor->select();
            $resData = $resData ? $resData->toArray() : [];
            $total = count($resData);
            $pageData = [
                'total' => $total,
                'pageSize' => 0,
                'lastPage' => 1,
                'currentPage' => 1,
            ];
        }
        $returnData = [
            'lists' => $resData,
            'page' => $pageData,
        ];
        return $returnData;
    }

    /**
     * 取得分页数据
     */
    public function getPageData($results)
    {
        return [
            'total' => $results['total'] ?? 0,
            'pageSize' => $results['per_page'] ?? 1,
            'lastPage' => $results['last_page'] ?? 1,
            'currentPage' => intval($results['current_page']) ?? 1,
        ];
    }

    /**
     * 通过经续度查询直线距离
     *
     * @param mixed $lng1 start_lng or [start_lng, start_lat]
     * @param mixed $lat1 start_lat or [end_lng, end_lat]
     * @param float $lng2 end_lng or null
     * @param float $lat2 end_lat or null
     * @return integer
     */
    public function getDistance($lng1, $lat1, $lng2 = null, $lat2 = null)
    {
        if (is_array($lng1) && is_array($lat1)) {
            if (count($lng1) < 2 || count($lat1) < 2) {
                return false;
            }
            $start = $lng1;
            $end = $lat1;
            $lng1 = $start[0];
            $lat1 = $start[1];
            $lng2 = $end[0];
            $lat2 = $end[1];
        }
        //将角度转为狐度
        $radLat1=deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2=deg2rad($lat2);
        $radLng1=deg2rad($lng1);
        $radLng2=deg2rad($lng2);
        $a=$radLat1-$radLat2;
        $b=$radLng1-$radLng2;
        $s= 2*asin(sqrt(pow(sin($a/2), 2)+cos($radLat1)*cos($radLat2)*pow(sin($b/2), 2)))*6371*1000;//计算出来的结果单位为米
        return floor($s);
    }

    /**
     * 取得计算经纬度距离字段的sql
     *
     * @param string $fieldLng 经度字段名
     * @param string $fieldlat 纬度字段名
     * @param string $lng 用户经度
     * @param string $lat 用户纬度
     * @return string
     */
    public function getDistanceFieldSql($fieldLng, $fieldlat, $lng, $lat, $asName = 'distance', $type = 0)
    {
        if ($type == 0) {
            $radius = 6371 * 1000;
            $sql = " (ST_DISTANCE_SPHERE(point({$fieldLng}, {$fieldlat}), point({$lng}, {$lat}), {$radius})) AS {$asName} ";
        } else {
            $sql = "ROUND(
                6371 * 2 * ASIN(
                    SQRT(
                        POW(SIN(({$lat} * PI() / 180 - {$fieldlat} * PI() / 180) / 2), 2) 
                        + COS({$lat} * PI() / 180) * COS({$fieldlat} * PI() / 180) * POW(SIN(({$lng} * PI() / 180 - {$fieldLng} * PI() / 180) / 2), 2)
                    )
                ) * 1000
            ) AS {$asName}";
        }
        return $sql;
    }

    /**
     * 跟据中心座标和距离计算四角经纬度
     *
     * @param double $lng 经度
     * @param double $lat 纬度
     * @param integer $distance 距离(单位：米)
     * @return void
     */
    public function getSquareCoord($lng, $lat = null, $distance = 1000)
    {
        $lnglatOffset = $this->getOffsetCoord($lng, $lat, $distance);
        $dlng = $lnglatOffset[0];
        $dlat = $lnglatOffset[1];
        return [
            'lt' => [$lng - $dlng, $lat + $dlat],
            'rt' => [$lng + $dlng, $lat + $dlat],
            'lb' => [$lng - $dlng, $lat - $dlat],
            'rb' => [$lng + $dlng, $lat - $dlat]
        ];
    }


    /**
     * 根据距离计算经纬度偏移值
     *
     * @param double $lng 经度
     * @param double $lat 纬度
     * @param integer $distance 距离(单位：米)
     * @return array [lng,lat]
     */
    public function getOffsetCoord($lng, $lat = null, $distance = 1000)
    {
        if (is_array($lng)) {
            if (count($lng) < 2) {
                $lng = $lng[0];
            }
            $lat = $lng[1];
            $lng = $lng[0];
        }
        $radius = 6371 * 1000;
        $dlng = 2 * asin(sin($distance / (2 * $radius)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);
        $dlat = $distance / $radius;
        $dlat = rad2deg($dlat);
        return [$dlng, $dlat];
    }

    /**
     * 根据距离生成经纬范围的sql where
     *
     * @param string $fieldLng 经度字段名
     * @param string $fieldlat 纬度字段名
     * @param string $lng 用户经度
     * @param string $lat 用户纬度
     * @param integer $distance 距离(单位：米)
     * @return string
     */
    public function buildCoordRangeWhereSql($fieldLng, $fieldlat, $lng, $lat, $distance = 1000)
    {
        $lnglatOffset = $this->getOffsetCoord($lng, $lat, $distance);
        $dlng = $lnglatOffset[0];
        $lngS = $lng - $dlng;
        $lngE = $lng + $dlng;
        $dlat = $lnglatOffset[1];
        $latS = $lat - $dlat;
        $latE = $lat + $dlat;
        $sql = "({$fieldLng} between {$lngS} AND {$lngE} AND {$fieldlat} between {$latS} AND {$latE} )";
        return $sql;
    }

    /**
     * 把多个字段包装到一个数组字段
     *
     * @param array $data 要处理的数组
     * @param array $fieldsRule 要包装的字段名 [newkey=>[field1,field2,'field3'=>'field3_fromat']]
     * @param integer $returnDataType 0返回包装后的所有，1仅返会被包装的数据
     * @return array
     */
    public function packFieldsToField($data, $fieldsRule, $returnDataType = 0)
    {
        $newKeyDatas = [];
        foreach ($fieldsRule as $newkey => $fieldArray) {
            $fieldnames = [];
            foreach ($fieldArray as $key => $value) {
                $fieldnames[$key] = !is_numeric($key) ? $key : $value;
            }
            $newKeyData = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $fieldnames)) {
                    $fieldname = $fieldArray[$key] ?? $key;
                    $newKeyData[$fieldname] = $value;
                    unset($data[$key]);
                }
            }
            $newKeyDatas[] = $newKeyData;
            $data[$newkey] = $newKeyData;
        }
        return $returnDataType ? $newKeyDatas : $data;
    }

    /**
     * 格式化字段类型
     *
     * @param array $data 要处理的数据
     * @param array $fieldTypeRule 处理规则 如 ['sex'=>'int']
     * @param string $dataType item or list
     * @return array
     */
    public function formatFieldType($data, $fieldTypeRule, $dataType = 'item')
    {
        if ($dataType === 'list') {
            foreach ($data as $key => $value) {
                $data[$key] = $this->formatFieldType($value, $fieldTypeRule, 'item');
            }
        } else {
            foreach ($data as $key => $value) {
                if (isset($fieldTypeRule[$key])) {
                    $type = $fieldTypeRule[$key];
                    if ($type === 'int') {
                        $data[$key] = intval($value);
                    } elseif ($type === 'float') {
                        $data[$key] = floatval($value);
                    } elseif ($type === 'string') {
                        $data[$key] = strval($value);
                    } elseif (is_array($type) && $type[0] == 'array') {
                        $sp = $type[1] ?? ',';
                        $data[$key] = explode($sp, $value);
                    } elseif (is_array($type) && $type[0] == 'string') {
                        $sp = $type[1] ?? ',';
                        $data[$key] = implode($sp, $value);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 更改数组key名称
     *
     * @param array $data 要处理的数据
     * @param array $keyRule 处理规则 如 ['uid'=>'u_uid']
     * @param string $dataType item or list
     * @return array
     */
    public function changeArrayKeyName($data, $keyRule, $dataType = 'item')
    {
        if ($dataType === 'list') {
            foreach ($data as $key => $value) {
                $data[$key] = $this->changeArrayKeyName($value, $keyRule, 'item');
            }
        } else {
            foreach ($data as $key => $value) {
                if (isset($keyRule[$key])) {
                    $newKey = $keyRule[$key];
                    $data[$newKey] = $value;
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /**
     * 生成随机16进制颜色
     *
     * @return string
     */
    public function randColor()
    {
        $str='0123456789ABCDEF';
        $estr='#';
        $len=strlen($str);
        for ($i=1; $i<=6; $i++) {
            $num = rand(0, $len-1);
            $estr = $estr.$str[$num];
        }
        return $estr;
    }

    /**
     * json to arrayy
     *
     * @param string $json Json string
     * @param boolean $noForceToArray 不强行返数组
     * @return array
     */
    public function json2Array($json, $noForceToArray = false)
    {
        if ($json === false) {
            return $noForceToArray ? false : [];
        }
        try {
            $res = json_decode($json, true);
        } catch (\Exception $e) {
            $res = $noForceToArray ? $json : [];
        }
        return $res;
    }
}
