<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\common\model\Attachment as AttachmentModel;

use think\Db;

/**
 * 附件相关
 * Class Link
 * @package app\admin\controller
 */
class Attachment extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        $this->checkPassport(1);
    }


    public function save($type=false){
        $is_image = 0;
        $file = $this->request->file('file');
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
            $upload_path  = trim($systemConfig['site_upload_path'])."/images" ;
            $is_image = 1;
            break;

          default:
            $this->jsonReturn(-1,'未开放其它格式的文件上传');

            break;
        }
        $fileInfo = AttachmentModel::where(['md5_code'=>$md5,'sha1_code'=>$sha1])->find();//我这里是将图片存入数据库，防止重复上传

        if(!empty($fileInfo)){
            return $this->jsonReturn(0,['id'=>$fileInfo['id'],'img_url'=>$site_domain.$fileInfo['filepath']],'上传成功');
        }

        $uid = $this->userBaseInfo['uid'];
        $module = input('param.module','content');
        $request = request();
        $DS = DIRECTORY_SEPARATOR;
        $imgPath = 'public' . $upload_path ;
        $info = $images->move(Env::get('root_path') . $imgPath);
        $path = $upload_path.$DS.date('Ymd',time()).$DS.$info->getFilename();
        $data = [
            'module'=> $module,
            'filesize'=> $upInfo['size'],
            'filepath'=> $path,
            'filename'=> $info->getFilename(),
            'fileext'=> $info->getExtension(),
            'is_image' => $is_image ,
            'is_admin' => 0 ,
            'create_time' => time(),
            'userid'=> $uid,
            'status' => 1 ,
            'md5_code' => $md5 ,
            'sha1_code' => $sha1 ,
            'title'=> $upInfo['name'],
            'ip' =>$request->ip(),
        ];
        if($img_id=AttachmentModel::insertGetId($data)){
            $this->jsonReturn(0,['id'=>$img_id,'img_url'=>$site_domain.$path],'上传成功');
        }else{
            $this->jsonReturn(-1,'附件入库失败');
        }

    }



    public function delete($id){

    }

}
