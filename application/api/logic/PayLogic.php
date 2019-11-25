<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/6
 * Time: 10:30
 */

namespace app\api\logic;

use think\Log;

class PayLogic
{

    /**
     * 析构流函数
     */
    protected $appid;
    protected $secret;
    protected $mch_id;
    protected $key;
    protected $openid;
    protected $out_trade_no;
    protected $body;
    protected $total_fee;
    protected $notify_url;
    protected $tui_url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';    //退款请求

    //标识qrcodeticket的类型，是永久还是临时
    const QRCODE_TYPE_TEMP = 1;
    const QRCODE_TYPE_LIMIT = 2;
    const QRCODE_TYPE_LIMIT_STR = 3;

    function __construct($openid="",$out_trade_no="",$total_fee=0,$body="下单消费",$notify_url="",$xcappid = '') {
        $this->appid = $xcappid?$xcappid:'wx9b04ac5aa5c4cc6a';
        $this->secret = 'c11af7bf248d6647128fcb3816492980';
        $this->openid = $openid;
        $this->mch_id = '1533376191';
        $this->key = '15998c70d2d3ee19be34d53e0df87d9c';
        $this->out_trade_no = $out_trade_no;
        $this->total_fee = $total_fee;
        $this->body = $body;
        $this->notify_url = $notify_url?$notify_url:url_add_domain('/index.php/Api/Weixin/notify');
    }

    public function pay() {
        //统一下单接口
        $return = $this->weixinapp();
        return $return;
    }

    public function weiReturn($tuiOrder){
        //$out_trade_no=date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);//自定义订单号
        //生成请求数据xml
        $data =['appid'=>$this->appid,
            'mch_id'=>$this->mch_id,
            'nonce_str'=>$this->createNoncestr(),
            'out_trade_no'=>$tuiOrder['out_trade_no'],
            'out_refund_no'=>$tuiOrder['out_refund_no'],
            'total_fee'=>$tuiOrder['total_fee']*100,
            'refund_fee'=>$tuiOrder['refund_fee']*100
        ];
        //生成签名sign
        $sign = $this->getSign($data);
        //完善请求数据
        $data['sign'] = $sign;
        //生成请求数据XML
        $xmlStr = $this->arr_to_xml($data);
        //现场退款请求下列方法
        $xmlStrReturn = $this->curls_post_ssl($this->tui_url, $xmlStr);
//         	    var_dump($xmlStr);
//         	    var_dump($this->tui_url);
         	    //var_dump($xmlStrReturn);
         	   // exit();
        if ($xmlStrReturn) {

            //将返回转成数组
            $postArr = $this->xmlToObject($xmlStrReturn);
            /*-----------     生成返回的json串保存以备后用           -----------*/
            $pdata = ['return_code'=>$postArr->return_code,
                'result_code'=>$postArr->result_code,
                'appid'=>$postArr->appid,
                'mch_id'=>$postArr->mch_id,
                'nonce_str'=>$postArr->nonce_str,
                'sign'=>$postArr->sign,
                'transaction_id'=>$postArr->transaction_id,
                'out_trade_no'=>$postArr->out_trade_no,
                'out_refund_no'=>$postArr->out_refund_no,
                'refund_id'=>$postArr->refund_id,
                'refund_fee'=>$postArr->refund_fee,
                'total_fee'=>$postArr->total_fee,
                'err_code_des'=>$postArr->err_code_des
            ];
            return $pdata;
            //$json = json_encode($pdata,true);
            /*---------     生成返回的json串保存以备后用          ----------*/
            //区分是否成功

        }else{
           return [];
        }
    }

