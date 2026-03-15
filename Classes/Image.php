<?php
class Image
{
    public function Update($Username = null)
    {
        if(!is_null($Username))
        {
            if(isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 5242880)
            {
                new ErrorHandler()->Throw(array('File too large.'), 'Message', 400);
                return;
            }
            
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");

            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            if($UserID != $GLOBALS['AccessToken']->UserID)
            {
                new ErrorHandler()->Throw(array('Access denied.'), 'Message', 401);
                return;
            }

            $Data = file_get_contents('php://input');
            $Type = $_SERVER['CONTENT_TYPE'];

            $Image = imagecreatefromstring($Data);

            if(!$Image)
            {
                new ErrorHandler()->Throw(array('Invalid image format.'), 'Message', 400);
                return;
            }

            imagepalettetotruecolor($Image);
            imagealphablending($Image, true);
            imagesavealpha($Image, true);

            $Directory = $GLOBALS['Config']['Service']['BackendPath'].'/Images/Users/';

            imagewebp($Image, $Directory.basename($Username).'.webp', 100);
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Message', 404);
        }
    }

    public function Get($Username = null)
    {
        if(!is_null($Username))
        {
            $Directory = $GLOBALS['Config']['Service']['BackendPath'].'/Images/';

            if(is_file($Directory.'Users/'.basename($Username).'.webp'))
            {
                echo file_get_contents($Directory.'Users/'.basename($Username).'.webp');
            }
            else
            {
                echo file_get_contents($Directory.'System/unknown.webp');
            }
        }
    }

    public function ProcessRequest()
    {
        $Method = $_SERVER['REQUEST_METHOD'];
        $BasePath = parse_url($GLOBALS['Config']['Service']['APIURI'], PHP_URL_PATH);
		$RequestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

		$RelativePath = str_replace($BasePath, '', $RequestPath);

		$URI = explode('/', ltrim($RelativePath, '/'));

        if($Method == 'GET')
        {
            if(isset($URI[1]))
            {
                $this->Get($URI[1]);
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Message', 404);
            }
        }

        if($Method == 'POST')
        {
            if(isset($URI[1]))
            {
                $this->Update($URI[1]);
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Message', 404);
            }
        }
    }
}