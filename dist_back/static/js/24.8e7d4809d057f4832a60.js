webpackJsonp([24],{"0i1J":function(t,e){},"m/gL":function(t,e,a){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var s=a("Xxa5"),i=a.n(s),n=a("exGp"),r=a.n(n),o=(a("9++/"),a("QhyB")),c=a("D6FL"),l={data:function(){return{page:1,list:[],finished:!1,loading:!1,count:""}},components:{vanList:o.a},created:function(){},methods:{onLoad:function(){var t=this;return r()(i.a.mark(function e(){var a,s;return i.a.wrap(function(e){for(;;)switch(e.prev=e.next){case 0:if(a=JSON.parse(localStorage.getItem("shebei_arr")),!t.loading){e.next=11;break}return e.next=4,Object(c.I)({arras:a.join(","),page:t.page,type:t.$route.query.type});case 4:s=e.sent,console.log(s.data),t.count=s.data.count,1==t.page?t.list=s.data.list:t.list=t.list.concat(s.data.list),t.loading=!1,t.page++,t.list.length>=t.count&&(t.finished=!0);case 11:case"end":return e.stop()}},e,t)}))()}},mounted:function(){}},u={render:function(){var t=this,e=t.$createElement,a=t._self._c||e;return a("div",{attrs:{id:"box2"}},[a("div",{staticClass:"top"},[1==t.$route.query.type?a("div",{staticClass:"f20"},[t._v("今日订单")]):t._e(),t._v(" "),2==t.$route.query.type?a("div",{staticClass:"f20"},[t._v("今日收益")]):t._e(),t._v(" "),3==t.$route.query.type?a("div",{staticClass:"f20"},[t._v("昨日收益")]):t._e(),t._v(" "),4==t.$route.query.type?a("div",{staticClass:"f20"},[t._v("近七天收益")]):t._e(),t._v(" "),5==t.$route.query.type?a("div",{staticClass:"f20"},[t._v("近一月收益")]):t._e(),t._v(" "),6==t.$route.query.type?a("div",{staticClass:"f20"},[t._v("今年收益")]):t._e()]),t._v(" "),a("van-list",{attrs:{finished:t.finished,"finished-text":"没有更多了"},on:{load:t.onLoad},model:{value:t.loading,callback:function(e){t.loading=e},expression:"loading"}},[a("div",{staticClass:"list"},t._l(t.list,function(e,s){return a("div",{key:s,staticClass:"bbg"},[a("div",{staticClass:"item"},[a("span",[t._v("酒店名称："+t._s(e.hotel_name))]),t._v(" "),a("span",[t._v(t._s(e.pay_price))])]),t._v(" "),a("div",{staticClass:"item"},[a("span",[t._v(t._s(e.pay_time))]),t._v(" "),a("span",[t._v("设备号："+t._s(e.number))])])])}))])],1)},staticRenderFns:[]};var d=a("VU/8")(l,u,!1,function(t){a("0i1J")},"data-v-f19bcdc6",null);e.default=d.exports}});
//# sourceMappingURL=24.8e7d4809d057f4832a60.js.map