    //统一下单接口
    protected function unifiedorder()
    {
        //$url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $ip = $_SERVER['REMOTE_ADDR'];

        $parameters = array(
            'appid' => $this->appid, //公众号appid
            //            'body' => 'test', //商品描述
            'body' =>  $this->body,
            'mch_id' => $this->mch_id, //商户号
            'nonce_str' => $this->createNoncestr(), //随机字符串
            'notify_url' => $this->notify_url, //通知地址  确保外网能正常访问
            'openid' => $this->openid, //用户id
//            'out_trade_no' => '2015450806125348', //商户订单号
            'out_trade_no'=> $this->out_trade_no,
            'spbill_create_ip' => "$ip", //终端IP
//            'total_fee' => floatval(0.01 * 100), //总金额 单位 分
            'total_fee' => intval($this->total_fee),
//            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'], //终端IP
            'trade_type' => 'JSAPI'//交易类型
        );
            
        //统一下单签名
        $parameters['sign'] = $this->getSign($parameters);

        $xmlData = $this->arrayToXml($parameters);
        //$return = $this->xmlToArray($this->wechatCurl($xmlData, $url));
        $return = $this->wechatCurl($xmlData, $url);

//        file_put_contents('./wxpay.txt', date('Ymd H:i').'：'.json_encode($return). "\r\n",FILE_APPEND);
        return $return;
    }

