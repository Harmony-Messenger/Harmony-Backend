<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Message {
    public function New()
    {
        $Data = json_decode(file_get_contents('php://input'), true);
        

        if(isset($Data['ChannelID']) && isset($Data['Message']))
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelChannelPermissions.Access FROM AccessLevelChannelPermissions
            LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelChannelPermissions.AccessLevelID
            INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
            LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
            WHERE AccessLevelChannelPermissions.Access = 1 AND Users.ID = :UserID AND AccessLevelChannelPermissions.ChannelID = :ChannelID");

            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":ChannelID", $Data['ChannelID']);
            $DBRequest->execute();

            $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

            if(!$HasAccess)
            {
                new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
                return;
            }

            $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Messages (UserID, ChannelID, Content, Sent) VALUES (:UserID, :ChannelID, :Content, NOW())");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":ChannelID", $Data['ChannelID']);
            $DBRequest->bindParam(":Content", $Data['Message']);
            $DBRequest->execute();

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

    public function Get($ChannelID = null, $MessageID = null)
    {
               
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelChannelPermissions.Access FROM AccessLevelChannelPermissions
        LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelChannelPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
        WHERE AccessLevelChannelPermissions.Access = 1 AND Users.ID = :UserID AND AccessLevelChannelPermissions.ChannelID = :ChannelID");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->execute();

        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

        if(!$HasAccess)
        {
            new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
            return;
        }
            
        if(!is_null($MessageID))
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM ChannelViews WHERE UserID = :UserID AND ChannelID = :ChannelID LIMIT 1");
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $ChannelViewID = $DBRequest->fetchColumn();

            if($ChannelViewID)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE ChannelViews SET LastViewedAt = NOW() WHERE ID = :ChannelViewID");
                $DBRequest->bindParam(":ChannelViewID", $ChannelViewID);
                $DBRequest->execute();
            }
            else
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO ChannelViews (UserID, ChannelID, LastViewedAt) VALUES (:UserID, :ChannelID, NOW())");
                $DBRequest->bindParam(":ChannelID", $ChannelID);
                $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();
            }

            if($MessageID == 'latest')
            {
                if(isset($_GET['LatestMessageID']))
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastActive = NOW() WHERE ID = :UserID");
                    $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                    $DBRequest->execute();

                    $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Messages.ID, Messages.Content, Messages.ChannelID, Messages.Sent, Messages.Edited, Users.Username, AccessLevels.Colour 
                    FROM Messages 
                    LEFT JOIN Users ON Users.ID = Messages.UserID 
                    LEFT JOIN AccessLevelAssignments ON Users.ID = AccessLevelAssignments.UserID 
                    LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID 
                    WHERE Messages.ChannelID = :ChannelID AND Messages.ID > :LastMessageID ORDER BY Sent DESC");
                    $DBRequest->bindParam(":ChannelID", $ChannelID);
                    $DBRequest->bindParam(":LastMessageID", $_GET['LatestMessageID']);
                    $DBRequest->execute();

                    $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            else
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastActive = NOW() WHERE ID = :UserID");
                $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Messages.ID, Messages.Content, Messages.ChannelID, Messages.Sent, Messages.Edited, Users.Username, AccessLevels.Colour FROM Messages LEFT JOIN Users ON Users.ID = Messages.UserID LEFT JOIN AccessLevelAssignments ON Users.ID = AccessLevelAssignments.UserID LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID WHERE Messages.ID = :MessageID AND Messages.ChannelID = :ChannelID");
                $DBRequest->bindParam(":MessageID", $MessageID);
                $DBRequest->bindParam(":ChannelID", $ChannelID);
                $DBRequest->execute();

                $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);
            }
            
        }
        else
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastActive = NOW() WHERE ID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();
            
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Messages.ID, Messages.Content, Messages.ChannelID, Messages.Sent, Messages.Edited, Users.Username, AccessLevels.Colour FROM Messages LEFT JOIN Users ON Users.ID = Messages.UserID LEFT JOIN AccessLevelAssignments ON Users.ID = AccessLevelAssignments.UserID LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID WHERE Messages.ChannelID = :ChannelID ORDER BY Sent DESC");
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);
        }

        

        $Response = new ResponseHandler();
        $Response->Code = 200;

        if(isset($DBResponse))
        {
            $Response->Data = $DBResponse;
        }
        else
        {   
            $Response->Data = array('OK');
        }

        $Response->Respond();


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
            if(isset($URI[1]) && isset($URI[2]))
            {
                $this->Get($URI[1], trim(strtok($URI[2], '?')));
            }
            else
            {
                $this->Get($URI[1]);
            }
        }
        else if($Method == 'POST')
        {
            $this->New();
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid Method.'), 'Message', 400);
        }
    }
}