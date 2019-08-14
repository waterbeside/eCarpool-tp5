<?php

namespace app\admin\controller;

use org\Auth;
use think\Loader;
use think\facade\Cache;
// use think\Controller;
use think\Response;
use app\common\controller\Base;
use think\Db;
use my\RedisData;
use my\DeptAuth;
use think\facade\Hook;
use Firebase\JWT\JWT;
use app\user\model\Department;
use think\facade\Env;
use think\facade\Lang;
use think\facade\Cookie;
use think\facade\Session;
use app\admin\service\Admin as AdminService;
use app\admin\behavior\CheckDeptAuth;

/**
 * 后台公用基础控制器
 * Class AdminBase
 * @package app\common\controller
 */
class AdminBase extends Base
{

    protected $jwtInfo;
    public $userBaseInfo;
    protected $redisObj = null;
    public $server_timezone_offset = 0;
    public $request_timezone_offset = 0;
    public $timezone_offset_diff = 0;
    public $un_check = [];
    public $check_dept_setting = [];
    public $authDeptData = [];
    public $activeLang = null;
    protected $check_dept_setting_default = [
        "action" => ['index', 'add', 'post.edit']
    ];


    protected function initialize()
    {
        parent::initialize();
        $this->loadLanguagePack();
        $this->getTimezoneOffset();

        $module     = strtolower($this->request->module());
        $controller = strtolower($this->request->controller());
        $action     = strtolower($this->request->action());
        $unCheckRoute = ['admin/login/index', 'admin/login/login', 'admin/login/logout'];

        if (!in_array($module . '/' . $controller . '/' . $action, $unCheckRoute)) {
            $this->checkAuthOnline(); //验证是否登入
            $this->checkAuth();   //验证菜单权限
            $this->checkDeptAuth(); //取得权限部门
        }

        $this->assign('systemConfig', $this->systemConfig);
        $this->assign('langs_select_list', config('others.langs_select_list'));

        // 输出当前请求控制器（配合后台侧边菜单选中状态）
        $this->assign('controller', Loader::parseName($this->request->controller()));
    }

    /**
     * 权限检查
     * @return bool
     */
    protected function checkAuth()
    {

        // $this->checkToken(); //如果使用jwt验证

        $module     = strtolower($this->request->module());
        $controller = strtolower($this->request->controller());
        $action     = strtolower($this->request->action());
        // dump($this->request);exit;

        // 排除权限
        $un_check = ['admin/index/index', 'admin/authgroup/getjson', 'admin/system/clear', 'admin/index/main'];
        $un_check = array_merge($un_check, $this->un_check);
        $un_check = array_map('strtolower', $un_check);
        $un_check_controller = ['publics', 'uploader'];

        $currentRoute = $module . '/' . $controller . '/' . $action;

        if (!in_array($currentRoute, $un_check) && !in_array($controller, $un_check_controller) && strpos($action, 'public_') === false) {
            $auth     = new Auth();
            $admin_id = $this->userBaseInfo['uid'];

            // $admin_id = Session::get('admin_id');
            if (!$auth->check($module . '/' . $controller . '/' . $action, $admin_id) && $admin_id != 1) {
                $referer = $this->request->header('referer');
                if ($this->request->isAjax()) {
                    $accept = $this->request->header('accept');
                    if (strpos($accept, "text/html") !== false) {
                        $this->error(lang('Permission denied'));
                    } else {
                        return $this->jsonReturn(-1, lang('Permission denied'));
                    }
                } else {
                    $this->error(lang('Permission denied'), null, null, -1);
                }
            }
        }
    }

    /**
     * 验证在线状态
     *
     * @return void
     */
    public function checkAuthOnline()
    {
        $AdminService = new AdminService();
        $res = $AdminService->checkRemUser();
        if (!$res) {
            if ($this->request->isAjax()) {
                $this->jsonReturn($AdminService->errorCode, $AdminService->errorMsg);
            } else {
                return $this->error($AdminService->errorMsg, 'admin/login/index');
            }
        }
        $AdminService->reSignPassport($res, 1);
        $this->userBaseInfo = $res['user'];
        $this->userBaseInfo['uid'] = $res['user']['id'];
        $this->assign('admin_id', $res['user']['id']);
        $this->assign('admin_name', $res['user']['username']);
        return $res;
    }

