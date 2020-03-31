<?php

namespace app\npd\controller\api\v1;

use think\Db;
use app\npd\controller\api\NpdApiBase;
use app\npd\model\Gallery as GalleryModel;
use my\RedisData;

/**
 * Api Gallery
 * Class Gallery
 * @package app\npd\controller\api\v1
 */
class Gallery extends NpdApiBase
{
    public function index($model = null, $aid = 0)
    {
        if (!$model) {
            return $this->jsonReturn(992, 'Param error');
        }
        $redis = RedisData::getInstance();
        $cacheKey = "NPD:gallery:m_{$model}_aid_{$aid}";
        $list = $redis->cache($cacheKey);
        if (!$list) {
            $where = [
                ['status', '=', 1],
                ['is_delete', '=', Db::raw(0)],
                ['model', '=', $model],
            ];
            if (is_numeric($aid) && $aid > 0) {
                $where[] = ['aid', '=', $aid ];
            }
            $list = GalleryModel::where($where)->order('sort DESC, id DESC')->select()->toArray();
            if ($list) {
                $redis->cache($cacheKey, $list, 60 * 3);
            }
        }
        $returnData = [
            // 'list' => $list,
            'list' => $this->replaceAttachmentDomain($list, 'url'),
        ];
        return $this->jsonReturn(0, $returnData, 'Succefully');
    }
}
