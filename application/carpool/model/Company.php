<?php

namespace app\carpool\model;

use think\facade\Cache;
use think\Model;
use my\RedisData;

class Company extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'company';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'company_id';

    protected $insert = ['status' => 1];
    protected $update = [];



    /**
     * 取得公司列表
     *
     */
    public function getCompanys()
    {
        $lists_cache = Cache::tag('public')->get('companys');
        if ($lists_cache) {
            $lists = $lists_cache;
        } else {
            $lists = $this->order('company_id ASC , company_name ')->select();
            if ($lists) {
                Cache::tag('public')->set('companys', $lists, 3600 * 12);
            }
        }
        return $lists;
    }

    /**
     * 取得以指定字段为key的列表
     *
     * @param string $by 被指定为key的字段
     * @return array
     */
    public function getCompanyListBy($by = 'company_name')
    {
        $list = $this->getCompanys();
        $arr = [];
        foreach ($list as $key => $value) {
            $arr[$value[$by]] = $value;
        }
        return $arr;
    }

    /**
     * 通过地区名返回或创建公司id
     *
     * @param string $regionName 地区名
     * @return integer 公司id
     */
    public function bulidIdByRegion($regionName)
    {
        $CompanyList = $this->getCompanyListBy();
        if ($regionName == 'Gaoming') {
            return 1;
        }
        $company_name = "Esquel Group ({$regionName})";
        if (isset($CompanyList[$company_name])) {
            $company_id = $CompanyList[$company_name]['company_id'];
        } else {
            $company_id = $this->buildItemByName($company_name);
        }
        return $company_id;
    }

    /**
     * 通过公司名创建公司数据
     *
     * @param string $name  公司名
     * @return integer 公司id
     */
    public function buildItemByName($name)
    {
        $data = [
            'company_name' => $name,
            'country' => 'China',
        ];
        $company_id = $this->insertGetId($data);
        if ($company_id > 0) {
            Cache::tag('public')->rm('companys');
        }
        return $company_id;
    }
}
