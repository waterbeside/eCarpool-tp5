if(typeof(GV)=="undefined"){
  var GV = {};
}
GV.lockForm = false;
if(typeof(layer) == 'undefined'){
  var layer = layui.layer;
}


function cPrefixO(num,length){ 
  return (Array(length).join('0')+num).slice(-length); 
}


function cFormatDate(date,fmt){
  var o = {
      "m+": date.getMonth() + 1, //月份
      "d+": date.getDate(), //日
      "h+": date.getHours(), //小时
      "i+": date.getMinutes(), //分
      "s+": date.getSeconds(), //秒
      "q+": Math.floor((date.getMonth() + 3) / 3), //季度
      "S": date.getMilliseconds() //毫秒
    };
    if (/(y+)/.test(fmt)) {
      fmt = fmt.replace(RegExp.$1, (date.getFullYear() + "").substr(4 - RegExp.$1.length));
    }
    for (var k in o)
      if (new RegExp("(" + k + ")").test(fmt))
          fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" + o[k]).substr(("" + o[k]).length)));
    return fmt;

}

function cRenderTimes(wrapper){
  wrapper = wrapper || null;
  if(wrapper){
    var $timesBoxs = $(wrapper).find(".J-times-format").not(".J-format-ok");
  }else{
    var $timesBoxs = $(".J-times-format").not(".J-format-ok");
  }
  $timesBoxs.each(function(index, el) {
    var formatStr = $(el).data('format');
        formatStr = formatStr ? formatStr : 'yyyy-mm-dd hh:ii:ss';
    var timestamp = parseInt($.trim($(el).text()));
    var  dateObject = new Date(timestamp);
    if(typeof(dateObject) == 'object' && !isNaN(dateObject.getDate())){
      var frm_time = cFormatDate(dateObject,formatStr);
      $(el).addClass('J-format-ok').html(frm_time);
    }
  });
}

//自动格式化时间戳
function cRenderTips(wrapper,type){
  wrapper = wrapper || null;
  type = type || 0;
  if(wrapper){
    var $tipsBoxs = $(wrapper).find("[data-tips]")
  }else{
    var $tipsBoxs = $("[data-tips]");
  }
  if(!type){
    $tipsBoxs = $("[data-tips]").not(".J-render-ok");
  }
  $tipsBoxs.each(function(index, el) {
    var $_this = $(this);
    $(this).addClass('J-render-ok')
    $(this).hover(function(){
      var tips = $_this.attr('data-tips');
      if(tips){
        var tipsPosition = $_this.data('tips-position') ;
        layer.tips(tips, this,{
          time:1000,
          tips:tipsPosition ? tipsPosition : 2,
        })
      }
    })
  });
}




/**
 * 动态加载JS
 * @param  {String}   url      URL
 * @param  {Function} callback 回调函数
 */
function cLoadScript(url, callback) {
  var script = document.createElement("script");
  script.type = "text/javascript";
  if(typeof(callback) != "undefined"){
    if (script.readyState) {
      script.onreadystatechange = function () {
        if (script.readyState == "loaded" || script.readyState == "complete") {
          script.onreadystatechange = null;
          callback();
        }
      };
    } else {
      script.onload = function () {
        callback();
      };
    }
  }
  script.src = url;
  document.body.appendChild(script);
}

function redirect(url,win) {
    var lct = typeof(win)!="undefined"  && win ? win.location : location;
    //console.log(lct);
    lct.href = url;
}

function reload(win) {
    var lct = typeof(win)!="undefined" && win ? win.location : location;
    //console.log(lct);
    lct.reload();
}



