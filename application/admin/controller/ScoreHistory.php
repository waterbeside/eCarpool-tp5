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


    /**
     * 积分历史（所有用户）
     * @return mixed
     */
    public function index($filter=null, $page = 1, $pagesize = 20)
    {
        $fields = 't.* ,d.fullname as full_department';
        $join = [
          ['carpool.t_department d','t.region_id = d.id','left'],
        ];
        $map = [];
        $map[] = ['t.is_delete','<>', 1];
        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if(isset($authDeptData['region_map'])){
          $map[] = $authDeptData['region_map'];
        }

        // dump($filter);
        if ($filter['reason']) {
            $map[] = ['t.reason','=', $filter['reason']];
        }

        //筛选时间
        if ($filter['time']) {
          $time_arr = $this->formatFilterTimeRange($filter['time'],'Y-m-d H:i:s','d');
          if(count($time_arr)>1){
            $map[] = ['t.time', '>=', $time_arr[0]];
            $map[] = ['t.time', '<', $time_arr[1]];
          }
        }


        $lists = HistoryModel::alias('t')->field($fields)->where($map)->join($join)
        ->order('t.time DESC, t.id DESC')->paginate($pagesize, false, ['query'=>request()->param()]);
        foreach ($lists as $key => $value) {
            if ($value['account_id']) {
                $userInfo = ScoreAccountModel::where(['id'=>$value['account_id']])->find();
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
          'pagesize'=>$pagesize,
          'reasons'=>$reasons
        ];
        return $this->fetch('index', $returnData);
    }

    /**
     * 积分历史（指定用户）
     * @return mixed
     */
    public function lists($type=1, $account=null, $account_id=null, $filter=null, $page = 1, $pagesize = 20)
    {
        $map = [];
        $map[] = ['is_delete','<>', 1];

        // dump($filter);
        if ($filter['reason']) {
            $map[] = ['reason','=', $filter['reason']];
        }
        if ($filter['time']) {
            $time_arr = explode(' ~ ', $filter['time']);
            if (is_array($time_arr)) {
                $time_s = date('Y-m-d H:i:s', strtotime($time_arr[0]));
                $time_e = date('Y-m-d H:i:s', strtotime($time_arr[1]) + 24*60*60);
                $map[] = ['time', 'between time', [$time_s, $time_e]];
            }
        }

        if ($type=='0' || $type=="score") { //直接从积分帐号取
            if (!$account&&!$account_id) { //当account为空时，读取全部
              $this->error("Lost account or account_id");
            }
            if ($account_id) {
                $accountInfo = ScoreAccountModel::where(['id'=>$account_id])->find();
            } else {
                $accountInfo = ScoreAccountModel::where(['account'=>$account])->find();
            }

            if (!$accountInfo) {
                $this->error("账号不存在");
            }
            if ($accountInfo['carpool_account']) {
                $userInfo = CarpoolUserModel::where(['loginname'=>$account])->find();
            }
            $map[] = ['account_id','=', $accountInfo['id']];
        } elseif ($type=='2'||$type=="carpool") { //从拼车帐号取
            if (!$account) { //当account为空时，读取全部
              $this->error("Lost account");
            }
            $accountInfo = ScoreAccountModel::where(['carpool_account'=>$account])->find();
            $userInfo = CarpoolUserModel::where(['loginname'=>$account])->find();
            if ($accountInfo) {
                $map[] = ['account_id','=', $accountInfo['id']];
            } else {
                $map[] = ['carpool_account','=', $account];
            }
        }
        if(isset($userInfo) && $userInfo){
          $this->checkDeptAuthByDid($userInfo['department_id'],1); //检查地区权限
        }

        $lists = HistoryModel::where($map)->order('time DESC, id DESC')->paginate($pagesize, false, ['query'=>request()->param()]);

        $auth['admin/ScoreHistory/edit'] = $this->checkActionAuth('admin/ScoreHistory/edit');
        $auth['admin/ScoreHistory/delete'] = $this->checkActionAuth('admin/ScoreHistory/delete');
        $reasons = config('score.reason');

        $returnData = [
          'lists' => $lists,
          'filter' => $filter,
          'pagesize'=>$pagesize,
          'type'=>$type,
          'auth'=>$auth,
          'account'=>$account,
          'account_id'=>$account_id,
          'reasons'=>$reasons
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
        $this->checkDeptAuthByDid($data['region_id'],1); //检查地区权限
        if (HistoryModel::where('id', $id)->update(['is_delete' => 1])) {
            $this->log('删除积分记录成功，id='.$id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除积分记录失败，id='.$id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }
}
