
            ╭───────────────────────╮
    ────┤           支付宝代码示例结构说明             ├────
            ╰───────────────────────╯ 
　                                                                  
　       接口名称：支付宝手机网站支付接口（alipay.wap.create.direct.pay.by.user）
　 　    代码版本：3.4
         开发语言：PHP
         版    权：支付宝（中国）网络技术有限公司
　       制 作 者：支付宝商户事业部技术支持组
         联系方式：商户服务电话0571-88158090

    ─────────────────────────────────

───────
 代码文件结构
───────

alipay.wap.create.direct.pay.by.user-php-UTF-8
  │
  ├lib┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈类文件夹
  │  │
  │  ├alipay_core.function.php ┈┈┈┈┈┈支付宝接口公用函数文件
  │  │
  │  ├alipay_notify.class.php┈┈┈┈┈┈┈支付宝通知处理类文件
  │  │
  │  ├alipay_submit.class.php┈┈┈┈┈┈┈支付宝各接口请求提交类文件
  │  │
  │  └alipay_md5.function.php┈┈┈┈┈┈┈支付宝接口MD5函数文件
  │
  ├log.txt┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈日志文件
  │
  ├alipay.config.php┈┈┈┈┈┈┈┈┈┈┈┈基础配置类文件
  │
  ├alipayapi.php┈┈┈┈┈┈┈┈┈┈┈┈┈┈支付宝接口入口文件
  │
  ├notify_url.php ┈┈┈┈┈┈┈┈┈┈┈┈┈服务器异步通知页面文件
  │
  ├return_url.php ┈┈┈┈┈┈┈┈┈┈┈┈┈页面跳转同步通知文件
  │
  ├cacert.pem ┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈用于CURL中校验SSL的CA证书文件
  │
  └readme.txt ┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈使用说明文本

※注意※

1、必须开启curl服务
（1）使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"即可
（2）文件夹中cacert.pem文件请务必放置到商户网站平台中（如：服务器上），并且保证其路径有效，提供的代码demo中的默认路径是当前文件夹下——getcwd().'\\cacert.pem'

2、需要配置的文件是：
alipay.config.php

curl、XML解析方法需您自行编写代码。


─────────
 类文件函数结构
─────────

alipay_core.function.php

function createLinkstring($para)
功能：把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
输入：Array  $para 需要拼接的数组
输出：String 拼接完成以后的字符