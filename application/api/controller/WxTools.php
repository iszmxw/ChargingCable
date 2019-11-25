<?php
/**
--------------------------------------------------
空间类型   客服控制器
--------------------------------------------------
Copyright(c) 2017 时代万网 www.agewnet.com
--------------------------------------------------
开发人员: lichao  <729167563@qq.com>
--------------------------------------------------
 */
namespace app\api\controller;

class WxTools {
    /**
     * 以post方式提交xml到对应的接口url
     * @param string $url 提交地址
     * @param string $param 需要post的xml数据
     * @param bool $file 是否上传文件
     * @param bool|array $cert 是否需要证书，默认不需要 如果是数组代表有证书地址 请按以下格式 array('cert' => 'cert.pem', 'key' => 'key.pem', 'rootca' => 'rootca.pem');
     * @param int $second
     * @return mixed
     */
    public static function postCurl($url, $param, $file = false, $cert = false, $second = 30)
    {
        $curl = curl_init();
        //设置超时
        curl_setopt($curl, CURLOPT_TIMEOUT, $second);
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (PHP_VERSION_ID >= 50500 && class_exists('\CURLFile')) {
            $is_file = true;
        } else {
            $is_file = false;
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
            }
        }
        if (is_string($param)) {
            $str_post = $param;
        } elseif ($file) {
            if ($is_file) {
                foreach ($param as $key => $val) {
                    if (substr($val, 0, 1) == '@') {
                        $param[$key] = new \CURLFile(realpath(substr($val, 1)));
                    }
                }
            }
            $str_post = $param;
        } else {
            $post = array();
            foreach ($param as $key => $val) {
                $post[] = $key . "=" . urlencode($val);
            }
            $str_post = join("&", $post);
        }

        //设置证书 todo 未验证
        if (is_array($cert)) {
            //请确保您的libcurl版本是否支持双向认证，版本高于7.20.1 使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($curl, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($curl, CURLOPT_SSLCERT, $cert['cert']);
            curl_setopt($curl, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($curl, CURLOPT_SSLKEY, $cert['key']);
            //红包使用
            if (empty($cert['rootca'])) {
                curl_setopt($curl, CURLOPT_SSLKEYTYPE, 'PEM');
                curl_setopt($curl, CURLOPT_CAINFO, $cert['rootca']);
            }
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $str_post);
        $content = curl_exec($curl);
        $status = curl_getinfo($curl);
        if (intval($status["http_code"]) == 200) {
            curl_close($curl);
//            ApiLog::setMessage(\Yii::$app->session->get('request_base_api_log_id'),['url' => $url, 'message'=> $content], 1);
            return $content;
        } else {
            $error = curl_errno($curl);
            curl_close($curl);
//            $this->err_code = $error;
//            $this->err_msg = $this->curl_error[$error];
//            ApiLog::setMessage(\Yii::$app->session->get('request_base_api_log_id'),['url' => $url, 'message'=> $content], 0);
            return false;
        }
    }


    /**
     * CURL GET 请求
     * @param $url
     * @return bool|mixed
     */
    public static function getCurl($url)
    {
        $curl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($curl);
        $status = curl_getinfo($curl);

        if (intval($status["http_code"]) == 200) {
            curl_close($curl);
//            ApiLog::setMessage(\Yii::$app->session->get('request_base_api_log_id'),['url' => $url, 'message'=> $content], 1);
            return $content;
        } else {
            $error = curl_errno($curl);
            curl_close($curl);
            file_put_contents('../web/logs/notify/error' . date('YmdHi') . '.txt', $error);
//            ApiLog::setMessage(\Yii::$app->session->get('request_base_api_log_id'),['url' => $url, 'message'=> $content], 0);
//            $this->err_code = $error;
//            $this->err_msg = $this->curl_error[$error];
            return false;
        }
    }

    /**
     * 微信api不支持中文转义的json结构
     * @param $arr
     * @return string
     */
    public static function jsonEncode($arr)
    {
        if (count($arr) == 0) return "[]";
        $parts = array();
        $is_list = false;
        //Find out if the given array is a numerical array
        $keys = array_keys($arr);
        $max_length = count($arr) - 1;
        if (($keys [0] === 0) && ($keys [$max_length] === $max_length)) { //See if the first key is 0 and last key is length - 1
            $is_list = true;
            for ($i = 0; $i < count($keys); $i++) { //See if each key correspondes to its position
                if ($i != $keys [$i]) { //A key fails at position check.
                    $is_list = false; //It is an associative array.
                    break;
                }
            }
        }
        foreach ($arr as $key => $value) {
            if (is_array($value)) { //Custom handling for arrays
                if ($is_list)
                    $parts [] = self::jsonEncode($value); /* :RECURSION: */
                else
                    $parts [] = '"' . $key . '":' . self::jsonEncode($value); /* :RECURSION: */
            } else {
                $str = '';
                if (!$is_list)
                    $str = '"' . $key . '":';
                //Custom handling for multiple data types
                if (!is_string($value) && is_numeric($value) && $value < 2000000000)
                    $str .= $value; //Numbers
                elseif ($value === false)
                    $str .= 'false'; //The booleans
                elseif ($value === true)
                    $str .= 'true';
                else
                    $str .= '"' . addslashes($value) . '"'; //All other things
                // :TODO: Is there any more datatype we should be in the lookout for? (Object?)
                $parts [] = $str;
            }
        }
        $json = implode(',', $parts);
        if ($is_list)
            return '[' . $json . ']'; //Return numerical JSON
        return '{' . $json . '}'; //Return associative JSON
    }

    /**
     * 数据解析
     * @param $data
     * @return bool|mixed
     */
    public static function parseData($data)
    {
        $data = json_decode($data, true);
        return $data;
    }


    /**
     * 使用curl 文件上传 版本大于5.5
     * @param $url
     * @param $tmp_name
     * @param $type
     * @param $path
     * @return int|mixed
     */
    public static function curl_post_file($url, $tmp_name, $type, $path)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
        $data = ['file' => new \CURLFile($tmp_name, $type, $path)];
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, "TEST");
        $result = curl_exec($curl);
        $status = curl_getinfo($curl);
        if (intval($status["http_code"]) == 200) {
            curl_close($curl);
            return $result;
        }
        $error = curl_errno($curl);
        curl_close($curl);
        return $error;
    }
}