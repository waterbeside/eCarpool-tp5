function MyDept(setting){

    
    this.deptsData = null;
    this.$targetShowWrapper = null;
    this.$targetIDInput = null;
    this.ids = [];
    

    this.renderShowBox =  function(data,$targetShowWrapper,itemCallback){
      var _this = this;
      $targetShowWrapper = $targetShowWrapper ? $targetShowWrapper : this.$targetShowWrapper ;
      data = data || this.deptsData;
      
      $targetShowWrapper.html('')
      if(data){
        for(var id in data){
          if(id > 0){
            var itemHtml = _this.selectedTemplate(data[id]);
            var $item = $(itemHtml);
            $targetShowWrapper.append($item);
            if(typeof(itemCallback)=='function'){
              itemCallback(id,item[id]);
            }
          }
        }
      }
    }

    /**
     * 已选项item模板
     */
    this.selectedTemplate =  function(data){
      var html = '<div class="item my-tag-item" data-id="'+data.id+'" title="'+data.fullname+'"><span>'+data.name+'</span><a class="close" onclick="MyDept().closeItem()" ><i class="fa fa-close"></i></a></div>';
      return html;
    }

    /**
     * 关闭已选项功作
     */
    this.closeItem =  function(callback){
      var e = e || event
      var $target = $(e.target);
      var $item = $target.closest('.item');
      var id = $item.data('id');

      var deptsData = typeof(this.deptsData) == "object"  ? this.deptsData : {};
      if(deptsData && typeof(deptsData[id])!="undefined"){
        delete deptsData[id] ;
        this.deptsData = deptsData;
        this.rebuildDeptIds();
      }

      if(typeof(this.$targetIDInput)=='object' && this.$targetIDInput){
        this.$targetIDInput.val(this.ids.join(','));
      }else{
        var o_ids = $target.closest('form').find("input[name='region_id']").val();
        var o_ids_array = o_ids.split(',');
        var n_v = ''
        $(o_ids_array).each(function(index,item){
          if(item != id){
            n_v += n_v ? ',' :'';
            n_v += item;
          }
        })
        $target.closest('form').find("input[name='region_id']").val(n_v);
      }

      // var newDataString = JSON.stringify(deptsData);
      // MyCookies.set('department_selected_list',newDataString,600);

      $item.addClass('delete');
      setTimeout(function(){
        $item.remove();
      },400);
      if(typeof(callback)=='function'){
        callback(id,item);
      }
    }

    /**
     * 重建 datas里的id列表
     */
    this.rebuildDeptIds = function(){
      var deptsData = this.deptsData;
      this.ids = [];
      for(var id in deptsData){
        if(id){
          this.ids.push(id);
        }
      }
      return this.ids;
    }

    this.setDeptsData = function(data){
      this.deptsData = data;
      this.rebuildDeptIds();
    }

    this.getFieldsSet =  function(){
      var list = [];
      var datas = this.deptsData
      for(id in this.deptsData){
        var item = this.deptsData[id];
        for(key in item){
          if(typeof(list[key])=='undefined'){
            list[key] = [];
          }
          list[key].push(item[key]);
        }
      }
      return list;
    }

    if(typeof(setting)=="object"){
      if(typeof(setting.deptsData)=="object"){
        this.deptsData = setting.deptsData
        this.rebuildDeptIds();
      }
      if(typeof(setting.$targetIDInput)=="object"){
        this.$targetIDInput = setting.$targetIDInput
      }
      if(typeof(setting.$targetShowWrapper)=="object" || typeof(setting.$targetShowWrapper) == 'string'){
        $targetShowWrapper = typeof(setting.$targetShowWrapper) == 'string' ? $(setting.$targetShowWrapper) : setting.$targetShowWrapper;
        this.$targetShowWrapper = $targetShowWrapper
      }
    }
    

    
    return this;

}

