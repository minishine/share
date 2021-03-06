<?php
return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'分享',

	// preloading 'log' component
	'preload'=>array('log'),

	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.components.*',
        'application.extensions.yii-mail.*',  
	),

	'modules'=>array(
		// uncomment the following to enable the Gii tool
	
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'xhuan',
			// If removed, Gii defaults to localhost only. Edit carefully to taste.
			'ipFilters'=>array('127.0.0.1','::1', '192.168.1.15'),
		),
        
        //后台管理模块创建成功后，一定要在这里引入才可以生效
       //后台默认的控制器是defalut
        'admin' => array(
            'class' => 'application.modules.admin.AdminModule'
        ),
    ),
    

	// application components
	'components'=>array(
		'user'=>array(
			// enable cookie-based authentication
			'allowAutoLogin'=>true,
            'loginUrl' => './index.php?r=user/login', //为前台设置默认的登录页面
		),
        'mail'=>array(  
            'class' => 'application.extensions.yii-mail.YiiMail',  
            'viewPath' => 'application.views.mail',  
            'logging' => true,  
            'dryRun' => false,  
            'transportType'=>'smtp',     // case sensitive!  
            'transportOptions'=>array(  
                'host'=>'smtp',   // smtp服务器  
                'username'=>'13307139608@163.com',    // 验证用户  
                'password'=>'weoking',   // 验证密码  
                'port'=>'25',           // 端口号  
                //'encryption'=>'ssl',   
                ),  
        ),  
		// uncomment the following to enable URLs in path-format
		/*
		'urlManager'=>array(
			'urlFormat'=>'path',
			'rules'=>array(
				'<controller:\w+>/<id:\d+>'=>'<controller>/view',
				'<controller:\w+>/<action:\w+>/<id:\d+>'=>'<controller>/<action>',
				'<controller:\w+>/<action:\w+>'=>'<controller>/<action>',
			),
		),
		
        
		'db'=>array(
			'connectionString' => 'sqlite:'.dirname(__FILE__).'/../data/testdrive.db',
		),
       
         */
		// uncomment the following to use a MySQL database
		
        //这下面是连接数据库的配置信息，在内部处理走的是PDO，因为此需要打开PDO扩展
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=share',
			'emulatePrepare' => true,
			'username' => 'root',
			'password' => 'root',
			'charset' => 'utf8',
            //'tablePrefix' => 'yii_', //这里设置数据表前缀,下划线不要忘记了
            'enableParamLogging' => true   //由于是pdo有预处理,所以运行的时候的sql语句查看不到真实的参数，开启这个就可以了
        ),
		
		'errorHandler'=>array(
			// use 'site/error' action to display errors
			'errorAction'=>'site/error',
		),
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
				// uncomment the following to show log messages on web pages
	/*
				array(
					'class'=>'CWebLogRoute', //这里可以帮我们记录相应的日志信息，包括运行的sql语句
				),
	*/
			),
		),
	),

	// application-level parameters that can be accessed
	// using Yii::app()->params['paramName']
	'params'=>array(
		// this is used in contact page
		'adminEmail'=>'xhuan@hgdonline.net',
	),
   
    'defaultController' => 'index',
    'layout'=>'share',
);
