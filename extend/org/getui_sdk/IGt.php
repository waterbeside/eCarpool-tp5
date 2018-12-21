<?php
namespace org\getui_sdk;
//
require_once(dirname(__FILE__) . '/' . 'IGt.Push.php');
require_once(dirname(__FILE__) . '/' . 'igetui/utils/AppConditions.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.Target.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.Message.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.ListMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.AppMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.ListMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.SingleMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.APNPayload.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.MultiMedia.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.TransmissionTemplate.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.NotificationTemplate.php');



//
use IGeTui;
use IGtSingleMessage;
use IGtListMessage;
use IGtTarget;
use IGtAppMessage;
use IGtTransmissionTemplate;
use IGtNotificationTemplate;
use AppConditions;
use IGtAPNPayload;
use SimpleAlertMsg;
use DictionaryAlertMsg;
use IGtMultiMedia;
use MediaType;

Class IGt
{
  protected $appkey;
  protected $appID;
  protected $masterSecret;
  protected $getui ;

  public function __construct($appkey, $masterSecret,$appID, $ssl = NULL){
    $host = 'http://sdk.open.api.igexin.com/apiex.htm';
    $this->appKey = $appkey;
    $this->appID = $appID;
    $this->masterSecret = $masterSecret;
    $this->getui = new IGeTui($host, $appkey, $masterSecret, $ssl);
  }


  /**
   *  指定用户推送消息
   * @param  IGtMessage message
   * @param  IGtTarget target
   * @return Array {result:successed_offline,taskId:xxx}  || {result:successed_online,taskId:xxx} || {result:error}
   ***/
  public function pushMessageToSingle($msg,$cid,$type = 1)
  {

      $template = $this->formatTemplate($msg,$type);
      //个推信息体
      $message = new IGtSingleMessage();
      $message->set_isOffline(true);//是否离线
      $message->set_offlineExpireTime(3600*12*1000);//离线时间
      $message->set_data($template);//设置推送消息类型
  //	$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
      //接收方
      $target = new IGtTarget();
      $target->set_appId($this->appID);
      $target->set_clientId($cid);
  //    $target->set_alias(ALIAS);

      try {
          $rep = $this->getui->pushMessageToSingle($message, $target);
      }catch(RequestException $e){
          $requstId =$e->getRequestId();
          $rep = $this->getui->pushMessageToSingle($message, $target,$requstId);
      }
      return $rep;

  }


  //多推接口案例
  public function pushMessageToList($msg,$cids,$type = 1)
  {
      putenv("gexin_pushList_needDetails=true");
      putenv("gexin_pushList_needAsync=true");

      //消息模版：
      // 1.TransmissionTemplate:透传功能模板
      // 2.LinkTemplate:通知打开链接功能模板
      // 3.NotificationTemplate：通知透传功能模板
      // 4.NotyPopLoadTemplate：通知弹框下载功能模板


      //$template = IGtNotyPopLoadTemplateDemo();
      //$template = IGtLinkTemplateDemo();
      //$template = IGtNotificationTemplateDemo();
      $template = $this->formatTemplate($msg,$type);

      //个推信息体
      $message = new IGtListMessage();
      $message->set_isOffline(true);//是否离线
      $message->set_offlineExpireTime(3600 * 12 * 1000);//离线时间
      $message->set_data($template);//设置推送消息类型
  //    $message->set_PushNetWorkType(1);	//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送
  //    $contentId = $igt->getContentId($message);
      $contentId = $this->getui->getContentId($message,"pushList_".time());	//根据TaskId设置组名，支持下划线，中文，英文，数字

      $targetList = [];
      //接收方1
      foreach ($cids as $key => $value) {
        $targetList[$key] = new IGtTarget();
        $targetList[$key]->set_appId($this->appID);
        $targetList[$key]->set_clientId($value);
      }

      $rep = $this->getui->pushMessageToList($contentId, $targetList);
      return $rep;
  }

  //群推接口案例
  public function pushMessageToApp($msg,$phoneType = NULL){
      $template = $this->formatTemplate($msg);
      //个推信息体

      //基于应用消息体
      $message = new IGtAppMessage();
      $message->set_isOffline(true);
      $message->set_offlineExpireTime(10 * 60 * 1000);//离线时间单位为毫秒，例，两个小时离线为3600*1000*2
      $message->set_data($template);
  //    $message->setPushTime("201808011537");
      $appIdList=array(APPID);

      if(is_string($phoneType)){
        $phoneTypeList = array($phoneType);
      }else if(is_array($phoneType)){
        $phoneTypeList = $phoneType;
      }

      $cdt = new AppConditions();
      if(isset($phoneTypeList) && !empty($phoneTypeList)){
        $cdt->addCondition(AppConditions::PHONE_TYPE, $phoneTypeList);
      }
      $message->set_conditions($cdt);
      $message->set_appIdList($appIdList);

      $rep = $this->getui->pushMessageToApp($message);
      return $rep;
  }



  public  function formatTemplate($setting,$type = 1){
    $default = [
      'title'=>"溢起拼车",
      'text'=>"",
      'transmissionType'=>1,
      'logo'=>'http://gitsite.net/carpool_assist/static/images/carpool80.png',
      'isRing'=>false,
      'isVibrate'=>false,
      'isClearable' => true,
      'onlyWifi' =>false,
    ];
    if(is_string($setting)){
      $tplSetting['content'] = $setting;
      $tplSetting['text'] = $setting;
      $tplSetting = array_merge($default,$tplSetting);
    }
    if(is_array($setting)){
      $tplSetting = array_merge($default,$setting);
    }

    if($type == 1){
      $template =  new IGtTransmissionTemplate();
    }else{
      $template =  new IGtNotificationTemplate();
    }

    $template->set_appId($this->appID);//应用appid
    $template->set_appkey($this->appKey);//应用appkey
    $template->set_transmissionType($tplSetting['transmissionType']);//透传消息类型
    if(isset($tplSetting['content'])){
      $template ->set_transmissionContent($tplSetting['content']);//透传内容
    }

    if($type == 1){
      // $apn = new IGtAPNPayload();
      // $alertmsg=new SimpleAlertMsg();
      // $alertmsg->alertMsg="test alertMsg";
      // $apn->alertMsg=$alertmsg;
      // $apn->badge="+1";
      // // $apn->sound="";
      // $apn->add_customMsg("payload","payload");
      // $apn->contentAvailable=1;
      // $apn->category="ACTIONABLE";
      // $template->set_apnInfo($apn);
      //APN高级推送
      $apn = new IGtAPNPayload();
      $alertmsg=new DictionaryAlertMsg();
      $alertmsg->body= $tplSetting['text'];
      $alertmsg->actionLocKey="ActionLockey";
      $alertmsg->locKey="LocKey";
      $alertmsg->locArgs=array("locargs");
      $alertmsg->launchImage="launchimage";
      //        IOS8.2 支持
      $alertmsg->title = $tplSetting['title'];
      $alertmsg->titleLocKey="TitleLocKey";
      $alertmsg->titleLocArgs=array("TitleLocArg");

      $apn->alertMsg=$alertmsg;

      $apn->badge= 1;
      // $apn->autoBadge = "+1";
      $apn->sound="";
      $apn->add_customMsg("payload","payload");
      $apn->contentAvailable=1;
      $apn->category="ACTIONABLE";

      //
      //    IOS多媒体消息处理
      // $media = new IGtMultiMedia();
      // $media->set_url($tplSetting['logo']);//打开连接地址
      // $media -> set_onlywifi($tplSetting['onlyWifi']);
      // $media -> set_type(MediaType::pic);
      // $medias = array();
      // $medias[] = $media;
      // $apn->set_multiMedias($medias);

      $template->set_apnInfo($apn);

    }else{
      $template->set_title($tplSetting['title']);//通知栏标题
      $template->set_text($tplSetting['text']);//通知栏内容
      $template->set_logo($tplSetting['isRing']);//通知栏logo
      $template->set_isRing($tplSetting['logo']);//是否响铃
      $template->set_isVibrate(true);//是否震动
      $template->set_isClearable(true);//通知栏是否可清除
      if(isset($tplSetting['url'])){
        $template ->set_url($tplSetting['url']);//打开连接地址
      }
      if(isset($tplSetting['content'])){
        $template ->set_transmissionContent($tplSetting['content']);//透传内容
      }
    }


    return $template;

  }




}
