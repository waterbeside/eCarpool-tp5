<?php

namespace app\api\controller\v1\shuttle;

use app\api\controller\ApiBase;
use app\carpool\model\ShuttleTripPartner;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use my\RedisData;
use my\Utils;

use think\Db;

/**
 * 历史同行者
 * Class Partner
 * @package app\api\controller
 */
class Partner extends ApiBase
{


    public function my()
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $PartnerModel = new ShuttleTripPartner();
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $redis = new RedisData();
        $ex = 60 * 2;
        $cacheKey  = $PartnerModel->getCommonListCacheKey($uid);
        $listData = $redis->cache($cacheKey);

        if (is_array($listData) && empty($listData)) {
            return $this->jsonReturn(20002, lang('No data'));
        }

        if (!$listData) {
            $field = 'uid, max(name) as name, max(sex) as sex, max(create_time) as create_time, count(uid) as use_count';
            $map = [
                ['creater_id', '=', $uid],
                ['is_delete', '=', Db::raw(0)],
            ];
            $listData = $PartnerModel::alias('t')->field($field)->where($map)->group('uid')->limit(12)->order('create_time DESC, use_count DESC')->select();
            if (!$listData) {
                $redis->cache($cacheKey, [], 10);
                $this->jsonReturn(20002, 'No data');
            }
            $listData = $listData->toArray();
            $redis->cache($cacheKey, $listData, $ex);
        }
        $list = ShuttleTripService::getInstance()->formatTimeFields($listData, 'list', ['create_time']);
        $list = Utils::getInstance()->filterListFields($list, ['create_time', 'use_count'], true);
        $returnData = [
            'lists' => $list
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }
}
