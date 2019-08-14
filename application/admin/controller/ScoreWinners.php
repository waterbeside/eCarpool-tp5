<?php

namespace app\admin\controller;

use think\facade\Env;
use app\admin\controller\AdminBase;
use app\common\model\Configs;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\user\model\Department as DepartmentModel;
use app\score\model\Account as ScoreAccountModel;
use app\score\model\Winners as WinnersModel;
use app\score\model\Prize as PrizeModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Db;

/**
 * 中奖管理
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScoreWinners extends AdminBase
{



    /**
     * 中奖列表
     * @return mixed
     */
    public function index($filter = [], $page = 1, $pagesize = 20, $export = 0)
    {
        $fields = 't.*
        ,pr.amount , pr.name as prize_name ,  pr.price , pr.level, pr.images, pr.total_count , pr.real_count
        ,lo.uuid as lottery_uuid, lo.buy_time
        ,ac.id as account_id , ac.carpool_account, ac.balance
        ,u.name as user_name , u.phone as user_phone , u.company_id ,u.loginname, u.Department, u.sex , u.companyname
        , d.fullname as full_department
        ';
        $join = [
            ['prize pr', 'pr.identity = t.prize_identity and pr.publication_number = t.publication_number', 'left'],
            ['lottery lo', 'lo.id = t.lottery_id', 'left'],
            ['account ac', 'ac.id = lo.account_id', 'left'],
            ['carpool.user u', 'u.loginname = ac.carpool_account', 'left'],
            // ['carpool.t_department ud','u.department_id = ud.id','left'],
            ['carpool.t_department d', 'lo.region_id = d.id', 'left'],
        ];
        $map = [];

        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['region_map'])) {
            $map[] = $authDeptData['region_map'];
        }
        // //地区排查
        // if($region_id){
        //   if(is_numeric($region_id)){
        //     $regionData = $this->getDepartmentById($region_id);
        //   }
        //   $region_map_sql = $this->buildRegionMapSql($region_id);
        //   $map[] = ['','exp', Db::raw($region_map_sql)];
        // }

        $map[] = ['t.is_delete', '<>', 1];
        //筛选奖品信息
        if (isset($filter['keyword_prize']) && $filter['keyword_prize']) {
            $map[] = ['pr.name', 'like', "%{$filter['keyword_prize']}%"];
        }

        //筛选用户信息
        if (isset($filter['keyword_user']) && $filter['keyword_user']) {
            $map[] = ['u.loginname|u.phone|u.name', 'like', "%{$filter['keyword_user']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            // $map[] = ['u.Department|u.companyname','like', "%{$filter['keyword_dept']}%"];
            $map[] = ['d.fullname', 'like', "%{$filter['keyword_dept']}%"];
        }

        if (isset($filter['is_exchange']) && $filter['is_exchange'] === '1') {
            $map[] = ['t.exchange_time', "> time", "1970-01-01"];
            $map[] = ['t.exchange_time', "NOT NULL", ""];
        }
        if (isset($filter['is_exchange']) && $filter['is_exchange'] === '0') {
            $map[] = ['t.exchange_time', "NULL", ""];
        }

        //筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }
        $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d H:i:s', 'd');
        if (count($time_arr) > 1) {
            $filterTimeField = isset($filter['time_type']) && $filter['time_type'] ? "t.lottery_time"  : "lo.buy_time";
            $map[] = ["$filterTimeField", '>=', $time_arr[0]];
            $map[] = ["$filterTimeField", '<', $time_arr[1]];
        }

        $lists = WinnersModel::alias('t')->field($fields)
            ->join($join)
            // ->view('lottery as lo', 'account_id,buy_time,total,platform,type,uuid ','lo.id = t.lottery_id','left')
            // ->view('account as ac','carpool_account','ac.id = lo.account_id','left')
            // ->view('carpool.user as u','name as user_name , phone as user_phone , company_id ,loginname, Department, sex , companyname','u.loginname = ac.carpool_account','left')
            ->json(['images'])
            ->where($map)
            ->order('end_time DESC')
            // ->fetchSql()->select();
            ->paginate($pagesize, false, ['query' => request()->param()]);

        $DepartmentModel = new DepartmentModel();
        foreach ($lists as $key => $value) {
            $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "";
            $lists[$key]['Department'] = $value['full_department'] ?
                $DepartmentModel->formatFullName($value['full_department'], 1) : $value['Department'];
        }
        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }
        // dump($lists);
        $returnData =  [
            'lists' => $lists,
            'pagesize' => $pagesize,
            'filter' => $filter,
            'companys' => $companys
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 中奖详情
     * @return mixed
     */
    public function detail($id)
    {
        if (!$id) {
            $this->error("Lost id");
        }

        //构建sql
        $fields = 't.*
            ,pr.amount , pr.name as prize_name ,  pr.price , pr.level, pr.images, pr.total_count , pr.real_count
            ,lo.uuid as lottery_uuid, lo.publish_number, lo.buy_time, lo.platform, lo.region_id
            ,ac.id as account_id , ac.carpool_account, ac.balance
        ';
        $join = [
            ['prize pr', 'pr.identity = t.prize_identity and pr.publication_number = t.publication_number', 'left'],
            ['lottery lo', 'lo.id = t.lottery_id', 'left'],
            ['account ac', 'ac.id = lo.account_id', 'left'],
        ];

        $data = WinnersModel::alias('t')->field($fields)
            ->join($join)
            ->json(['images'])
            // ->fetchSql()->select();
            ->find($id);

        if (!$data) {
            $this->error("数据不存在");
        } else {
            $this->checkDeptAuthByDid($data['region_id'], 1); //检查地区权限

            $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "";
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
        }


        $companyLists = (new CompanyModel())->getCompanys();
        $companys = [];
        foreach ($companyLists as $key => $value) {
            $companys[$value['company_id']] = $value['company_name'];
        }

        return $this->fetch('detail', ['data' => $data, 'companys' => $companys]);
    }




    /**
     * 完结订单
     * @param  integer $id       订单id
     * @param  string  $order_no 订单号
     */
    public function finish($id = 0)
    {
        $admin_id = $this->userBaseInfo['uid'];
        if ($this->request->isPost()) {
            if (!$id) {
                $this->error("Params error");
            }

            //构建sql
            $fields = 't.*
          ,lo.region_id
        ';
            $join = [
                ['lottery lo', 'lo.id = t.lottery_id', 'left'],
            ];
            $data = WinnersModel::alias('t')->field($fields)->join($join)->find($id);
            if (!$data) {
                $this->error("数据不存在");
            }
            // dump($data['region_id']);exit;
            $this->checkDeptAuthByDid($data['region_id'], 1); //检查地区权限

            if ($data['exchange_time']) {
                $this->error(lang('Redeemed, not operational'));
            }
            $result = WinnersModel::where('id', $id)->update(["exchange_time" => date('Y-m-d H:i:s')]);
            if ($result) {
                $this->log('完成兑奖成功' . json_encode($this->request->post()), 0);
                $this->success(lang('Successfully completed the redemption'));
            } else {
                $this->log('完成兑奖失败' . json_encode($this->request->post()), -1);
                $this->success(lang('Failed to completed the redemption failed'));
            }
        }
    }
}
