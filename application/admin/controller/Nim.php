<?php
namespace app\admin\controller;

use app\carpool\model\User as UserModel;

use app\admin\controller\AdminBase;
use think\facade\Config;
use think\facade\Validate;
use think\Db;
use com\Nim as NimServer;

/**
 * 云信管理
 * Class AdminUser
 * @package app\admin\controller
 */
class Nim extends AdminBase
{
    protected $NIM;

    protected function initialize()
    {
        parent::initialize();
        $appKey     = config('secret.nim.appKey');
        $appSecret  = config('secret.nim.appSecret');
        $this->NIM = new NimServer($appKey,$appSecret);
    }

    /**
     * @return mixed
     */
    public function index()
    {

    }


    /**
     * 生成im帐号
     */
    public function create_imid($uid,$isUpdate=1){
      $user = UserModel::get($uid);
      $imid       = $user->im_id ? $user->im_id : $user->loginname  ;
      $icon       = $user->imgpath ? config('secret.avatarBasePath').$user->imgpath : config('secret.avatarBasePath')."im/default.png";
      $rs         = $this->NIM->createUserId($imid,$user->name,'',$icon);

      if($isUpdate && $rs['code']==200){
        $user->im_md5password     = $rs['info']['token'];
        $user->im_id              = $imid;
        $res = $user->save();
        if($res){
          $this->log('创建云信帐号成功，id='.$uid.',im_id='.$imid,0);
        }else{
          $this->log('创建云信帐号成功，但回写token失败，id='.$uid.',im_id='.$imid,0);
        }
      }
      if($rs['code']!=200){
        $this->log('创建云信帐号失败，id='.$uid.',im_id='.$imid,0);
      }
      return $rs;

    }

    /**
     * 刷新im帐号
     */
    public function update_token($uid,$isUpdate=1,$isAction=0){
      $user = UserModel::get($uid);
      $imid       = $user->im_id ? $user->im_id : $user->loginname  ;
      $rs         = $this->NIM->updateUserToken($imid);
      $upDateSuccess = 0;
      if($isUpdate && $rs['code']==200){
        $user->im_md5password     = $rs['info']['token'];
        $user->im_id              = $rs['info']['accid'];
        $res = $user->save();
        if($res){
          $this->log('更新云信帐号成功，id='.$uid.',im_id='.$imid,0);
          $upDateSuccess = 1;
        }else{
          $this->log('更新云信帐号成功，但回写token失败，id='.$uid.',im_id='.$imid,0);
        }
      }
      if($rs['code']!=200){
        $this->log('更新云信帐号失败，id='.$uid.',im_id='.$imid,0);
      }
      if(!$isAction){
        return $rs;
      }else{
        if($upDateSuccess || !$isUpdate){
          return $this->jsonReturn(0,$rs,'更新token成功');
        }else{
          return $this->jsonReturn(-1,'更新失败');
        }
      }

    }

    //im帐号管理
    public function im_user($uid){
      $user = UserModel::get($uid);
      // $userData = UserModel::find($uid);
      if(!$user){
        return false;
      }

      // var_dump($user->im_id);exit;
      $isCreateSuccess = 0;
      if($user->im_id && $user->im_md5password){
        $res = $this->NIM->getUinfos([$user->im_id]);
        if($res['code']==200){
          $nimData = $res['uinfos'][0];
          // dump($nimData);exit;
        }else{
          $res = $this->create_imid($uid);
          if($res['code']==200){
            $user->im_md5password     = $res['info']['token'];
            // $user->save();
            $isCreateSuccess = 1;
          }
        }

      }else{
        $res = $this->create_imid($uid);
        if($res['code']==200){
          $user->im_md5password     = $res['info']['token'];
          $isCreateSuccess = 1;
          // $user->save();
        }else if($res['code']==414 && $res['desc']=="already register"){
            $res = $this->update_token($uid);
            if($res['code']==200){
              $user->im_md5password     = $res['info']['token'];
              $isCreateSuccess = 1;
            }else{
              echo("帐号出错，请重新打开。");exit;
            }
        }else{
          echo("帐号出错，请重新打开。");exit;
        }
      }


      if(!isset($nimData) && $isCreateSuccess){
        $nimData['icon']       = $user->imgpath ? config('secret.avatarBasePath').$user->imgpath : config('secret.avatarBasePath')."im/default.png";
        $nimData['accid']      = $user->loginname;
        $nimData['name']       = $user->name;

      }

      return $this->fetch('im_user', ['nimData' => $nimData,'userData'=>$user]);



    }





    /**
     * 创建云信帐号
     * @return mixed
     */
    public function add()
    {

    }



    /**
     * 编辑
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {

    }







}
