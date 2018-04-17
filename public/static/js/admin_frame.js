/**
 * 后台JS主入口
 */

  layui.use(['element', 'layer','jquery'], function(){
    var element = layui.element,
        layer = layui.layer,
        $ = layui.jquery;
        console.log($);

        //监听左侧的二级导航点击事件
    element.on('nav(aside)', function(elem) {
        var name = $(elem).text();
        var href = $(elem).data('href');
        var  iid = $(elem).data('id');
        console.log(iid)
        if(!iid || !href){
          return false;
        }

        var iframes = $('iframe');
        var iframesArr = $.makeArray(iframes);
        var arr = [];
        for(var i in iframesArr){
            arr.push($(iframesArr[i]).attr('name'));
        }

        if(arr.length >= 18){
            layer.msg('当前导航最多同时存在18个,请先关闭部分选项卡');
        }else{
            var index = $.inArray(name, arr);
            if( index == -1){
                var iframe = '<iframe src="'+href+'" name="'+name+'"></iframe>';
                element.tabAdd('tab', {
                    title: name
                    ,content: iframe
                    ,id: iid
                });

                var index = $('.layui-tab-title li').length;
                // console.log(index)
                element.tabChange('tab', iid);
            }else{
                element.tabChange('tab', iid);
            }
        }
    });

    $(document).on('contextmenu','.layui-tab-title li',function(){
        return false;
    });
    $(document).on('mousedown','.layui-tab-title li',function(e){
        if(e.which === 3){
            var a = $.makeArray($('.layui-tab-title li'));
            var b = $.makeArray($(this));
            var index = $.inArray(b[0], a);

            var left = e.clientX;
            var isNow = $(this).hasClass('layui-this');
            $('.tabOperation').remove();
            var html = returntabOperation(left,isNow,index);
            $('.layui-tab-content').append(html);
            return false;
        }else if (e.which === 1) {
            if($('.tabOperation').length !== 0){
                $('.tabOperation').remove();
            }
        }
    });

    $(".aframe-sidebox .layui-nav-item a").each(function(index, el) {
      var $_this = $(this);
      $(this).hover(function(){
        var msg = $(this).text();
        layer.tips(msg, this,{time:1000})
      })
    });

    //刷新当前页
    $(document).on('click','[data-target=refreshCurrent]',function(){

        var src = $('.layui-show iframe').attr('src');
        $('.layui-show iframe').attr('src',src);
        $('.tabOperation').remove();
    });

    //显示当前页
    $(document).on('click','[data-target=showCurrent]',function(){
        var iid = $('.layui-tab-title > li.layui-this').attr('lay-id');
        element.tabChange('tab', iid);
    });

    //关闭当前页
    $(document).on('click','[data-target=closeCurrent]',function(){
      var iid = $('.layui-tab-title > li.layui-this').attr('lay-id');
      element.tabDelete('tab', iid);

    });

    //关闭其他页
    $(document).on('click','[data-target=closeOther]',function(){
        var len = $('.layui-tab-title li').length;
        $('.layui-tab-title > li').each(function(){
          var iid = $(this).attr('lay-id');
          if(!$(this).hasClass('layui-this')){
            element.tabDelete('tab', iid);
          }
        })
    });

    $(document).on('click','[data-target=showFull]',function(){
        var left = parseInt($('.aframe-con').css('left'));
        if(left<70){
            $('.aframe-con').css('left',"200px");
            $('.aframe-sidebox').removeClass('fold')
        }else{
            $('.aframe-con').css('left','46px');
            $('.aframe-sidebox').addClass('fold')



        }
    });

    function showTabToolOptions(type){
      type = type || 0;
      $obox = $(".aframe-tab-tool-options");
      if(type){
        $obox.addClass('layui-show');
      }else{
        $obox.removeClass('layui-show');
      }

    }
    $(document).on('click','.aframe-tab-tool',function(){
      var $obox = $(".aframe-tab-tool-options");
      if($obox.hasClass('layui-show')){
        showTabToolOptions(0);
      }else{
        showTabToolOptions(1);
      }
      // showTabToolOptions();
    });

    $(document).on('click',function(e){
      // console.log(e)
      if(!$(e.target).hasClass('aframe-tab-tool-options') && !$(e.target).parents("aframe-tab-tool-options").length > 0 && !$(e.target).hasClass('aframe-tab-tool')){
        showTabToolOptions(0)
      }
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


})
