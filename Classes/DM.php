<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class DM {
    public function Rekey($Username = null)
    {
        if(!is_null($Username))
        {
            $Data = json_decode(file_get_contents('php://input'), true);

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, RequesterID, ResponderID, RequesterPublicKey, ResponderPublicKey FROM DirectMessageSessions WHERE (RequesterID = :UserID AND ResponderID = :ThisUserID) OR (RequesterID = :ThisUserID AND ResponderID = :UserID) LIMIT 1");
            $DBRequest->bindParam(":UserID", $UserID);
            $DBRequest->bindParam(":ThisUserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

            if(isset($DBResponse['ID']))
            {
                if($DBResponse['RequesterID'] == $GLOBALS['AccessToken']->UserID)
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE DirectMessageSessions SET RequesterPublicKey = :PublicKey WHERE ID = :SessionID");
                    $DBRequest->bindParam(":PublicKey", $Data['PublicKey']);
                    $DBRequest->bindParam(":SessionID", $DBResponse['ID']);
                    $DBRequest->execute();
                }
                else
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE DirectMessageSessions SET ResponderPublicKey = :PublicKey WHERE ID = :SessionID");
                    $DBRequest->bindParam(":PublicKey", $Data['PublicKey']);
                    $DBRequest->bindParam(":SessionID", $DBResponse['ID']);
                    $DBRequest->execute();
                }

                echo 'ppp';

                $Response = new ResponseHandler();
                $Response->Code = 200;
                $Response->Data = array('OK');

                $Response->Respond();
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing session.'), 'Message', 400);
            }
        }
        else
        {
            new ErrorHandler()->Throw(array('Missing required fields.'), 'Message', 400);
        }
    }

    public function Get($Username = null)
    {
        try
        {
            if(!is_null($Username))
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
                $DBRequest->bindParam(":Username", $Username);
                $DBRequest->execute();

                $UserID = $DBRequest->fetchColumn();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, RequesterID, ResponderID, RequesterPublicKey, ResponderPublicKey FROM DirectMessageSessions WHERE (RequesterID = :UserID AND ResponderID = :ThisUserID) OR (RequesterID = :ThisUserID AND ResponderID = :UserID) LIMIT 1");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->bindParam(":ThisUserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();

                $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

                if(isset($DBResponse['ID']))
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM DirectMessageViews WHERE UserID = :UserID AND SessionID = :SessionID LIMIT 1");
                    $DBRequest->bindParam(":SessionID", $DBResponse['ID']);
                    $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->execute();

                    $SessionViewID = $DBRequest->fetchColumn();

                    if($SessionViewID)
                    {
                        $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE DirectMessageViews SET LastViewedAt = NOW() WHERE ID = :SessionViewID");
                        $DBRequest->bindParam(":SessionViewID", $SessionViewID);
                        $DBRequest->execute();
                    }
                    else
                    {
                        $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO DirectMessageViews (UserID, SessionID, LastViewedAt) VALUES (:UserID, :SessionID, NOW())");
                        $DBRequest->bindParam(":SessionID", $DBResponse['ID']);
                        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                        $DBRequest->execute();
                    }

                    $PublicKeyRequired = false;

                    if($DBResponse['RequesterID'] == $GLOBALS['AccessToken']->UserID)
                    {
                        if($DBResponse['RequesterPublicKey'] == null)
                        {
                            $DBResponse['PublicKeyRequired'] = true;
                        }

                        $DBResponse['RecipientPublicKey'] = $DBResponse['ResponderPublicKey'];
                        $DBResponse['MyPublicKey'] = $DBResponse['RequesterPublicKey'];
                        
                    }
                    else if($DBResponse['ResponderID'] == $GLOBALS['AccessToken']->UserID)
                    {
                        if($DBResponse['ResponderPublicKey'] == null)
                        {
                            $DBResponse['PublicKeyRequired'] = true;
                        }

                        $DBResponse['RecipientPublicKey'] = $DBResponse['RequesterPublicKey'];
                        $DBResponse['MyPublicKey'] = $DBResponse['ResponderPublicKey'];
                    }

                    if($DBResponse['ID'] != '')
                    {
                        if(isset($_GET['LatestMessageID']))
                        {
                            try{
                                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, FromUserID, ToUserID, Content, FromUserPrivateKey, ToUserPrivateKey, IV, Sent FROM DirectMessages WHERE SessionID = :SessionID AND ID > :LastMessageID ORDER BY ID DESC");
                                $DBRequest->bindParam(":SessionID", $DBResponse['ID']);
                                $DBRequest->bindParam(":LastMessageID", $_GET['LatestMessageID']);
                                $DBRequest->execute();
                            }
                            catch(Exception $e)
                            {
                                new ErrorHandler()->Throw(array('Missing required fields.'), 'Message', 400);
                            }
                        }
                        else
                        {
                            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, FromUserID, ToUserID, Content, FromUserPrivateKey, ToUserPrivateKey, IV, Sent FROM DirectMessages WHERE SessionID = :SessionID ORDER BY ID DESC");
                            $DBRequest->bindParam(":SessionID", $DBResponse['ID']);
                            $DBRequest->execute();
                        }

                        $DBResponse['Messages'] = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

                        foreach($DBResponse['Messages'] as &$Row)
                        {
                            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.Username, AccessLevels.Colour AS UsernameColour FROM Users LEFT JOIN AccessLevelAssignments ON UserID = Users.ID LEFT JOIN AccessLevels ON AccessLevelID = AccessLevels.ID WHERE Users.ID = :UserID LIMIT 1");
                            $DBRequest->bindParam(":UserID", $Row['FromUserID']);
                            $DBRequest->execute();

                            $UserData = $DBRequest->fetch(PDO::FETCH_ASSOC);

                            $Row['Username'] = $UserData['Username'];
                            $Row['UsernameColour'] = $UserData['UsernameColour'];

                            if($Row['FromUserID'] == $GLOBALS['AccessToken']->UserID)
                            {
                                $Row['PrivateKey'] = $Row['FromUserPrivateKey'];
                                $Row['Me'] = true;
                            }
                            else if($Row['ToUserID'] == $GLOBALS['AccessToken']->UserID)
                            {
                                $Row['PrivateKey'] = $Row['ToUserPrivateKey'];
                            }

                            unset($Row['FromUserPrivateKey']);
                            unset($Row['ToUserPrivateKey']);
                            unset($Row['FromUserID']);
                            unset($Row['ToUserID']);
                        }

                        unset($DBResponse['ResponderPublicKey']);
                        unset($DBResponse['RequesterPublicKey']);
                        unset($DBResponse['ResponderID']);
                        unset($DBResponse['RequesterID']);
                    }
                }

                $Response = new ResponseHandler();
                $Response->Code = 200;

                if(!$DBResponse)
                {
                    $Response->Data = array('');
                }
                else
                {
                    $Response->Data = $DBResponse;
                }

                $Response->Respond();
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing required fields.'), 'Message', 400);
            }
        }
        catch(Exception $e)
        {
            print_r($e);
        }

    }

    public function New($ToUsername)
    {
        try{
            $Data = json_decode(file_get_contents('php://input'), true);

            if(isset($ToUsername) && isset($Data['Content']))
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
                $DBRequest->bindParam(":Username", $ToUsername);
                $DBRequest->execute();

                $UserID = $DBRequest->fetchColumn();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, ResponderID, ResponderPublicKey FROM DirectMessageSessions WHERE (RequesterID = :UserID AND ResponderID = :ThisUserID) OR (RequesterID = :ThisUserID AND ResponderID = :UserID) LIMIT 1");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->bindParam(":ThisUserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();

                $Session = $DBRequest->fetch(PDO::FETCH_ASSOC);

                if($Session)
                {
                    if($Session['ResponderID'] == $GLOBALS['AccessToken']->UserID && $Session['ResponderPublicKey'] == null)
                    {
                        $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE DirectMessageSessions SET ResponderPublicKey = :ResponderPublicKey WHERE ID = :SessionID");
                        $DBRequest->bindParam(":ResponderPublicKey", $Data['PublicKey']);
                        $DBRequest->bindParam(":SessionID", $Session['ID']);
                        $DBRequest->execute();
                    }

                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO DirectMessages (SessionID, FromUserID, ToUserID, Content, IV, FromUserPrivateKey, ToUserPrivateKey, Sent) VALUES (:SessionID, :FromUserID, :ToUserID, :Content, :IV, :FromUserPrivateKey, :ToUserPrivateKey, NOW())");
                    $DBRequest->bindParam(":SessionID", $Session['ID']);
                    $DBRequest->bindParam(":FromUserID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->bindParam(":ToUserID", $UserID);
                    $DBRequest->bindParam(":Content", $Data['Content']);
                    $DBRequest->bindParam(":IV", $Data['IV']);
                    $DBRequest->bindParam(":FromUserPrivateKey", $Data['FromUserPrivateKey']);
                    $DBRequest->bindParam(":ToUserPrivateKey", $Data['ToUserPrivateKey']);
                    $DBRequest->execute();
                }
                else
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO DirectMessageSessions (RequesterID, ResponderID, RequesterPublicKey) VALUES (:RequesterID, :ResponderID, :RequesterPublicKey)");
                    $DBRequest->bindParam(":RequesterID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->bindParam(":ResponderID", $UserID);
                    $DBRequest->bindParam(":RequesterPublicKey", $Data['PublicKey']);
                    $DBRequest->execute();

                    $SessionID = $GLOBALS['DB']->Handler->lastInsertId();

                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO DirectMessages (SessionID, FromUserID, ToUserID, Content, Sent) VALUES (:SessionID, :FromUserID, :ToUserID, :Content, NOW())");
                    $DBRequest->bindParam(":SessionID", $SessionID);
                    $DBRequest->bindParam(":FromUserID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->bindParam(":ToUserID", $UserID);
                    $DBRequest->bindParam(":Content", $Data['Content']);
                    $DBRequest->execute();
                }

                $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastActive = NOW() WHERE ID = :UserID");
                $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();         

                $Response = new ResponseHandler();
                $Response->Code = 200;
                $Response->Data = array('OK');

                $Response->Respond();
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing required fields.'), 'Message', 400);
            }
        }
        catch(Exception $e)
        {
            new ErrorHandler()->Throw(array('Something went really wrong.'), 'Message', 500);
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
            if(isset($URI[1]) && $URI[1] != '')
            {
                $this->Get($URI[1]);
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing required fields.'), 'Message', 400);
            }
        }
        if($Method == 'POST')
        {
            if(isset($URI[1]) && $URI[1] != '')
            {
                if(isset($URI[2]))
                {
                    if($URI[2] == 'rekey')
                    {   
                        $this->Rekey($URI[1]);
                    }
                }
                else
                {
                    $this->New($URI[1]);
                }
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing required fields.'), 'Message', 400);
            }
        }
    }
}