
            ╭───────────────────────╮
    ────┤           支付宝代码示例结构说明             ├────
            ╰───────────────────────╯ 
　                                                                  
　       接口名称：支付宝快捷登录接口（alipay.auth.authorize）
　 　    代码版本：3.3
         开发语言：PHP
         版    权：支付宝（中国）网络技术有限公司
　       制 作 者：支付宝商户事业部技术支持组
         联系方式：商户服务电话0571-88158090

    ─────────────────────────────────

───────
 代码文件结构
───────

alipay.auth.authorize-php-UTF-8
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
alipayapi.php

●本代码示例（DEMO）采用fsockopen()的方法远程HTTP获取数据、采用DOMDocument()的方法解析XML数据。

请根据商户网站自身情况来决定是否使用代码示例中的方式——
如果不使用fsockopen，那么建议用curl来代替；
如果环境不是PHP5版本或其以上，那么请用其他方法代替DOMDocument()。

curl、XML解析方法需您自行编写代码。


─────────
 类文件函数结构
─────────

alipay_core