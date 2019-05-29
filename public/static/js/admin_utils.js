if(typeof(GV)=="undefined"){
  var GV = {};
}
if(typeof(layer) == 'undefined'){
  var layer = layui.layer;
}
GV['lang'] = 'zh-cn';

//从cookie取得存储的过程
function getLangFromCookie(){
  var lang = MyCookies.get('lang');
  return lang ? lang : 'zh-cn';
}

var myLang = {
  langs : ['zh-cn','en','vi'],
  lists : {
    'zh-cn':{
      'warning': '警告',
      'tips': '提示',
      'yes': '是',
      'no': '否',
      'cancel': '取消',
      'confirm': '确认',
      'are you sure you want to do this?' : '确定执行此操作？',
      'please confirm whether to delete' : '请确定是否删除',
      'a form is being submitted, please try again later':'有表单正在提交，请稍候再试',
      'network error, please try again later':'网络出错，请稍候再试',
      'Upload successfully':'上传成功',
      'Upload failed':'上传失败',
      'Upload failed, please try again later':'上传失败,请稍候再试',
    },
    'en':{
      'warning': 'Warning',
      'tips': 'Tips',
      'yes': 'Yes',
      'no': 'No',
      'cancel': 'Cancel',
      'confirm': 'Confirm',
      'are you sure you want to do this?' : 'Are you sure you want to do this?',
      'please confirm whether to delete':'Please confirm whether to delete',
      'a form is being submitted, please try again later':'A form is being submitted, please try again later',
      'network error, please try again later':'Network error, please try again later',
      'Upload successfully':'Upload successfully',
      'Upload failed':'Upload failed',
      'Upload failed, please try again later':'Upload failed, please try again later',
    },
    'vi':{
      'warning': 'Warning',
      'tips': 'Tips',
      'yes': 'Yes',
      'no': 'No',
      'cancel': 'Cancel',
      'confirm': 'Confirm',
      'are you sure you want to do this?' : 'Bạn có chắc chắn muốn làm điều này?',
      'please confirm whether to delete':'Vui lòng xác nhận xem có nên xóa không',
      'a form is being submitted, please try again later':'Một mẫu đơn đang được gửi, vui lòng thử lại sau',
      'network error, please try again later':'Lỗi mạng, vui lòng thử lại sau',
      'Upload successfully':'Upload successfully',
      'Upload failed':'Upload failed',
      'Upload failed, please try again later':'Upload failed, please try again later',
    },
  },
  init : function(){
    var lang = getLangFromCookie();
    GV['lang'] = $.inArray(lang, myLang.langs) == -1 ? GV['lang'] : lang;
  },
  r:function(str){
    if(typeof(myLang.lists[GV['lang']][str.toLowerCase()]) !="undefined"){
      str = myLang.lists[GV['lang']][str.toLowerCase()];
    }

    return str;
  }
}


var MyCookies = {
  //写cookies
  set: function(name, value, time = 0) {
    let days = 30
    let exp = new Date()
    if(time){
      exp.setTime(exp.getTime() + time*1000)
    }else{
      exp.setTime(exp.getTime() + days*24*60*60*1000)
    }
    document.cookie = name + '=' + escape (value) + ';expires=' + exp.toGMTString()+';path=/'
  },
  //读取cookies
  get: function (name) {
    let arr = null
    let reg = new RegExp('(^| )'+name+'=([^;]*)(;|$)')
    if (document.cookie && (arr = document.cookie.match(reg))) {
      return unescape(arr[2])
    } else {
      return null;
    }
  },
  //删除cookies
  delete: function (name) {
    let cval = this.get(name)
    if (cval!=null) {
      document.cookie = name + '=' + cval + ';expires=' + (new Date(0)).toGMTString()+';path=/'
    }
  }
}

var MyDynItem = {
    /**
     * 添加一条
     */
    add:function(obj,is_clone,callback){
      is_clone = is_clone || 0;
      var $item = $(obj).closest('.item');
      var $item_new = $item.clone();
      $item.after($item_new);
      if(!is_clone){
        $item_new.find('input').val('');
        $item_new.find('textarea').val('');
      }
      if(typeof(callback)=="function"){
        callback();
      }
    },
    /**
     * 删除一条
     */
    del:function(obj,setting){
      var defaults = {
        'delete_last':false,
        'dl_action':0,
      }
      var opt = $.extend(defaults,setting);
      var $item = $(obj).closest('.item');
      var $sib = $item.siblings('.item');
      if($sib.length < 1 && !opt.delete_last){
        if(opt.dl_action == 1){
          layer.msg('不能删除最后一项');
        }
        if(opt.dl_action == 2){
          $item.find('input').val('');
          $item.find('textarea').val('');
        }
        if(typeof(callback)=="function"){
          callback();
        }
        return false;
      }
      $item.fadeOut();
      setTimeout(function(){
        $item.remove();
      },400)
      if(typeof(callback)=="function"){
        callback();
      }
    },
    /**
     * 取得数据
     */
    getData:function(wrapper,fields,sp){
      var returnData = [];
      var spr = sp || '';
      $(wrapper).find('.item').each(function(index,item){
        var data = {};
        for(var i in fields){
          var field = fields[i];
          var $input = $(item).find('[name="'+field+'"]');
          var field_x = field;
          if($input.length > 0){
            if(spr){
              var f_arr = field.split(spr);
              if(f_arr.length > 1){
                field_x = f_arr[1];
              }
            }
            data[field_x] = $input.val();
          }
        }
        returnData.push(data);
      });
      return returnData;
    },


}