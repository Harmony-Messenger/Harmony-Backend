<?php
class Video {
    public function Get($Username = null)
    {
        try
        {
            if($Username != null)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.ID, VideoSessions.ID AS SessionID FROM Users LEFT JOIN VideoSessions ON Users.ID = VideoSessions.UserID WHERE Users.Username = :Username LIMIT 1");
                $DBRequest->bindParam(":Username", $Username);
                $DBRequest->execute();

                $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM VideoSessionsConnectedUsers WHERE UserID = :UserID LIMIT 1");
                $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();

                $AlreadyConnected = $DBRequest->fetch(PDO::FETCH_ASSOC);

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VideoSessionsConnectedUsers WHERE LastActive < NOW() - INTERVAL 10 SECOND");
                $DBRequest->execute();
                
                if($AlreadyConnected == false)
                {                  
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO VideoSessionsConnectedUsers (UserID, LastActive, SessionID) VALUES (:UserID, NOW(), :SessionID)");
                    $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->bindParam(":SessionID", $DBResponse['SessionID']);
                    $DBRequest->execute(); 
                }
                else
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE VideoSessionsConnectedUsers SET LastActive = NOW() WHERE UserID = :UserID LIMIT 1");
                    $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->execute();
                }

                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.ID, VideoSessions.ID AS SessionID FROM Users LEFT JOIN VideoSessions ON Users.ID = VideoSessions.UserID WHERE Users.Username = :Username LIMIT 1");
                $DBRequest->bindParam(":Username", $Username);
                $DBRequest->execute();

                $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

                if(isset($DBResponse['ID']) && !is_null($DBResponse['ID']))
                {
                    if(isset($_GET['ConnectedUsers']))
                    {
                        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT COUNT(ID) AS Total FROM VideoSessionsConnectedUsers WHERE SessionID = :SessionID LIMIT 1");
                        $DBRequest->bindParam(":SessionID", $DBResponse['SessionID']);
                        $DBRequest->execute();

                        $Users = $DBRequest->fetch(PDO::FETCH_ASSOC);

                        $Response = new ResponseHandler();
                        $Response->Code = 200;
                        $Response->Data = $Users;
                        
                        $Response->Respond();
                    }
                    else
                    {
                        $Directory = '../VideoStreams/'.basename($Username);

                        if(!is_file($Directory.'/current-id'))
                        {
                            header('Current-ID: 1');
                            new ErrorHandler()->Throw(array('Stream not started.'), 'Access', 204);
                            return;
                        }

                        $CurrentID = (int)file_get_contents($Directory.'/current-id');


                        if(isset($_GET['LastProcessedID']))
                        {
                            $RequestedID = (int)$_GET['LastProcessedID'];
                        }
                        else
                        {
                            $RequestedID = 1;
                        }

                        if(isset($_GET['LastProcessedID']) && $_GET['LastProcessedID'] != 1)
                        {
                            if($CurrentID - $RequestedID >= 3)
                            {
                                $RequestedID = $CurrentID - 1;
                            }
                        }
                        else
                        {
                            $RequestedID = 1;
                        }
                    

                        if(is_file($Directory.'/chunk-'.$RequestedID.'.webm'))
                        {
                            $Chunk = file_get_contents($Directory.'/chunk-'.$RequestedID.'.webm');

                            header('X-Content-Type-Options: nosniff');
                            header('Content-Security-Policy: default-src "self"');
                            header('Content-Type: video/webm');
                            header('Content-Length: '.strlen($Chunk));

                            header('Current-ID: '.$RequestedID);
                            
                            echo $Chunk;
                            exit;               
                        }
                        else
                        {
                            header('Current-ID: '.$CurrentID);
                            new ErrorHandler()->Throw(array('Not found.'), 'Access', 204);
                        }
                    }  
                    
                }
                else
                {
                    new ErrorHandler()->Throw(array('Missing required parameters.'), 'Access', 400);
                }
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing required parameters.'), 'Access', 400);
            }
        }
        catch(Exception $e)
        {
            new ErrorHandler()->Throw(array($e), 'Access', 500);
        }
    }

    public function New() 
    {
        try
        {
            $Data = file_get_contents('php://input');

            $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastActive = NOW() WHERE ID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.Username, VideoSessions.ID AS VideoSessionID FROM Users LEFT JOIN VideoSessions ON VideoSessions.UserID = Users.ID WHERE Users.ID = :UserID LIMIT 1");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

            if(isset($DBResponse['Username']) && !is_null($DBResponse['Username']))
            {
                $Directory = '../VideoStreams/'.basename($DBResponse['Username']);

                if(!is_dir($Directory))
                {
                    mkdir($Directory, 0755, true);
                }

                if(file_exists($Directory.'/current-id'))
                {
                    $LastID = (int)file_get_contents($Directory.'/current-id');
                }
                else
                {
                    $LastID = 0;
                }

                $NextID = $LastID + 1;

                if($LastID > 10)   
                {
                    $RemovalID = $LastID - 10;
                    $OldChunk = $Directory.'/chunk-'.$RemovalID.'.webm';

                    if(file_exists($OldChunk) && ($LastID - 10) > 1)
                    {
                        unlink($OldChunk);
                    }
                }

                file_put_contents($Directory.'/chunk-'.$NextID.'.webm', $Data);
                file_put_contents($Directory.'/current-id', $NextID);
    
                if(isset($DBResponse['VideoSessionID']) && !is_null($DBResponse['VideoSessionID']))
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE VideoSessions SET LastReceived = NOW() WHERE ID = :SessionID");
                    $DBRequest->bindParam(":SessionID", $DBResponse['VideoSessionID']);
                    $DBRequest->execute();
                }
                else
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO VideoSessions (UserID, LastReceived) VALUES (:UserID, NOW())");
                    $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->execute();
                }
            }
        
        }
        catch(Exception $e)
        {
            new ErrorHandler()->Throw(array('Something went wrong.'), 'Access', 500);
        }
    }

    public function Disconnect()
    {
        try
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Username FROM Users WHERE ID = :UserID LIMIT 1");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();



            $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VideoSessions WHERE UserID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();
        }
        catch(Exception $e)
        {
            new ErrorHandler()->Throw(array('Something went wrong.'), 'Access', 500);
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
                $this->Get(preg_replace("/[^a-zA-Z0-9]/", "", trim(strtok($URI[1], '?'))));
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endoint.'), 'Access', 404);
            }
        }
        else if($Method == 'POST')
        {
            if(isset($URI[2]))
            {
                if($URI[2] == 'disconnect')
                {
                    $this->Disconnect();
                }
            }
            else
            {
                $this->New();
            }
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid method.'), 'Access', 500);
        }       
    }
}