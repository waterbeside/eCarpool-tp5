<?php

namespace app\admin\controller;

use think\facade\Env;
use app\admin\controller\AdminBase;
use app\common\model\Configs;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\user\model\Department as DepartmentModel;
use app\score\model\Account as ScoreAccountModel;
use app\score\model\AccountMix as AccountMixModel;
use app\score\model\Order as OrderModel;
use app\score\model\Goods as GoodsModel;
use app\score\model\OrderGoods as OrderGoodsModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use my\CurlRequest;
use think\Db;

/**
 * 订单管理
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScoreOrder extends AdminBase
{

    public $check_dept_setting = [
        "action" => ['index', 'goods']
    ];

    /**
     * 订单列表
     * @return mixed
     */
    public function index($filter = [], $status = "0", $page = 1, $pagesize = 15, $export = 0)
    {
        //构建sql
        $fields = 't.*, ac.carpool_account, ac.balance  ';
        $fields .= ',cu.uid, cu.loginname,cu.name, cu.nativename, cu.phone, cu.Department, cu.sex ,cu.company_id, cu.companyname, 
            d.fullname as full_department';

        $join = [
            ['account ac', 't.creator = ac.id', 'left'],
        ];
        $join[] = ['carpool.user cu', 'cu.loginname = ac.carpool_account', 'left'];
        // $join[] = ['carpool.t_department d','cu.department_id = d.id','left'];
        $join[] = ['carpool.t_department d', 't.region_id = d.id', 'left'];


        $map = [];
        $map[] = ['t.is_delete', '=', Db::raw(0)];
        //筛选状态
        if (is_numeric($status)) {
            $map[] = ['t.status', '=', $status];
        }
        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['region_map'])) {
            $map[] = $authDeptData['region_map'];
        }


        //筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }
        $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d H:i:s', 'd');
        if (count($time_arr) > 1) {
            $map[] = ['t.creation_time', '>=', $time_arr[0]];
            $map[] = ['t.creation_time', '<', $time_arr[1]];
        }


        //筛选单号
        $mapOrderRaw = '';
        if (isset($filter['order_num']) && $filter['order_num']) {
            $orderNums = explode("|", $filter['order_num']);
            foreach ($orderNums as $key => $value) {
                $mapOrderRaw = $mapOrderRaw ? $mapOrderRaw . " or " : " ";
                if (strpos($value, "/") > 0) {
                    $oNums = explode("/", $value);
                    $mapOrderRaw  .= " t.uuid like  '%{$oNums[0]}%' ";
                } else {
                    if (is_numeric($value)) {
                        $mapOrderRaw .= " t.id = '{$value}' ";
                    } else {
                        $mapOrderRaw .= " t.uuid like '%{$value}%'  ";
                    }
                }
            }
        }

        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['cu.loginname|cu.phone|cu.nativename', 'like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            //筛选状态
            if (isset($filter['is_hr']) && $filter['is_hr'] == 0) {
                $map[] = ['cu.Department|cu.companyname', 'like', "%{$filter['keyword_dept']}%"];
            } else {
                $map[] = ['d.fullname', 'like', "%{$filter['keyword_dept']}%"];
            }
        }




        $ModelBase = OrderModel::alias('t')->field($fields)->join($join)->where($map)
            ->json(['content'])
            ->order('t.operation_time ASC, t.creation_time ASC , t.id ASC');
        if (!empty($mapOrderRaw)) {
            $ModelBase = $ModelBase->whereRaw($mapOrderRaw);
        }
        if ($export) {
            $lists = $ModelBase->select();
        } else {
            $lists = $ModelBase
                // ->fetchSql()->select();
                ->paginate($pagesize, false, ['query' => request()->param()]);
        }

        $goodList = []; //商品缓存
        $GoodsModel = new GoodsModel();
        $DepartmentModel = new DepartmentModel();
        foreach ($lists as $key => $value) {
            $lists[$key]['Department'] = $lists[$key]['full_department'] ?
                $DepartmentModel->formatFullName($lists[$key]['full_department'], 1) : $lists[$key]['Department'];

            $goods = []; //商品
            foreach ($value['content'] as $gid => $num) {
                if (isset($goodList[$gid])) {
                    $good = $goodList[$gid];
                } else {
                    $good = $GoodsModel->getItem($gid);
                    $goodList[$gid] =  $good ? $good : [];
                }
                if ($good) {
                    $images = json_decode($good['images'], true);
                    $good['thumb'] = $images ? $images[0] : "";
                } else {
                    $good['id'] = $gid;
                    $good['name'] = "#" . $gid;
                    $good['thumb'] = '';
                }
                $good['num'] = $num;
                $goods[] =  $good;
            }
            $lists[$key]['goods'] = $goods;
        }


        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }

        /* 导出报表 */
        if ($export) {
            $encoding = input('param.encoding');
            $filename =  md5(json_encode($filter)) . '_' . $status . '_' . time() . ($encoding ? '.xls' : '.csv');

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            /*设置表头*/
            $sheet->setCellValue('A1', lang('Order number'))
                ->setCellValue('B1', lang('Name'))
                ->setCellValue('C1', lang('Phone'))
                ->setCellValue('D1', lang('Account'))
                ->setCellValue('E1', lang('Company'))
                ->setCellValue('F1', lang('Department'))
                ->setCellValue('G1', lang('Branch'))
                ->setCellValue('H1', lang('Order time'))
                ->setCellValue('I1', lang('Prize name'))
                ->setCellValue('J1', lang('Status'))
                ->setCellValue('k1', lang('Consumption points'));

            foreach ($lists as $key => $value) {
                $rowNum = $key + 2;
                $goodStr = '';

                foreach ($value['goods'] as $k => $good) {
                    $goodStr .= $good['name'] . '×' . $good['num'] . PHP_EOL;
                }

                $sheet->setCellValue('A' . $rowNum, iconv_substr($value['uuid'], 0, 8) . '/' . $value['id'])
                    ->setCellValue('B' . $rowNum, $value['nativename'] . "(#" . $value['uid'] . ")")
                    ->setCellValue('C' . $rowNum, $value['phone'])
                    ->setCellValue('D' . $rowNum, $value['loginname'])
                    ->setCellValue('E' . $rowNum, isset($companys[$value['company_id']]) ? $companys[$value['company_id']] : $value['company_id'])
                    ->setCellValue('F' . $rowNum, $value['Department'])
                    ->setCellValue('G' . $rowNum, $value['companyname'])
                    ->setCellValue('H' . $rowNum, $value['creation_time'])
                    ->setCellValue('I' . $rowNum, $goodStr)
                    ->setCellValue('J' . $rowNum, $value['status'])
                    ->setCellValue('K' . $rowNum, $value['total']);
                $sheet->getStyle('I' . $rowNum)->getAlignment()->setWrapText(true);
            }
            /*$value = "Hello World!" . PHP_EOL . "Next Line";
            $sheet->setCellValue('A1', $value)；
            $sheet->getStyle('A1')->getAlignment()->setWrapText(true);*/

            $writer = $encoding ? new Xls($spreadsheet) : new Csv($spreadsheet);
            if ($encoding) {
                // header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header("Content-Type: application/vnd.ms-excel; charset=GBK");
            }
            header('Content-Disposition: attachment;filename="' . $filename . '"'); //告诉浏览器输出浏览器名称
            header('Cache-Control: max-age=0'); //禁止缓存
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            // dump($lists);
            exit;
        } else {
            // dump($lists);
            $statusList = config('score.order_status');
            $auth = [];
            $auth['admin/ScoreOrder/finish'] = $this->checkActionAuth('admin/ScoreOrder/finish');
            $auth['admin/ScoreOrder/cancel'] = $this->checkActionAuth('admin/ScoreOrder/cancel');
            $auth['admin/ScoreOrder/delete'] = $this->checkActionAuth('admin/ScoreOrder/delete');
            $returnData = [
                'lists' => $lists,
                'pagesize' => $pagesize,
                'statusList' => $statusList,
                'filter' => $filter,
                'status' => $status,
                'companys' => $companys,
                'auth' => $auth
            ];
            return $this->fetch('index', $returnData);
        }
    }


    /**
     * 订单详情
     * @return mixed
     */
    public function detail($id)
    {
        if (!$id) {
            $this->error("Lost id");
        }

        $fields = 't.*, ac.carpool_account, ac.balance , d.fullname as full_department';
        $join = [
            ['account ac', 't.creator = ac.id', 'left'],
            ['carpool.t_department d', 't.region_id = d.id', 'left'],
        ];
        $data = OrderModel::alias('t')->field($fields)->join($join)->where('t.id', $id)->json(['content'])->find();
        if (!$data) {
            $this->error("No Data");
        } else {
            $this->checkDeptAuthByDid($data['region_id'], 1); //检查地区权限

            $data['userInfo'] = CarpoolUserModel::alias('t')
                ->field('t.*, d.fullname as full_department')
                ->join([['t_department d', 't.department_id = d.id', 'left']])
                ->where(['loginname' => $data['carpool_account']])
                ->find();
            if ($data['userInfo']) {
                $avatarBasePath = config('secret.avatarBasePath');
                $data['userInfo']['avatar'] = $data['userInfo']['imgpath'] ?
                    $avatarBasePath . $data['userInfo']['imgpath'] : $avatarBasePath . "im/default.png";
            }

            $goods = [];
            $GoodsModel = new GoodsModel();
            foreach ($data['content'] as $gid => $num) {
                $good = $GoodsModel->getItem($gid);
                if ($good) {
                    $images = json_decode($good['images'], true);
                    $good['thumb'] = $images ? $images[0] : "";
                } else {
                    $good['name'] = "#" . $gid;
                    $good['id'] = $gid;
                    $good['price'] = '-';
                    $good['thumb'] = '';
                }
                $good['num'] = $num;
                $goods[] =  $good;
            }
            $data['goods'] = $goods;
        }
        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }
        $statusList = config('score.order_status');
        $auth = [];
        $auth['admin/ScoreOrder/finish'] = $this->checkActionAuth('admin/ScoreOrder/finish');
        $auth['admin/ScoreOrder/cancel'] = $this->checkActionAuth('admin/ScoreOrder/cancel');
        $auth['admin/ScoreOrder/delete'] = $this->checkActionAuth('admin/ScoreOrder/delete');

        return $this->fetch('detail', ['data' => $data, 'companys' => $companys, 'statusList' => $statusList, 'auth' => $auth]);
    }


    /**
     * 商品兑换数统计
     * @return mixed
     */
    public function goods($filter = ['status' => 0])
    {
        $map = [];
        $map[] = ['o.is_delete', '=', Db::raw(0)];
        $map[] = ['t.is_delete', '=', Db::raw(0)];

        //筛选状态
        if (is_numeric($filter['status'])) {
            $map[] = ['o.status', '=', $filter['status']];
        } elseif ($filter['status'] == "all_01") {
            $map[] = ['o.status', 'in', [0, 1]];
        }
        $fields = "g.*, sum(t.count) as num, d.fullname, g.p_region_id ";
        $join = [
            ['order o', 'o.id = t.oid', 'left'],
            ['goods g', 'g.id = t.gid', 'left'],
            ['carpool.t_department d', 'g.p_region_id = d.id', 'left']
        ];

        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;

        if (isset($authDeptData['region_map'])) {
            $map[] = $authDeptData['region_map'];
        }



        //筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }
        $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d H:i:s', 'd');
        if (count($time_arr) > 1) {
            $map[] = ['o.creation_time', '>=', $time_arr[0]];
            $map[] = ['o.creation_time', '<', $time_arr[1]];
        }

        $lists = OrderGoodsModel::alias('t')->field($fields)
            ->json(['images'])
            ->join($join)
            ->where($map)
            ->group('t.gid')
            ->order('t.gid DESC')
            ->select();
        foreach ($lists as $key => $value) {
            $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "";
        }

        $statusList = config('score.order_status');

        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'statusList' => $statusList
        ];
        return $this->fetch('goods', $returnData);
    }




    /**
     * 完结订单
     * @param  integer $id       订单id
     * @param  string  $order_no 订单号
     */
    public function finish($id = 0, $order_no = null)
    {
        $statusList = config('score.order_status');
        $admin_id = $this->userBaseInfo['uid'];


        if ($this->request->isPost()) {
            if (!$id) {
                $this->error("Params error");
            }
            $where = [
                ['is_delete', '=', Db::raw(0)]
            ];
            $where[] = is_array($id) ? ['id', 'in', $id] : ['id', '=', $id];
            /*if(!$order_no ){
                $this->error("Params error");
            }*/

            $res = OrderModel::alias('t')->where($where)->json(['content'])->select();
            if (!$res) {
                $this->error(lang('Order does not exist'));
            }
            $resCount = count($res);
            foreach ($res as $key => $data) {
                $checkAuthRes = $this->checkDeptAuthByDid($data['region_id'], 0); //检查地区权限
                if (!$checkAuthRes) {
                    return $this->error('你所属的地区权限，并不能操作你选择的个别数据');
                    break;
                }
                
                if (intval($data['status']) !== 0) {
                    $statusMsg = isset($statusList[$data['status']]) ? $statusList[$data['status']] : $data['status'];
                    if ($resCount === 1) {
                        $this->error(lang('The order status is [%s], no operation is allowed', [$statusMsg]));
                    } else {
                        $this->error("有个别的订单状态为\"{$statusMsg}\", 操作失败，请刷新列表后重新选择再试");
                    }
                }
            }
            $where[] = ['status', '=', Db::raw(0)];
            $result = OrderModel::where($where)->update(["status" => 1, "handler" => -1 * intval($admin_id)]);

            if ($result) {
                $this->log('完结订单成功' . json_encode($this->request->post()), 0);
                $this->success(lang('End order successfully'));
            } else {
                $this->log('完结订单失败' . json_encode($this->request->post()), -1);
                $this->success(lang('Ending order failed'));
            }
        }
    }



    /**
     * 商品订单 下单者列表
     */
    public function good_owners($gid, $time, $pagesize = 30, $status = 0, $filter = ['keyword' => ''])
    {
        if (!$gid) {
            $this->error('Lost id');
        }
        $join = [
            ['carpool.t_department d', 't.p_region_id = d.id', 'left'],
        ];
        $good = GoodsModel::alias('t')->field('t.*, d.fullname as full_department')->where("t.id", $gid)->json(['images'])->join($join)->find();
        if (!$good) {
            $this->error(lang('Goods does not exist'));
        }
        $good['thumb'] = is_array($good["images"]) ? $good["images"][0] : "";



        $map = [];
        $map[] = ['t.gid', '=', $gid];
        $map[] = ['o.is_delete', '=', Db::raw(0)];
        //筛选状态
        if (is_numeric($status)) {
            $map[] = ['o.status', '=', $status];
        } elseif ($status == "all_01") {
            $map[] = ['o.status', 'in', [0, 1]];
        }

        $time_arr = $this->formatFilterTimeRange($time, 'Y-m-d H:i:s', 'd');
        if (count($time_arr) > 1) {
            $map[] = ['o.creation_time', '>=', $time_arr[0]];
            $map[] = ['o.creation_time', '<', $time_arr[1]];
        }



        $map_sub  = [];
        $fields_sub = "SUM(t.count) as num, t.creator , MIN(t.add_time) as add_time ";
        $join_sub = [
            ['order o', 'o.id = t.oid', 'left'],
        ];
        $subQuery = OrderGoodsModel::alias('t')->field($fields_sub)->join($join_sub)->where($map)->group('t.creator')->buildSql();

        $map_2  = [];
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map_2[] = ['cu.loginname|cu.phone|cu.Department|cu.nativename|cu.companyname', 'like', "%{$filter['keyword']}%"];
        }
        if (isset($filter['company_id']) && $filter['company_id']) {
            $map_2[] = ['cu.company_id', '=', $filter['company_id']];
        }

        $fields = "s.num , s.creator, s.add_time,  ac.id as account_id , 
            ac.carpool_account,  ac.is_delete as ac_is_delete, ac.balance,
            cu.uid, cu.loginname,cu.name, cu.nativename, cu.phone, cu.Department, cu.sex ,cu.company_id, cu.companyname
            ";
        $join = [
            ['account ac', 's.creator = ac.id', 'left'],
            ['carpool.user cu', 'cu.loginname = ac.carpool_account', 'left'],
        ];

        $sumRes = Db::connect('database_score')->table($subQuery . ' s')->field('sum(s.num) as sum')->join($join)->where($map_2)->find();
        $lists = Db::connect('database_score')->table($subQuery . ' s')->field($fields)
            ->join($join)
            ->where($map_2)
            ->order('s.creator ASC ')
            ->paginate($pagesize, false, ['query' => request()->param()]);
        // $lists = Db::connect('database_score')->table($subQuery . ' s')->field($fields)->join($join)->where($map_2)->fetchSql()->select();
        $total = $lists->total();
        $sum = $sumRes ? $sumRes['sum'] : 0;

        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }
        $statusList = config('score.order_status');

        $returnData = [
            'good' => $good,
            'lists' => $lists,
            'companys' => $companys,
            'time' => $time,
            'filter' => $filter,
            'status' => $status,
            'statusList' => $statusList,
            'pagesize' => $pagesize,
            'total' => $total,
            'sum' => $sum
        ];
        return $this->fetch('good_owners', $returnData);
    }


    /**
     * 删除记录
     * @param $id
     */
    public function delete($id)
    {
        $data = OrderModel::find($id);
        $this->checkDeptAuthByDid($data['region_id'], 1); //检查地区权限
        $admin_id = $this->userBaseInfo['uid'];

        if (OrderModel::where('id', $id)->update(['is_delete' => 1, "handler" => -1 * intval($admin_id)])) {
            $this->log('删除订单记录成功，id=' . $id, 0);
            return $this->jsonReturn(0, lang('Successfully deleted'));
        } else {
            $this->log('删除订单记录失败，id=' . $id, -1);
            return $this->jsonReturn(-1, lang('Failed to delete'));
        }
    }


    /**
     * 取消订单
     * @param $id
     */
    public function cancel($id)
    {
        $orderData = OrderModel::find($id);
        $this->checkDeptAuthByDid($orderData['region_id'], 1); //检查地区权限
        $admin_id = $this->userBaseInfo['uid'];

        if (intval($orderData['status']) !== 0) {
            return $this->jsonReturn(-1, lang('Only orders waiting to be redeemed are allowed to be cancelled'));
        }
        Db::connect('database_score')->startTrans();
        try {
            $orderData = OrderModel::where('id', $id)->find();
            $upDataOrderRes = OrderModel::where('id', $id)->update(['status' => -1, "handler" => -1 * intval($admin_id)]); //取消订单状态
            if (!$upDataOrderRes) {
                throw new \Exception(lang('Unsuccessful cancellation'));
            }
            $platform = $orderData['platform'] ?? 0;
            $isFromRP = $platform == 3; // 是否来源合理化建议，暂定来自h5的都是合理化建议
            $upScoredata = [
                'reason' => 200,
                'operand' => $orderData['total'],
                'account_id' => $orderData['creator'],
            ];
            $AccountModel = new AccountMixModel();
            $upScoreRes = $AccountModel->updateScore($upScoredata, $isFromRP);  //更新订单分数
            if (!$upScoreRes) {
                throw new \Exception($AccountModel->errorMsg);
            }
            // 提交事务
            Db::connect('database_score')->commit();
        } catch (\Exception $e) {
            Db::connect('database_score')->rollback();
            $this->log('取消订单失败，id=' . $id, -1);
            return $this->jsonReturn(-1, lang('Failed to delete order'), [], ["errorMsg" => $e->getMessage()]);
        }

        $this->log('取消订单记录成功，id=' . $id, 0);
        return $this->jsonReturn(0, lang('Successful cancellation'));
    }
}
