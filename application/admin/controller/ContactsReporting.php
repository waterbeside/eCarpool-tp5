<?php

namespace app\admin\controller;


use app\carpool\model\ContactsReporting as ReportingModel;
use app\admin\controller\AdminBase;
use app\user\model\Department;

/**
 * 通讯录管理
 * Class Contacts
 * @package app\admin\controller
 */
class ContactsReporting extends AdminBase
{

  /**
   * 列表
   *
   * @param array $filter
   * @param integer $pagesize 每页条数
   */
  public function index($filter = null, $pagesize = 15)
  {
    $map = [];
    $uid = isset($filter['uid']) && is_numeric($filter['uid']) ?  $filter['uid'] : 0;
    if ($uid > 0) {
      $map[] = ['t.uid', '=', $uid];
    }
    if (isset($filter['keyword']) && $filter['keyword'] != "") {
      $map[] = ['u.name|u.loginname|u.nativename', 'like', $filter['keyword']];
    }
    if (isset($filter['is_delete']) && is_numeric($filter['is_delete'])) {
      $map[] = ['t.is_delete', '=', $filter['is_delete'] == 1 ? 1 : 0];
    }
    $field = "t.*, u.name, u.loginname, u.nativename, u.Department";
    $lists = ReportingModel::alias('t')
      ->field($field)
      ->join([['user u', 't.uid = u.uid', 'left']])
      ->where($map)
      ->order('t.start_time DESC, t.end_time DESC')
      ->paginate($pagesize, false, ['query' => request()->param()]);
    $returData = [
      'lists' => $lists,
      'filter' => $filter,
    ];
    return $this->fetch('index', $returData);
  }


  /**
   * 通过按钮对话框访问的列表
   *
   * @param integer $uid 用户id
   */
  public function list_by_user($uid)
  {
    return $this->index(['uid' => $uid]);
  }

  public function detail($id)
  {
    if (!$id) {
      $this->error('Error id');
    }
    $field = "t.*, u.name, u.loginname, u.nativename, u.Department, u.is_delete as u_is_delete";
    $data = ReportingModel::alias('t')
      ->field($field)
      ->join([['user u', 't.uid = u.uid', 'left']])
      ->find($id);

    if (!$data) {
      return $this->error('数据不存在');
    }
    $history = json_decode($data->history, true);
    $historyData = [];
    if ($history) {
      $Department = new Department();
      foreach ($history as $key => $value) {
        if (isset($value['nid'])) {
          $value['node'] = $Department->getItem($value['nid']);
          $historyData[] = $value;
        }
      }
    }


    $returData = [
      'data' => $data,
      'history' => $historyData
    ];

    return $this->fetch('detail', $returData);
  }

  /**
   * 删除
   *
   * @param integer $id
   */
  public function delete($id)
  {
    if (!$id || !is_numeric($id)) {
      return $this->jsonReturn(992, 'Error params');
    }
    $res = ReportingModel::where([['id', '=', $id]])->update(['is_delete' => 1]);
    if ($res === false) {
      $this->log("移除通讯录可疑行为失败。id = $id", -1);
      return $this->jsonReturn(-1, '删除失败');
    } else {
      $this->log("移除通讯录可疑行为成功。id = $id", 0);
      return $this->jsonReturn(0, '删除成功');
    }
  }
}
