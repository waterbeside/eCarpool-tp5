<?php

namespace app\api\controller\v1\inner;

use think\Db;
use app\api\controller\ApiBase;
use app\common\model\Attachment as AttachmentModel;
use app\carpool\model\User as UserModel;
use think\facade\Log;
use think\facade\Env;
use com\nim\Nim as NimServer;

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

    public function index($type = false)
    {
        $this->jsonReturn(0, '');
    }

    /**
     * 上传图片
     */
    public function save($module = false, $type = 'image')
    {
        $systemConfig = $this->systemConfig;
        $token = input('post.token/s', '');
        $ip = request()->ip();

        $innerToken = config('secret.inner.token');
        $uploadPath = config('inner');

        $allowModules = ['sedo']; // 允许的模块
        if (empty($module) || !is_string($module) || !in_array($module, $allowModules)) {
            $this->jsonReturn(992, 'Error model');
        }
        if ($token != $innerToken) {
            $this->jsonReturn(993, 'Error Token');
        }

        $module = strtolower($module);
        $name = input('post.name/s', '', 'trim');
        $dev = input('param.dev');
        $file = $this->request->file('file');

        if ($dev) {
            dump(input());
            dump($file);
            dump(isset($_FILES["file"]) ? $_FILES["file"] : "");
            dump(isset($_REQUEST["file"]) ? $_REQUEST["file"] : "");
        }
        if (!$file) {
            $this->jsonReturn(-1, lang('The file is too large to upload'));
            // $this->jsonReturn(-1, lang('Please upload attachments'));
        }
        //计算md5和sha1散列值，作用避免文件重复上传
        $md5 = $file->hash('md5');
        $sha1 = $file->hash('sha1');
        $upInfo = $file->getInfo();
        $fileName = $upInfo['name'];
        if (in_array($module, ['sedo'])) {
            $fileExt = $this->getFilenameExt($upInfo['name']);
            $fileName = !empty($name) ? $name.'.'.$fileExt : $fileName;
        }
        if ($dev) {
            dump($fileExt);
            dump($upInfo);
            dump($upInfo['type']);
        }
        
        $htmlServerRootPath = config('inner.htmlServerRootPath');
        $uploadPath = config('inner.uploadPath');
        
        switch ($type) {
            case 'image':
                if ($upInfo['size'] > 819200) {
                    $this->jsonReturn(-1, lang('Images cannot be larger than {:size}', ["size" => "800K"]));
                }
                if (strpos($upInfo['type'], 'image') === false) {
                    $this->jsonReturn(-1, ['type'=>$upInfo['type']], lang('Not image file format'));
                }
                
                $image = \think\Image::open(request()->file('file'));
                $extra = [
                    "width" => $image->width(),
                    "height" => $image->height(),
                    "type" => $image->type(),
                    "mime" => $image->mime(),
                ];

                $uploadPath  = $uploadPath . DIRECTORY_SEPARATOR . "images/$module";
                $is_image = 1;
                break;
            case 'file':
                if ($upInfo['size'] >= 1887436) { // 2m = 2097152
                    $this->jsonReturn(-1, lang('Images cannot be larger than {:size}', ["size" => "1.8M"]));
                }
                if (!$this->checkAllowFileType($upInfo) || !$this->checkAllowFileExt($upInfo)) {
                    return $this->jsonReturn(992, $upInfo, lang('Wrong format'));
                }
                $uploadPath  = $uploadPath . DIRECTORY_SEPARATOR . "files/$module";
                $extra = [];
                break;
            default:
                $this->jsonReturn(992, lang('Wrong format'));
                break;
        }

        //////// 进行真正的上传操作 ///////////
        $fullUploadPath = $htmlServerRootPath . $uploadPath;
        // dump($fullUploadPath);
        // exit;

        $site_domain        = trim($systemConfig['site_host']) ? trim($systemConfig['site_host']) : $this->request->server('SERVER_NAME');
        $site_domain        = "http://" . $site_domain;

        $info = $file->move($fullUploadPath, $fileName);
        $path = $uploadPath . DIRECTORY_SEPARATOR . $info->getFilename();

        $returnData = [
            'fullpath' => $site_domain . $path ,
            'filepath' => $path,
            'hash' => [$md5, $sha1],
            'filesize' => $upInfo['size'],
            // 'extra_info' => $extra,
        ];
        $this->jsonReturn(0, $returnData, lang('upload successful'));
    }


    /**
     * 检查文件后缀类型是否可上传
     *
     * @param array $flieInfo 文件信息
     * @return boolean
     */
    public function checkAllowFileExt($flieInfo)
    {
        $fileName = $flieInfo['name'];
        $allowExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip'];
        $fileExt = $this->getFilenameExt($fileName);
        if (!in_array($fileExt, $allowExt)) {
            return false;
        }
        return true;
    }

    /**
     * 检查文件类型是否可上传
     *
     * @param array $flieInfo 文件信息
     * @return boolean
     */
    public function checkAllowFileType($flieInfo)
    {
        $type = $flieInfo['type'];
        $allowTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'text/plain',
        ];
        if (!in_array($type, $allowTypes)) {
            return false;
        }
        return true;
    }

    /**
     * 通过文件名取得文件后缀
     *
     * @param string $filename 文件名
     * @return string
     */
    public function getFilenameExt($filename)
    {
        $filenameExplode = explode('.', $filename);
        $ext = '';
        if (count($filenameExplode) > 1) {
            $ext = end($filenameExplode);
        }
        return $ext;
    }
}
