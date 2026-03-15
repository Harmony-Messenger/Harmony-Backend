<?php
$GLOBALS['Config'] = parse_ini_file('../config.ini', true);

require_once('DB.php');
require_once('ErrorHandler.php');
require_once('AuthenticationHandler.php');
require_once('User.php');
require_once('DM.php');
require_once('Channel.php');
require_once('Message.php');
require_once('Voice.php');
require_once('Video.php');
require_once('Me.php');
require_once('Image.php');
require_once('Permissions.php');
require_once('Server.php');
require_once('Notifications.php');
require_once('PHPMailer/PHPMailer.php');
require_once('PHPMailer/SMTP.php');
require_once('PHPMailer/Exception.php');
require_once('JWT/JWTExceptionWithPayloadInterface.php');
require_once('JWT/ExpiredException.php');
require_once('JWT/BeforeValidException.php');
require_once('JWT/SignatureInvalidException.php');
require_once('JWT/CachedKeySet.php');
require_once('JWT/Key.php');
require_once('JWT/JWT.php');

class RequestHandler {
	private $DB;

	public function __construct($DB)
	{
		$BasePath = parse_url($GLOBALS['Config']['Service']['APIURI'], PHP_URL_PATH);
		$RequestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

		$RelativePath = str_replace($BasePath, '', $RequestPath);

		$URI = explode('/', ltrim($RelativePath, '/'));

		if(isset(getallheaders()['Authorization']))
		{
			$AuthenticationToken = getallheaders()['Authorization'];
			$AuthenticationToken = str_replace('bearer ', '', $AuthenticationToken);
		}
		else
		{
			$AuthenticationToken = null;
		}

		$AuthenticationHandler = new AuthenticationHandler($AuthenticationToken);


		if(isset($URI[0]) && isset($URI[1]) && isset($URI[2]))
		{
			if($URI[0] != 'user' && $URI[1] != 'actions' && $URI[2] != 'register' 
			|| $URI[0] != 'user' && $URI[1] != 'actions' && $URI[2] != 'login'
			|| $URI[0] != 'user' && $URI[1] != 'actions' && $URI[2] != 'activate')
			{
				$AuthenticationHandler->Authenticate();
			}
		}
		else
		{
			$AuthenticationHandler->Authenticate();
		}

		if($AuthenticationHandler->Authenticated)
		{
			$GLOBALS['DB'] = $DB;

			if($GLOBALS['DB']->Connected)
			{
				if(isset($URI[0]))
				{
					switch($URI[0])
					{
						case 'user':
							$User = new User();
							$User->ProcessRequest();
						break;

						case 'channel':
							$Channel = new Channel();
							$Channel->ProcessRequest();
						break;

						case 'message':
							$Message = new Message();
							$Message->ProcessRequest();
						break;

						case 'voice':
							$Voice = new Voice();
							$Voice->ProcessRequest();
						break;

						case 'dm':
							$DM = new DM();
							$DM->ProcessRequest();
						break;

						case 'video':
							$Video = new Video();
							$Video->ProcessRequest();
						break;

						case 'me':
							$Me = new Me();
							$Me->ProcessRequest();
						break;

						case 'image':
							$Image = new Image();
							$Image->ProcessRequest();
						break;

						case 'permissions':
							$Permissions = new Permissions();
							$Permissions->ProcessRequest();
						break;

						case 'notifications':
							$Notifications = new Notifications();
							$Notifications->ProcessRequest();
						break;
					
						default:
							new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Access', 404);
						break;
					}
				}
				else
				{
					new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Access', 404);
				}
			
			}
		}
		else
		{
			if(isset($URI[0]) && isset($URI[1]) && isset($URI[2]))
			{
				if($URI[0] == 'user' && $URI[1] == 'actions' && $URI[2] == 'register' 
				|| $URI[0] == 'user' && $URI[1] == 'actions' && $URI[2] == 'login'
				|| $URI[0] == 'user' && $URI[1] == 'actions' && $URI[2] == 'activate')
				{
					$User = new User();
					$User->ProcessRequest();
				} 
				else
				{
					new ErrorHandler()->Throw(array('Unauthenticated.'), 'Authentication', 401);
				}
				
			}
			else
			{
				if($URI[0] == 'server')
				{
					$Server = new Server();
					$Server->ProcessRequest();
				}
				else if(trim(strtok($URI[0], '?')) == 'setup')
				{
					if(is_dir($GLOBALS['Config']['Service']['BackendPath'].'/Setup'))
					{
						include($GLOBALS['Config']['Service']['BackendPath'].'Setup/index.php');
					}
				}
				else
				{
					new ErrorHandler()->Throw(array('Unauthenticated.'), 'Authentication', 401);
				}
			}
		}
	}
}