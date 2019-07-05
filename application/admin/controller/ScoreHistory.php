<?php

namespace app\admin\controller;

use app\score\model\History as HistoryModel;
use app\score\model\Account as ScoreAccountModel;
use app\carpool\model\User as CarpoolUserModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 积分历史
 * Class ScoreHistory
 * @package app\admin\controller
 */
class ScoreHistory extends AdminBase
{

  public  $check_dept_setting = [
    "action" => ['index', 'trips_group_user']
  ];

  /**
   * 积分历史（所有用户）
   * @return mixed
   */
  public function index($filter = null, $page = 1, $pagesize = 20)
  {
    $fields = 't.* ,d.fullname as full_department';
    $join = [
      ['carpool.t_department d', 't.region_id = d.id', 'left'],
    ];
    $map = [];
    $map[] = ['t.is_delete', '<>', 1];
    //地区排查 检查管理员管辖的地区部门
    $authDeptData = $this->authDeptData;
    if (isset($authDeptData['region_map'])) {
      $map[] = $authDeptData['region_map'];
    }

    // dump($filter);
    if ($filter['reason']) {
      $map[] = ['t.reason', '=', $filter['reason']];
    }

    //筛选时间
    if ($filter['time']) {
      $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d H:i:s', 'd');
      if (count($time_arr) > 1) {
        $map[] = ['t.time', '>=', $time_arr[0]];
        $map[] = ['t.time', '<', $time_arr[1]];
      }
    }


    $lists = HistoryModel::alias('t')->field($fields)->where($map)->join($join)
      ->order('t.time DESC, t.id DESC')->paginate($pagesize, false, ['query' => request()->param()]);
    foreach ($lists as $key => $value) {
      if ($value['account_id']) {
        $userInfo = ScoreAccountModel::where(['id' => $value['account_id']])->find();
        $lists[$key]['account'] = $userInfo['carpool_account'] ? $userInfo['carpool_account'] : ($userInfo['phone'] ? $userInfo['carpool_account'] : $userInfo['identifier']);
      } elseif ($value['carpool_account']) {
        $lists[$key]['account'] = $value['carpool_account'];
      } else {
        $lists[$key]['account'] = '-';
      }
    }

    $reasons = config('score.reason');

    $returnData = [
      'lists' => $lists,
      'filter' => $filter,
      'pagesize' => $pagesize,
      'reasons' => $reasons
    ];
    return $this->fetch('index', $returnData);
  }

  /**
   * 积分历史（指定用户）
   * @return mixed
   */
  public function lists($type = 1, $account = null, $account_id = null, $filter = null, $page = 1, $pagesize = 20)
  {
    $map = [];
    $map[] = ['is_delete', '<>', 1];

    // dump($filter);
    if ($filter['reason']) {
      $map[] = ['reason', '=', $filter['reason']];
    }
    if ($filter['time']) {
      $time_arr = explode(' ~ ', $filter['time']);
      if (is_array($time_arr)) {
        $time_s = date('Y-m-d H:i:s', strtotime($time_arr[0]));
        $time_e = date('Y-m-d H:i:s', strtotime($time_arr[1]) + 24 * 60 * 60);
        $map[] = ['time', 'between time', [$time_s, $time_e]];
      }
    }

    if ($type == '0' || $type == "score") { //直接从积分帐号取
      if (!$account && !$account_id) { //当account为空时，读取全部
        $this->error("Lost account or account_id");
      }
      if ($account_id) {
        $accountInfo = ScoreAccountModel::where(['id' => $account_id])->find();
      } else {
        $accountInfo = ScoreAccountModel::where(['account' => $account])->find();
      }

      if (!$accountInfo) {
        $this->error(lang('Account does not exist'));
      }
      if ($accountInfo['carpool_account']) {
        $userInfo = CarpoolUserModel::where(['loginname' => $account])->find();
      }
      $map[] = ['account_id', '=', $accountInfo['id']];
    } elseif ($type == '2' || $type == "carpool") { //从拼车帐号取
      if (!$account) { //当account为空时，读取全部
        $this->error("Lost account");
      }
      $accountInfo = ScoreAccountModel::where(['carpool_account' => $account])->find();
      $userInfo = CarpoolUserModel::where(['loginname' => $account])->find();
      if ($accountInfo) {
        $map[] = ['account_id', '=', $accountInfo['id']];
      } else {
        $map[] = ['carpool_account', '=', $account];
      }
    }
    if (isset($userInfo) && $userInfo) {
      $this->checkDeptAuthByDid($userInfo['department_id'], 1); //检查地区权限
    }

    $lists = HistoryModel::where($map)->order('time DESC, id DESC')->paginate($pagesize, false, ['query' => request()->param()]);

    $auth['admin/ScoreHistory/edit'] = $this->checkActionAuth('admin/ScoreHistory/edit');
    $auth['admin/ScoreHistory/delete'] = $this->checkActionAuth('admin/ScoreHistory/delete');
    $reasons = config('score.reason');

    $returnData = [
      'lists' => $lists,
      'filter' => $filter,
      'pagesize' => $pagesize,
      'type' => $type,
      'auth' => $auth,
      'account' => $account,
      'account_id' => $account_id,
      'reasons' => $reasons
    ];
    return $this->fetch('lists', $returnData);
  }


