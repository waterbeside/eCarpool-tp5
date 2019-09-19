<?php

namespace app\admin\controller;

use app\carpool\model\Company as CompanyModel;
use app\carpool\model\Info as InfoModel;
use app\carpool\model\Wall as WallModel;
use app\carpool\model\InfoActiveLine;
use app\admin\controller\AdminBase;
use think\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

use app\carpool\service\Trips as TripsService;
use app\carpool\service\TripsDetail as TripsDetailService;

/**
 * 拼车行程管理
 * Class CarpoolTrips
 * @package app\admin\controller
 */
class CarpoolTrips extends AdminBase
{
    public $check_dept_setting = [
        "action" => []
    ];
    /**
     * [index description]
     * @param  array   $filter   筛选项
     * @param  string||integer  $status  行程状态
     * @param  integer  $type   0： info表， 1：love_wall 空座位表
     * @param  integer $page      当前第几页
     * @param  integer $pagesize  每页多少条
     * @return mixed
     *
     */
    public function index($filter = [], $status = "all", $type = 0, $page = 1, $pagesize = 20, $export = 0)
    {
        if ($export) {
            ini_set('memory_limit', '128M');
            if (!$filter['time'] && !$filter['keyword'] && !$filter['keyword_dept']) {
                $this->error('数据量过大，请筛选后再导出');
            }
        }
        $TripsService = new TripsService();

        $map = [];
        //地区排查 检查管理员管辖的地区部门
        $deptAuthMapSql_dd = $this->buildRegionMapSql($this->userBaseInfo['auth_depts'], 'dd');
        if ($deptAuthMapSql_dd) {
            $map[] = ['', 'exp', Db::raw($deptAuthMapSql_dd)];
        }
        if (!$type) {
            $deptAuthMapSql_pd = $this->buildRegionMapSql($this->userBaseInfo['auth_depts'], 'pd');
            if ($deptAuthMapSql_pd) {
                $map[] = ['', 'exp', Db::raw($deptAuthMapSql_pd)];
            }
        }


        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $map[] = ['status', '=', $filter['status']];
        }
        //筛选状态
        if (is_numeric($status)) {
            $map[] = ['t.status', '=', $status];
        }

