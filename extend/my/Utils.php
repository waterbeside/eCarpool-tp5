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
}