    /**
     * 验证验入session
     */
    public function checkAuthSession()
    {
        if (!Session::has('admin_id')) {
            if ($this->request->isAjax()) {
                return $this->jsonReturn(10004, lang('You are not logged in'));
            } else {
                return $this->error(lang('You are not logged in'), 'admin/login/index');
            }
        }
        $this->userBaseInfo = [
            'uid' => Session::get('admin_id'),
            'username' => Session::get('admin_name')
        ];
    }

    /**
     * 取得当前管理员的部门权限信息
     */
    public function getDeptAuth()
    {
        $auth_dept_data = $this->getAuthDepartments($this->userBaseInfo['uid']);
        $this->userBaseInfo['auth_depts_str']  =  $auth_dept_data ? $auth_dept_data['depts'] : 0;
        $this->userBaseInfo['auth_depts']  = $auth_dept_data ? explode(',', $auth_dept_data['depts']) : [];
        $this->userBaseInfo['auth_depts_isAll'] = $auth_dept_data ? (in_array(0, $this->userBaseInfo['auth_depts']) ? 1 : 0) : 1;
    }

    /**
     * 检查部门权限
     */
    public function checkDeptAuth()
    {
        $this->getDeptAuth(); //取得权限部门
        $check_dept_setting = array_merge($this->check_dept_setting_default, (is_array($this->check_dept_setting) ? $this->check_dept_setting : []));
        $action     = strtolower($this->request->action());
        $method     = strtolower($this->request->method());
        if (in_array($action, $check_dept_setting['action']) || in_array($method . '.' . $action, $check_dept_setting['action'])) {
            $res = Hook::listen("check_dept_auth", $this, [], true);
            $authDeptData = $this->authDeptData;
            $region_id = $authDeptData['region_id'];
            $regionData = $authDeptData['region_datas'] ? $authDeptData['region_datas'][0] : null;

            $this->assign('regionData', $regionData);
            $this->assign('region_id', $region_id);
            $this->assign('region_datas', $authDeptData['region_datas']);

            if (in_array($action, ['add', 'edit'])) {
                if (!$this->request->isPost()) {
                    if (count($this->userBaseInfo['auth_depts']) > 0 || $this->userBaseInfo['auth_depts_isAll']) {
                        $this->assign('showSelectDepartment', 1);
                    } else {
                        $this->assign('showSelectDepartment', 0);
                    }
                }
                if ($action == 'add') {
                    $department_default_selected = !$this->userBaseInfo['auth_depts_isAll'] && isset($authDeptData['filter_region_datas'][0]) ? $authDeptData['filter_region_datas'][0] : null;
                    $this->assign('department_default_selected', $department_default_selected);
                }
            }
        }
    }

    /**
     * 检查该地区id有没有权限
     */
    public function checkDeptAuthByDid($did, $returnType = 0)
    {
        $DepartmentModel = new Department;
        $errorMsg = lang('You do not have permission to manage content in this region or department');
        if ($this->userBaseInfo['auth_depts_isAll']) {
            return true;
        } elseif (!$did) {
            $this->errorMsg = $errorMsg;
            return  $returnType ? $this->error($this->errorMsg) : false;
        }
        $department_data = $DepartmentModel->getItem($did);
        if (!$department_data) {
            $this->errorMsg = $errorMsg;
            return  $returnType ? $this->error($this->errorMsg) : false;
        }
        if (!$this->userBaseInfo['auth_depts']) {
            $this->errorMsg = $errorMsg;
            return  $returnType ? $this->error($this->errorMsg) : false;
        }
        $check_res = array_intersect($this->userBaseInfo['auth_depts'], explode(',', ($department_data['path'] . ',' . $did)));
        if (empty($check_res)) {
            $this->errorMsg = $errorMsg;
            return  $returnType ? $this->error($this->errorMsg) : false;
        } else {
            return true;
        }
    }

    /**
     * 验证路由权限
     */
    public function checkActionAuth($r)
    {
        $auth     = new Auth();
        $admin_id = $this->userBaseInfo['uid'];
        if (!$auth->check($r, $admin_id) && $admin_id != 1) {
            return false;
        } else {
            return true;
        }
    }



    /**
     * 取得后台用户有权的部门
     */
    public function getAuthDepartments($uid)
    {
        $DeptAuth     = new DeptAuth();
        return $DeptAuth->getGroup($uid);
    }

