/**
 * 获取省份
 */
function get_province(){
    var url = '/index.php?m=Admin&c=Api&a=getRegion&level=1&parent_id=0';
    $.ajax({
        type : "GET",
        url  : url,
        error: function(request) {
            alert("服务器繁忙, 请联系管理员!");
            return;
        },
        success: function(v) {
            v = '<option value="0">选择省份</option>'+ v;
            $('#province').empty().html(v);
        }
    });
}
//根据key值返回spec_goods_price记录
function search_spec_goods_price(spec_key)
{
    var spec_goods_price_item = [];
    $.each(spec_goods_price, function (i, o) {
        if (o.key == spec_key) {
            spec_goods_price_item = o;
        }
    })
    return spec_goods_price_item;
}


/**
 * 获取城市
 * @param t  省份select对象
 * @param city
 * @param district
 * @param twon
 */
function get_city(t,city,district,twon){
    var parent_id = $(t).val();
    if(!parent_id > 0){
        return;
    }
    var city_id = 'city';
    if(typeof(city) != 'undefined' && city != ''){
        city_id = city;
    }
    var district_id = 'district';
    if(typeof(district) != 'undefined' && district != ''){
        district_id = district;
    }
    var twon_id = 'twon';
    if(typeof(twon) != 'undefined' && twon != ''){
        twon_id = twon;
    }
    $('#'+district_id).empty().css('display','none');
    $('#'+twon_id).empty().css('display','none');
    var url = '/index.php?m=Home&c=Api&a=getRegion&level=2&parent_id='+ parent_id;
    $.ajax({
        type : "GET",
        url  : url,
        error: function(request) {
            alert("服务器繁忙, 请联系管理员!");
            return;
        },
        success: function(v) {
            v = '<option value="0">选择城市</option>'+ v;
            $('#'+city_id).empty().html(v);
        }
    });
}
/**
 * 获取地区
 * @param t  城市select对象
 * @param district
 * @param twon
 */
function get_area(t,district,twon){
    var parent_id = $(t).val();
    if(!parent_id > 0){
        return;
    }
    var district_id = 'district';
    if(typeof(district) != 'undefined' && district != ''){
        district_id = district;
    }
    var twon_id = 'twon';
    if(typeof(twon) != 'undefined' && twon != ''){
        twon_id = twon;
    }
    $('#'+district_id).empty().css('display','inline');
    $('#'+twon_id).empty().css('display','none');
    var url = '/index.php?m=Home&c=Api&a=getRegion&level=3&parent_id='+ parent_id;
    $.ajax({
        type : "GET",
        url  : url,
        error: function(request) {
            alert("服务器繁忙, 请联系管理员!");
            return;
        },
        success: function(v) {
            v = '<option>选择区域</option>'+ v;
            $('#'+district_id).empty().html(v);
        }
    });
}

// 获取最后一级乡镇
function get_twon(obj,twon){
    var twon_id = 'twon';
    if(typeof(twon) != 'undefined' && twon != ''){
        twon_id = twon;
    }
    var parent_id = $(obj).val();
    var url = '/index.php?m=Home&c=Api&a=getTwon&parent_id='+ parent_id;
    $.ajax({
        type : "GET",
        url  : url,
        success: function(res) {
            if(parseInt(res) == 0){
                $('#'+twon_id).empty().css('display','none');
            }else{
                $('#'+twon_id).css('display','inline').empty().html(res);
            }
        }
    });
}


/**
 * 输入为空检查
 * @param name '#id' '.id'  (name模式直接写名称)
 * @param type 类型  0 默认是id或者class方式 1 name='X'模式
 */
function is_empty(name,type){
    if(type == 1){
        if($('input[name="'+name+'"]').val() == ''){
            return true;
        }
    }else{
        if($(name).val() == ''){
            return true;
        }
    }
    return false;
}

/**
 * 邮箱格式判断
 * @param str
 */
