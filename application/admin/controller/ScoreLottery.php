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
use app\score\model\IntegralSpecialWinner as SpecialWinnerModel;
use app\score\model\Lottery as LotteryModel;
use app\score\model\Prize as PrizeModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Db;

/**
 * 抽奖情况管理
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScoreLottery extends AdminBase
{



  /**
   * 已抽奖列表
   * @return mixed
   */
  public function index($filter=[],$type="all",$result="all",$page = 1,$pagesize = 20, $export=0)
  {
    $map = [];
    $map[] = ['t.is_delete','<>', 1];


    //构建sql
    $fields = 't.* ';
    $fields .=' ,pr.amount , pr.name as prize_name ,  pr.price , pr.level, pr.images, pr.total_count , pr.real_count';
    $fields .=' ,ac.id as account_id , ac.carpool_account, ac.balance';
    $fields .=' ,u.name as user_name , u.phone as user_phone , u.company_id ,u.loginname, u.Department, u.sex , u.companyname,  d.fullname as full_department';

    $join = [
      ['prize pr', 'pr.identity = t.prize_identity and pr.publication_number = t.publication_number','left'],
      ['account ac','ac.id = t.account_id','left'],
      ['carpool.user u','u.loginname = ac.carpool_account','left'],
      // ['carpool.t_department d','u.department_id = d.id','left'],
      ['carpool.t_department d','t.region_id = d.id','left'],
    ];


    //地区排查 检查管理员管辖的地区部门
    $authDeptData = $this->authDeptData;
    if(isset($authDeptData['region_map'])){
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

    //筛选用户信息
    if (isset($type) && is_numeric($type) ){
      $map[] = ['t.type','=', $type];
    }
    //筛选时间
    if(!isset($filter['time']) || !$filter['time']){
      $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d','m');
    }
    $time_arr = $this->formatFilterTimeRange($filter['time'],'Y-m-d H:i:s','d');
    if(count($time_arr)>1){
      $map[] = ['t.buy_time', '>=', $time_arr[0]];
      $map[] = ['t.buy_time', '<', $time_arr[1]];
    }

    //筛选用户信息
    if (isset($filter['keyword_user']) && $filter['keyword_user'] ){
      $map[] = ['u.loginname|u.phone|u.name','like', "%{$filter['keyword_user']}%"];

    }
    //筛选部门
    if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
      $map[] = ['u.Department|u.companyname','like', "%{$filter['keyword_dept']}%"];
      // $map[] = ['d.fullname','like', "%{$filter['keyword_dept']}%"];

    }

    //筛选奖品信息
    if (isset($filter['keyword_prize']) && $filter['keyword_prize'] ){
      if (isset($type) && is_numeric($type) && $type == 1 ){
        $map[] = ['pr.name','like', "%{$filter['keyword_prize']}%"];
      }else{
        $map[] = ['pr.name|t.result_str','like', "%{$filter['keyword_prize']}%"];
      }
    }


    //筛选奖品信息
    if (is_numeric($result)){
      $map[] = ['t.result','=', $result];
    }else if($result == "gt_0"){
      $map[] = ['t.result','>', 0];
    }


    $lists = LotteryModel::alias('t')->field($fields)
            ->join($join)
            ->json(['images'])
            ->where($map)
            ->order('buy_time DESC')
            // ->fetchSql()->select();
            ->paginate($pagesize, false,  ['query'=>request()->param()]);
    // dump($lists);exit;
    $DepartmentModel = new DepartmentModel();

    foreach ($lists as $key => $value) {
      $lists[$key]['Department'] = $lists[$key]['full_department'] ? $DepartmentModel->formatFullName($lists[$key]['full_department'],1) : $lists[$key]['Department']  ;
      $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;

    }
    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }
    $returnData = [
      'lists' => $lists,
      'pagesize'=>$pagesize,
      'filter'=>$filter,
      'companys'=>$companys,
      'type'=>$type,
      'result'=>$result
    ];
    return $this->fetch('index', $returnData);

  }


  /**
   * 抽奖详情
   * @return mixed
   */
  public function detail($id)
  {
    if(!$id){
      $this->error("Lost id");
    }

    //构建sql
    $fields = 't.* ';
    $fields .=' ,pr.amount , pr.name as prize_name ,  pr.price , pr.level, pr.images, pr.total_count , pr.real_count';
    $fields .=' ,ac.id as account_id , ac.carpool_account, ac.balance';
    $fields .=' ,d.fullname as full_department';
    $join = [
      ['prize pr', 'pr.identity = t.prize_identity and pr.publication_number = t.publication_number','left'],
      ['account ac','ac.id = t.account_id','left'],
      ['carpool.t_department d','t.region_id = d.id','left'],
    ];

    $data = LotteryModel::alias('t')->field($fields)
            ->join($join)
            ->json(['images'])
            // ->fetchSql()->select();
            ->find($id);

    if(!$data){
      $this->error(lang('Data does not exist'));
    }else{
      $this->checkDeptAuthByDid($data['region_id'],1); //检查地区权限

      $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "" ;
      $data['userInfo'] = CarpoolUserModel::alias('t')
                          ->field('t.*, d.fullname as full_department')
                          ->join([['t_department d','t.department_id = d.id','left']])
                          ->where(['loginname'=>$data['carpool_account']])
                          ->find();
      if($data['result'] > 0 && $data['type'] ==1){
        $data['winnersInfo'] = WinnersModel::alias('t')->where('lottery_id',$data['id'])->find();
      }
      if($data['result'] < 0 && $data['type'] === 0){
        $data['winnersInfo'] = SpecialWinnerModel::alias('t')->where('lottery_id',$data['id'])->find();
      }
      if($data['userInfo']){
        $data['userInfo']['avatar'] = $data['userInfo']['imgpath'] ? config('secret.avatarBasePath').$data['userInfo']['imgpath'] : config('secret.avatarBasePath')."im/default.png";
      }

    }


    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }


    return $this->fetch('detail', ['data' => $data,'companys' => $companys]);

  }





}