    public function getSignPackage() {
        $jsapiTicket = $this->getJsApiTicket();

        // 注意 URL 一定要动态获取，不能 hardcode.
        $url = $_SERVER['HTTP_REFERER'];
        if(empty($url)){
            $url = 'http://'.$_SERVER['HTTP_HOST'].'/dist/index.html';
        }
        //Log::write('前端获取签名：'.$url);
        $timestamp = time();

        $nonceStr = $this->createNonceStr(16);

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        //$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        //echo $string;
        $signature = sha1($string);

        $signPackage = array(
            "appId" => $this->appid,
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage;
    }


    public function getSignPackage_map($url) {
        $jsapiTicket = $this->getJsApiTicket();

        // 注意 URL 一定要动态获取，不能 hardcode.


        //Log::write('前端获取签名：'.$url);
        $timestamp = time();

        $nonceStr = $this->createNonceStr(16);

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        //$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        //echo $string;
        $signature = sha1($string);

        $signPackage = array(
            "appId" => $this->appid,
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage;
    }

    private function getJsApiTicket() {
        // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
        $data = json_decode(file_get_contents("jsapi_ticket.json"));

        if ($data->expire_time < time()) {

            $accessToken = $this->getAccessToken();

	
            // file_put_contents('./pay/log.txt', $access_token.PHP_EOL, FILE_APPEND);
            // 如果是企业号用以下 URL 获取 ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";

            $res = json_decode($this->httpGet($url));

            $ticket = $res->ticket;
            if ($ticket) {
				
                $data->expire_time = time() + 7000;
                $data->jsapi_ticket = $ticket;
                $fp = fopen("jsapi_ticket.json", "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        } else {

            $ticket = $data->jsapi_ticket;
        }
		

        return $ticket;
    }

    public function getAccessToken() {
        // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
        $data = json_decode(file_get_contents("access_token.json"));
      
        if ($data->expire_time < time()) {
            // 如果是企业号用以下URL获取access_token
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->secret";
            $res = json_decode($this->httpGet($url));
            $access_token = $res->access_token;
            if ($access_token) {
                //file_put_contents('./pay/log.txt', $access_token.PHP_EOL, FILE_APPEND);
                $data->expire_time = time() + 7000;
                $data->access_token = $access_token;
                $fp = fopen("access_token.json", "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        } else {
            $access_token = $data->access_token;
        }
        // file_put_contents('./pay/log.txt', $access_token.PHP_EOL, FILE_APPEND);
        return $access_token;
    }

    private function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    /**
     * 根据access_token获取ticket
     * @param $content 内容
     * @param int $type qr码类型
     * @param int $expire 有效期，如果是临时类型需指定
     * @return string  ticket
     */
    public function getQRCodeTicket($content,$type=3,$expire=2592000)
    {
        $access_token = $this->getAccessToken();
        // file_put_contents('./pay/log.txt', $access_token.PHP_EOL, FILE_APPEND);
        Log::write('获取ticket：'.json_encode($access_token));
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$access_token;
        $type_list = array(
            self::QRCODE_TYPE_TEMP => 'QR_SCENE',
            self::QRCODE_TYPE_LIMIT=>'QR_LIMIT_SCENE',
            self::QRCODE_TYPE_LIMIT_STR=>'QR_LIMIT_STR_SCENE'
        );
        $action_name = $type_list[$type];
        //post发送的数据
        switch ($type){
            case self::QRCODE_TYPE_TEMP:
                $data_arr['expire_seconds']=$expire;
                $data_arr['action_name'] = $action_name;
                $data_arr['action_info']['scene']['scene_id']=$content;
                break;
            case self::QRCODE_TYPE_LIMIT:
                $data_arr['action_name'] = $action_name;
                $data_arr['action_info']['scene']['scene_id'] = $content;
                break;
            case self::QRCODE_TYPE_LIMIT_STR:
                $data_arr['action_name'] = $action_name;
                $data_arr['action_info']['scene']['scene_str'] = $content;
                break;
        }
        $data = json_encode($data_arr);
        $result = $this->_request('post',$url,$data);
        if(!$result){
            return false;
        }
        $result_obj = json_decode($result);
        Log::write('获取ticket：'.json_encode($result_obj));
        return $result_obj->ticket;
    }

    //根据ticket获取二维码
    /**
     * @param int|string $content qrcode内容标识
     * @param [type] $file 存储为文件的地址，如果null直接输出
     * @param integer $type 类型
     * @param integer $expire 如果是临时，标识有效期
     * @return  [type]
     */
    public function getQRCode($content,$file=NULL,$type=3,$expire=604800)
    {
        //获取ticket
        $ticket = $this->getQRCodeTicket($content,$type=3,$expire=604800);
        $url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=$ticket";
        //发送，取得图片数据
        $result = $this->_request('get',$url);
        Log::write('获取二维码：'.$result);
        if($file){
            file_put_contents($file,$result);
        }else{
            header('Content-Type:image/jpeg');
            echo $result;
        }
    }


    private function _request($method='get',$url,$data=array(),$ssl=true){
        //curl完成，先开启curl模块
        //初始化一个curl资源
        $curl = curl_init();
        //设置curl选项
        curl_setopt($curl,CURLOPT_URL,$url);//url
        //请求的代理信息
        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']: 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0 FirePHP/0.7.4';
        curl_setopt($curl,CURLOPT_USERAGENT,$user_agent);
        //referer头，请求来源
        curl_setopt($curl,CURLOPT_AUTOREFERER,true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);//设置超时时间
        //SSL相关
        if($ssl){
            //禁用后，curl将终止从服务端进行验证;
            curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
            //检查服务器SSL证书是否存在一个公用名
            curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,2);
        }
        //判断请求方式post还是get
        if(strtolower($method)=='post') {
            /**************处理post相关选项******************/
            //是否为post请求 ,处理请求数据
            curl_setopt($curl,CURLOPT_POST,true);
            curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
        }
        //是否处理响应头
        curl_setopt($curl,CURLOPT_HEADER,false);
        //是否返回响应结果
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);

        //发出请求
        $response = curl_exec($curl);
        if (false === $response) {
            echo '<br>', curl_error($curl), '<br>';
            return false;
        }
        //关闭curl
        curl_close($curl);
        return $response;
    }

    /*protected static function postXmlCurl($xml, $url, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);


        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }*/

    function wechatCurl($xml,$url)
    {
        //header("Content-type:text/xml");
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }    else    {
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        }
        //设置header
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //传输文件
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        curl_close($ch);
        return xmlToArray($data);//这里已经转了

    }


    /*//数组转换成xml
    protected function arrayToXml($arr) {
        $xml = "<root>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
        }
        $xml .= "</root>";
        return $xml;
    }*/

    /**
     * 数组转XML
     * @param array $data 需要转化的数组
     * @return string XML格式返回
     */
    function arrayToXml($data=array())
    {
        if(!is_array($data) || count($data) <= 0)
        {
            return '数组异常';
        }

        $xml = "<xml>";
        foreach ($data as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }


    //xml转换成数组
    protected function xmlToArray($xml) {


        //禁止引用外部xml实体


        libxml_disable_entity_loader(true);


        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);


        $val = json_decode(json_encode($xmlstring), true);


        return $val;
    }


    //微信小程序接口
    public  function weixinapp() {
        //统一下单接口
        $unifiedorder = $this->unifiedorder();
//        print_r($unifiedorder);
        $parameters = array(
            'appId' => $this->appid, //小程序ID
            'timeStamp' => '' . time() . '', //时间戳
            'nonceStr' => $this->createNoncestr(), //随机串
            'package' => 'prepay_id=' . $unifiedorder['prepay_id'], //数据包
            'signType' => 'MD5'//签名方式
        );
        //签名
        $parameters['paySign'] = $this->getSign($parameters);
        return $parameters;
    }


    //作用：产生随机字符串，不长于32位
    protected function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }


    /*//作用：生成签名
    public  function getSign($Obj) {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }*/

    function getSign($params) {
        ksort($params);        //将参数数组按照参数名ASCII码从小到大排序
        foreach ($params as $key => $item) {
            if (!empty($item)) {         //剔除参数值为空的参数
                $newArr[] = $key.'='.$item;     // 整合新的参数数组
            }
        }

        $stringA = implode("&", $newArr);         //使用 & 符号连接参数
        $stringSignTemp = $stringA."&key=". $this->key;        //拼接key

        // key是在商户平台API安全里自己设置的
        $stringSignTemp = MD5($stringSignTemp);       //将字符串进行MD5加密
        $sign = strtoupper($stringSignTemp);      //将所有字符转换为大写
        return $sign;
    }


    ///作用：格式化参数，签名过程需要使用
    protected function formatBizQueryParaMap($paraMap, $urlencode) {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }


    /**
     *
     * 请确保您的libcurl版本是否支持双向认证，版本高于7.20.1
     * $url 退款请求地址
     * $vars 退款请求数据
     */

    function curls_post_ssl($url, $vars, $second=30,$aHeader=array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL,$url);

        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);

        //以下两种方式需选择一种

        //第一种方法，cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT,PLUGINS_PATH.'payment/weixin/cert/mini/apiclient_cert.pem');
        //return getcwd().'/APP/Api/Common/apiclient_cert.pem';//生成文件路径
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY,PLUGINS_PATH.'payment/weixin/cert/mini/apiclient_key.pem');

        //第二种方式，两个文件合成一个.pem文件
        // 	curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');

        if( count($aHeader) >= 1 ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        }
        else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }
    /**
     * 将数组装换成XML格式的串;
     */

    public  function arr_to_xml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val){
            if(is_array($val)){
                $xml.="<".$key.">".$this->arr_to_xml($val)."</".$key.">";
            }else{
                $xml.="<".$key.">".$val."</".$key.">";
            }
        }
        $xml.="</xml>";

        return $xml;
    }


    /**
     * 解析xml文档，转化为对象
     * @author
     * @param  String $xmlStr xml文档
     * @return Object         返回Obj对象
     */
    public function xmlToObject($xmlStr) {
        if (!is_string($xmlStr) || empty($xmlStr)) {
            return false;
        }
        // 由于解析xml的时候，即使被解析的变量为空，依然不会报错，会返回一个空的对象，所以，我们这里做了处理，当被解析的变量不是字符串，或者该变量为空，直接返回false
        $postObj = simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $postObj = json_decode(json_encode($postObj));
        //$postObj = json_encode($postObj,true);
        //将xml数据转换成对象返回
        return $postObj;
    }

}