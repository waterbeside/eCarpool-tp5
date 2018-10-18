<?php
namespace app\api\controller\v1;

use think\facade\Env;
use app\api\controller\ApiBase;
use app\common\model\Attachment as AttachmentModel;

use think\Db;

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
        $this->checkPassport(1);

        $is_image = 0;
        $returnData = [];
        $extra = [];
        $uid = $this->userBaseInfo['uid'];
        $module = input('param.module','content');
        $title = input('param.title');
        $dev = input('param.dev');

        $file = $this->request->file('file');

        if($dev){
          dump(input());
          dump(isset($_FILES["file"])?$_FILES["file"]:"" );
          dump(isset($_REQUEST["file"])?$_REQUEST["file"]:"" );
        }
        if(!$file){
          $this->jsonReturn(-1,'请上传附件');
        }
        //计算md5和sha1散列值，TODO::作用避免文件重复上传
        $md5 = $file->hash('md5');
        $sha1= $file->hash('sha1');
        $upInfo = $file->getInfo();

        $systemConfig = $this->systemConfig;
        $site_domain  = trim($systemConfig['site_upload_domain']) ? trim($systemConfig['site_upload_domain']) : $this->request->root(true) ;
        $upload_path = trim($systemConfig['site_upload_path'])."/$type" ;
        switch ($type) {
          case 'image':
            if(strpos($upInfo['type'],'image')===false){
              $this->jsonReturn(-1,'请上传图片格式');
            }

            if($upInfo['size'] > 819200){
              $this->jsonReturn(-1,'图片不能大于800K');
            }
            $image = \think\Image::open(request()->file('file'));
            $extra = [
              "width" => $image->width(),
              "height" => $image->height(),
              "type" => $image->type(),
              "mime" => $image->mime(),
            ] ;


            $upload_path  = trim($systemConfig['site_upload_path'])."/images" ;
            $is_image = 1;
            break;

          default:
            $this->jsonReturn(992,'未开放其它格式的文件上传');

            break;
        }
        $fileInfo = AttachmentModel::where([
          ['md5_code','=',$md5],
          ['sha1_code','=',$sha1],
          ['is_admin','=',0],
        ])->find();//我这里是将图片存入数据库，防止重复上传



        if(!empty($fileInfo)){
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


          AttachmentModel::where(['md5_code'=>$md5,'sha1_code'=>$sha1])->update([
            'status'	    => 1,
            'times'	      => Db::raw('times+1'),
            'last_userid'	=> $uid,
            'last_time'   => $returnData['last_time'],
          ]);
          return $this->jsonReturn(0,$returnData,'上传成功');
        }

        $request = request();
        $DS = DIRECTORY_SEPARATOR;
        $imgPath = 'public' . $upload_path ;
        $info = $file->move(Env::get('root_path') . $imgPath);
        $path = $upload_path.$DS.date('Ymd',time()).$DS.$info->getFilename();
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
            'ip' =>$request->ip(),
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
            $this->jsonReturn(0,$returnData,'上传成功');
        }else{
            $this->jsonReturn(-1,'附件入库失败');
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
        $this->jsonReturn(20002,'找不到文件');
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
            $tempData[$iid]['desc'] = '找不到文件';
            continue;
          }
          if($fileInfo['userid']!=$uid && $fileInfo['times'] > 1 ){
            $tempData[$iid]['code'] = 30002;
            $tempData[$iid]['desc'] = '该附件不可以删除';
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
          $this->jsonReturn($mode ? 0 : 20002,'找不到文件');
        }
        if($fileInfo['userid']!=$uid || $fileInfo['times'] > 1  ){
          $this->jsonReturn($mode ? 0 : 30002,'该附件不可以删除');
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
