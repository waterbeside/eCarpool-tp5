<?php
namespace com\nim;

use com\nim\Base;

/**
 * 云信超大群
 * Class SuperTeam
 * @author  hzchensheng15@corp.netease.com
 * @created date    2015-10-27  16:30s
 *
 * @modified date 2016-06-15 19:30
 * *** 添加直播相关示例接口 ***
 *
 ***/

class SuperTeam extends Base
{
    /*** 超大群 ***/
    /**
     * 群组功能（超大群）-创建群
     * @param array 参数
     *  owner string 必须 群主用户帐号，最大长度32字符
     *  inviteAccids string 必须 邀请的群成员列表。["aaa","bbb"](JSONArray对应的accid，如果解析出错会报414)，inviteAccids与owner总和上限为200。inviteAccids中无需再加owner自己的账号。
     *  tname string 群名称，最大长度64字符
     *  intro string - 群描述，最大长度512字符
     *  announcement String - 群公告，最大长度1024字符
     *  serverCustom String - 自定义群扩展属性，第三方可以根据此属性自定义扩展自己的群属性，最大长度1024字符
     *  icon string - 群头像，最大长度1024字符
     * @return $result   [返回array数组对象]
     */
    public function createGroup($data)
    {
        $url = 'https://api.netease.im/nimserver/superteam/create.action';
        if (!$data['inviteAccids']) {
            $data['inviteAccids'] = [];
        }
        if ($this->RequestType == 'curl') {
            $result = $this->postDataCurl($url, $data);
        } else {
            $result = $this->postDataFsockopen($url, $data);
        }
        return $result;
    }

    /**
     * 拉人入群
     * @param array 参数
     *  tid string 必须 云信服务器产生，群唯一标识，创建群时会返回，最大长度128字符
     *  owner string 必须 群主用户帐号，最大长度32字符
     *  inviteAccids string 必须 被拉入群的accid(JSONArray)，["aaa","bbb"]，一次最多操作200个
     * @return $result   [返回array数组对象]
     */
    public function addIntoGroup($data)
    {
        $url = 'https://api.netease.im/nimserver/superteam/invite.action';
        if ($this->RequestType == 'curl') {
            $result = $this->postDataCurl($url, $data);
        } else {
            $result = $this->postDataFsockopen($url, $data);
        }
        return $result;
    }

    /**
     * 踢人出群
     * @param array 参数
     *  tid string 必须 云信服务器产生，群唯一标识，创建群时会返回，最大长度128字符
     *  owner string 必须 群主用户帐号，最大长度32字符
     *  kickAccids string 必须 被拉入群的accid(JSONArray)，["aaa","bbb"]，一次最多操作200个
     * @return $result   [返回array数组对象]
     */
    public function kickFromGroup($data)
    {
        $url = 'https://api.netease.im/nimserver/superteam/kick.action';
        if ($this->RequestType == 'curl') {
            $result = $this->postDataCurl($url, $data);
        } else {
            $result = $this->postDataFsockopen($url, $data);
        }
        return $result;
    }

    /**
     * 解散群
     * @param  $tid       [云信服务器产生，群唯一标识，创建群时会返回，最大长度128字节]
     * @param  $owner       [群主用户帐号，最大长度32字节]
     * @return $result      [返回array数组对象]
     */
    public function removeGroup($tid, $owner)
    {
        $url = 'https://api.netease.im/nimserver/superteam/dismiss.action';
        $data = array(
            'tid' => $tid,
            'owner' => $owner
        );
        if ($this->RequestType == 'curl') {
            $result = $this->postDataCurl($url, $data);
        } else {
            $result = $this->postDataFsockopen($url, $data);
        }
        return $result;
    }

    /**
     * 取得群信息
     * @param array $tids tid列表，如["3083","3084"]
     */
    public function getDetail($tids)
    {
        $url = 'https://api.netease.im/nimserver/superteam/getTinfos.action';
        $data = array(
            'tids' => json_encode($tids),
        );
        if ($this->RequestType == 'curl') {
            $result = $this->postDataCurl($url, $data);
        } else {
            $result = $this->postDataFsockopen($url, $data);
        }
        return $result;
    }

    /**
     * @param array 参数
     *  tid string 必须 云信服务器产生，群唯一标识，创建群时会返回，最大长度128字符
     *  timetag string 必须 时间戳，单位毫秒，查询的时间起点。
     *  limit string 本次查询的条数上限(最多100条)，小于等于0，或者大于100，会提示参数错误
     *  reverse string - 1:按时间正序排列，2:按时间降序排列。其它会提示参数错误。默认是1按时间正序排列
     * @return $result   [返回array数组对象]
     */
    public function getLists($data)
    {
        $url = 'https://api.netease.im/nimserver/superteam/getTlists.action';
        if ($this->RequestType == 'curl') {
            $result = $this->postDataCurl($url, $data);
        } else {
            $result = $this->postDataFsockopen($url, $data);
        }
        return $result;
    }

}
