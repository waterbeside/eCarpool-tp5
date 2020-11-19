<?php

namespace app\admin\controller;

use think\facade\Env;
use app\common\model\Attachment;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * 上传功能
 * Class Uploader
 * @package app\admin\controller
 */
class Uploader extends AdminBase
{

    public function images()
    {
        if ($this->request->isPost()) {
            $admin_id = $this->userBaseInfo['uid'];
            //接收参数
            $images = $this->request->file('file');
            if (!$images) {
                $this->jsonReturn(-1, lang('Please upload attachments'));
            }
            //计算md5和sha1散列值，TODO::作用避免文件重复上传
            $md5 = $images->hash('md5');
            $sha1 = $images->hash('sha1');
            $upInfo = $images->getInfo();
            if (strpos($upInfo['type'], 'image') === false) {
                $this->jsonReturn(-1, lang('Not image file format'));
            }
            if ($upInfo['size'] > 819200) {
                $this->jsonReturn(-1, lang('Images cannot be larger than {:size}', ["size" => "800K"]));
            }
            $image = \think\Image::open(request()->file('file'));
            $extra = [
                "width" => $image->width(),
                "height" => $image->height(),
                "type" => $image->type(),
                "mime" => $image->mime(),
            ];

            $systemConfig = $this->systemConfig;
            $deptPath = "";


            $module = strtolower(input('param.module', 'admin')); // 取得图片所用模块
            $fixedUrlPath = '';
            if ($module == 'admin/mis/tech') {
                $site_domain  = trim($systemConfig['public_upload_url']) ?: $this->request->root(true);
                $upload_path  = trim($systemConfig['public_upload_server_path']) . "/images";
                $deptPath = "gek_tech";
                $fixedUrlPath = 'images';
            } else {
                $site_domain  = trim($systemConfig['site_upload_domain']) ?: $this->request->root(true);
                $upload_path  = trim($systemConfig['site_upload_path']) . "/images";
            }

            
            //判断图片文件是否已经上传
            $checkHasMap = [
                ['md5_code', '=', $md5],
                ['sha1_code', '=', $sha1],
                ['module', '<>', 'user/avatar'],
            ];
            if (in_array($module, ['admin/mis/tech'])) {
                $checkHasMap[] = ['module', '=', $module];
            }
            $img = Attachment::where($checkHasMap)->find(); //我这里是将图片存入数据库，防止重复上传



            if (!empty($img)) {
                Attachment::where($checkHasMap)->update([
                    'status'        => 1,
                    'times'          => Db::raw('times+1'),
                    'last_userid'    => $admin_id,
                    'last_time'   => time(),
                ]);
                $returnData = [
                    'img_id' => $img['id'],
                    'img_url' => $site_domain . $img['filepath'],
                    'filepath' => $img['filepath'],
                ];
                $this->jsonReturn(0, $returnData, lang('upload successful'));
            } else { // 直正上传
                $request = request();
                $now = date('Ymd');

                $DS = DIRECTORY_SEPARATOR;
                // $info = $images->move(Env::get('root_path') . $imgPath);
                // $path = $upload_path . $DS . date('Ymd', time()) . $DS . $info->getFilename();

                $fullUploadPath = Env::get('root_path') . 'public' . $upload_path;
                
                if ($module == "admin/mis/tech") {
                    $fullUploadPath = ($upload_path . $DS . $deptPath ) ?: $fullUploadPath;
                    $info = $images->move($fullUploadPath);
                    $imgpath =  $now . DIRECTORY_SEPARATOR . $info->getFilename();
                    $path = $fixedUrlPath . DIRECTORY_SEPARATOR . $deptPath . DIRECTORY_SEPARATOR . $imgpath;
                } else {
                    $info = $images->move($fullUploadPath);
                    $path = $upload_path . DIRECTORY_SEPARATOR . $now . DIRECTORY_SEPARATOR . $info->getFilename();
                }
                $data = [
                    'module' => $module,
                    'filesize' => $upInfo['size'],
                    'filepath' => $path,
                    'filename' => $info->getFilename(),
                    'filetype' => $upInfo['type'],
                    'fileext' => mb_strtolower($info->getExtension()),
                    'is_image' => 1,
                    'is_admin' => 1,
                    'create_time' => time(),
                    'userid' => $admin_id,
                    'status' => 1,
                    'md5_code' => $md5,
                    'sha1_code' => $sha1,
                    'title' => $upInfo['name'],
                    'ip' => $request->ip(),
                    'extra_info' => json_encode($extra),
                ];

                if ($img_id = Attachment::insertGetId($data)) {
                    $returnData = [
                        'img_id' => $img_id,
                        'img_url' => $site_domain . $path,
                        'filepath' => $path,
                    ];
                    $this->jsonReturn(0, $returnData, lang('upload successful'));
                } else {
                    $this->jsonReturn(-1, lang('Attachment information failed to be written'));
                }
            }
        } else {
            $this->jsonReturn(-1, '非法请求');
        }
    }
}
