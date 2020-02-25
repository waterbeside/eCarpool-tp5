<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;
use app\npd\model\ProductData;
use app\npd\model\ProductMerchandizing;
use app\npd\model\ProductPatent;

class Product extends Model
{
    protected $insert = ['create_time'];

    protected $connection = 'database_npd';
    protected $table = 't_product';
    protected $pk = 'id';


    public function getDetail($id)
    {
        $data     = $this->find($id);
        if (!$data) {
            return null;
        }
        $data['data_zh']  = ProductData::where([['pid', '=', $id], ['lang', '=', 'zh-cn']])->find();
        $data['data_en']  = ProductData::where([['pid', '=', $id], ['lang', '=', 'en']])->find();
        $data['merchandizing']   = ProductMerchandizing::where([['pid', '=', $id]])->select();
        $data['patent']          = ProductPatent::where([['pid', '=', $id]])->select();
        $patentTypeList = config('npd.patent_type');
        $patentTypeNameList = [];
        foreach ($patentTypeList as $key => $value) {
            $patentTypeNameList[$value['name']] = $value['name_en'];
        }
        foreach ($data['patent'] as $key => $value) {
            $data['patent'][$key]['type_name_en'] = $patentTypeNameList[$value['type_name']] ?? $value['type_name'];
        }

        
        $data['data_zh']['extra_info'] = $data['data_zh']['extra_info'] ? json_decode($data['data_zh']['extra_info'], true) : null;
        $data['data_en']['extra_info'] = $data['data_en']['extra_info'] ? json_decode($data['data_en']['extra_info'], true) : null;
        return $data;
    }
}
