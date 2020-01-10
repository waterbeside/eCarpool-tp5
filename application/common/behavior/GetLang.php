<?php

namespace app\common\behavior;

use think\Request;
use think\Db;

class GetLang
{
    public $language_l = null;

    public function run(Request $request, $controller = null, $setting = [])
    {
        if (!$controller->language_l) {
            $lang_s = input('request._language') ?: (input('post._language') ?: input('get._language'));
            $lang_s = $lang_s ?: (input('request.lang') ?: (input('post.lang') ?: input('get.lang')));
            $lang_s = $lang_s ?: request()->header('Accept-Language');

            $language = $this->formatLangCode($lang_s);
            $controller->language_l = $this->language_l;
            $controller->language =  $language;
            return $language;
        } else {
            return $controller->language;
        }
    }



    /**
     * 格式化最后要得到的语言码
     * @param  string $language
     * @return string
     */
    public function formatLangCode($language)
    {
        $lang_l = $this->formatAcceptLang($language);
        $this->language_l = $lang_l;
        $language = $lang_l[0] == 'zh' ? (isset($lang_l[1]) ? $this->formatZhLang($lang_l[1], 'zh-cn') : 'zh-cn') : $this->formatZhLang($lang_l[0]);
        return $language;
    }

    /**
     * 格式化中文语言
     * @param  string $language
     * @return string
     */
    public function formatZhLang($language, $default = null)
    {
        if ($language == 'zh-hant-hk') {
            return 'zh-hk';
        }
        if ($language == 'zh-hant-tw') {
            return 'zh-tw';
        }
        if ($language == 'zh-hans') {
            return 'zh-cn';
        }
        if (strpos($language, 'zh-hant') !== false) {
            return 'zh-hk';
        }
        if (strpos($language, 'zh-hans') !== false) {
            return 'zh-cn';
        }
        return $default ? $default : $language;
    }

    /**
     * 格式化Accept-language得来的语言。
     * @param  string $language
     * @return array
     */
    public function formatAcceptLang($language)
    {
        $lang_l = explode(',', $language);
        $lang_format_list = [];
        $q_array = [];

        foreach ($lang_l as $key => $value) {
            $temp_arr = explode(';', $value);
            $q = isset($temp_arr[1]) ? $temp_arr[1] : 1;
            $q_array[]  = $q;
            $lang_format_list[$key] = ['lang' => $temp_arr[0], 'q' => $q];
        }

        array_multisort($q_array, SORT_DESC,  $lang_format_list);
        $lang = [];
        foreach ($lang_format_list as $key => $value) {
            $lang[] = strtolower(trim($value['lang']));
        }
        $baseLangArray = explode('-', $lang[0]);
        $baseLang  = $baseLangArray[0];
        $lang = array_merge([$baseLang], $lang);
        $lang = array_unique($lang);
        return $lang;
    }
}
