<?php
namespace app\common\model;

use think\Db;
use think\Model;
use think\facade\Cache;

class I18nLang extends Model
{

  public function getPublicList($recache = 0){
    $lists_cache = Cache::tag('public')->get('langs');
    if($lists_cache && !$recache){
      $lists = $lists_cache;
    }else{
      $lists = $this->where('status',1)->order('sort desc , code ASC, id ASC ')->select();
      if($lists){
        Cache::tag('public')->set('langs',$lists,3600);
      }
    }
    $returnLists = [];
    foreach($lists as $key => $value) {
        $returnLists[] = [
          'id'=>$value['id'],
          'code'=>$value['code'],
          'name'=>$value['name'],
          'is_default'=>$value['is_default'],
        ];
    }
    return $returnLists;
    // $this->jsonReturn(0,['lists'=>$returnLists],'success');
  }

  public function formatLangCode($lang){
    $lang_list = $this->getPublicList();
    $langs = [];
    foreach ($lang_list as $key => $value) {
      array_push($langs,$value['code']);
    }
    if(in_array($lang,$langs)){
      return $lang;
    }
    if(strpos($lang,'-') >0 ){
      $langArr = explode('-',$lang);
      return $langArr[0]== "zh" ? "zh-cn" : "";
    }
  }

}
