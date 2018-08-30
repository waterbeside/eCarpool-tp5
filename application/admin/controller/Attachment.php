<?php
namespace app\admin\controller;

use app\common\model\Attachment as AttachmentModel;
use app\carpool\model\User as UserModel;
use app\common\model\AdminUser as AdminUserModel;
use app\admin\controller\AdminBase;
use think\facade\Env;
use think\facade\Validate;
use think\facade\Config;
use think\Db;

/**
 * 附件管理
 * Class Attachment
 * @package app\admin\controller
 */
class Attachment extends AdminBase
{

   protected  function getExtList(){
     $res = AttachmentModel::field('fileext')->group('fileext')->cache(true)->select();
     foreach ($res as $key => $value) {
       $res[$key]['fileext'] = mb_strtolower($value['fileext']);
     }
     return $res;
   }

    /**
     * 附件列表
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter=[],$page = 1,$pagesize = 50)
    {
        $map = [];
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['title','like', "%".$filter['keyword']."%"];
        }
        if (isset($filter['ip']) && $filter['ip']) {
            $map[] = ['ip','like', "%".$filter['ip']."%"];
        }

        //筛选时间
        if (isset($filter['time']) && $filter['time'] ){
          $time_arr = explode(' ~ ',$filter['time']);
          $time_s = date('Y-m-d H:i:s',strtotime($time_arr[0]));
          $time_e = date('Y-m-d H:i:s',strtotime($time_arr[1]) + 24*60*60);
          $map[] = ['last_time', 'between time', [$time_s, $time_e]];
        }



        if (isset($filter['fileext']) && $filter['fileext'] ){
          $map[] = ['fileext','=', $filter['fileext']];
        }


        $fields = 'a.* ';
        $lists = AttachmentModel::alias('a')->where($map)->order('last_time DESC , id DESC ')->field($fields)->paginate($pagesize, false,  ['query'=>request()->param()]);
        foreach ($lists as $key => $value) {
          $value['fileext'] = mb_strtolower($value['fileext']);
        }
        $extList = $this->getExtList();
        // dump($extList);exit;
        return $this->fetch('index', ['lists' => $lists, 'filter'=>$filter,'pagesize'=>$pagesize,'extlist'=>$extList]);
    }


    /**详情**/
    public function read($id){
      if(!$id){
        $this->error('Lost id');
      }
      $data = AttachmentModel::alias('a')->where('id',$id)->find();
      if(!$data){
        $this->error('没有此数据');
      }


      $userData       = $data['is_admin'] ? AdminUserModel::where('id',$data['userid'])->find() : UserModel::where('uid',$data['userid'])->find();
      $last_userData  = $data['last_userid'] ? ($data['is_admin'] ? AdminUserModel::where('id',$data['last_userid'])->find() : UserModel::where('uid',$data['last_userid'])->find() ) : [];

      $data['uploader'] = [
        "name" =>     $data['is_admin'] ?  $userData['nickname']  : $userData['name'] ,
        "username" => $data['is_admin'] ?  $userData['username']  : $userData['loginname'] ,
      ];

      $data['last_uploader'] = empty($last_userData) ? [] : [
        "name" =>     $data['is_admin'] ?  $last_userData['nickname']  : $last_userData['name'] ,
        "username" => $data['is_admin'] ?  $last_userData['username']  : $last_userData['loginname'] ,
      ];

      return $this->fetch('read', ['data' => $data]);

    }


    /**
     * 删除文件
     * @param  [type]  $id   id or id|id|id...
     * @param  integer $mode 模式 0:返回对应错误码，1：永远返0； 当批量时，mode设置不起作用
     */
      public function delete($id=0){

        if(!$id ){
          $this->jsonReturn(992,'empty id');
        }
        $fileInfo = AttachmentModel::where('id',$id)->find();
        if(!$fileInfo){
          $this->jsonReturn(20002,'找不到文件');
        }

        AttachmentModel::where('id',$id)->delete();
        unlink( Env::get('root_path') .'public' . $fileInfo['filepath']);
        $this->jsonReturn(0 ,'删除成功');


    }

}
