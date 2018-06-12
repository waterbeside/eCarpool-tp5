/**
 * 后台JS主入口
 */
  // layui.use('element', function(){
if(typeof(GV)=="undefined"){
  var GV = {};
}
GV.lockForm = false;

  var layer = layui.layer,
      // element = layui.element(),
      element =  layui.element
      laydate = layui.laydate,
      // form = layui.form();
      form = layui.form;

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


/**
 * 通过layer的iframe打开
 */
function openLayer(url,opt){
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
  layer.open(options);
}


function ajaxSubmit(setting){
  if(GV.lockForm){
    layer.msg('有表单正在提交，请稍候再试');
    return false;
  }
  GV.lockForm = true;
  var loading = layer.load(2,{ shade: [0.2,'#fff']});
  var defaults = {
    dataType:"json",
    type:"post",
    jump:"",
    unrefresh:false
  }
  var opt = $.extend({}, defaults, setting);
  $.ajax({
      url: opt.url,
      dataType:opt.dataType,
      type: opt.type,
      data: opt.data,
      success: function (res) {
        if (res.code === 0) {
          if(opt.unrefresh!=1 || opt.jump!=""){
            setTimeout(function () {
              if(opt.jump!=""){
                location.href = opt.jump;
              }else if(res.url){
                location.href = res.url;
              }else{
                location.reload();
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
        if(typeof(opt.error)=="function"){
          opt.error(jqXHR, textStatus, errorThrown);
        }
      },
      complete:function(){
        layer.close(loading);
        setTimeout(function(){
          GV.lockForm = false;
        },1000)
        if(typeof(opt.complete)=="function"){
          opt.complete();
        }
      }
  });
}

function admin_init(){
  /**
   * 通用单图上传
   */

  /*layui.upload({
      url: "/index.php/api/upload/upload",
      type: 'image',
      ext: 'jpg|png|gif|bmp',
      success: function (data) {
          if (data.error === 0) {
              document.getElementById('thumb').value = data.url;
          } else {
              layer.msg(data.message);
          }
      }
  });*/
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

  /**
   * 通用表单提交(AJAX方式)
   */
  form.on('submit(*)', function (data) {
    ajaxSubmit({
      url: data.form.action,
      dataType:'json',
      type: data.form.method,
      data: $(data.form).serialize(),
      unrefresh: $(data.form).data('unrefresh') ? $(data.form).data('unrefresh') : false,
      jump : $(data.form).data('jump') ? $(data.form).data('jump') : ""
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
          content: '确定执行此操作？',
          btn: ['确定', '取消'],
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
      layer.open({
          shade: false,
          content: '确定删除？',
          btn: ['确定', '取消'],
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
}

admin_init();
// })
