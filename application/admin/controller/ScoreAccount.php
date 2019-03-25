<?php
namespace app\admin\controller;

use app\score\model\Account as ScoreAccountModel;
use app\score\model\AccountMix as AccountMixModel;
use app\carpool\model\User as CarpoolUserModel;
use app\admin\controller\AdminBase;
use think\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * 积分帐号
 * Class Link
 * @package app\admin\controller
 */
class ScoreAccount extends AdminBase
{

    /**
     * 积分帐号
     * @return mixed
     */
    public function index($type='2',$filter=[],$page = 1,$pagesize = 20,$region_id=0,$export=0)
    {
      if($export){
        ini_set ('memory_limit', '128M');
        if( !$filter['floor'] &&  !$filter['ceiling'] && !$filter['keyword'] && !$filter['keyword_dept']){
          $this->error('数据量过大，请筛选后再导出');
        }
      }

      // $type = strval($type);
      if( $type=='0'|| $type=="score"){  //积分帐号列表
        $fields = 'ac.carpool_account , ac.id as account_id, ac.account, ac.balance, ac.identifier, ac.platform,ac.register_date, ac.status,
        d.fullname as full_department, d.path ';
        $map = [];
        $join = [];

        $map[] = ['ac.is_delete','<>', 1];

        $fields .=' ,cu.uid ,cu.name as name , cu.phone as phone , cu.company_id ,cu.loginname, cu.Department, cu.sex , cu.companyname, d.fullname as full_department';
        $fields .=' ,c.company_name ';
        $join[] =  ['carpool.user cu','cu.loginname = ac.carpool_account', 'left'];
        $join[] =  ['carpool.company c','cu.company_id = c.company_id','left'];
        $join[] =  ['carpool.t_department d','cu.department_id = d.id','left'];


        //地区排查
        if($region_id){
          if(is_numeric($region_id)){
            $regionData = $this->getDepartmentById($region_id);
          }
          $region_map_sql = $this->buildRegionMapSql($region_id);
          $map[] = ['','exp', Db::raw($region_map_sql)];
        }

        //筛选分数范围 - 下限
        if (isset($filter['floor']) && is_numeric($filter['floor']) && $filter['floor']>0 ){
          $map[] = ['ac.balance','EGT', $filter['floor']];
        }
        //筛选分数范围 - 上限
        if (isset($filter['ceiling']) && is_numeric($filter['ceiling']) && $filter['ceiling']>0 ){
          $map[] = ['ac.balance','ELT', $filter['ceiling']];
        }
        $isJoinUser = $export ? true : false;
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword'] ){
          $map[] = ['cu.loginname|cu.name|cu.phone','like', "%{$filter['keyword']}%"];
          $isJoinUser = true;
        }
        // //筛选部门
        // if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
        //   $map[] = ['cu.Department|cu.companyname','like', "%{$filter['keyword_dept']}%"];
        //   $isJoinUser = true;
        // }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
          $map[] = ['d.fullname|cu.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
          // $map[] = ['u.Department|u.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
          $isJoinUser = true;
        }



        if($export){
          $lists = ScoreAccountModel::alias('ac')->field($fields)->join($join)->where($map)->order('ac.id DESC')->select();
        }else{
          $lists = ScoreAccountModel::alias('ac')->field($fields)->join($join)->where($map)->order('ac.id DESC')
          // ->fetchSql()->select();echo($lists);exit;
          ->paginate($pagesize, false,  ['query'=>request()->param()]);
          // dump($lists);exit;
        }

        if(!$export){
          return $this->fetch('index', ['lists' => $lists, 'filter' => $filter,'pagesize'=>$pagesize,'type'=>$type]);
        }

      }elseif($type=='1'||$type=="phone"){//电话

      }elseif($type=='2'||$type=="carpool"){ //拼车帐号列表

        $fields  ='cu.uid ,cu.name as name , cu.phone as phone , cu.company_id ,cu.loginname, cu.Department, cu.sex , cu.companyname, cu.is_active, d.fullname as full_department';
        $fields .=' ,c.company_name ';
        $map = [];
        $isJoinAccount = $export ? true : false;
        //筛选分数范围 - 下限
        if (isset($filter['floor']) && is_numeric($filter['floor']) && $filter['floor']>0 ){
          $map[] = ['ac.balance','EGT', $filter['floor']];
          $isJoinAccount = true;
        }
        //筛选分数范围 - 上限
        if (isset($filter['ceiling']) && is_numeric($filter['ceiling']) && $filter['ceiling']>0 ){
          $map[] = ['ac.balance','ELT', $filter['ceiling']];
          $isJoinAccount = true;
        }
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword'] ){
          $map[] = ['cu.loginname|cu.phone|cu.name','like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
          // $map[] = ['cu.Department|cu.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
          $map[] = ['d.fullname|cu.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
        }
        $join = [
          ['company c','cu.company_id = c.company_id','left'],
        ];
        $join[] =  ['t_department d','cu.department_id = d.id','left'];

        if($isJoinAccount){
          $fields .=' ,ac.carpool_account ,ac.id as account_id  , ac.account , ac.balance ,ac.identifier, ac.platform, ac.register_date';
          $join[] =  ['carpool_score.t_account ac','cu.loginname = ac.carpool_account', 'left'];
        }
        if($export){
          $lists = CarpoolUserModel::alias('cu')->field($fields)->join($join)->where($map)->order('uid DESC')->select();
        }else{
          $lists = CarpoolUserModel::alias('cu')->field($fields)->join($join)->where($map)->order('uid DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
        }

        foreach ($lists as $key => $value) {
          if(!$isJoinAccount){
            $accountInfo = ScoreAccountModel::where(['carpool_account'=>$value['loginname']])->find();
            $lists[$key]['carpool_account'] = $accountInfo['carpool_account'] ? $accountInfo['carpool_account'] : '';
            $lists[$key]['account_id'] = $accountInfo['id'] ? $accountInfo['id'] : '';
            $lists[$key]['account'] = $accountInfo['account'] ? $accountInfo['account'] : '';
            $lists[$key]['balance'] = $accountInfo['balance'] ? $accountInfo['balance'] : '';
            $lists[$key]['identifier'] = $accountInfo['identifier'] ? $accountInfo['identifier'] : '';
            $lists[$key]['platform'] = $accountInfo['platform'] ? $accountInfo['platform'] : '';
            $lists[$key]['register_date'] = $accountInfo['register_date'] ? $accountInfo['register_date'] : '';
          }

        }
        if(!$export){
          return $this->fetch('index_carpool', ['lists' => $lists, 'filter' => $filter,'pagesize'=>$pagesize,'type'=>$type]);
        }
      }

      //导出表格
      if($export){
        $filename = md5(json_encode($filter)).'_'.time().'.csv';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /*设置表头*/
        $sheet->setCellValue('A1', '用户id')
        ->setCellValue('B1','姓名')
        ->setCellValue('C1','电话')
        ->setCellValue('D1','账号')
        ->setCellValue('E1','公司')
        ->setCellValue('F1','分厂')
        ->setCellValue('G1','部门')
        ->setCellValue('H1','部门(HR)')
        ->setCellValue('I1','积分余额')
        ->setCellValue('J1','性别')
        ;

        foreach ($lists as $key => $value) {
          $rowNum = $key+2;
          $sheet->setCellValue('A'.$rowNum, $value['uid'])
          ->setCellValue('B'.$rowNum,$value['name'])
          ->setCellValue('C'.$rowNum,$value['phone'])
          ->setCellValue('D'.$rowNum,$value['loginname'] ? $value['loginname'] : '☹︎ '.$value['carpool_account'])
          ->setCellValue('E'.$rowNum,$value['company_name'] ?  $value['company_name'] :  $value['company_id'] )
          ->setCellValue('F'.$rowNum,$value['companyname'])
          ->setCellValue('G'.$rowNum,$value['Department'])
          ->setCellValue('H'.$rowNum,$value['full_department'])
          ->setCellValue('I'.$rowNum,$value['balance'])
          ->setCellValue('J'.$rowNum,$value['sex'])
          ;
        }
        /*$value = "Hello World!" . PHP_EOL . "Next Line";
        $sheet->setCellValue('A1', $value)；
        $sheet->getStyle('A1')->getAlignment()->setWrapText(true);*/

        $writer = new Csv($spreadsheet);
        /*$filename = Env::get('root_path') . "public/uploads/temp/hello_world.xlsx";
        $writer->save($filename);*/
        header('Content-Disposition: attachment;filename="'.$filename.'"');//告诉浏览器输出浏览器名称
        header('Cache-Control: max-age=0');//禁止缓存
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        // dump($lists);
        exit;
      }
    }

    /**
     * 取得积分帐号信息
     */
    public function detail($type=2,$account=NULL,$account_id=NULL){

      $accountInfo = null;
      $userInfo = null;
      $accountModel = new AccountMixModel();
      if( ($type=='0' || $type=="score") && $account_id ){ //直接从积分帐号取
        $accountInfo = $accountModel->getDetailById($account_id);
      }else{
        $accountInfo = $accountModel->getDetailByAccount($type,$account);
      }
      if($accountInfo && $accountInfo['carpool']){
        $userInfo = $accountInfo['carpool'];
      }
      if($userInfo){
        $userInfo->avatar = $userInfo->imgpath ? config('secret.avatarBasePath').$userInfo->imgpath : config('secret.avatarBasePath')."im/default.png";
      }
      if(!isset($accountInfo['id'])){
        $accountInfo = NULL ;
      }
      $returnData = [
        'accountInfo'=>$accountInfo,
        'userInfo'=>$userInfo
      ];
      return $this->fetch('detail', $returnData);

    }

    /**
     * 取得积分帐号信息
     */
    public function public_get_account($type=2){
      /*account = 0,
      phone = 1,
      carpool = 2,
      weixin = 3,
      qq = 4,*/
      $account  = $this->request->get('account');
      if($type=='2'||$type=="carpool"){
        if(!$account){
          $this->jsonReturn(-1,[],'lost account');
        }
        $map = [];
        $map[] = ['is_delete','<>', 1];
        $fieldName = "carpool_account";
        $map[$fieldName] = $account;
        $map["is_delete"]= 0;
        $data = ScoreAccountModel::where($map)->field("id,account,platform,register_date,identifier,balance")->find();
        $this->jsonReturn(0,$data,'success');
      }
    }

    //取得积分
    public function public_get_balance($type=2){
      if($type=='2'||$type=="carpool"){
        $account  = $this->request->get('account');
        if(!$account){
          $this->jsonReturn(-1,[],'lost account');
        }
        $map = [];
        $map[] = ['is_delete','<>', 1];
        $fieldName = "carpool_account";
        $map[$fieldName] = $account;
        $map["is_delete"]= 0;
        $data = ScoreAccountModel::where($map)->value("balance");
        $this->jsonReturn(0,$data,'success');
      }
    }

}
