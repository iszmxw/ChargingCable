<?php

/**
 * 基础设置缓存
 * ============================================================================
 * 版权所有 2015-2027 深圳时代万网网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.agewnet.net/
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 玩味
 * Date: 2019-08-31
 */

namespace app\admin\logic;

use think\Model;
use think\Cache;
use think\Db;

class FinanceLogic extends Model
{


    /**
     * [wechat 提现到微信]
     * @return [type] [description]
     */
    public function wechat($log = [])
    {   
        if (empty($log['user_id'])) 
        {
            return array('status'=>0,'msg'=>'会员id不能为空！！');
        }

        $user = get_user_info($log['user_id'],0);

        if (empty($user)) 
        {
            return array('status'=>0,'msg'=>'会员不存在！！');
        }
        $realmoney = $log['money'];
        if ($log['taxfee']) 
        {
            $realmoney -= $log['taxfee'];
        }

        if(empty($user['openid']))
        {
            return array('status'=>0,'msg'=>'未绑定微信号，无法微信提现打款！');
        }

        if(empty($log['pay_code']))
        {
            return array('status'=>0,'msg'=>'提现单号错误！');
        }

        if($realmoney < 0.01)
        {
            return array('status'=>0,'msg'=>'打款金额太低！');
        }

        
        
        $pars = array();
     	
        
        $pars['nonce_str'] 	= $this->random(32);
        $pars['partner_trade_no'] = $log['pay_code'];
        $pars['amount'] = $realmoney * 100;
        $pars['desc'] = '余额提现';

        if(!$log['type']) 
        {
	        // 提现到零钱
	        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
	        $pars['mch_appid'] 	= C('APPID');
            $pars['mchid']      = C('MCHID');
	        $pars['openid'] = $user['openid'];
	        $pars['check_name'] = 'NO_CHECK';

	        $pars['spbill_create_ip'] = gethostbyname($_SERVER['HTTP_HOST']);
        }else
        {
        	
        	if(!$log['bank_card'] || !$log['realname']|| !$log['bank_type']) 
        	{
        		return array('status'=>0,'msg'=>'银行卡信息有误！');
        	}

	        // 提现到银行卡
	        $url = 'https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank';
	        $pars['enc_bank_no'] 	= $this->publicEncrypt($log['bank_card']);

	        $pars['enc_true_name'] 	= $this->publicEncrypt($log['realname']);
	        $pars['bank_code'] 		= $log['bank_type'];
            $pars['mch_id']         = C('MCHID');
        }

        $pars['sign'] = $this->getParam($pars);
        $xml = arrayToXml($pars);
        
        $certs = M('cert_file')->where('id = 1')->find();
		
        $extras = array();
        $errmsg = '未上传完整的微信支付证书，请到【系统设置】->【支付方式】中上传!';

        if (is_array($certs)) 
        {
            if (empty($certs['cert_file']) || empty($certs['key_file']) ) 
            {
                return array('status'=>0,'msg'=>$errmsg);
            }

            $certfile = './pay/cert/' . $this->random(128);
            file_put_contents($certfile, $certs['cert_file']);
            $keyfile = './pay/cert/' . $this->random(128);
            file_put_contents($keyfile, $certs['key_file']);
            
            $extras['CURLOPT_SSLCERT'] = $certfile;
            $extras['CURLOPT_SSLKEY'] = $keyfile;
            
        } else {
            return array('status'=>0,'msg'=>$errmsg);
        }
        $resp = $this->http_post($url,$xml, $extras);


        @unlink($certfile);
        @unlink($keyfile);
       
        
        if (empty($resp['content'])) {
            return array('status'=>0,'msg'=>'网络错误');
        }

        $arr = json_decode(json_encode(simplexml_load_string($resp['content'], 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        if (($arr['return_code'] == 'SUCCESS') && ($arr['result_code'] == 'SUCCESS')) 
        {
            return array('status'=>1,'msg'=>'打款成功');
        }

        if ($arr['return_msg'] == $arr['err_code_des']) 
        {
            $msg = $arr['return_msg'];
        } else {
            $msg = $arr['return_msg'] . ' | ' . $arr['err_code_des'];
        }

        return array('status'=>0,'msg'=>$msg);

    }



    /**
     * 公钥加密，银行卡号和姓名需要RSA算法加密
     * @param string $data    需要加密的字符串，银行卡/姓名
     * @return null|string    加密后的字符串
     * linux先执行这条命令  openssl rsa -RSAPublicKey_in -in publicrsa.pem -out publicrsa8.pem
     */
    private function publicEncrypt($data)
    {
    	// $public_pkcs = $this->get_pub_key();
        // $pubkey = openssl_get_publickey($public_pkcs);
		// $pubkey = openssl_pkey_get_public($public_pkcs); 
        // 进行加密
        $pubkey = openssl_pkey_get_public(file_get_contents('./pay/file/publicrsa8.pem'));
       
		if(!$pubkey)
		{
			return false;
		}

        $encrypt_data = '';
        $encrypted = '';
        $r = openssl_public_encrypt($data,$encrypt_data,$pubkey,OPENSSL_PKCS1_OAEP_PADDING);

        if($r){//加密成功，返回base64编码的字符串
            return base64_encode($encrypted.$encrypt_data);
        }else{
            return false;
        }
    }




    /*
     * 获取公钥,格式为PKCS#1 转PKCS#8
     *  openssl rsa  -RSAPublicKey_in -in   <filename>  -out <out_put_filename>
     * */
    private function get_pub_key()
    {
        $rsafile = './pay/file/publicrsa.pem';
        if(!is_file($rsafile)){
            $data['mch_id'] = C('MCHID');
            $data['nonce_str'] = $this->random(12);
            $data['sign_type'] = 'MD5';
            $data['sign'] = $this->getParam($data);

            $xml = arrayToXml($data);
            $url = 'https://fraud.mch.weixin.qq.com/risk/getpublickey';

            $certs = M('cert_file')->where('id = 1')->find();
            $certfile = './pay/cert/' . $this->random(128);
            file_put_contents($certfile, $certs['cert_file']);
            $keyfile = './pay/cert/' . $this->random(128);
            file_put_contents($keyfile, $certs['key_file']);
            
            $extras['CURLOPT_SSLCERT'] = $certfile;
            $extras['CURLOPT_SSLKEY'] = $keyfile;
		
            $ret =  $this->httpsPost($url,$xml,true,$extras);
            @unlink($certfile);
        	@unlink($keyfile);

            
            if($ret['return_code'] == 'SUCCESS' && isset($ret['pub_key'])){
                file_put_contents($rsafile,$ret['pub_key']);
                return $ret['pub_key'];
            }else{
                return null;
            }
        }else{
            return file_get_contents($rsafile);
        }
    }



    //对参数排序，生成MD5加密签名
    private function getParam($paramArray, $isencode=false)
    {
        $paramStr = '';
        ksort($paramArray);
       
        $i = 0;
        foreach ($paramArray as $key => $value)
        {
            if ($key == 'Signature'){
                continue;
            }
            if ($i == 0){
                $paramStr .= '';
            }else{
                $paramStr .= '&';
            }
            $paramStr .= $key . '=' . ($isencode?urlencode($value):$value);
            ++$i;
        }
        $stringSignTemp=$paramStr."&key=".C('APIKEY');
        $sign=strtoupper(md5($stringSignTemp));
        return $sign;
    }





    /*
    * 发起POST网络请求
    * @params string $url : 请求的url链接地址
    * @params string $data : 数据包
    * @params bool $ssl : 是否加载证书
    * return array $result : 返回的数据结果 
    */
    private function httpsPost($url,$data,$ssl = false,$extras)
    {
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
        if($ssl) {
            curl_setopt ( $ch,CURLOPT_SSLCERT,$extras['CURLOPT_SSLCERT']);
            curl_setopt ( $ch,CURLOPT_SSLKEY,$extras['CURLOPT_SSLKEY']);
        }
        curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'Errno: '.curl_error($ch);
        }
        curl_close($ch);
        return $this->xmlToArray($result);
    }



    /*
    * 将xml转换成数组
    * @params xml $xml : xml数据
    * return array $data : 返回数组
    */
    private function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring),true);
        return $val;
    }



	public function http_post($url, $post, $extra) 
	{
    	$ch = curl_init();
    	if (stripos($url, "https://") !== FALSE) {
    		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    	}
    	if ($this->strexists($url, 'https://') && !extension_loaded('openssl')) 
    	{
			if (!extension_loaded("openssl")) 
			{
				return array('status'=>0,'msg'=>'请开启您PHP环境的openssl');
			}
		}

		$urlset = parse_url($url);

		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $urlset['scheme'] . '://' . $urlset['host'] . ($urlset['port'] == '80' || empty($urlset['port']) ? '' : ':' . $urlset['port']) . $urlset['path'] . $urlset['query']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		@curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);


		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSLVERSION, 1);
		if (defined('CURL_SSLVERSION_TLSv1')) {
			curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
		}
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');


		if (!empty($extra) && is_array($extra)) 
		{
			$headers = array();
			foreach ($extra as $opt => $value) 
			{
				if ($this->strexists($opt, 'CURLOPT_')) 
				{
					curl_setopt($ch, constant($opt), $value);
				} elseif (is_numeric($opt)) 
				{
					curl_setopt($ch, $opt, $value);
				} else 
				{
					$headers[] = "{$opt}: {$value}";
				}
			}
			if (!empty($headers)) 
			{
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
		}
		$data = curl_exec($ch);

		$status = curl_getinfo($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if (empty($data)) 
		{
			return array('status'=>0,'msg'=>$error);
		} else 
		{
			return $this->ihttp_response_parse($data);
		}
		
    }




    public function ihttp_response_parse($data, $chunked = false) 
    {
		$rlt = array();
		$headermeta = explode('HTTP/', $data);
		if (count($headermeta) > 2) 
		{
			$data = 'HTTP/' . array_pop($headermeta);
		}
		$pos = strpos($data, "\r\n\r\n");
		$split1[0] = substr($data, 0, $pos);
		$split1[1] = substr($data, $pos + 4, strlen($data));
		
		$split2 = explode("\r\n", $split1[0], 2);
		preg_match('/^(\S+) (\S+) (.*)$/', $split2[0], $matches);
		$rlt['code'] = $matches[2];
		$rlt['status'] = $matches[3];
		$rlt['responseline'] = $split2[0];
		$header = explode("\r\n", $split2[1]);
		$isgzip = false;
		$ischunk = false;
		foreach ($header as $v) 
		{
			$pos = strpos($v, ':');
			$key = substr($v, 0, $pos);
			$value = trim(substr($v, $pos + 1));
			if (is_array($rlt['headers'][$key])) 
			{
				$rlt['headers'][$key][] = $value;
			} elseif (!empty($rlt['headers'][$key])) 
			{
				$temp = $rlt['headers'][$key];
				unset($rlt['headers'][$key]);
				$rlt['headers'][$key][] = $temp;
				$rlt['headers'][$key][] = $value;
			} else 
			{
				$rlt['headers'][$key] = $value;
			}
			if(!$isgzip && strtolower($key) == 'content-encoding' && strtolower($value) == 'gzip') 
			{
				$isgzip = true;
			}
			if(!$ischunk && strtolower($key) == 'transfer-encoding' && strtolower($value) == 'chunked') 
			{
				$ischunk = true;
			}
		}
		if($chunked && $ischunk) 
		{
			$rlt['content'] = $this->ihttp_response_parse_unchunk($split1[1]);
		} else {
			$rlt['content'] = $split1[1];
		}
		$rlt['content'] = $split1[1];

		if($isgzip && function_exists('gzdecode')) 
		{
			$rlt['content'] = gzdecode($rlt['content']);
		}

		$rlt['meta'] = $data;
		if($rlt['code'] == '100') {
			return $this->ihttp_response_parse($rlt['content']);
		}
		
		return $rlt;
	}


	public function ihttp_response_parse_unchunk($str = null) 
	{
		if(!is_string($str) or strlen($str) < 1) 
		{
			return array('status'=>0,'msg'=>'未知原因');
		}
		$eol = "\r\n";
		$add = strlen($eol);
		$tmp = $str;
		$str = '';
		do {
			$tmp = ltrim($tmp);
			$pos = strpos($tmp, $eol);
			if($pos === false) 
			{
				return false;
			}
			$len = hexdec(substr($tmp, 0, $pos));
			if(!is_numeric($len) or $len < 0) 
			{
				return false;
			}
			$str .= substr($tmp, ($pos + $add), $len);
			$tmp  = substr($tmp, ($len + $pos + $add));
			$check = trim($tmp);
		} while(!empty($check));
		unset($tmp);
		return $str;
	}

	public function random($length, $numeric = FALSE) 
	{
		$seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
		$seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
		if ($numeric) 
		{
			$hash = '';
		} else 
		{
			$hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
			$length--;
		}
		$max = strlen($seed) - 1;
		for ($i = 0; $i < $length; $i++) 
		{
			$hash .= $seed{mt_rand(0, $max)};
		}
		return $hash;
	}
	
    public function strexists($string, $find) 
    {
		return !(strpos($string, $find) === FALSE);
	}

}