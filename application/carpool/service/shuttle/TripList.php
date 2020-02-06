<?php
namespace app\carpool\service\shuttle;

use app\common\service\Service;
use app\carpool\model\User as UserModel;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\ShuttleLineDepartment;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\shuttle\Trip as ShuttleTripService;
use app\carpool\service\TripsList as TripsListService;
use app\carpool\service\TripsMixed as TripsMixedService;
use app\carpool\service\shuttle\Partner as PartnerService;
use app\carpool\service\TripsPushMsg;
use app\carpool\model\ShuttleLineRelation;
use app\carpool\model\ShuttleTripPartner;
use app\user\model\Department;
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
     * @param integer $type      上下班类型
     * @param array $extData 扩展数据
     * @return array
     */
    public function lists($user_type, $userData = null, $type = -1, $extData = null)
    {
        $Utils = new Utils();
        // 取得班车行程
        $list = [];
        $user_type = intval($user_type);
        $type = intval($type);
        $limit = ( $extData['limit'] ?? 0 ) ?: 0;
        $pagesize = ( $extData['limit'] ?? 0 ) ?: 0;
        $lineId = ( $extData['line_id'] ?? 0 ) ?: 0;
        $userData = $userData ?: (( $extData['userData'] ?? null ) ?: null);
        $showPassenger = ( $extData['show_passenger'] ?? 0 ) ?: 0;
        $page = input('get.page', 1);

        $ShuttleTripModel = new ShuttleTripModel();
        $TripsService = new TripsService();
        $ShuttleTripService = new ShuttleTripService();
        $userAlias = 'u';

        $cacheKey =  $ShuttleTripModel->getListCacheKeyByLineId(0, ($user_type == 1 ? 'cars' : 'requests'));
        $rowCacheKey = "tzList_limit{$limit}_pz{$pagesize}_p{$page}".($lineId ? "lineId_$lineId" : '');
        $returnData = $this->redis()->hCache($cacheKey, $rowCacheKey);
        if (!is_array($returnData)) {
            $offsetTimeArray = [
                date('Y-m-d H:i:s', time() - (5 * 60)),
                date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 2))
            ];
            
            $tripFields = ['id', 'comefrom', 'user_type','trip_id', 'line_id', 'plate', 'status', 'time', 'create_time', 'seat_count', 'extra_info'];
            $userDefaultFields = [ 'uid', 'loginname', 'name','nativename',  'Department', 'sex', 'company_id', 'department_id', 'imgpath'];
            $fields = implode(',', $Utils->arrayAddString($tripFields, 't.'));
            $fields .= ", 'shuttle_trip' as `from`";
            $fields .=  ',' .$TripsService->buildUserFields($userAlias, $userDefaultFields);
            if ($lineId > 0) {
                $friendLines = (new ShuttleLineRelation())->getFriendsLine($lineId, 0, 0) ?: [];
                $friendLinesWhen = '';
                if (count($friendLines) >0) {
                    $friendLinesStr = array_map('strval', $friendLines);
                    $friendLinesWhen = count($friendLines) >0 ? "WHEN t.line_id in($friendLinesStr) THEN 1" : '';
                }
                $fields .= ", (CASE WHEN t.line_id = $lineId THEN 10 
                    {$friendLinesWhen} 
                    ELSE 0 END) AS line_sort ";
            } else {
                $fields .= ', 0 AS line_sort';
            }
            
            if ($user_type === 1) {
                // $userAlias = 'd';
                $userType = 1;
                $comefrom = 1;
            } else {
                // $userAlias = 'p';
                $offsetTimeArray[0] = date('Y-m-d H:i:s', time() + 30);
                $userType = 0;
                $comefrom = 2;
            }
            $join = [
                ["user {$userAlias}", "t.uid = {$userAlias}.uid", 'left'],
            ];
            $map  = [
                ['t.status', 'between', [0,1]],
                ['t.time', 'between', $offsetTimeArray],
                ['trip_id', '=', Db::raw(0)],
                ['user_type', '=', $userType],
                ['comefrom', '=', $comefrom],
            ];
            if (is_numeric($type) && $type > -1) {
                $map[] = ['t.line_type', '=', $type];
            } elseif ($type == -2) {
                $map[] = ['t.line_type', '>', 0];
            }
            // 排除已删用户；
            $map[] = ["{$userAlias}.is_delete", '=', Db::raw(0)];
            if ($userData) {
                // 查找自己所在部门的可见路线;
                $lineidSql = (new ShuttleLineDepartment())->getIdsByDepartmentId($userData['department_id'], true);
                $map[] = ['', 'exp', Db::raw("line_id in $lineidSql")];
            }

            $ctor = $ShuttleTripModel->alias('t')->field($fields)->join($join)->where($map)->order('line_sort DESC, t.time ASC');
            // var_dump($ctor);exit;
            if ($limit) {
                $list = $ctor->limit($limit)->select();
                $list = $list ? $list->toArray() : [];
                $isEmpty  = empty($list);
            } else {
                $returnData = $Utils->getListDataByCtor($ctor, $pagesize);
                $list = $returnData['lists'];
                $isEmpty  = empty($returnData['lists']);
            }
            $lineFields = ['start_name', 'start_longitude', 'start_latitude', 'end_name', 'end_longitude', 'end_latitude', 'map_type'];
            foreach ($list as $key => $value) {
                $value['line_data'] =  $ShuttleTripService->getExtraInfoLineData($value, 2);
                $value['line_data'] = $Utils->filterDataFields($value['line_data'], $lineFields);
                $value['time'] = strtotime($value['time']);
                // $value['time_od'] = strtotime(date('Y-m-d H', $value['time']).':00:00');
                $value['create_time'] = strtotime($value['create_time']);
                if ($user_type == 1) {
                    $value['took_count'] =  $ShuttleTripModel->countPassengers($value['id']);
                    if ($showPassenger) {
                        $userFields = ['uid', 'loginname', 'name', 'sex'];
                        $value['passengers'] = $ShuttleTripService->passengers($value['id'], $userFields, ['id','status','time'], 0) ?: [];
                    }
                } else {
                    unset($value['plate']);
                }
                $list[$key] = $value;
            }
            $returnData['lists'] = $list;
            $exp = $isEmpty ? 10 : 20;
            $this->redis()->hCache($cacheKey, $rowCacheKey, $returnData, $exp);
        }

        return $returnData;
    }
}
