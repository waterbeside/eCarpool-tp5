<?php

namespace app\admin\controller;

use app\common\model\I18n as I18nModel;
use app\common\model\I18nData as I18nDataModel;
use app\common\model\I18nLang as I18nLangModel;
use think\facade\Config;
use app\admin\controller\AdminBase;
use think\Db;
use think\facade\Cache;
use think\facade\Validate;

/**
 * 国际化文档首页
 * Class Index
 * @package app\admin\controller
 */
class I18n extends AdminBase
{

    protected $I18n_model;

    protected function initialize()
    {
        parent::initialize();
        $this->I18n_model = new I18nModel();
    }

    /**
     * 部门管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($keyword = '', $page = 1, $lang = 'zh-cn', $module = '')
    {
        $map = [];
        if ($keyword) {
            $map[] = ['t.name|t.title|d.content', 'like', "%{$keyword}%"];
        }
        if ($module) {
            $map[] = ['t.module', '=', "$module"];
        }
        $join = [
            ['i18n_data d', 't.id = d.iid AND d.lang = "' . $lang . '"', 'left'],
        ];

        $fields = 't.id,t.lang_list, t.name, t.title, t.key_ios, t.key_android ,t.status, d.lang, d.content ';
        $order = 'name ASC , title ';
        $lists = $this->I18n_model->alias('t')
            ->join($join)
            ->where($map)
            ->order($order)
            ->field($fields)
            ->paginate(50, false, ['query' => request()->param()]);

        $modules = $this->I18n_model->field('module')->group('module')->select();
        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword, 'lang' => $lang, 'module' => $module, 'modules' => $modules]);
    }

    /**
     * 新加字条
     * @param $id
     * @return mixed
     */
    public function add()
    {

        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $data['status']  = $this->request->post('status/d', 0);

            // 开始验证
            $rule = [
                'name'   => 'require|unique:i18n,name',
            ];
            $msg = [
                'name.require' => '请填写key',
                'name.unique' => '该key已经存在',
            ];

            $validate   = Validate::make($rule, $msg);
            $validate_result = $validate->check($data);
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate->getError());
            }


            $lists_sort = array(); //提取排序字段用于数组排序
            foreach ($data['langData'] as $key => $value) {
                $data['langData'][$key]['lang'] = $key;
                $lists_sort[] = $value['sort'];
            }
            //重排数组
            array_multisort($lists_sort, SORT_ASC, $data['langData']);
            $data['lang_list'] = "";
            foreach ($data['langData'] as $key => $value) {
                $data['lang_list'] = $data['lang_list'] == "" ? $value['lang'] : $data['lang_list'] . "," . $value['lang'];
            }
            //验证中文内容是否存在
            if (!$data['langData']['zh-cn']['content']) {
                $this->jsonReturn(-1, '请输入中文内容');
            }

            // 启动事务
            Db::startTrans();
            try {
                $res_add = $this->I18n_model->allowField(true)->save($data); //主表插入数据
                $iid = $this->I18n_model->id; //插入成功后取得id
                if ($iid) {
                    //查找已有DATA并删除
                    if (I18nDataModel::where(['iid' => $iid])->count() > 0) {
                        I18nDataModel::where(['iid' => $iid])->delete();
                    }
                    //整理新的DATA并写入
                    $contentDatas = [];
                    foreach ($data['langData'] as $key => $value) {
                        $value['iid'] = $iid;
                        $contentDatas[] = $value;
                    }
                    $I18nDataModel =   new I18nDataModel;
                    $I18nDataModel->saveAll($contentDatas);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $this->log('新加字条失败', -1);
                $this->jsonReturn(-1, '保存失败');
            }
            $this->log('新加字条成功，id=' . $iid, 0);
            $this->jsonReturn(0, '保存成功');
        } else {
            return $this->fetch();
        }
    }


    /**
     * 编辑字条
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $data['status']  = $this->request->post('status/d', 0);

            // 开始验证
            $rule = [
                'name'   => 'require|unique:i18n,name',
            ];
            $msg = [
                'name.require' => '请填写key',
                'name.unique' => '该key已经存在',
            ];

            $validate   = Validate::make($rule, $msg);
            $validate_result = $validate->check($data);
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate->getError());
            }


            $lists_sort = array(); //提取排序字段用于数组排序
            foreach ($data['langData'] as $key => $value) {
                $data['langData'][$key]['lang'] = $key;
                $lists_sort[] = $value['sort'];
            }
            //重排数组
            array_multisort($lists_sort, SORT_ASC, $data['langData']);
            $data['lang_list'] = "";
            foreach ($data['langData'] as $key => $value) {
                $data['lang_list'] = $data['lang_list'] == "" ? $value['lang'] : $data['lang_list'] . "," . $value['lang'];
            }
            //验证中文内容是否存在
            if (!$data['langData']['zh-cn']['content']) {
                $this->jsonReturn(-1, '请输入中文内容');
            }

            // 启动事务
            Db::startTrans();
            try {
                $res_add = $this->I18n_model->allowField(true)->save($data, ['id' => $id]); //主表插入数据
                $iid = $id; //插入成功后取得id
                if ($iid) {
                    //查找已有DATA并删除
                    if (I18nDataModel::where(['iid' => $iid])->count() > 0) {
                        I18nDataModel::where(['iid' => $iid])->delete();
                    }
                    //整理新的DATA并写入
                    $contentDatas = [];
                    foreach ($data['langData'] as $key => $value) {
                        $value['iid'] = $iid;
                        $contentDatas[] = $value;
                    }
                    $I18nDataModel =   new I18nDataModel;
                    $I18nDataModel->saveAll($contentDatas);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $this->log('更新字条失败，id=' . $id, -1);
                $this->jsonReturn(-1, '更新失败');
            }
            $this->log('更新字条成功，id=' . $id, 0);
            $this->jsonReturn(0, '更新成功');
        } else {
            $datas = $this->I18n_model->find($id);
            if ($datas) {
                $fields = 't.iid , t.content, t.lang as lang_code, l.name as lang_name ';
                $join = [
                    ['i18n_lang l', 'l.code = t.lang', 'left'],
                ];
                $datas['content'] = I18nDataModel::alias('t')->join($join)->field($fields)
                    ->where(['t.iid' => $datas['id']])
                    ->order('t.sort ASC')
                    ->select();
            }
            return $this->fetch('edit', ['data' => $datas]);
        }
    }


    /**
     * 删除字条
     * @param $id
     */
    public function delete($id)
    {
        // 启动事务
        Db::startTrans();
        try {
            $this->I18n_model->destroy($id);
            if (I18nDataModel::where(['iid' => $id])->count() > 0) {
                I18nDataModel::where(['iid' => $id])->delete();
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->log('新加字条成功，id=' . $id, -1);
            $this->jsonReturn(-1, '删除失败');
        }
        $this->log('删除字条成功，id=' . $id, 0);
        $this->jsonReturn(0, '删除成功');
    }


    /**
     * 语言代码列表
     */
    public function lang_list($keyword = '', $page = 1, $lang = 'zh-cn')
    {
        $map = [];
        if ($keyword) {
            $map[] = ['t.code|t.name', 'like', "%{$keyword}%"];
        }

        $fields = '*';
        $order = 'sort ASC , id ';
        $lists = I18nLangModel::alias('t')->where($map)->order($order)->field($fields)->paginate(30, false, ['query' => request()->param()]);
        return $this->fetch('lang_list', ['lists' => $lists, 'keyword' => $keyword]);
    }

    /**
     * 用于选择列表
     */
    public function public_langs()
    {
        $I18nLangModel = new I18nLangModel();
        $returnLists = $I18nLangModel->getPublicList();
        $this->jsonReturn(0, ['lists' => $returnLists], 'success');
    }

    /**
     * 添加语言
     * @return mixed
     */
    public function lang_add()
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $data['status']  = $this->request->post('status/d', 0);
            $data['is_default']  = $this->request->post('is_default/d', 0);

            // 开始验证
            $validate_result = $this->validate($data, 'I18nLang');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }

            $lang_model = new I18nLangModel();
            if ($lang_model->allowField(true)->save($data)) {
                Cache::tag('public')->rm('langs');
                $pk = $lang_model->id; //插入成功后取得id
                $this->log('新加语言成功，id=' . $pk, 0);
                return $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('新加语言失败', -1);
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->fetch('lang_add');
        }
    }

    /**
     * 编辑语言
     * @param $id
     * @return mixed
     */
    public function lang_edit($id)
    {
        $lang_model = new I18nLangModel();
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $data['status']  = $this->request->post('status/d', 0);
            $data['is_default']  = $this->request->post('is_default/d', 0);
            // 开始验证
            $validate_result = $this->validate($data, 'I18nLang');
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            }


            if ($lang_model->allowField(true)->save($data, ['id' => $id]) !== false) {
                Cache::tag('public')->rm('langs');
                $this->log('修改语言成功，id=' . $id, 0);
                return $this->jsonReturn(0, '修改成功');
            } else {
                $this->log('修改语言失败，id=' . $id, -1);
                return $this->jsonReturn(-1, '修改失败');
            }
        } else {
            $datas = $lang_model->find($id);
            return $this->fetch('lang_edit', ['data' => $datas]);
        }
    }

    /**
     * 删除语言
     * @param $id
     */
    public function lang_delete($id)
    {
        $lang_model = new I18nLangModel();
        if ($lang_model->destroy($id)) {
            Cache::tag('public')->rm('langs');
            $this->log('删除语言成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除语言失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }

    /**
     * 导入
     */
    public function test_import()
    {
        exit('false');
        $sql = "SELECT cn.tmp_key as name, cn.tmp_value as content_cn, en.tmp_value as content_en , vi.tmp_value as content_vi
      FROM  tmp_lanauage as cn
      LEFT JOIN (select max(tmp_value) as tmp_value , tmp_key from tmp_lanauage_en group by tmp_key ) as en ON cn.tmp_key = en.tmp_key
      LEFT JOIN (select max(tmp_value) as tmp_value , tmp_key from tmp_lanauage_vi group by tmp_key ) as vi ON cn.tmp_key = vi.tmp_key

       ";
        $data =  Db::query($sql);
        // dump(count($data));exit;
        foreach ($data as $key => $value) {
            $value['lang_list'] = 'zh-cn,en,vi';
            $value['key_ios'] = $value['name'];
            $value['key_android'] = str_replace('.', '_', $value['name']);
            $value['title'] = $value['content_cn'] ? mb_substr($value['content_cn'], 0, 81, 'utf-8') : "";
            $value['status'] = 1;


            /*echo "<br />";
         dump($value);
         echo "<br />";*/

            $model = new I18nModel();
            $res = $model->allowField(true)->save($value); //主表插入数据
            if ($res) {
                $iid = $model->id; //插入成功后取得id
                $langData  = [];
                $langData[] = ["lang" => "zh-cn", "sort" => 0, "content" => $value['content_cn'] ? $value['content_cn'] : "", "iid" => $iid];
                $langData[] = ["lang" => "en", "sort" => 1, "content" => $value['content_en'] ? $value['content_en'] : "", "iid" => $iid];
                $langData[] = ["lang" => "vi", "sort" => 2, "content" => $value['content_vi'] ? $value['content_vi'] : "", "iid" => $iid];
                $I18nDataModel =   new I18nDataModel;
                $I18nDataModel->saveAll($langData);
                echo "<br />";
                echo ($value['name'] . 'is OK');
                echo "<br />";
            }
            # code...
        }
        $this->log('批量导入i18n数据', 0);
    }
}
