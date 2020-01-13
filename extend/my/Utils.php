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
     * @return void
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
                unset($data[$k]);
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
     * @return array
     */
    public function stringSetToArray($data, $mapFun = null)
    {
        $data = is_string($data) ? array_map('trim', explode(',', $data)) :
        (
            is_numeric($data) ? [$data] :
            (is_array($data) ? array_map('trim', $data) : [])
        );
        $data = array_unique($data);
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
}
