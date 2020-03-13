<?php
namespace app\carpool\service\nmtrip;

use app\common\service\Service;
use app\carpool\model\User as UserModel;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\nmtrip\Trip as NmTripService;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\user\model\Department as DepartmentModel;
use my\RedisData;
use my\Utils;
use think\Db;

class TripList extends Service
{

    public $defaultUserFields = [
        'uid', 'loginname', 'name','nativename', 'phone', 'mobile', 'Department', 'sex',
        'company_id', 'department_id', 'companyname', 'imgpath', 'carcolor', 'im_id'
    ];

    /**
     * 推荐列表
     *
     * @param integer $user_type 用户类型 1司机，0乘客
     * @param array   $userData  用户数据
     * @param array $extData 扩展数据
     * @return array
     */
    public function lists($user_type, $userData = null, $extData = null)
    {
        $Utils = new Utils();
        $user_type = intval($user_type);
        $limit = ( $extData['limit'] ?? 0 ) ?: 0;
        $pagesize = ( $extData['limit'] ?? 0 ) ?: 0;
        $map_type = $extData['map_type'] ?? 0;
        $showPassenger = ( $extData['show_passenger'] ?? 0 ) ?: 0;
        $page = input('get.page', 1);

        $userData = $userData ?: (( $extData['userData'] ?? null ) ?: null);
        $uid = $userData['uid'] ?? 0;
        $offsetTimeArray = [
            date('YmdHi', time() - (5 * 60)),
            date('YmdHi', time() + (60 * 60 * 24 * 2))
        ];
        $TripsService = new TripsService();
        $NmTripService = new NmTripService();
        $InfoModel = new InfoModel();
        $WallModel = new WallModel();
        $cacheKey = $user_type === 1 ?
            $WallModel->getListCacheKey($userData['company_id']) : $InfoModel->getRqListCacheKey($userData['company_id']);
        $rowCacheKey = "tzList,limit{$limit},pz{$pagesize},p{$page},mapType_{$map_type}";
        $returnData = $this->redis()->hCache($cacheKey, $rowCacheKey);
        if (!is_array($returnData)) {
            $userDefaultFields = [ 'uid', 'loginname', 'name','nativename',  'Department', 'sex', 'company_id', 'department_id', 'imgpath', 'carnumber'];
            if ($user_type === 1) { // 如果是墙上空座位
                $map = [
                    ['t.status', 'in', [0,1]],
                    ['t.time', 'between', $offsetTimeArray],
                ];
                $map[] = $TripsService->buildCompanyMap($userData, 'u');

                $fields = $TripsService->buildQueryFields('wall_list_tz', 't', ['d'=>'u'], false, 1);
                $fields.= ", 'wall' as `from`, 0 as line_type, 0 as line_sort, 1 as user_type , 0 as time_offset";
                $fields .=  ',' .$TripsService->buildUserFields('u', $userDefaultFields);
                $join = $TripsService->buildTripJoins("s, e, d", 't', ['d'=>'u']);
                $ctor = $WallModel->alias('t')->field($fields)->join($join)->where($map)->order('t.time ASC');
                // var_dump($ctor);exit;
            } else { // 如果是约车需求
                // $company_id = $userData['company_id'];
                $offsetTimeArray[0] = date('YmdHi', time() + 30);
                $map = [
                    ['t.status', 'in', [0,1]],
                    ['t.carownid', '<', 1],
                    ['t.time', 'between', $offsetTimeArray],
                ];
                $map[] = $TripsService->buildCompanyMap($userData, 'u');
                $fields = $TripsService->buildQueryFields('info_list_tz', 't', ['p'=>'u'], false, 1);
                $fields.= ", 'info' as `from`, 0 as line_id, 0 as line_sort, 0 as user_type, 1 as seat_count";
                $fields .=  ',' .$TripsService->buildUserFields('u', $userDefaultFields);
                $join = $TripsService->buildTripJoins("s, e, p", 't', ['p'=>'u']);
                $ctor = $InfoModel->alias('t')->field($fields)->join($join)->where($map)->order('t.time ASC');
            }
            if ($limit) {
                $list = $ctor->limit($limit)->select();
                $list = $list ? $list->toArray() : [];
                $isEmpty  = empty($list);
            } else {
                $returnData = $Utils->getListDataByCtor($ctor, $pagesize);
                $list = $returnData['lists'];
                $isEmpty  = empty($returnData['lists']);
            }
            foreach ($list as $key => $value) {
                $value = $this->formatListItemLineData($value);
                $value['time'] = strtotime($value['time'].'00');
                // $value['time_od'] = strtotime(date('Y-m-d H', $value['time']).':00:00');
                $value['create_time'] = strtotime($value['subtime'].'00');
                $value['trip_id'] = $value['love_wall_ID'] ?? 0;
                if ($user_type == 1) {
                    $value['plate'] = $value['u_carnumber'] ?? '';
                    $value['took_count'] =  $InfoModel->countPassengers($value['id']);
                    if ($showPassenger) {
                        $userFields = ['uid', 'loginname', 'name', 'sex'];
                        $value['passengers'] = $NmTripService->passengers($value['id'], $userFields, ['id','status','time'], 'u', 0) ?: [];
                    }
                }
                unset($value['subtime']);
                unset($value['u_carnumber']);
                unset($value['love_wall_ID']);
                $list[$key] = $value;
            }
            $returnData['lists'] = $list;
            $exp = $isEmpty ? 10 : 20;
            $this->redis()->hCache($cacheKey, $rowCacheKey, $returnData, $exp);
        }
        return $returnData;
    }

    public function formatListItemLineData($data)
    {
        $Utils = new Utils();
        $fieldsRule = [
            'line_data' => ['start_name', 'start_longitude', 'start_latitude', 'end_name', 'end_longitude', 'end_latitude']
        ];
        $data = $Utils->packFieldsToField($data, $fieldsRule, 0);
        // $data['line_data']['map_type'] =  $data['map_type'] ?? 0;
        return $data;
    }
}
