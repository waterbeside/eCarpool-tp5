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
            $site_domain  = trim($systemConfig['site_upload_domain']) ? trim($systemConfig['site_upload_domain']) : $this->request->root(true);
            $upload_path  = trim($systemConfig['site_upload_path']) . "/images";
            //判断图片文件是否已经上传
            $checkHasMap = [
                ['md5_code', '=', $md5],
                ['sha1_code', '=', $sha1],
                ['module', '<>', 'user/avatar'],
            ];
            $img = Attachment::where($checkHasMap)->find(); //我这里是将图片存入数据库，防止重复上传



            if (!empty($img)) {
                Attachment::where($checkHasMap)->update([
                    'status'        => 1,
                    'times'          => Db::raw('times+1'),
                    'last_userid'    => $admin_id,
                    'last_time'   => time(),
                ]);
                $this->jsonReturn(0, ['img_id' => $img['id'], 'img_url' => $site_domain . $img['filepath']], lang('upload successful'));
            } else {
                $module = input('param.module', 'admin');
                $request = request();
                $DS = DIRECTORY_SEPARATOR;
                $imgPath = 'public' . $upload_path;
                $info = $images->move(Env::get('root_path') . $imgPath);
                $path = $upload_path . $DS . date('Ymd', time()) . $DS . $info->getFilename();
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
                    $this->jsonReturn(0, ['img_id' => $img_id, 'img_url' => $site_domain . $path], lang('upload successful'));
                } else {
                    $this->jsonReturn(-1, lang('Attachment information failed to be written'));
                }
            }
        } else {
            $this->jsonReturn(-1, '非法请求');
        }
    }
}