    /**
     * 获取侧边栏菜单
     */
    protected function getMenu()
    {
        $menu     = [];
        // $admin_id = Session::get('admin_id');
        $admin_id = $this->userBaseInfo['uid'];
        $auth     = new Auth();

        $auth_rule_list = Db::name('auth_rule')->where('status', 1)->order(['sort' => 'DESC', 'id' => 'ASC'])->select();

        $activeLang  = $this->activeLang;
        foreach ($auth_rule_list as $value) {
            if ($auth->check($value['name'], $admin_id) || $admin_id == 1) {
                $value['title_zh'] = $value['title'];
                if ($activeLang != 'zh-cn') {
                    $value['title'] =  $value['title_en'] ? $value['title_en'] : $value['title'];
                    if ($activeLang == 'vi') {
                        $value['title'] =  $value['title_vi'] ? $value['title_vi'] : $value['title'];
                    }
                }
                $menu[] = $value;
            }
        }
        $menu = !empty($menu) ? array2tree($menu) : [];
        foreach ($menu as $key => $value) {
            $reChildren =  isset($value['children']) ? $value['children'] : [];
            if (!empty($reChildren)) {
                array_multisort(array_column($reChildren, 'sort'), SORT_DESC, $reChildren);
                $menu[$key]['children'] = $reChildren;
            }
        }
        array_multisort(array_column($menu, 'sort'), SORT_DESC, $menu);
        $this->assign('menu', $menu);
    }


    public function log($desc = '', $status = 2)
    {
        $request = request();
        $data['uid'] = $this->userBaseInfo['uid'];
        $data['ip'] = $request->ip();
        // $data['path'] = $request->path();
        $isAjaxShow =  $request->isAjax() ? " (Ajax)" : "";
        $data['type'] = $request->method() . "$isAjaxShow";
        $data['route'] = $request->module() . '/' . $request->controller() . '/' . $request->action();
        $data['query_string'] = $request->query();
        $data['description'] = $desc;
        $data['status'] = $status;
        $data['time'] = time();
        Db::name('admin_log')->insert($data);
    }

    /**
     * 更新数据版本，用于不经常更新的数据，以减少前端请求
     * @param  要更新的redis的key；
     * @return 返回最新版本；
     */
    public function updateDataVersion($cacheVersionKey)
    {
        $redisObj = $this->redis();
        $cacheVersion = $redisObj->get($cacheVersionKey);
        $newVersion = $cacheVersion ? intval($cacheVersion) + 1 : 1;
        $redisObj->set($cacheVersionKey, $newVersion);
        return $newVersion;
    }

    /**
     * 创建redis对像
     * @return redis
     */
    public function redis()
    {
        if (!$this->redisObj) {
            $this->redisObj = new RedisData();
        }
        return $this->redisObj;
    }

    /**
     * 取得子部门id
     * @param  integer $pid 父id
     */
    public function getDepartmentChildrenIds($pid)
    {
        $DepartmentModel = new Department();
        return $DepartmentModel->getChildrenIds($pid);
    }

    /**
     * 构造"有权查看的区域的sql"
     * @param  integer $region_id
     * @param  string $as     关联的部门表别名
     */
    public function buildRegionMapSql($region_id, $as = 'd')
    {
        return (new CheckDeptAuth())->buildRegionMapSql($region_id, $as);
        // return Hook::exec(['app\\admin\\behavior\\CheckDeptAuth','buildRegionMapSql'], $region_id, $as);
    }

    /**
     * 通过id取部门信息
     * @param  integer $id;
     */
    public function getDepartmentById($id)
    {
        $DepartmentModel = new Department();
        return $DepartmentModel->getItem($id);
    }


    /**
     * 格式化筛选的时间范围
     * @param  string||array $date     输入的时间 以 "2019-01-01 ~ 2019-01-02" 格式传入，或以数组格式["2019-01-01", "2019-01-02"]
     * @param  string $formater 输出的格式
     * @param  string $accuracy 精度范围
     * @return array          输出数组 [start time,end time];
     */
    public function formatFilterTimeRange($date, $formater = "Y-m-d H:i:s", $accuracy = "d", $timezone_offset_switch = 1)
    {
        if (is_string($date)) {
            $time = $date;
            $time_arr = explode(' ~ ', $date);
            $time_arr = count($time_arr) > 1 ? $time_arr : explode('+~+', $time);
        }
        if (is_array($date) && is_string($date[0]) && is_string($date[1])) {
            $time_arr = $date;
        }
        switch ($accuracy) {
            case 'Y':
                $endAdd = 24 * 60 * 60;
                break;
            case 'm':
                $endAdd = "first day of next month";
                break;
            case 'd':
                $endAdd = "+1 day";
                break;
            case 'H':
                $endAdd = "+1 hour";
                break;
            case 'i':
                $endAdd = "+60 seconds";
                break;
            case 's':
                $endAdd = "+1 second";
                break;
            default:
                $endAdd = '';
                break;
        }
        $timezone_offset_d = $timezone_offset_switch ? $this->timezone_offset_diff : 0;
        $time_s_timestamp = strtotime($time_arr[0]) - $timezone_offset_d;
        $time_e_timestamp = strtotime($time_arr[1] . $endAdd) - $timezone_offset_d;
        $time_s = date($formater, $time_s_timestamp);
        $time_e = date($formater, $time_e_timestamp);
        return [$time_s, $time_e];
    }


