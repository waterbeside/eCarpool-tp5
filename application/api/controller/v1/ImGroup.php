<?php

namespace app\api\controller\v1;

use think\Db;
use app\api\controller\ApiBase;
use app\carpool\model\ImGroupInvitation;
use app\carpool\model\User as UserModel;
use com\nim\Nim as NimServer;
use my\RedisData;

/**
 * 用户群相关接口
 * Class ImGroup
 * @package app\api\controller\v1
 */
class ImGroup extends ApiBase
{

    protected $NIM_OBJ;
    protected $baseInvitationUrl = "http://gitsite.net/j/i.php";
    protected $placeholder_users = array("ph_f272e017046d1b26", "ph_2c2351e0b9e80029", "ph_7e88149af8dd32b2"); //联系人，微信好友，facebook好友。
    protected $inventMsg = "{{username}}邀请你加入溢起拼车群聊组,点击链接完善资料即刻加入"; //邀请返回的信息

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
        $appKey     = config('secret.nim.appKey');
        $appSecret  = config('secret.nim.appSecret');
        $this->NIM_OBJ = new NimServer($appKey, $appSecret);
    }





    //取得占位用户列表
    public function placeholders()
    {
        $Placeholder_users = $this->placeholder_users;
        $this->jsonReturn(0, array("lists" => $Placeholder_users), 'Successful');
    }



    /**
     * 查出邀请连接的相关邀请信息。
     */
    public function invitation()
    {
        $link_code = input('get.link_code');
        if (!$link_code) {
            $this->jsonReturn(992, 'Error params');
        }
        $now       = time();
        $redis = new RedisData();
        $cacheKey = "carpool:im_group:invitation:$link_code";
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            $this->jsonReturn(0, $cacheData, 'Successful');
        }
        $row  = ImGroupInvitation::where([['link_code', '=', $link_code]])->order('id desc')->find();
        $data = [
            'status' => 1,
            'group_detail' => null,
            'inviter' => null,
        ];
        if ($row) {
            $data['status'] = $row['status'];
            if ($now > $row['expiration_time']) {
                $data['status'] = 0;
            }

            //验证该群是否还存在
            $group = $row['im_group']; //群id
            $groupDetail = $this->NIM_OBJ->queryGroup([$group], 0);
            if ($groupDetail && $groupDetail['code'] === 200) {
                $data['group_detail'] = isset($groupDetail['tinfos'][0]) ? $groupDetail['tinfos'][0] : null;
            } else {
                $data['group_detail'] = null;
            }
            if (!$data['group_detail']) {
                $data['status'] = 0;
            }

            //查找邀请者的用户信息
            $inviterData = UserModel::find($row['inviter_uid']);
            $data['inviter'] = array(
                'uid' => $inviterData['uid'],
                'name' => $inviterData['name'],
                'imgpath' => $inviterData['imgpath'],
            );

            $data['type'] = $row['type'];
            $cacheExp = $data['type'] ? 60 : 20;
            $cacheData = $redis->cache($cacheKey, $data, $cacheExp);

            $this->jsonReturn(0, $data, 'Successful');
        } else {
            $this->jsonReturn(20002, [], 'No data');
        }
    }


    /**
     * 应用外邀请用户
     */
    public function external_invite()
    {
        $userData = $this->getUserData(1);
        $uid =  $userData['uid'];

        $owner         = input('post.owner');
        $type          = input('post.type/d', 1);
        $source        = input('post.source');
        $identifier    = input('post.identifier');
        $signature     = input('post.signature');
        $group         = input('post.group');
        $duration      = input('post.duration');
        $is_overseas   = input('post.is_overseas', 0);
        $now           = time();
        $baseInvitationUrl = $this->baseInvitationUrl;

        $inventMsg     = str_replace("{{username}}", $userData['name'], $this->inventMsg);

        if (empty($identifier)) {
            $this->jsonReturn(992, [], 'empty identifier');
        }

        if (empty($type)) {
            $this->jsonReturn(992, [], 'empty type');
        }

        if (empty($source)) {
            $this->jsonReturn(992, [], 'empty source');
        }

        if (!$signature && !$group) {
            $this->jsonReturn(992, [], 'empty signature or group');
        }

        //参数分钟为单位，现改为s为单位
        if (!$duration) {
            $duration = $type === 1 ? 7 * 24 * 60 * 60 : ($type === 0 ? 3 * 24 * 60 * 60 : 0);
        } else {
            $duration = $duration * 60;
        }

        $expiration_time = $now + $duration;

        $ImGroupInvitation = new ImGroupInvitation();
        $link_code = $ImGroupInvitation->create_link_code(8);

        $returnData = array(
            'placeholder' => $this->placeholder_users[$source],
            'url' => $baseInvitationUrl . '?lc=' . $link_code,
            'link_code' => $link_code,
            'desc' => $inventMsg,
        );
        if ($is_overseas) {
            $returnData['url'] .= '&s=' . $is_overseas;
        }


        // ---------- start 检查是否已有重复数据；
        $map_check = [
            ['inviter_uid', '=', $uid],
            ['identifier', '=', $identifier],
            ['source', '=', $source],
        ];
        if ($signature) {
            $map_check[] = ['signature', '=', $signature];
        }
        if ($group) {
            $map_check[] = ['im_group', '=', $group];
        }

        $data_check = $ImGroupInvitation->where($map_check)->find();

        if ($data_check) {  //如果有重复，
            if (empty($data_check['link_code'])) {
                $data_update = [
                    'link_code' => $link_code,
                    'expiration_time' => $expiration_time,
                ];
                $rowCount = $ImGroupInvitation->where('id', $data_check['id'])->update($data_update);
                if ($rowCount) {
                    return $this->jsonReturn(0, $returnData, 'Successful');
                } else {
                    return $this->jsonReturn(-1, [], 'fail');
                }
            } else {
                $data_update = [
                    'expiration_time' => $expiration_time,
                ];
                $rowCount = $ImGroupInvitation->where('id', $data_check['id'])->update($data_update);
                $returnData['url'] = $baseInvitationUrl . '?lc=' . $data_check['link_code'];
                if ($is_overseas) {
                    $returnData['url'] .= '&s=' . $is_overseas;
                }
                $returnData['link_code'] = $data_check['link_code'];
                return $this->jsonReturn(0, $returnData, 'Successful');
            }
        }
        // ---------- end 检查是否已有重复数据；

        // 创建记录；
        $data_insert = [
            'inviter_uid' => $uid,
            'create_time' => $now,
            'link_code' => $link_code,
            'status' => 1,
            'identifier' => $identifier,
            'source' => $source,
            'type' => $type,
            'expiration_time' => $now + $duration,
        ];


        if ($group) {
            $data_insert['im_group']   = $group;
        } elseif ($signature) {
            $data_insert['signature'] = $signature;
        }


        if ($owner) {  //如果传了群主id，则由服务端拉占位用户入群。
            $resNim = $this->NIM_OBJ->addIntoGroup($group, $owner, [$this->placeholder_users[$source]]);
        }
        $result = $ImGroupInvitation->strict(false)->insert($data_insert);
        if ($result) {
            $this->jsonReturn(0, $returnData, 'Successful');
        } else {
            $this->jsonReturn(-1, [], 'fail');
        }
    }



    /**
     * 创建邀请回写群号接口。
     */
    public function external_invite_writeback()
    {
        $userData = $this->getUserData(1);
        $uid =  $userData['uid'];

        $signature     = input('request.signature') ?: (input('post.signature') ?: input('get.signature'));
        $group     = input('request.group/s') ?: (input('post.group/s') ?: input('get.group/s'));

        if (!$signature || !$group) {
            $this->jsonReturn(992, [], 'Empty signature or group');
        }

        $map = [
            ['inviter_uid', '=', $uid],
            ['signature', '=', $signature],
        ];

        $data = ImGroupInvitation::where($map)->find();
        if ($data) {
            $res = ImGroupInvitation::where($map)->update(['im_group' => $group]);
            if ($res !== false) {
                $this->jsonReturn(0, [], 'Successful');
            } else {
                $this->jsonReturn(-1, [], 'update group error');
            }
        } else {
            $this->jsonReturn(-1, [], 'row is inexistence');
        }
    }


    /**
     * 把占位移出群
     */
    public function kick_placeholder()
    {
        $userData = $this->getUserData(1);
        $uid =  $userData['uid'];

        $owner     = input('request.owner') ?: (input('post.owner') ?: input('get.owner'));
        $group     = input('request.group') ?: (input('post.group') ?: input('get.group'));
        $now       = time();

        $placeholder_users = $this->placeholder_users;

        if (!$group) {
            $this->jsonReturn(992, [], 'empty group');
        }
        if (!$owner) {
            $this->jsonReturn(992, [], 'empty owner');
        }
        $callbackData          = array(
            'inviting'  => [1, 1, 1],
        );

        $inviting_count_0     = $this->check_inviting($group, 0);
        if (!$inviting_count_0) {
            $callbackData['inviting'][0] = 0;
            $this->NIM_OBJ->kickFromGroup($group, $owner, $placeholder_users[0]);
            // $this->NIM_OBJ->leaveFromGroup($group,$placeholder_users[0]);
        }

        $inviting_count_1     = $this->check_inviting($group, 1);
        if (!$inviting_count_1) {
            $callbackData['inviting'][1] = 0;
            $this->NIM_OBJ->kickFromGroup($group, $owner, $placeholder_users[1]);
            // $this->NIM_OBJ->leaveFromGroup($group,$placeholder_users[1]);
        }

        $inviting_count_2     = $this->check_inviting($group, 2);
        if (!$inviting_count_2) {
            $callbackData['inviting'][2] = 0;
            $this->NIM_OBJ->kickFromGroup($group, $owner, $placeholder_users[2]);
            // $this->NIM_OBJ->leaveFromGroup($group,$placeholder_users[2]);
        }

        $this->jsonReturn(0, $callbackData);
        // kickFromGroup($tid,$owner,$member)
    }

    /**
     * 验证群是否正在邀请用户
     */
    public function check_inviting($group, $source = false, $isMy = 0)
    {
        $now = time();
        $map = [
            ['status', '=', 1],
            ['im_group', '=', $group],
        ];
        if ($source !== false) {
            $map[] = ['source', '=', $source];
        }
        if ($isMy) {
            $userData = $this->getUserData(1);
            $uid =  $userData['uid'];
            $map[] = ['inviter_uid', '=', $uid];
        }
        $map[] = ['expiration_time', '=', $now];

        $inviting_count     = ImGroupInvitation::where($map)->count();
        return $inviting_count;
    }


    /**
     * 邀请登入提交
     */
    public function signin_invitation($link_code = false, $type = 0)
    {

        if (!$link_code) {
            $this->jsonReturn(992, 'Error Params');
        }

        $UserModel = new UserModel();
        $username = input('post.username');

        if ($type == 1) {
            $userData = $UserModel->where([['loginname', '=', $username], ['is_delete', '=', Db::raw(0)], ['is_active', '=', 1]])->find();
            if (!$userData) {
                $this->jsonReturn(10004, lang('User does not exist or has resigned'));
            }
        } else {
            $password = input('post.password');
            $userData = $UserModel->checkedPassword($username, $password);
            if (!$userData) {
                $this->jsonReturn($UserModel->errorCode, $UserModel->errorMsg);
            }
        }

        $returnData = [
            'loginname' => $userData['loginname'],
            'im_id' => $userData['im_id'],
            'name' => $userData['name'],
            'department' => $userData['Department'],
            'company_id' => $userData['company_id'],
        ];

        $uid = $userData['uid'];


        $cData = array(
            "loginname" => $userData['loginname'],
            "im_id" => $userData['im_id'],
            "name" => $userData['name'],
        );

        if (!$userData['im_id'] || !$userData['im_md5password']) { //创建云信账号
            $avatarBasePath     = config('secret.avatarBasePath');

            $upNimData = [
                'accid' => $userData['loginname'],
                'name' => $userData['name'],
                'icon' => $userData['imgpath'] ? $avatarBasePath . $userData['imgpath'] : $avatarBasePath . 'im/default.png',
            ];
            $imid       =  $userData['loginname'];
            $rs         =  $this->NIM_OBJ->createUserId($upNimData);
            $cData["im_id"] = $imid;
            $returnData['im_id'] = $imid;

            if ($rs['code'] == 414) { //创建云信帐号失败，则尝试看是否已存在此im_id，如果是，更新一次im_md5password.
                $rs_r = $this->NIM_OBJ->updateUserToken($imid);
                if ($rs_r['code'] == 200) {
                    UserModel::where('uid', $uid)->update(array('im_id' => $imid, 'im_md5password' => $rs_r['info']['token']));
                    $this->jsonReturn(0, $returnData, 'success');
                    $result = $this->createUserFromInvitation($cData, $link_code, 2);
                    if ($result) {
                        $this->jsonReturn(0, $returnData, 'success');
                    } else {
                        $this->jsonReturn(-1, [], 'create user fail');
                    }
                } else {
                    $this->jsonReturn(-1, [], 'create im_id fail');
                }
            }

            if ($rs['code'] == 200) {
                UserModel::where('uid', $uid)->update(array('im_id' => $imid, 'im_md5password' => $rs_r['info']['token']));
                $this->jsonReturn(0, $returnData, 'success');

                $result = $this->createUserFromInvitation($cData, $link_code, 2);
                if ($result) {
                    $this->jsonReturn(0, $returnData, 'success');
                } else {
                    $this->jsonReturn(-1, [], 'create user fail');
                }
            }
        } else {
            $result = $this->createUserFromInvitation($cData, $link_code, 2);
            if ($result) {
                $this->jsonReturn(0, $returnData, 'success');
            } else {
                $this->jsonReturn(-1, [], 'create user fail');
            }
        }
    }


    /**
     * 从邀请连接创件用户
     *
     * @param array $datas 用于创建用户的数据
     * @param string|boolean $link_code link_code
     * @param integer $type 1:注册来源，2:登入来源
     * @param boolean $doSave 是否执行创建用户操作 0 || 1
     * @return void
     */
    public function createUserFromInvitation($datas, $link_code = false, $type = 1, $doSave = 0)
    {

        if ($doSave == 1) {
            if ($type == 2) {
                return true;
            }
            //TODO:用$datas注册（创建）新用户;(但暂不开放此功能)
            // $UserModel = new UserModel();
            return true;
        }

        if ($link_code) { //如果存在link_code，则查出邀请连接的相关数据。
            $row     = ImGroupInvitation::where([['link_code', '=', $link_code]])->find();
        }

        if (!$link_code || !$row  || $row['expiration_time'] < time() || $row['status'] == 0) {
            return $this->createUserFromInvitation($datas, false, $type, 1);
        } else {
            $group = $row['im_group']; //群id
            $groupData = $this->NIM_OBJ->queryGroup([$group]); // 查出群信息
            if ($groupData && $groupData['code'] == 200) { //如果存在群。
                $owner = $groupData['tinfos'][0]['owner']; //查出群主。
                $resAddGroup = $this->NIM_OBJ->addIntoGroup($group, $owner, [$datas['im_id']], 0, lang('invites you to join the group'), '{"is_external":"1"}'); //加人入群

                // $magree='0',$msg='请您入伙',$attach="";

                $uid = $this->createUserFromInvitation($datas, false, $type, 1);
                if ($uid) {
                    if ($row->type === '0' || $row->type === 0) {
                        ImGroupInvitation::where('id', $row->id)->update(array('last_signup_time' => time(), 'status' => 0));
                    }
                }
                return $uid;
            } else { //如果查群失败
                ImGroupInvitation::where('id', $row->id)->update(array('last_signup_time' => time()));
                $result = $this->createUserFromInvitation($datas, false, $type, 1);
                return $result;
            }
        }
    }
}