  /**
   * 删除记录
   * @param $id
   */
  public function delete($id)
  {
    $data = HistoryModel::find($id);
    $this->checkDeptAuthByDid($data['region_id'], 1); //检查地区权限
    if (HistoryModel::where('id', $id)->update(['is_delete' => 1])) {
      $this->log('删除积分记录成功，id=' . $id, 0);
      return $this->jsonReturn(0, lang('Successfully deleted'));
    } else {
      $this->log('删除积分记录失败，id=' . $id, -1);
      return $this->jsonReturn(-1, lang('Failed to delete'));
    }
  }

  // public function trips_group_user($filter= null , $pagesize = 20)
  // {
  //   $fields = 'ac.carpool_account , ac.balance',
  // }

  public function trips_group_user($filter = null, $pagesize = 15)
  {

    $fields = 't.account_id, t.reason , t.region_id, 
    sum(t.operand) as operand_sum , max(t.time) as max_time ,
    count(t.account_id) as total,
    ROUND( sum(t.operand) / count(t.account_id),2 )  as rate, 
    ac.carpool_account, ac.balance,
    d.fullname as full_department';
    $join = [
      ['carpool.t_department d', 't.region_id = d.id', 'left'],
      ['t_account ac', 't.account_id = ac.id', 'left'],
    ];
    $map = [];
    $map[] = ['t.is_delete', '<>', 1];
    $map[] = ['t.reason', '=', 100];
    $map[] = ['', 'exp', Db::raw('t.account_id IS NOT NULL')];

    // //筛选分数范围 - 下限
    // if (isset($filter['floor']) && is_numeric($filter['floor'])  ){
    //   $map[] = ['sum(t.operand)','EGT', $filter['floor']];
    // }
    // //筛选分数范围 - 上限
    // if (isset($filter['ceiling']) && is_numeric($filter['ceiling']) ){
    //   $map[] = ['sum(t.operand)','ELT', $filter['ceiling']];
    // }

    if (!isset($filter['time']) || !$filter['time']) {
      $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
    }

    //筛选时间
    if ($filter['time']) {
      $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d H:i:s', 'd');
      if (count($time_arr) > 1) {
        $map[] = ['t.time', '>=', $time_arr[0]];
        $map[] = ['t.time', '<', $time_arr[1]];
      }
    }




    //地区排查 检查管理员管辖的地区部门
    $authDeptData = $this->authDeptData;
    if (isset($authDeptData['region_map'])) {
      $map[] = $authDeptData['region_map'];
    }


    // $map_c1 = [
    //   ['time', '>=', $time_arr[0]],
    //   ['time', '<', $time_arr[1]],
    //   ['reason', '=', 100],
    //   ['operand', '>', 0],
    //   ['', 'exp', Db::raw('account_id = t.account_id')]
    // ];
    // $sql_count_success = HistoryModel::field('count(*)')->where($map_c1)->buildSql();

    // $map_c2 = [
    //   ['time', '>=', $time_arr[0]],
    //   ['time', '<', $time_arr[1]],
    //   ['reason', '=', 100],
    //   ['operand', '=', 0],
    //   ['', 'exp', Db::raw('account_id = t.account_id')]
    // ];
    // $sql_count_fail = HistoryModel::field('count(*)')->where($map_c2)->buildSql();

    // $fields .= ", $sql_count_success as count_success , $sql_count_fail as count_failed";



    $lists = HistoryModel::alias('t')->field($fields)->where($map)->group('t.account_id, t.reason, t.region_id')->join($join)
      ->order('rate DESC, operand_sum DESC, max_time DESC')
      ->paginate($pagesize, false, ['query' => request()->param()]);
    // ->fetchSql()->select();
    // dump($lists);exit;

    foreach ($lists as $key => $value) {
      // 创建 子map
      $map_c1 = [
        ['time', '>=', $time_arr[0]],
        ['time', '<', $time_arr[1]],
        ['operand', '>', 0],
        ['reason', '=', 100],
        ['account_id', '=', $value['account_id']],
        // ['','exp',Db::raw('account_id = t.account_id')]
      ];
      $lists[$key]['count_success'] = HistoryModel::where($map_c1)->count();
      $map_c2 = [
        ['time', '>=', $time_arr[0]],
        ['time', '<', $time_arr[1]],
        ['operand', '=', 0],
        ['reason', '=', 100],
        ['account_id', '=', $value['account_id']],
        // ['','exp',Db::raw('account_id = t.account_id')]
      ];
      $lists[$key]['count_failed']  = HistoryModel::where($map_c2)->count();
    }

    $reasons = config('score.reason');

    $returnData = [
      'lists' => $lists,
      'filter' => $filter,
      'pagesize' => $pagesize,
      'reasons' => $reasons
    ];
    return $this->fetch('trips_group_user', $returnData);
  }
}
