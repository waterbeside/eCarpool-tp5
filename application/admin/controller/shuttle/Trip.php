<?php

namespace app\admin\controller\shuttle;

use app\carpool\model\User as UserModel;
use app\user\model\Department;
use app\carpool\model\ShuttleLine as ShuttleLineModel;
use app\carpool\model\ShuttleTrip as ShuttleTripModel;
use app\carpool\model\ShuttleLineDepartment;
use app\carpool\model\ShuttleTripPartner as ShuttleTripPartner;
use app\carpool\service\shuttle\Trip as ShuttleTripServ;
use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsMixed;
use app\admin\controller\AdminBase;
use my\Utils;
use think\facade\Validate;
use think\Db;

/**
 * 班车行程管理
 * Class Trip
 * @package app\admin\controller
 */
class Trip extends AdminBase
{

    /**
     *
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter = [], $status = "not_cancel", $page = 1, $pagesize = 40, $export = 0)
    {
        if ($export) {
            ini_set('memory_limit', '128M');
            if (!$filter['time'] && !$filter['keyword'] && !$filter['keyword_dept']) {
                $this->error('数据量过大，请筛选后再导出');
            }
        }
        $map = [];

        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['allow_region_ids'])) {
            $deptAuthMapSql_ud = $this->buildRegionMapSql($authDeptData['allow_region_ids'], 'ud');
            if ($deptAuthMapSql_ud) {
                $map[] = ['', 'exp', Db::raw($deptAuthMapSql_ud)];
            }
        }

        // if (isset($filter['status']) && is_numeric($filter['status'])) {
        //     $map[] = ['t.status', '=', $filter['status']];
        // }
        //筛选状态
        if (is_numeric($status)) {
            $map[] = ['t.status', '=', $status];
        } elseif ($status == 'not_cancel') {
            $map[] = ['t.status', '>=', 0];
        } elseif ($status == 'finish') {
            $map[] = ['t.status', '>', 2];
        } else {
            $statusArray = explode(',', $status);
            $statusArray = array_map('trim', $statusArray);
            $statusArray = array_filter($statusArray, function ($value) {
                return is_numeric($value) ? true : false;
            });
            $map[] = ['t.status', 'in', $statusArray];
        }

        //筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }
        $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d H:i:s', 'd');
        if (count($time_arr) > 1) {
            $map[] = ['t.time', '>=', $time_arr[0]];
            $map[] = ['t.time', '<', $time_arr[1]];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            //筛选状态
            if (isset($filter['is_hr']) && $filter['is_hr'] == 0) {
                $keyword_dept_str =  'u.companyname|u.Department';
            } else {
                $keyword_dept_str = 'ud.fullname';
            }
            $map[] = [$keyword_dept_str, 'like', "%{$filter['keyword_dept']}%"];
        }

        //筛选地址
        if (isset($filter['keyword_address']) && $filter['keyword_address']) {
            $map[] = ['s.addressname|e.addressname', 'like', "%{$filter['keyword_address']}%"];
        }

        //筛选上下班类型
        if (isset($filter['line_type']) && is_numeric($filter['line_type'])) {
            $map[] = ['t.line_type', '=', $filter['line_type']];
        } else {
            $map[] = ['t.line_type', '>', 0];
        }

        //筛选用户类型(司机或乘客)
        if (isset($filter['user_type']) && is_numeric($filter['user_type'])) {
            $map[] = ['t.user_type', '=', $filter['user_type']];
        }

        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['d.loginname|d.phone|d.name|d.nativename', 'like', "%{$filter['keyword']}%"];
        }

        $TripsService = new TripsService();

        $fields = 't.id, t.time, t.time_offset, t.user_type, t.status, t.seat_count, t.plate , t.trip_id  , t.create_time, t.line_type, t.comefrom';
        $fields .= ', t.start_id, t.start_name, t.start_longitude, t.start_latitude, t.end_id, t.end_name, t.end_longitude, t.end_latitude, t.extra_info';
        $fields .= ", (CASE WHEN t.user_type = 1 THEN t.id ELSE trip_id END) AS x_trip_id, DATE_FORMAT(t.time,'%Y-%m-%d') as time_hour";
        $fields .= ',' . $TripsService->buildUserFields('u');// 用户相关字段
        $fields .= ', ud.fullname as u_full_department  ';

         // join
        $join = [
            ["user u", "t.uid = u.uid", 'left'],
            ["t_department ud", "u.department_id = ud.id", 'left'],
            // ["t_shuttle_line l", "t.line_id = l.id", 'left'],
        ];

        $orderString = 'time_hour DESC, x_trip_id DESC, t.user_type DESC ,t.time DESC';

        if ($export) {
            $lists = ShuttleTripModel::alias('t')->field($fields)->join($join)->where($map)->order($orderString)->select();
            // var_dump($lists);exit;
        } else {
            $lists = ShuttleTripModel::alias('t')->field($fields)->join($join)->where($map)->order($orderString)
                // ->fetchSql()->select();dump($lists);exit;
                ->paginate($pagesize, false, ['query' => request()->param()]);
        }
        $colors = [];
        $Utils = new Utils;
        foreach ($lists as $key => $value) {

            if (!$export) {
                $value['extra_info'] = $Utils->json2Array($value['extra_info']);
            }

            $value['time'] = strtotime($value['time']);
            $value['create_time'] = strtotime($value['create_time']);
            $color = $colors[$value['x_trip_id']] ?? null;
            if (empty($color)) {
                $color = $value['x_trip_id'] === 0 ? '' : $Utils->randColor();
                $colors[$value['x_trip_id']] = $color;
            }
            $value['color'] = $color;
            $lists[$key] = $value;
        }
        if ($export) {
            $lists = $lists->toArray();
            $pagination = [
                'total' => count($lists),
                'page' => $page,
                'render' => '',
            ];
        } else {
            $pagination = [
                'total' => $lists->total(),
                'page' => $page,
                'render' => $lists->render(),
            ];
            $lists_to_array = $lists->toArray();
            $lists = $lists_to_array['data'];
        }
        $returnData = [
            'lists' => $lists,
            'pagination' => $pagination,
            'pagesize' => $pagesize,
            'status' => $status,
            'filter' => $filter,
        ];
        if (!$export) {
                return $this->fetch('index', $returnData);
        } else {
            return $returnData;
        }
    }

    /**
     * 明细
     */
    public function detail($id)
    {
        if (!$id) {
            return $this->error('Lost id');
        }
        if (!$id) {
            return $this->jsonReturn(992, lang('Error Param'));
        }
        $ShuttleTripServ = new ShuttleTripServ();
        
        $data  = $ShuttleTripServ->getUserTripDetail($id, [], [], true);
        if (!$data) {
            return $this->jsonReturn(20002, 'No data');
        }
        $trip_id = $data['trip_id'];

        if ($data['user_type'] == 1) {
                $tripFields = ['id', 'time', 'create_time', 'status', 'comefrom', 'user_type'];
                $data['passengers'] = $ShuttleTripServ->passengers($id, [], $tripFields) ?: [];
                $data['took_count'] = count($data['passengers']);
        } else {
            $ShuttleTripPartner = new ShuttleTripPartner();
            $data['partners'] = $ShuttleTripPartner->getPartners($id, 1) ?? [];
            $data['driver'] = $ShuttleTripServ->getUserTripDetail($trip_id, [], [], 0) ?: null;
        }
        $TripsMixed = new TripsMixed();
        $data['have_started'] = $TripsMixed->haveStartedCode($data['time']);
        unset($data['trip_id']);
        $returnData = [
            'data' => $data,
        ];
        return $this->fetch('detail', $returnData);
    }
}
