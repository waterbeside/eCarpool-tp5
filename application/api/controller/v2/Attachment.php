<?php
namespace app\api\controller\v2;

use app\api\controller\ApiBase;
use app\common\model\Attachment as AttachmentModel;
use app\carpool\model\User as UserModel;
use think\facade\Log;
use think\facade\Env;
use think\Db;
use com\Nim as NimServer;

/**
 * 附件相关
 * Class Attachment
 * @package app\api\controller
 */
class Attachment extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    public function index($type=false){
      $this->jsonReturn(0);
    }

    /**
     * 上传图片
     */
    public function save($type=false){
        $systemConfig = $this->systemConfig;

        $this->checkPassport(1);

        $is_image = 0;
        $returnData = [];
        $extra = [];
        $uid = $this->userBaseInfo['uid'];

        $module = strtolower(input('param.module','content'));
        $title = input('param.title');
        $dev = input('param.dev');
        $file = $this->request->file('file');


        if($dev){
          dump(input());
          dump(isset($_FILES["file"])?$_FILES["file"]:"" );
          dump(isset($_REQUEST["file"])?$_REQUEST["file"]:"" );
        }
        if(!$file){
          $this->jsonReturn(-1,lang('Please upload attachments'));
        }
        //计算md5和sha1散列值，TODO::作用避免文件重复上传
        $md5 = $file->hash('md5');
        $sha1= $file->hash('sha1');
        $upInfo = $file->getInfo();

        $site_domain  = trim($systemConfig['site_upload_domain']) ? trim($systemConfig['site_upload_domain']) : $this->request->root(true) ;
        $upload_path = trim($systemConfig['site_upload_path']).DIRECTORY_SEPARATOR."$type" ;
        switch ($type) {
          case 'image':
            if(strpos($upInfo['type'],'image')===false){
              $this->jsonReturn(-1,lang('Not image file format'));
            }

            // if($upInfo['size'] > 819200){
            //   $this->jsonReturn(-1,lang('Images cannot be larger than 800K'));
            // }
            if($upInfo['size'] > 2048000){
              $this->jsonReturn(-1,lang('Images cannot be larger than {:size}',["size"=>"2M"]));
            }
            $image = \think\Image::open(request()->file('file'));
            $extra = [
              "width" => $image->width(),
              "height" => $image->height(),
              "type" => $image->type(),
              "mime" => $image->mime(),
            ] ;


            $upload_path  = trim($systemConfig['site_upload_path']).DIRECTORY_SEPARATOR."images" ;
            $is_image = 1;
            break;

          default:
            $this->jsonReturn(992,lang('Wrong format'));

            break;
        }



        ////////////检查是否有重复上传的图片 ///////////////

        $checkMap = [
          ['md5_code','=',$md5],
          ['sha1_code','=',$sha1],
          ['is_admin','=',0],
        ];
        if($module == "user/avatar"){ //如果是头像上传
          $checkMap[] = ['module','=','user/avatar'];
          $site_domain        = trim($systemConfig['site_host']) ? trim($systemConfig['site_host']) : $this->request->server('SERVER_NAME');
          $site_domain        = "http://".$site_domain;
        }else{
          $checkMap[] = ['module','<>','user/avatar'];
        }
        $fileInfo = AttachmentModel::where($checkMap)->find();//查找入库的文件信息，防止重复上传


        if(!empty($fileInfo)){  //如果有重复图片
          $returnData = [
            'id'=>$fileInfo['id'],
            'path'=>$site_domain.$fileInfo['filepath'],
            'filepath'=>$fileInfo['filepath'],
            'hash'=>[$md5,$sha1],
            'filesize'=>$fileInfo['filesize'],
            'upload'=>0,
            'last_time' => time(),
            'extra_info' => $extra,
          ];
          if($module == "user/avatar"){ //如果是头像上传
            $imgpath =  str_replace(config('secret.avatarUploadPath').DIRECTORY_SEPARATOR,'',$fileInfo['filepath']);
            $upAvatarRes = $this->upDataAvatar($imgpath); //更新用户头像字段
            if(!$upAvatarRes){
              return $this->jsonReturn(-1,lang('Failed'));
            }
          }
          AttachmentModel::where(['md5_code'=>$md5,'sha1_code'=>$sha1])->update([
            'status'	    => 1,
            'times'	      => Db::raw('times+1'),
            'last_userid'	=> $uid,
            'last_time'   => $returnData['last_time'],
          ]);
          return $this->jsonReturn(0,$returnData,lang('upload successful'));
        }


        //////// 进行真正的上传图片操作 ///////////
        $now = date('Ymd');
        $fullUploadPath = Env::get('root_path') . 'public' . $upload_path;
        if($module == "user/avatar"){
          $fullUploadPath = config('secret.avatarRootServerPath')  ? config('secret.avatarRootServerPath').config('secret.avatarUploadPath') : $fullUploadPath;
          $info = $file->move($fullUploadPath);
          $imgpath =  $now.DIRECTORY_SEPARATOR.$info->getFilename();
          $path = config('secret.avatarUploadPath').DIRECTORY_SEPARATOR.$imgpath;
          $upAvatarRes = $this->upDataAvatar($imgpath); //更新用户头像字段
          if(!$upAvatarRes){
            return $this->jsonReturn(-1,lang('Failed'));
          }
          $title = $this->userBaseInfo['loginname'];
        }else{
          $info = $file->move($fullUploadPath);
          $path = $upload_path.DIRECTORY_SEPARATOR.$now.DIRECTORY_SEPARATOR.$info->getFilename();
        }
        $data = [
            'module'=> $module,
            'filesize'=> $upInfo['size'],
            'filepath'=> $path,
            'filename'=> $info->getFilename(),
            'filetype'=> $upInfo['type'],
            'fileext'=> mb_strtolower($info->getExtension()),
            'is_image' => $is_image,
            'is_admin' => 0 ,
            'create_time' => time(),
            'userid'=> $uid,
            'status' => 1 ,
            'md5_code' => $md5 ,
            'sha1_code' => $sha1 ,
            'title'=> empty($title) ? $upInfo['name'] : $title,
            'ip' =>$this->request->ip(),
            'times' => 1,
            'last_time' => time(),
            'extra_info' => json_encode($extra),
        ];
        if($img_id=AttachmentModel::insertGetId($data)){
            $returnData = [
              'id'=>$img_id,
              'path'=>$site_domain.$path,
              'filepath'=>$path,
              'hash'=>[$md5,$sha1],
              'filesize'=>$upInfo['size'],
              'upload'=>1,
              'extra_info' => $extra,
            ];
            $this->jsonReturn(0,$returnData,lang('upload successful'));
        }else{
            $this->jsonReturn(-1,lang('Attachment information failed to be written'));
        }

    }



    /**
     * 更新用户头像
     */
    function upDataAvatar($imgpath){
      $uid = $this->userBaseInfo['uid'];
      $upAvatarRes = UserModel::where([['uid','=',$uid]])->update(['imgpath'=>$imgpath]);
      if($upAvatarRes !==false){ //更新成功后，同步更新云信头像
        $appKey     = config('secret.nim.appKey');
        $appSecret  = config('secret.nim.appSecret');
        $NIM = new NimServer($appKey,$appSecret);
        $avatarBasePath     = config('secret.avatarBasePath');
        $upNimData = [
          'accid' => $this->userBaseInfo['loginname'],
          'icon'  => $avatarBasePath.$imgpath,
        ];
        $upNimRes = $NIM->updateUinfoByData($upNimData);
        return true;
      }else{
        return false;
      }

    }

    /**
     * 取得文件[详情]
     * @param  [type]  $id   id
     * @param  integer $mode 模式 0|json:返回json; 1|file:直接返回文件路径；
     */
    public function read($id,$mode = 0){
      if(!$id ){
        $this->jsonReturn(992,'empty id');
      }
      $fileInfo = AttachmentModel::where('id',$id)->find();
      if(!$fileInfo ){
        $this->jsonReturn(20002,lang('File not found'));
      }
      $systemConfig = $this->systemConfig;

      $site_domain  = trim($systemConfig['site_upload_domain']) ? trim($systemConfig['site_upload_domain']) : $this->request->root(true) ;
      $returnData = [
        'id'=>$fileInfo['id'],
        'path'=>$site_domain.$fileInfo['filepath'],
        'filepath'=>$fileInfo['filepath'],
        'hash'=>[$fileInfo['md5_code'],$fileInfo['sha1_code']],
        'filesize'=>$fileInfo['filesize'],
        'create_time'=>$fileInfo['create_time'],
        'last_time' => $fileInfo['last_time'],
      ];

      if( intval($mode) === 0 || $mode=='json'){
        $this->jsonReturn(0,$returnData);
      }
      if($mode ==1 || $mode=='file'){
        return redirect($fileInfo['filepath']);

        // return download(str_replace('//','/', Env::get('root_path') . $fileInfo['filepath']));
      }
    }


    /**
     * 删除文件
     * @param  [type]  $id   id or id|id|id...
     * @param  integer $mode 模式 0:返回对应错误码，1：永远返0； 当批量时，mode设置不起作用
     */
      public function delete($id=0,$mode = 0){

      $this->checkPassport(1);
      $uid = $this->userBaseInfo['uid'];

      if(strpos($id,',')>0){
        $ids = explode(',',$id);

        $tempData = [];
        foreach ($ids as $iid) {
          $fileInfo = AttachmentModel::where('id',$iid)->find();
          $tempData[$iid] = [];
          if(!$fileInfo){
            $tempData[$iid]['code'] = 20002;
            $tempData[$iid]['desc'] = lang('File not found');
            continue;
          }
          if($fileInfo['userid']!=$uid || $fileInfo['times'] > 1 ){
            $tempData[$iid]['code'] = 30002;
            $tempData[$iid]['desc'] = lang('This attachment cannot be deleted');
            continue;
          }
          AttachmentModel::where('id',$iid)->delete();
          unlink( Env::get('root_path') .'public' . $fileInfo['filepath']);
          $tempData[$iid]['code'] = 0;
          $tempData[$iid]['desc'] = 'success';
        }
        $this->jsonReturn(0,$tempData,"success");

      }else{
        if(!$id ){
          $this->jsonReturn($mode ? 0 : 992,'empty id');
        }
        $fileInfo = AttachmentModel::where('id',$id)->find();
        if(!$fileInfo){
          $this->jsonReturn($mode ? 0 : 20002, lang('File not found'));
        }
        if($fileInfo['userid']!=$uid || $fileInfo['times'] > 1  ){
          $this->jsonReturn($mode ? 0 : 30002, lang('This attachment cannot be deleted'));
        }
        AttachmentModel::where('id',$id)->delete();
        try{
            unlink( Env::get('root_path') .'public' . $fileInfo['filepath']);
        } catch (\Exception $e) {
            // return false;
        }
      }
      $this->jsonReturn(0,"success");
    }


}