/**上传单图 */
function cRenderUploadBtn(setting){
  var defautls = {
    url: "/admin/Uploader/images",
    data:{"module":"admin/common/thumb"},
    inputName:'thumb',
    text_uploadBtn :'上传图片',
    defaultImg:'',
    relative: false, //是否使用相对路径
    baseUrl: '', //使用相对路径时，用于显示图片的图片路径
    inputReadOnly: false,

  }
  var opt = $.extend({},defautls,setting);
  
  var wrapper = opt.wrapper;
  var $wrapper = $(wrapper);
  var defaultImg = opt.defaultImg
  var showDefaultImg = opt.showDefaultImg
  defaultImg = $.trim((defaultImg || $wrapper.data('default')) || '');
  showDefaultImg = (showDefaultImg || $wrapper.data('show-default')) || '';
  
  var showDefaultImg = showDefaultImg || (!opt.relative || defaultImg.indexOf('http') === 0 ? defaultImg : opt.baseUrl + defaultImg );

  var isReadOnly_html = opt.inputReadOnly ? 'readonly="readonly"' : '';
  var html_input = '<input type="text" name="'+opt.inputName+'" value="'+defaultImg+'" '+ isReadOnly_html +' class="layui-input j-upload-input">'
  var html_btn = '<a  class="amain-uploadImgBtn j-upload-btn" id="">\
      <img class="layui-upload-img j-upload-img" src="'+showDefaultImg+'" >\
      <div class="text">\
        <i class="fa fa-upload"></i>'+opt.text_uploadBtn+'\
      </div>\
    </a>';
  var html_tipsText = '<p class="j-upload-tips"></p>'
  $wrapper.append(html_input+html_btn+html_tipsText);
  // return false;

  var $thumbInput = $wrapper.find('.j-upload-input');
  var $preViewImg = $wrapper.find('.j-upload-img');
  var $tipsTextBox = $wrapper.find('.j-upload-tips');
  $thumbInput.keyup(function(event) {
    /* Act on the event */
    var thumbPath = $thumbInput.val();
    $preViewImg.attr('src', thumbPath);
  });

  var uploadInst = layui.upload.render({
    elem: wrapper+' '+'.j-upload-btn'
    ,url: opt.url
    ,data: opt.data
    ,before: function(obj){
      //预读本地文件示例，不支持ie8
      obj.preview(function(index, file, result){
        // $('#item-thumb').attr('src', result); //图片链接（base64）
      });
      if(typeof(opt.before)=="function"){
        opt.before(obj);
      }
    }
    ,done: function(res){
      console.log(res);
      //如果上传失败
      if(res.code > 0){
        return layer.msg(myLang.r('Upload failed'));
      }
      if(res.code===0){
        layer.msg(myLang.r('Upload successfully'));
        $preViewImg.attr('src', res.data.img_url); //图片链接（base64）
        var inputVal = opt.relative ? res.data.filepath : res.data.img_url;
        $thumbInput.val(inputVal);
        $tipsTextBox.html('');

      }else{
        layer.msg(res.desc);
      }
      if(typeof(opt.done)=="function"){
        opt.done(res);
      }
      //上传成功
    }
    ,error: function(err){
      //演示失败状态，并实现重传
      var html = '<span style="color: #FF5722;">'+myLang.r('Upload failed, please try again later')+'</span>';
      // html += '<a class="layui-btn layui-btn-xs reUpload">重试</a>';
      $tipsTextBox.html(html);
      if(typeof(opt.error)=="function"){
        opt.error(err);
      }
    }
  });
  
  return uploadInst;

}



/**
 * 通过layer的iframe打开
 */
function openLayer(url,opt,oe){
  var e = oe || e || event;
  var openLength = 0
  var $target = $(e.target);
  GV['lastOpenLayer'] = [];
  GV['lastOpenLayer']['target'] = e.target;

  var $targetWrapper = $target.attr('onclick') ? $target : $target.closest('[onclick]');
  console.log($targetWrapper);
  
  var defaults = {
    type: 2,
    area: ['700px', '90%'],
    fixed: true,
    maxmin: true,
  }
  var opt_s = {};
  if(typeof(opt)=="string"){
    defaults.title = opt;
  }else{
    opt_s = opt;
  }
  if(typeof(url)=="object"){
    opt_s = url;
  }else if(typeof(url)=="string"){
    defaults.content = url;
  }
  var options = $.extend(true, defaults, opt_s);
  if(typeof(options.urlParam)=='undefined'){
    // var paramStr = $targetWrapper.data('paramstr');
    // paramObject = typeof(paramStr)=="object"  ? paramStr : false;
    var paramStr = $targetWrapper.attr('data-paramstr');
    var paramObject = paramStr ? JSON.parse(paramStr) : false;
  }else{
    var paramObject = typeof(options.urlParam)=='object' ? options.urlParam : false ;
  }
  var paramFormatStr = '';
  if(typeof(paramObject)=='object'){
    paramFormatStr = options.content.indexOf('?') == -1 ? '?' : ''
    for(k in paramObject){
      paramFormatStr += paramFormatStr == "?"  ? '' : '&';
      paramFormatStr += k+'='+paramObject[k];
    }
  }
  options.content += paramFormatStr;
  GV['lastOpenLayer']['layer'] = layer.open(options);
}