        //筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }
        $time_arr = $this->formatFilterTimeRange($filter['time'], 'YmdHi', 'd');
        if (count($time_arr) > 1) {
            $map[] = ['t.time', '>=', $time_arr[0]];
            $map[] = ['t.time', '<', $time_arr[1]];
        }



        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            //筛选状态
            if (isset($filter['is_hr']) && $filter['is_hr'] == 0) {
                $keyword_dept_str = $type == 1 ? 'd.companyname|d.Department' : 'd.companyname|d.Department|p.companyname|p.Department';
            } else {
                $keyword_dept_str = $type == 1 ? 'dd.fullname' : 'dd.fullname|pd.fullname';
            }
            $map[] = [$keyword_dept_str, 'like', "%{$filter['keyword_dept']}%"];
        }

        //筛选地址
        if (isset($filter['keyword_address']) && $filter['keyword_address']) {
            $map[] = ['s.addressname|e.addressname', 'like', "%{$filter['keyword_address']}%"];
        }


        $cacheKey = "carpool_admin:trips_list:" . md5(json_encode($map) . json_encode($filter)) . ":type_{$type}:status_{$status}";
        $cacheKey = $export ? $cacheKey : $cacheKey . ":pagesize_{$pagesize}:page_{$page}";
        $redis = $this->redis();
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            $lists = isset($cacheData['lists']) ? $cacheData['lists'] : null;
            $pagination = $cacheData['pagination'];
        }
        if (!$cacheData || $lists === null) {
            $join = [];
            //从info表取得行程
            if (is_numeric($type) && $type == 0) {
                //筛选用户信息
                if (isset($filter['keyword']) && $filter['keyword']) {
                    $map[] = ['d.loginname|d.phone|d.name|d.nativename|p.loginname|p.phone|p.name|p.nativename', 'like', "%{$filter['keyword']}%"];
                }
                $fields = 't.infoid , t.love_wall_ID , t.time,  t.time, t.status, t.passengerid, t.carownid ,   t.subtime, t.map_type ';
                $fields .= ',' . $TripsService->buildUserFields('d');
                $fields .= ', dd.fullname as d_full_department  ';
                $fields .= ',' . $TripsService->buildUserFields('p');
                $fields .= ', pd.fullname as p_full_department  ';
                $fields .=  $TripsService->buildAddressFields();
                $join   = $TripsService->buildTripJoins();
                if (is_numeric($status)) {
                    $map[] = ['t.status', '=', $status];
                } else {
                    switch ($status) {
                        case 'cancel':
                            $map[] = ['t.status', '=', 2];
                            break;
                        case 'success':
                            $map[] = ['t.status', 'in', [1, 3, 4]];
                            break;
                        case 'fail':
                            $map[] = ['t.status', '=', 0];
                            break;
                        default:
                            // code...
                            break;
                    }
                }

                if ($export) {
                    $lists_res = InfoModel::alias('t')->field($fields)->join($join)->where($map)->order('t.time DESC, love_wall_ID DESC ')->select();
                } else {
                    $lists_res = InfoModel::alias('t')->field($fields)->join($join)->where($map)->order('t.time DESC, love_wall_ID DESC ')
                        // ->fetchSql()->select();dump($lists);exit;
                        ->paginate($pagesize, false, ['query' => request()->param()]);
                }
                // dump($lists->toArray());exit;

                // ->fetchSql()->select();
                // dump($lists);exit;

                // dump($lists);exit;
            }

            if ($type == 1) {
                //筛选用户信息
                if (isset($filter['keyword']) && $filter['keyword']) {
                    $map[] = ['d.loginname|d.phone|d.name|d.nativename', 'like', "%{$filter['keyword']}%"];
                }
                $subQuery = InfoModel::field('count(love_wall_ID) as count , love_wall_ID')
                    ->group('love_wall_ID')
                    ->where('status <> 2')
                    ->fetchSql(true)
                    ->buildSql();
                // $fields .=' , (select count(*) from info as i where i.love_wall_ID = t.love_wall_ID and i.status <> 2 ) AS took_count'
                // $fields .= ',d.loginname as driver_loginname , d.name as driver_name, d.phone as driver_phone, d.Department as driver_department, d.sex as driver_sex ,d.company_id as driver_company_id, d.companyname as driver_companyname, d.carnumber';
                // $join[] = ['user d','d.uid = t.carownid', 'left'];
                $fields = 't.time, t.status, t.seat_count';
                $fields .= ', t.love_wall_ID  , t.subtime , t.carownid as driver_id , t.map_type ';
                $fields .= ',' . $TripsService->buildUserFields('d');
                $fields .= ', dd.fullname as d_full_department  ';
                $fields .=  $TripsService->buildAddressFields();
                $join = $TripsService->buildTripJoins("s,e,d,department");


                $fields .= ',ic.count as took_count';
                $join[] = [[$subQuery => 'ic'], 'ic.love_wall_ID = t.love_wall_ID', 'left'];
                $map_stauts = [];
                if (is_numeric($status)) {
                    $map_stauts[] = ['t.status', '=', $status];
                } else {
                    switch ($status) {
                        case 'cancel':
                            $map_stauts[] = ['t.status', '=', 2];
                            break;
                        case 'success':
                            $map_stauts[] = ['t.status', '<>', 2];
                            $map_stauts[] = ['ic.count', '>', 0];
                            break;
                        case 'fail':
                            $map_stauts  = 't.status <> 2 AND (ic.count IS NULL OR ic.count = 0)';
                            break;
                        default:
                            // code...
                            break;
                    }
                }

                if ($export) {
                    $lists_res = WallModel::alias('t')->field($fields)->join($join)->where($map)->where($map_stauts)->order('t.time DESC')->select();
                    // var_dump($lists);exit;
                } else {
                    $lists_res = WallModel::alias('t')->field($fields)->join($join)->where($map)->where($map_stauts)->order('t.time DESC')
                        // ->fetchSql()->select();dump($lists);exit;
                        ->paginate($pagesize, false, ['query' => request()->param()]);
                }


                // dump($lists);exit;
            }


            if ($export) {
                $lists = $lists_res->toArray();
                $pagination = [
                    'total' => count($lists),
                    'page' => $page,
                    'render' => '',
                ];
            } else {
                $pagination = [
                    'total' => $lists_res->total(),
                    'page' => $page,
                    'render' => $lists_res->render(),
                ];
                $lists_to_array = $lists_res->toArray();
                $lists = $lists_to_array['data'];
            }

            foreach ($lists as $key => $value) {
                $value  = $TripsService->formatResultValue($value);
                $lists[$key] = $value;
                $lists[$key]['time'] = date('Y-m-d H:i', $value['time']);
                $lists[$key]['subtime'] = date('Y-m-d H:i', $value['subtime']);
            }
            // foreach ($lists as $key => $value) {
            // // $lists[$key]['took_count']       = InfoModel::where([['love_wall_ID','=',$value['love_wall_ID']],['status','<>',2]])->count(); //取已坐数
            // // $data['took_count_all']   = Info::model()->count('love_wall_ID='.$data['love_wall_ID'].' and status <> 2'); //取已坐数
            // // $data['hasTake']          = Info::model()->count('love_wall_ID='.$data['love_wall_ID'].' and status < 2 and passengerid ='.$uid.''); //查看是否已搭过此车主的车
            // // $data['hasTake_finish']   = Info::model()->count('love_wall_ID='.$data['love_wall_ID'].' and status = 3 and passengerid ='.$uid.''); //查看是否已搭过此车主的车
            // }

            $redis->cache($cacheKey, ['lists' => $lists, 'pagination' => $pagination], 20);
        }




        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }
        // dump($lists);
        $returnData = [
            'lists' => $lists,
            'pagination' => $pagination,
            'pagesize' => $pagesize,
            'type' => $type,
            'status' => $status,
            'filter' => $filter,
            'companys' => $companys
        ];
        if (!$export) {
            if ($type) {
                return $this->fetch('wall_index', $returnData);
            } else {
                return $this->fetch('index', $returnData);
            }
        } else {
            return $returnData;
        }
    }


    public function export($filter = [], $status = "all", $type = 0)
    {
        $res = $this->index($filter, $status, $type, 1, 0, 1);
        $lists = isset($res['lists']) ? $res['lists'] : [];
        $companys = $res['companys'];
        //导出表格
        $encoding = input('param.encoding');
        $filename =  md5(json_encode($filter)) . '_' . time() . ($encoding ? '.xls' : '.csv');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /*设置表头*/
        $sheet->setCellValue('A1', '起点')
            ->setCellValue('B1', '终点')
            ->setCellValue('C1', '出发时间')
            ->setCellValue('D1', $type ? '发布时间' : '发布(搭车)时间')
            ->setCellValue('E1', '司机')
            ->setCellValue('F1', '司机工号')
            ->setCellValue('G1', '司机部门')
            ->setCellValue('H1', '司机部门(HR)')
            ->setCellValue('I1', '司机电话')
            ->setCellValue('J1', $type ? '乘客数' : '乘客')
            ->setCellValue('K1', $type ? '发布空位数' : '乘客工号')
            ->setCellValue('L1', $type ? '-' : '乘客部门')
            ->setCellValue('M1', $type ? '-' : '乘客部门(HR)')
            ->setCellValue('N1', $type ? '-' : '乘客电话');
        foreach ($lists as $key => $value) {
            $rowNum = $key + 2;
            $sheet->setCellValue('A' . $rowNum, $value['start_addressname'])
                ->setCellValue('B' . $rowNum, $value['end_addressname'])
                ->setCellValue('C' . $rowNum, $value['time'])
                ->setCellValue('D' . $rowNum, $value['subtime'])
                ->setCellValue('E' . $rowNum, $value['d_nativename'])
                ->setCellValue('F' . $rowNum, $value['d_loginname'])
                ->setCellValue('G' . $rowNum, isset($companys[$value['d_company_id']]) ? $value['d_department'] . '/' . $companys[$value['d_company_id']] : $value['d_department'])
                ->setCellValue('H' . $rowNum, $value['d_full_department'])
                ->setCellValue('I' . $rowNum, $value['d_phone'])
                ->setCellValue('J' . $rowNum, $type ? $value['took_count'] : $value['p_nativename'])
                ->setCellValue('K' . $rowNum, $type ? $value['seat_count'] : $value['p_loginname'])
                ->setCellValue('L' . $rowNum, $type ? "-" : (isset($companys[$value['p_company_id']]) ? $value['p_department'] . '/' . $companys[$value['p_company_id']] : $value['p_department']))
                ->setCellValue('M' . $rowNum, $type ? "-" : $value['p_full_department'])
                ->setCellValue('N' . $rowNum, $type ? "-" : $value['p_phone']);
        }
        /*$value = "Hello World!" . PHP_EOL . "Next Line";
        $sheet->setCellValue('A1', $value)；
        $sheet->getStyle('A1')->getAlignment()->setWrapText(true);*/
        $writer = $encoding ? new Xls($spreadsheet) : new Csv($spreadsheet);
        if ($encoding) {
            header("Content-Type: application/vnd.ms-excel; charset=GBK");
        }
        /*$filename = Env::get('root_path') . "public/uploads/temp/hello_world.xlsx";
        $writer->save($filename);*/
        header('Content-Disposition: attachment;filename="' . $filename . '"'); //告诉浏览器输出浏览器名称
        header('Cache-Control: max-age=0'); //禁止缓存
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        // dump($lists);
        exit;
    }

    /**
     * 明细
     */
    public function detail($id, $type = 0)
    {
        if (!$id) {
            return $this->error('Lost id');
        }

        $TripsService = new TripsService();

        // $fields = 't.time, t.status';
        // $fields .= ',s.addressname as start_addressname, s.latitude as start_latitude, s.longtitude as start_longtitude';
        // $fields .= ',e.addressname as end_addressname, e.latitude as end_latitude, e.longtitude as end_longtitude';
        // $join = [];
        // $join[] = ['address s','s.addressid = t.startpid', 'left'];
        // $join[] = ['address e','e.addressid = t.endpid', 'left'];


        if ($type) { // type为1时，查love_wall表的数据
            // $fields .= ', t.love_wall_ID,   t.love_wall_ID , t.subtime , t.carownid as driver_id , t.seat_count, (select count(*) from info as i where i.love_wall_ID = t.love_wall_ID and i.status <> 2 ) AS took_count';
            // $fields .= ',d.loginname as driver_loginname , d.name as driver_name, d.phone as driver_phone, d.Department as driver_department, d.sex as driver_sex ,d.company_id as driver_company_id, d.companyname as driver_companyname, d.carnumber,d.imgpath as driver_imgpath';
            // $join[] = ['user d','d.uid = t.carownid', 'left'];

            $fields = 't.time, t.status,  t.seat_count, t.map_type';
            $fields .= ', t.love_wall_ID as id ,t.love_wall_ID , t.subtime , t.carownid as driver_id  ';
            $fields .= ',' . $TripsService->buildUserFields('d');
            $fields .=  $TripsService->buildAddressFields();
            $join = $TripsService->buildTripJoins("s,e,d");

            $data = WallModel::alias('t')->field($fields)->join($join)
                // ->fetchSql()
                ->find($id);
            $data  = $TripsService->formatResultValue($data);

            $data['d_avatar'] = $data['d_imgpath'] ? config('secret.avatarBasePath') . $data['d_imgpath'] : config('secret.avatarBasePath') . "im/default.png";
            $data['time'] = date('Y-m-d H:i', $data['time']);
            $data['subtime'] = date('Y-m-d H:i', $data['subtime']);
            // $data['took_count']       = InfoModel::where([['love_wall_ID','=',$data['love_wall_ID']],['status','<>',2]])->count(); //取已坐数
            $fields2 = 't.*';
            $fields2 .= ',p.uid ,p.loginname  , p.name,  p.nativename, p.phone , p.Department as department, p.sex, p.company_id , p.companyname, p.carnumber, p.imgpath ';
            $join2 = [
                ['user p', 'p.uid = t.passengerid', 'left'],
            ];

            $data['passengers']  = InfoModel::alias('t')->field($fields2)->join($join2)->where([['love_wall_ID', '=', $data['love_wall_ID']], ['status', '<>', 2]])->select(); //取乘客
            foreach ($data['passengers'] as $key => $value) {
                $data['passengers'][$key]['avatar'] = $value['imgpath'] ? config('secret.avatarBasePath') . $value['imgpath'] : config('secret.avatarBasePath') . "im/default.png";
            }


            // dump($data);exit;
        } else { // type为0时，查info表的数据
            // $fields .= ',t.infoid, t.love_wall_ID , t.subtime , t.carownid as driver_id , t.passengerid as passenger_id ';
            // $fields .= ',d.loginname as driver_loginname , d.name as driver_name, d.phone as driver_phone, d.Department as driver_department, d.sex as driver_sex ,d.company_id as driver_company_id, d.companyname as driver_companyname, d.carnumber, d.imgpath as driver_imgpath';
            // $fields .= ',p.loginname as passenger_loginname , p.name as passenger_name, p.phone as passenger_phone, p.Department as passenger_department, p.sex as passenger_sex ,p.company_id as passenger_company_id, p.companyname as passenger_companyname , p.imgpath as passenger_imgpath';
            // $join[] = ['user d','d.uid = t.carownid', 'left'];
            // $join[] = ['user p','p.uid = t.passengerid', 'left'];

            $fields = 't.time, t.status, t.map_type';
            $fields .= ',t.infoid as id, t.infoid , t.love_wall_ID , t.subtime , t.carownid as driver_id, t.passengerid as passenger_id  ';

            $fields .= ',' . $TripsService->buildUserFields('d');
            $fields .= ',' . $TripsService->buildUserFields('p');
            $fields .=  $TripsService->buildAddressFields();
            $join   = $TripsService->buildTripJoins();

            $data   = InfoModel::alias('t')->field($fields)->join($join)->find($id);

            $data   = $TripsService->formatResultValue($data);

            $avatarBasePath = config('secret.avatarBasePath');

            $data['d_avatar'] = $data['d_imgpath'] ? $avatarBasePath . $data['d_imgpath'] : $avatarBasePath . "im/default.png";
            $data['p_avatar'] = $data['p_imgpath'] ? $avatarBasePath . $data['p_imgpath'] : $avatarBasePath . "im/default.png";
            $data['time'] = date('Y-m-d H:i', $data['time']);
            $data['subtime'] = date('Y-m-d H:i', $data['subtime']);
        }
        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }
        $returnData = [
            'data' => $data,
            'companys' => $companys,
        ];
        $template_name = $type ? 'wall_detail' : 'detail';
        return $this->fetch($template_name, $returnData);
    }


    /**
     * 上传的GPS点查询
     *
     */
    public function public_activeline_list($infoid = 0, $pagesize = 100, $orderField = "locationtime", $orderType = 'desc')
    {
        $orderType = $orderType == 'desc'  ? 'desc' : 'asc';
        $orderField = in_array($orderField, ['locationtime', 'uid', 'infoid'])  ?  $orderField : 'locationtime';
        $map = [];
        if (is_numeric($infoid) && $infoid > 0) {
            $map[] = ['t.infoid', '=', $infoid];
        }
        $join = [];
        $join[] = ['user u', 'u.uid = t.uid', 'left'];
        $lists = InfoActiveLine::alias('t')
                    ->field('t.*,u.name,u.nativename, u.loginname')
                    ->join($join)
                    ->where($map)
                    ->order("$orderField $orderType")
                    ->paginate($pagesize, false, ['query' => request()->param()]);
        $returnData = [
            'lists' => $lists,
            'pagesize' => $pagesize,
            'infoid' => $infoid,
            'orderField' => $orderField,
            'orderType' => $orderType,
        ];
        return $this->fetch('public_activeline_list', $returnData);
    }

    /**
     * 验证行程是否合法
     *
     * @param integer $infoid 行程id
     */
    public function public_check_compliant($infoid, $returnType = 1)
    {
        $result = Db::connect('database_carpool')->query("call mine_trip(:infoid)", [
            'infoid' => $infoid,
        ]);
        $res =  [
            'code' => '',
            'message' => '查询失败',
        ];
        if ($result && isset($result[0][0]['result'])) {
            $res = json_decode($result[0][0]['result'], true);
        }

        $returnData = [
            'infoid' => $infoid,
            'res' => $res,
        ];
        return $returnType ? $this->fetch('public_check_compliant', $returnData) : $this->jsonReturn(0, $returnData);
    }
}
