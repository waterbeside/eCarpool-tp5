<?php
namespace app\admin\controller;


use think\facade\Env;
use app\admin\controller\AdminBase;
use app\common\model\Configs;
use app\carpool\model\User as CarpoolUserModel;
use app\carpool\model\Company as CompanyModel;
use app\score\model\Account as ScoreAccountModel;
use app\score\model\Winners as WinnersModel;
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
  public function index($filter=[],$type="all",$result="all",$page = 1,$pagesize = 20,$export=0)
  {
    $map = [];
    $map[] = ['t.is_delete','<>', 1];
    //筛选用户信息
    if (isset($type) && is_numeric($type) ){
      $map[] = ['t.type','=', $type];
    }
    //筛选时间
    if(!isset($filter['time']) || !$filter['time'] || !is_array(explode(' ~ ',$filter['time']))){
      $time_s = date("Y-m-01");
      $time_e = date("Y-m-d",strtotime("$time_s +1 month"));
      $time_e_o = date("Y-m-d",strtotime($time_e)- 24*60*60);
      $filter['time'] = $time_s." ~ ".$time_e_o;
    }
    $time_arr = explode(' ~ ',$filter['time']);
    $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
    $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
    $map[] = ['buy_time', 'between time', [$time_s, $time_e]];


    $isJoinUser = false;
    //筛选用户信息
    if (isset($filter['keyword_user']) && $filter['keyword_user'] ){
      $map[] = ['u.loginname|u.phone|u.name','like', "%{$filter['keyword_user']}%"];
      $isJoinUser = true;
    }
    //筛选部门
    if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
      $map[] = ['u.Department|u.companyname','like', "%{$filter['keyword_dept']}%"];
      $isJoinUser = true;
    }



    //筛选奖品信息
    if (isset($filter['keyword_prize']) && $filter['keyword_prize'] ){
      $map[] = ['pr.name','like', "%{$filter['keyword_prize']}%"];
    }


    //筛选奖品信息
    if (is_numeric($result)){
      $map[] = ['t.result','=', $result];
    }else if($result == "gt_0"){
      $map[] = ['t.result','>', 0];
    }

    //构建sql
    $fields = 't.* ';
    $fields .=' ,pr.amount , pr.name as prize_name ,  pr.price , pr.level, pr.images, pr.total_count , pr.real_count';
    $fields .=' ,ac.id as account_id , ac.carpool_account, ac.balance';
    // $fields .=' ,u.name as user_name , u.phone as user_phone , u.company_id ,u.loginname, u.Department, u.sex , u.companyname';
    $join = [
      ['prize pr', 'pr.identity = t.prize_identity and pr.publication_number = t.publication_number','left'],
      ['account ac','ac.id = t.account_id','left'],
      // ['carpool.user u','u.loginname = ac.carpool_account','left'],
    ];


    if($isJoinUser){
      $fields .=' ,u.name as user_name , u.phone as user_phone , u.company_id ,u.loginname, u.Department, u.sex , u.companyname';
      $join[] =  ['carpool.user u','u.loginname = ac.carpool_account','left'];
    }
    $lists = LotteryModel::alias('t')->field($fields)
            ->join($join)
            ->json(['images'])
            ->where($map)
            ->order('buy_time DESC')
            // ->fetchSql()->select();
            ->paginate($pagesize, false,  ['query'=>request()->param()]);
    // dump($lists);exit;

    foreach ($lists as $key => $value) {
      if( !$isJoinUser){
        $userInfo = CarpoolUserModel::where(['loginname'=>$value['carpool_account']])->find();
        $lists[$key]['uid'] = $userInfo['uid'] ;
        $lists[$key]['loginname'] = $userInfo['loginname'] ;
        $lists[$key]['user_name'] = $userInfo['name'] ;
        $lists[$key]['user_phone'] = $userInfo['phone'] ;
        $lists[$key]['Department'] = $userInfo['Department'] ;
        $lists[$key]['sex'] = $userInfo['sex'] ;
        $lists[$key]['company_id'] = $userInfo['company_id'] ;
        $lists[$key]['companyname'] = $userInfo['companyname'] ;
      }
      $lists[$key]['thumb'] = is_array($value["images"]) ? $value["images"][0] : "" ;
    }
    $companyLists = (new CompanyModel())->getCompanys();
    $companys = [];
    foreach($companyLists as $key => $value) {
      $companys[$value['company_id']] = $value['company_name'];
    }

    return $this->fetch('index', ['lists' => $lists,'pagesize'=>$pagesize,'filter'=>$filter,'companys'=>$companys,'type'=>$type,'result'=>$result]);

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
    $join = [
      ['prize pr', 'pr.identity = t.prize_identity and pr.publication_number = t.publication_number','left'],
      ['account ac','ac.id = t.account_id','left'],
    ];

    $data = LotteryModel::alias('t')->field($fields)
            ->join($join)
            ->json(['images'])
            // ->fetchSql()->select();
            ->find($id);

    if(!$data){
      $this->error("数据不存在");
    }else{
      $data['thumb'] = is_array($data["images"]) ? $data["images"][0] : "" ;

      $data['userInfo'] = CarpoolUserModel::where(['loginname'=>$data['carpool_account']])->find();
      if($data['result'] > 0 && $data['type'] ==1){
        $data['winnersInfo'] = WinnersModel::alias('t')->where('lottery_id',$data['id'])->find();
      }
      if($data['userInfo']){
        $data['userInfo']['avatar'] = $data['userInfo']['imgpath'] ? config('app.avatarBasePath').$data['userInfo']['imgpath'] : config('app.avatarBasePath')."im/default.png";
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