function checkEmail(str){
    var reg = /^[a-z0-9]([a-z0-9\\.]*[-_]{0,4}?[a-z0-9-_\\.]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+([\.][\w_-]+){1,5}$/i;
    if(reg.test(str)){
        return true;
    }else{
        return false;
    }
}
/**
 * 手机号码格式判断
 * @param tel
 * @returns {boolean}
 */
function checkMobile(tel) {
    //var reg = /(^1[3|4|5|7|8][0-9]{9}$)/;
    var reg = /^1[0-9]{10}$/;
    if (reg.test(tel)) {
        return true;
    }else{
        return false;
    };
}

/**
 * 固定电话号码判断
 * @param tel
 * @returns {boolean}
 */
function checkTelphone(tel){
    var reg = /^((0\d{2,3})-)(\d{7,8})(-(\d{3,}))?$/;
    if(reg.test(tel)){
        return true;
    }else{
        return false;
    }
}

/**
 * 固定电话号码判断
 * @param tel
 * @returns {boolean}
 */
function checkTelphones(tel){
    var reg = /^(([0-9]\d{2,3})-)(\d{7,8})(-(\d{3,}))?$/;
    if(reg.test(tel)){
        return true;
    }else{
        return false;
    }
}
/*
 * 上传图片 后台专用
 * @access  public
 * @null int 一次上传图片张图
 * @elementid string 上传成功后返回路径插入指定ID元素内
 * @path  string 指定上传保存文件夹,默认存在public/upload/temp/目录
 * @callback string  回调函数(单张图片返回保存路径字符串，多张则为路径数组 )
 */
function GetUploadify(num,elementid,path,callback,fileType)
{	   	
	var upurl ='/index.php?m=Admin&c=Uploadify&a=upload&num='+num+'&input='+elementid+'&path='+path+'&func='+callback+'&fileType='+fileType;
    var title = '上传图片';
    if(fileType == 'Flash'){
        title = '上传视频';
    }
    layer.open({
        type: 2,
        title: title,
        shadeClose: true,
        shade: false,
        maxmin: true, //开启最大化最小化按钮
        area: ['50%', '60%'],
        content: upurl
     });
}

/**
 * 海报专用
 */
function GetUploadifyPoster(num,elementid,path,callback,fileType)
{
    var upurl ='/index.php?m=Admin&c=Uploadify&a=poster_upload&num='+num+'&input='+elementid+'&path='+path+'&func='+callback+'&fileType='+fileType;
    var title = '上传图片';
    if(fileType == 'Flash'){
        title = '上传视频';
    }
    layer.open({
        type: 2,
        title: title,
        shadeClose: true,
        shade: false,
        maxmin: true, //开启最大化最小化按钮
        area: ['50%', '60%'],
        content: upurl
    });
}


/*
 * 上传图片 前台专用
 * @access  public
 * @null int 一次上传图片张图
 * @elementid string 上传成功后返回路径插入指定ID元素内
 * @path  string 指定上传保存文件夹,默认存在public/upload/temp/目录
 * @callback string  回调函数(单张图片返回保存路径字符串，多张则为路径数组 )
 */
function GetUploadify2(num,elementid,path,callback)
{	   	
	var upurl ='/index.php?m=Home&c=Uploadify&a=upload&num='+num+'&input='+elementid+'&path='+path+'&func='+callback;
    layer.open({
        type: 2,
        title: '上传图片',
        shadeClose: true,
        shade: false,
        maxmin: true, //开启最大化最小化按钮
        area: ['50%', '60%'],
        content: upurl
     });
}
/*
 * 删除组图input
 * @access   public
 * @val  string  删除的图片input
 */
function ClearPicArr(val)
{
	$("li[rel='"+ val +"']").remove();
	$.get(
		"{:U('Admin/Uploadify/delupload')}",{action:"del", filename:val},function(){}
	);
}
/*
 * 删除组图input
 * @access   public
 * @val  string  删除的图片input
 */
function ClearPicArr2(val)
{
    $("li[rel='"+ val +"']").remove();
    $.get(
        "{:U('Home/Uploadify/delupload')}",{action:"del", filename:val},function(){}
    );
}
	
// 获取活动剩余天数 小时 分钟
//倒计时js代码精确到时分秒，使用方法：注意 var EndTime= new Date('2013/05/1 10:00:00'); //截止时间 这一句，特别是 '2013/05/1 10:00:00' 这个js日期格式一定要注意，否则在IE6、7下工作计算不正确哦。
//js代码如下：
function GetRTime(end_time){
      // var EndTime= new Date('2016/05/1 10:00:00'); //截止时间 前端路上 http://www.51xuediannao.com/qd63/
	   var EndTime= new Date(end_time); //截止时间 前端路上 http://www.51xuediannao.com/qd63/
       var NowTime = new Date();
       var t =EndTime.getTime() - NowTime.getTime();
       /*var d=Math.floor(t/1000/60/60/24);
       t-=d*(1000*60*60*24);
       var h=Math.floor(t/1000/60/60);
       t-=h*60*60*1000;
       var m=Math.floor(t/1000/60);
       t-=m*60*1000;
       var s=Math.floor(t/1000);*/

       var d=Math.floor(t/1000/60/60/24);
       var h=Math.floor(t/1000/60/60%24);
       var m=Math.floor(t/1000/60%60);
       var s=Math.floor(t/1000%60);
	   if(t >= 0){
           return d + '天' + h + '小时' + m + '分' +s+'秒';
       }	else{
           return '已结束';
       }
   }
   
   
/**
 * 获取多级联动的商品分类
 */
function get_category(id,next,select_id){
    if(id == 0){
        var html = "<option value='0'>请选择商品分类</option>";
        $('#'+next).empty().html(html);
        return false;
    }
    $.ajax({
        type : "GET",
        url  : '/index.php?m=admin&c=goods&a=get_category&parent_id='+ id,
        dataType:'json',
        success: function(data) {
            var html = "<option value='0'>请选择商品分类</option>";
            if(data.status == 1){
                for (var i=0 ;i<data.result.length;i++){
                    html+= "<option value='"+data.result[i].id+"'>"+data.result[i].name+"</option>";
                }
            }
            $('#'+next).empty().html(html);
            (select_id > 0) && $('#'+next).val(select_id);//默认选中
        }
    });
}

// 读取 cookie
function getCookie(c_name)
{
	if (document.cookie.length>0)
	{
	  c_start = document.cookie.indexOf(c_name + "=")
	  if (c_start!=-1)
	  { 
	    c_start=c_start + c_name.length+1 
	    c_end=document.cookie.indexOf(";",c_start)
	    if (c_end==-1) c_end=document.cookie.length
	    	return unescape(document.cookie.substring(c_start,c_end))
	  } 
	}
	return "";
}

function setCookies(name, value, time)
{
	var cookieString = name + "=" + escape(value) + ";";
	if (time != 0) {
		var Times = new Date();
		Times.setTime(Times.getTime() + time);
		cookieString += "expires="+Times.toGMTString()+";"
	}
	document.cookie = cookieString+"path=/";
}
function delCookie(name){
    var exp=new Date();
    exp.setTime(exp.getTime()-1);
    var cval=getCookie(name);
    if(cval!=null){
        document.cookie=name+"="+cval+";expires="+exp.toGMTString() +"path=/";
    }
}

/**
* 获取地址栏的推荐人id 写入cookie
* 使用这个方法必须先导入 jqueryUrlGet.js
*/
function set_first_leader()
{ 
	 //获取地址栏 分销推广链接id 将推荐人id 存入cookie
	 var first_leader = GetUrlParams("first_leader");
	 if(!(first_leader > 0)){
		 first_leader = GetFirstLeaderByMode('first_leader/');
    	 if(first_leader == -1){
    		 first_leader = GetFirstLeaderByMode('first_leader=');
    	 } 
	 }
	 // 将推荐人id 存入cookie	
	 if(first_leader > 0){
		 setCookies('first_leader', first_leader);
	 }
}

function GetFirstLeaderByMode(mode){
  	 var req_url = window.location.href;
 	 var regexp = /[0-9]*/;
  	 var split_str = req_url.split(mode); 
  	 if(split_str.length < 2){
  		 return -1;
  	 }
  	 var match_result = split_str[1].match(regexp)
  	 return match_result[0];
}

function GetUrlParams(name)
{
     var reg = new RegExp("(^|&)"+ name +"=([^&]*)(&|$)");
     var r = window.location.search.substr(1).match(reg);
     if(r!=null)return  unescape(r[2]); return null;
}


function layConfirm(msg , callback){
	layer.confirm(msg, {
		  btn: ['确定','取消'] //按钮
		}, function(){
			callback();
			layer.closeAll();
		}, function(index){
			layer.close(index);
			return false;// 取消
		}
	);
}

function isMobile(){
	return "yes";
}

// 判断是否手机浏览器
function isMobileBrowser()
{
    var sUserAgent = navigator.userAgent.toLowerCase();    
    var bIsIpad = sUserAgent.match(/ipad/i) == "ipad";    
    var bIsIphoneOs = sUserAgent.match(/iphone os/i) == "iphone os";    
    var bIsMidp = sUserAgent.match(/midp/i) == "midp";    
    var bIsUc7 = sUserAgent.match(/rv:1.2.3.4/i) == "rv:1.2.3.4";    
    var bIsUc = sUserAgent.match(/ucweb/i) == "ucweb";    
    var bIsAndroid = sUserAgent.match(/android/i) == "android";    
    var bIsCE = sUserAgent.match(/windows ce/i) == "windows ce";    
    var bIsWM = sUserAgent.match(/windows mobile/i) == "windows mobile";    
    if (bIsIpad || bIsIphoneOs || bIsMidp || bIsUc7 || bIsUc || bIsAndroid || bIsCE || bIsWM ){    
        return true;
    }else 
	    return false;
}

function getCookieByName(name) {
    var start = document.cookie.indexOf(name + "=");
    var len = start + name.length + 1;
    if ((!start) && (name != document.cookie.substring(0, name.length))) {
        return null;
    }
    if (start == -1)
        return null;
    var end = document.cookie.indexOf(';', len);
    if (end == -1)
        end = document.cookie.length;
    return unescape(document.cookie.substring(len, end));
}
function showErrorMsg(msg){
    // layer.msg(msg, {icon: 2});
    layer.open({content: msg, time: 2});
}
//关闭页面
function CloseWebPage(){
    if (navigator.userAgent.indexOf("MSIE") > 0) {
        if (navigator.userAgent.indexOf("MSIE 6.0") > 0) {
            window.opener = null;
            window.close();
        } else {
            window.open('', '_top');
            window.top.close();
        }
    }
    else if (navigator.userAgent.indexOf("Firefox") > -1 || navigator.userAgent.indexOf("Chrome") > -1) {
       // window.location.href = 'about:blank';
        window.open('', '_top');
        window.top.close();
    } else {
        window.opener = null;
        window.open('', '_self', '');
        window.close();
    }
}

//时间戳转换
function add0(m){return m<10?'0'+m:m }
function  formatDate(now)   {
    var time = new Date(now);
    var y = time.getFullYear();
    var m = time.getMonth()+1;
    var d = time.getDate();
    var h = time.getHours();
    var mm = time.getMinutes();
    var s = time.getSeconds();
    return y+'/'+add0(m)+'/'+add0(d)+' '+add0(h)+':'+add0(mm)+':'+add0(s);
}
function round(x, num){
    return Math.round(x * Math.pow(10, num)) / Math.pow(10, num) ;
}
// 校验组织机构代码
function orgcodevalidate(value) {
    if (value != "") {
        var part1 = value.substring(0, 8);
        var part2 = value.substring(value.length - 1, 1);
        var ws = [3, 7, 9, 10, 5, 8, 4, 2];
        var str = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        var reg = /^([0-9A-Z]){8}$/;
        if (!reg.test(part1)) {
            return true
        }
        var sum = 0;
        for (var i = 0; i < 8; i++) {
            sum += str.indexOf(part1.charAt(i)) * ws[i];
        }
        var C9 = 11 - (sum % 11);
        var YC9 = part2 + '';
        if (C9 == 11) {
            C9 = '0';
        } else if (C9 == 10) {
            C9 = 'X';
        } else {
            C9 = C9 + '';
        }
        return YC9 != C9;
    }
}
// 校验地址码
function checkAddressCode(addressCode) {
    var provinceAndCitys = {
        11: "北京",
        12: "天津",
        13: "河北",
        14: "山西",
        15: "内蒙古",
        21: "辽宁",
        22: "吉林",
        23: "黑龙江",
        31: "上海",
        32: "江苏",
        33: "浙江",
        34: "安徽",
        35: "福建",
        36: "江西",
        37: "山东",
        41: "河南",
        42: "湖北",
        43: "湖南",
        44: "广东",
        45: "广西",
        46: "海南",
        50: "重庆",
        51: "四川",
        52: "贵州",
        53: "云南",
        54: "西藏",
        61: "陕西",
        62: "甘肃",
        63: "青海",
        64: "宁夏",
        65: "新疆",
        71: "台湾",
        81: "香港",
        82: "澳门",
        91: "国外"
    };
    var check = /^[1-9]\d{5}$/.test(addressCode);
    if (!check) return false;
    if (provinceAndCitys[parseInt(addressCode.substring(0, 2))]) {
        return true;
    } else {
        return false;
    }
}

/**
 * 地区选择控件
 * @param province_id
 * @param city_id
 * @param district_id
 * @param town_id
 * @param province_select
 * @param city_select
 * @param district_select
 * @param town_select
 */
function widget_area(province_id, city_id, district_id, town_id, province_select, city_select, district_select, town_select) {
    var url = '/index.php?m=Home&c=Api&a=area';
    $.ajax({
        type: "POST",
        url: url,
        data: {province_id: province_id, city_id: city_id, district_id: district_id},
        dataType: 'json',
        success: function (data) {
            if (data.status == 1) {
                var province_list_option_html = '<option value="0">请选择</option>';
                var city_list_option_html = '<option value="0">请选择</option>';
                var district_list_option_html = '<option value="0">请选择</option>';
                var town_list_option_html = '<option value="0">请选择</option>';
                $.each(data.result.province_list, function (n, value) {
                    province_list_option_html += '<option value="' + value.id + '">' + value.name + '</option>';
                });
                $('#' + province_select).html(province_list_option_html).val(province_id);
                $.each(data.result.city_list, function (n, value) {
                    city_list_option_html += '<option value="' + value.id + '">' + value.name + '</option>';
                });
                $('#' + city_select).html(city_list_option_html).val(city_id);
                $.each(data.result.district_list, function (n, value) {
                    district_list_option_html += '<option value="' + value.id + '">' + value.name + '</option>';
                });
                $('#' + district_select).html(district_list_option_html).val(district_id);
                $.each(data.result.town_list, function (n, value) {
                    town_list_option_html += '<option value="' + value.id + '">' + value.name + '</option>';
                });
                $('#' + town_select).html(town_list_option_html).val(town_id);
                if(data.result.town_list.length > 0){
                    $('#' + town_select).show();
                }else{
                    $('#' + town_select).hide();
                }

            } else {
                console.log(data);
            }
        }
    });
}
//小时分钟秒的后尾补零
function checkTime(i){
    if (i<10){
        i = "0" + i;
    }
    return i;
}
//将时间戳转成字符串
function time_format(timestamp)
{
    var d = new Date(timestamp * 1000);    //根据时间戳生成的时间对象
    return (d.getFullYear()) + "-" + (d.getMonth() + 1) + "-" + (d.getDate()) + " " + (checkTime(d.getHours())) + ":" + (checkTime(d.getMinutes()));
}

//局部搜索条件验证表单提交
function clickSearch(obj,form){
    var html = "<input type='hidden' name='is_search'  value='1'>"
    $(obj).after(html);
    $('#'+form).submit();
}