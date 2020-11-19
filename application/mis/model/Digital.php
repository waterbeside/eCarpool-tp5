<?php
namespace app\mis\model;

use app\common\model\Configs;
use think\Db;
use think\Model;

class Digital extends Model
{
    protected $connection = 'database_mis';
    protected $table = 't_digital';
    protected $pk = 'id';

    public function getFullThumbPath($thumbPath)
    {
        if (strpos($thumbPath, 'http') === 0) {
            return $thumbPath;
        }
        $ConfigsModel = new Configs();
        $configs = $ConfigsModel->getConfigs();
        $urlPath = $configs['public_upload_url'] ?? '';
        if (strpos($thumbPath, '/') === false) {
            return $urlPath . 'images/gek_tech/digital/' . $thumbPath;
        } else {
            return $urlPath .  $thumbPath;
        }
    }
}
