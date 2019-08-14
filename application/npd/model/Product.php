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
        return $data;
    }
}