    public function getFilterTimeRangeDefault($formater = "Y-m-d H:i:s", $accuracy = "d")
    {
        switch ($accuracy) {
            case 'Y':
                $time_s =  date('Y');
                $time_e = date('Y');
                break;
            case 'm':
                $time_s = date('Y-m-d', strtotime("first day of this month"));
                $time_e = date('Y-m-d', strtotime("last day of this month"));
                break;
            case 'w':
                $time_s = date("Y-m-d", strtotime('-1 week last sunday'));
                $time_e = date("Y-m-d", strtotime("$time_s +1 week") - 24 * 60 * 60);
                break;
            case 'd':
                $time_s =  date('Y-m-d');
                $time_e = date('Y-m-d');
                break;
            case 'H':
                $time_s =  date('Y-m-d H');
                $time_e = date('Y-m-d H');
                break;
            default:
                $endAdd = '';
                break;
        }
        $time_s_format =  date($formater, strtotime($time_s));
        $time_e_format =  date($formater, strtotime($time_e));
        $date = $time_s_format . " ~ " . $time_e_format;

        return $date;
    }


    /**
     * getFormatOffsetTime
     * @param  string||array $data     输入的时间 以 "2019-01-01 ~ 2019-01-02" 格式传入，或以数组格式["2019-01-01", "2019-01-02"]
     * @param  string $formater 输出的格式
     * @param  string $accuracy 精度范围
     * @return array          输出数组 [start time,end time];
     */
    public function getFormatOffsetTime($dateStr, $formater = "Y-m-d H:i:s")
    {
        $timestamp = strtotime($dateStr);
        $timezone_offset_d = $this->timezone_offset_diff ? $this->timezone_offset_diff : 0;
        return date($formater, $timestamp - $timezone_offset_d);
    }

    /**
     * getDetail
     * @param  integer  $type  0:date('Z') ;  1: request_timezone_offset; false: both (0,1) ;
     */
    public function getTimezoneOffset($type = false)
    {
        $this->server_timezone_offset = intval(date('Z'));

        $request_timezone_offset_string = isset($_COOKIE["timezoneOffset"]) && is_numeric($_COOKIE["timezoneOffset"]) ? intval($_COOKIE["timezoneOffset"]) : false;
        $this->request_timezone_offset  =  $request_timezone_offset_string  !== false ? $request_timezone_offset_string * 60 * (-1) : intval(date('Z'));
        $this->timezone_offset_diff     = $this->server_timezone_offset -  $this->request_timezone_offset;
        $this->timezone_offset_diff     = intval($this->timezone_offset_diff);
        if ($type === 1) {
            return $this->request_timezone_offset;
        }
        if ($type === 0) {
            return $this->server_timezone_offset;
        }
        return [$this->server_timezone_offset, $this->request_timezone_offset];
    }


    /**
     * 加载语言包
     * @param  string  $language   语言，当不设时，自动选择
     * @param  integer $formCommon 语言包路径位置。
     */
    public function loadLanguagePack($language = null, $formCommon = 0)
    {
        $path = $formCommon ?
            Env::get('root_path') . 'application/common/lang/' : Env::get('root_path') . 'application/admin/lang/';

        $lang_s =  input('request._language');
        $lang_s = $lang_s ? $lang_s : input('request.lang');
        if ($lang_s) {
            $d_lang = $this->getLang();
            Cookie::set('lang', $d_lang, 60 * 60 * 24 * 30);
        } else {
            $d_lang = Cookie::get('lang');
            if (!$d_lang) {
                $d_lang = $this->getLang();
            }
            $d_lang = $this->formatLangCode($d_lang);
        }
        $lang = $language ? $language  : $d_lang;
        $langs_list =  config('others.langs_select_list');
        $lang = isset($langs_list[$lang]) ? $lang : config('default_lang');
        $this->activeLang = $lang;
        $this->assign('active_lang', $lang);
        return Lang::load($path . $lang . '.php');
    }
}
