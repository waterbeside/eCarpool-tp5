<?php
namespace app\admin\controller;

use app\admin\controller\AdminBase;
use think\Db;
use app\common\model\Pushmsg as PushmsgModel;

/**
 * 推送功能
 * Class Slide
 * @package app\admin\controller
 */
class Pushmsg extends AdminBase
{
    protected function initialize()
    {
        parent::initialize();
    }


    /**
     * 管理后台推送消息管理
     * @return mixed
     */
    public function index($filter = [], $page = 1)
    {
        $map = [];
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['u.loginname|u.phone|u.name','like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept']) {
            $map[] = ['d.fullname|u.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
            // $map[] = ['u.Department|u.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
        }

        $field = "t.*, u.loginname, u.name,u.nativename ";

        $join = [
         ['carpool.user u','t.uid = u.uid', 'left']
        ];
        $order = 'create_time DESC ';

        $lists = PushMsgModel::alias('t')->join($join)->where($map)->order($order)->field($field)->json(['extra_data'])->paginate(50, false, ['query'=>request()->param()]);
        return $this->fetch('index', ['lists' => $lists, 'filter' => $filter]);
    }

    /**
     * 添加
     * @return mixed
     */
    public function add($uid = 0)
    {

        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $data['creator'] = $this->userBaseInfo['uid'];

            $validate_result = $this->validate($data, 'Pushmsg');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            } else {
                $PushMsgModel = new PushMsgModel();
                $app_id = $data['app_id'];

                if ($PushMsgModel->allowField(true)->save($data)) {
                    $push_id = $PushMsgModel->id; //插入成功后取得id
                    try {
                      $push_res = $PushMsgModel->push($uid,$data,$app_id);
                      if(!$push_res){
                        $push_res = $PushMsgModel->errorMsg;
                      }
                    } catch (\Exception $e) {
                      $push_res = $e->getMessage();
                    }
                    // $this->jsonReturn(0,$push_res,'提交成功',['errorMsg'=>$PushMsgModel->errorMsg]);exit;
                    $PushMsgModel->extra_data =  json_encode(['push_res'=>$push_res]);
                    $PushMsgModel->push_time  = date("Y-m-d H:i:s") ;
                    if(isset($push_res['result']) && $push_res['result']=='ok'){
                      $PushMsgModel->push_status = 1;
                    }else{
                      $PushMsgModel->push_status = -1;
                    }
                    $fill_back = $PushMsgModel->save();
                    $returnData = [
                      'id' =>$push_id,
                      'push_res'=> $push_res,
                      'fill_back'=> $fill_back,
                    ];
                    $this->jsonReturn(0,$returnData,'提交成功');
                } else {
                    $this->jsonReturn(-1, '提交失败');
                }
            }
        } else {
            $this->assign('app_id_list', config('others.app_id_list'));
            return $this->fetch('add',['uid'=>$uid]);
        }
    }

    /**
     * 详情页
     * @param  integer $id 行id
     */
    public function detail($id=0)
    {
      if(!$id){
        $this->error('Lost id');
      }
      $map =[
        ["id","=",$id]
      ];
      $field = "t.*, u.loginname, u.name,u.nativename ";
      $join = [
       ['carpool.user u','t.uid = u.uid', 'left']
      ];
      $order = 'create_time DESC ';

      $data = PushMsgModel::alias('t')->join($join)->where($map)->order($order)->field($field)->find();
      $data['extra_data'] = json_decode($data['extra_data'],true);
      $this->assign('app_id_list', config('others.app_id_list'));
      return $this->fetch('detail', ['data' => $data]);
    }
}
