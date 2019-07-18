/**
 * 后台JS主入口
 */
  // layui.use('element', function(){
if(typeof(GV)=="undefined"){
  var GV = {};
}

GV['timezoneOffset'] = new Date().getTimezoneOffset();
GV['config'] = {
  url : {
    amapScript : 'http://webapi.amap.com/maps?v=1.4.6&key='
  }
}

if(typeof(layer) == 'undefined'){
  var layer = layui.layer;
}
var element =  layui.element;
var laydate = layui.laydate;
var form = layui.form;


function initLayuiTable(options){
  var defaults = {
    limit: 50,
    tabFilter:'listtable'
  }
  var opt = $.extend({}, defaults, options);
  layui.table.init(opt.tabFilter, opt);
  $(window).resize(function(event) {
    layui.table.init(opt.tabFilter, opt);
  });
}


////////////////////////////////////////////////////////

function admin_init(){
  myLang.init(); //初始化语言
  cRenderTips();
  /**
   * AJAX全局设置
   */
  $.ajaxSetup({
    type: "post",
    dataType: "json"
  });

  /**
  * 后台侧边菜单选中状态
  */
  $('.layui-nav-item').find('a').removeClass('layui-this');
  $('.layui-nav-tree').find('a[href*="' + GV.current_controller + '"]').parent().addClass('layui-this').parents('.layui-nav-item').addClass('layui-nav-itemed');

  /**
   * 通用单图上传
   */
  var layUpload = layui.upload;
  var uploadInst = layUpload.render({
    elem: '#upload-input' //绑定元素
    ,url: '/index.php/api/upload/upload' //上传接口
    ,done: function(res){
      //上传完毕回调
      if (res.error === 0) {
          document.getElementById('thumb').value = res.url;
      } else {
          layer.msg(res.desc);
      }
    }
    ,error: function(){
      //请求异常回调
    }
  });

  /**
   * 通用日期时间选择
   */
  $('.datetime').on('click', function () {
    laydate.render({
      elem: this
      ,type: 'datetime'
      // ,lang:'en'
      ,range: false //或 range: '~' 来自定义分割字符
    });
    /*  laydate({
          elem: this,
          istime: true,
          format: 'YYYY-MM-DD hh:mm:ss'
      })*/
  });

  $(".pagination li a ").click(function(){
    layer.load(2);
  })

  $("a[showloading]").click(function(){
    layer.load(2);
  })

  $("form[showloading]").submit(function(){
    layer.load(2);
    return;
  })
  

  /**
   * 通用表单提交(AJAX方式)
   */
  form.on('submit(*)', function (data) {
    var $form = $(data.form);
    var beforeSubmit = $form.attr('beforeSubmit');
    if(typeof(beforeSubmit) != "undefined"){
      console.log(typeof(eval(beforeSubmit)));
      if(typeof(eval(beforeSubmit)) == "function"){
        eval(beforeSubmit)(data);
      }
    }
    
    ajaxSubmit({
      url: data.form.action,
      dataType:'json',
      type: data.form.method,
      data: $form.serialize(),
      unrefresh: $form.data('unrefresh') ? $(data.form).data('unrefresh') : false,
      jump : $form.data('jump') ? $(data.form).data('jump') : "" ,
      jumpWin: $form.data('jump-target') == "parent" ? parent : null
    });
    return false;
  });

  /**
   * 通用批量处理（审核、取消审核、删除）
   */
  $(document).on('click',  '.ajax-action', function() {
  // $('.ajax-action').on('click', function () {
    var _action = $(this).data('action');
    layer.open({
      shade: false,
      title:myLang.r('tips'),
      content: myLang.r('are you sure you want to do this?'),
      btn: [myLang.r('confirm'), myLang.r('cancel')],
      yes: function (index) {
        $.ajax({
          url: _action,
          dataType:'json',
          data: $('.ajax-form').serialize(),
          success: function (res) {
            if (res.code === 0) {
              setTimeout(function () {
                if(res.url){
                  location.href = res.url;
                }else{
                  location.reload();
                }
              }, 1000);
            }
            layer.msg(res.desc);
          }
        });
        layer.close(index);
      }
    });

    return false;
  });

  /**
   * 通用全选
   */
  $(document).on('click',  '.check-all', function() {
  // $('.check-all').on('click', function () {
      $(this).parents('table').find('input[type="checkbox"]').prop('checked', $(this).prop('checked'));
  });

  /**
   * 通用删除
   */
  $(document).on('click',  '.ajax-delete', function() {
  // $('.ajax-delete').on('click', function () {
      var _href = $(this).attr('href');
      var content = $(this).data('hint') || myLang.r('please confirm whether to delete');
      layer.open({
        shade: false,
        title:myLang.r('warning'),
        content: content,
        btn: [myLang.r('yes'), myLang.r('no')],
        yes: function (index) {
          $.ajax({
            url: _href,
            dataType:'json',
            type: "get",
            success: function (res) {
              if (res.code === 0) {
                setTimeout(function () {
                  if(res.url){
                    location.href = res.url;
                  }else{
                    location.reload();
                  }
                }, 1000);
              }
              layer.msg(res.desc);
            }
          });
          layer.close(index);
        }
      });

      return false;
  });

  /**
   * 清除缓存
   */
  $(document).on('click',  '#clear-cache', function() {
  // $('#clear-cache').on('click', function () {
      var _url = $(this).data('url');
      if (_url !== 'undefined') {
          $.ajax({
              url: _url,
              dataType:'json',
              success: function (res) {
                  if (res.code === 0) {
                    setTimeout(function () {
                      if(res.url){
                        location.href = res.url;
                      }else{
                        location.reload();
                      }
                    }, 1000);
                  }
                  layer.msg(res.desc);
              }
          });
      }

      return false;
  });

  /**
   * 下拉按钮
   */
  $(document).on('click',  '.btn-drop > .drop-btn', function() {
  // $('#clear-cache').on('click', function () {
      var $wrapper = $(this).closest('.btn-drop');
      var $dropBox = $wrapper.find('.drop-box');

      if($dropBox.hasClass('show')){
        $dropBox.removeClass('show');
        $wrapper.removeClass('active');
      }else{
        $dropBox.addClass('show');
        $wrapper.addClass('active');
      }
      $dropBox.click(function(e){
        e.stopPropagation();
      })
      // $dropBox.find('a').click(function(e){
      //   e.stopPropagation();
      // })


      return false;
  });


  /**
   * 关闭drop
   */
  $(document).on('click', function(e) {
    $('.drop-box').removeClass('show')
  });

  /**
   * jump-select
   */
  $(document).on('change','select.select-jump', function(e) {
      var e=e||event;
      var $target = $(e.target);
      var href = $target.find('option:selected').attr('href');
      if(href){
        layer.load(2,{ shade: [0.2,'#fff']});
        location.href = href;
      }else{
        return false;
      }
  });


  cRenderTimes();
  var timezoneOffset  = new Date().getTimezoneOffset();
  MyCookies.set('timezoneOffset',timezoneOffset);
  $("input[name='timezone_offset'].J-local-timezoneOffset").val(GV['timezoneOffset']);

}  //end of admin_init


admin_init();
// })
