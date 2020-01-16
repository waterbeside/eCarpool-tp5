<?php

namespace app\carpool\service;

use app\common\service\Service;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\Address;
use app\carpool\model\Configs as ConfigsModel;
use app\carpool\model\User as UserModel;
use app\common\model\PushMessage;
use app\user\model\Department as DepartmentModel;
use think\Db;
use my\Utils;

class TripsMixed extends Service
{

    /**
     * 取得我的混合行程Cache Key
     *
     * @param integer $uid 用户id
     * @return string
     */
    public function getMyListCacheKey($uid)
    {
        return "carpool:mixTrip:my:{$uid}";
    }

    /**
     * 删除我的未来所有混合行程缓存
     *
     * @param integer $uid 用户id
     * @return string
     */
    public function delMyListCache($uid)
    {
        $cacheKey = $this->getMyListCacheKey($uid);
        $this->redis()->del($cacheKey);
    }
}