/**
 * 通过layer的iframe打开
 */
function openParentLayer(url,opt){
  var defaults = {
    type: 2,
    area: ['700px', '90%'],
    fixed: true,
    maxmin: true,
  }
  var opt_s = {};
  if(typeof(opt)=="string"){
    defaults.title = opt;
  }else{
    opt_s = opt;
  }
  if(typeof(url)=="object"){
    opt_s = url;
  }else if(typeof(url)=="string"){
    defaults.content = url;
  }
  var options = $.extend(true, defaults, opt_s);
  if(parent){
    parent.layer.open(options);
  }else{
    layer.open(options);
  }
}


function ajaxSubmit(setting){
  var defaults = {
    dataType:"json",
    type:"post",
    jump:"",
    unrefresh:false,
    jumpWin:null,
    loadingLock:true,
  }
  var opt = $.extend({}, defaults, setting);
  if(opt.loadingLock && GV.lockForm){
    layer.msg(myLang.r('a form is being submitted, please try again later'));
    return false;
  }
  if (opt.loadingLock) {
    GV.lockForm = true;
  }
  var loading = layer.load(2,{ shade: [0.2,'#fff']});
  
  $.ajax({
      url: opt.url,
      dataType:opt.dataType,
      type: opt.type,
      data: opt.data,
      success: function (res) {
        if(typeof(res)=='undefined'){
          if (opt.loadingLock) {
            GV.lockForm = false;
          }
          layer.msg(myLang.r('network error, please try again later'));
          return false;
        }
        if (res.code === 0) {
          if(opt.unrefresh!=1 || opt.jump!=""){
            setTimeout(function () {
              if(opt.jump!=""){
                redirect(opt.jump,opt.jumpWin);
              }else if(res.url){
                redirect(res.url,opt.jumpWin);
              }else if(res.extra.url){
                redirect(res.extra.url,opt.jumpWin);
              }else{
                reload(opt.jumpWin);
              }
            }, 400);
          }
        }
        layer.msg(res.desc);
        if(typeof(opt.success)=="function"){
          opt.success(res);
        }
      },
      error:function(jqXHR, textStatus, errorThrown){
        layer.msg(myLang.r('network error, please try again later'));
        if(typeof(opt.error)=="function"){
          opt.error(jqXHR, textStatus, errorThrown);
        }
      },
      complete:function(){
        layer.close(loading);
        setTimeout(function(){
          if (opt.loadingLock) {
            GV.lockForm = false;
          }
        },1000)
        if(typeof(opt.complete)=="function"){
          opt.complete();
        }
      }
  });
}


/**
 * [cCetPicFromContent]
 * 获取文本中首张图片地址
 * @param  {String} content [富文本]
 * @return {String}          [图片路径]
 */
function cGetPicFromContent(content){
  var $content = $(content);
  var $imgs = $content.find('img');
  var $img = $imgs.eq(0);
  var url = $img.attr('src');
  return url ? url : '';
}

/**
 * 为is_delete行添加样式
 * @param {String} pageClassName 父级盒子className
 */
function cSetLayTableDelRowStyle(pageClassName) {
  var $tableWrapper = $("." + pageClassName + " [lay-filter=LAY-table-1] .layui-table-box");
  var $tableWrapper_main = $tableWrapper.find(".layui-table-main");
  var $tr = $tableWrapper_main.find('tr');
  var $tableWrapper_l = $tableWrapper.find(".layui-table-fixed .layui-table-body");
  $tr.each(function(index, el) {
      if($(el).find("[data-field=id]").find('.is_delete').length > 0){
          $(el).addClass('delete');
          $(el).find('.ajax-delete').hide();
          $tableWrapper_l.find('tr').eq(index).addClass('delete')
      }
  });
}