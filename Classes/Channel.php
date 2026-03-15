<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Channel {
    public function Update($ChannelID)
    {
        if(isset($ChannelID))
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelChannelPermissions.Modify FROM AccessLevelChannelPermissions
            INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelChannelPermissions.AccessLevelID
            INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
            LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
            WHERE AccessLevelChannelPermissions.Modify = 1 AND Users.ID = :UserID AND AccessLevelChannelPermissions.ChannelID = :ChannelID");

            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->execute();

            $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

            if(!$HasAccess)
            {
                new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
                return;
            }

            $Data = json_decode(file_get_contents('php://input'), true);

            $AccessLevelsExist = true;

            foreach($Data['Permissions'] as $Permission)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Name FROM AccessLevels WHERE ID = :AccessLevelID LIMIT 1");
                $DBRequest->bindParam(":AccessLevelID", $Permission['ID']);
                $DBRequest->execute();

                $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

                if($DBResponse['Name'] != $Permission['Name'])
                {
                    $AccessLevelsExist = false;
                }
            }
        
            if(isset($Data['Name']) && isset($Data['Permissions']) && isset($Data['Type']) && $AccessLevelsExist)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Channels SET Name = :Name WHERE ID = :ChannelID");
                $DBRequest->bindParam(":Name", $Data['Name']);
                $DBRequest->bindParam(":ChannelID", $ChannelID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM AccessLevelChannelPermissions WHERE ChannelID = :ChannelID");
                $DBRequest->bindParam(":ChannelID", $ChannelID);
                $DBRequest->execute();

                foreach($Data['Permissions'] as $Permission)
                { 
                    $Modify = $Permission['Modify'] == true ? 1 : 0;
                    $Access = $Permission['Access'] == true ? 1 : 0;

                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO AccessLevelChannelPermissions (ChannelID, AccessLevelID, Modify, Access) VALUES (:ChannelID, :AccessLevelID, :Modify, :Access)");
                    $DBRequest->bindParam(":ChannelID", $ChannelID);
                    $DBRequest->bindParam(":AccessLevelID", $Permission['ID']);
                    $DBRequest->bindParam(":Modify", $Modify);
                    $DBRequest->bindParam(":Access", $Access);
                    $DBRequest->execute();
                }

                $Response = new ResponseHandler();
                $Response->Code = 200;
                $Response->Data = array('OK');

                $Response->Respond();
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing required parameters.'), 'Message', 400);
            }
        }
        else
        {
            new ErrorHandler()->Throw(array('Missing required parameters.'), 'Message', 400);
        }
    }

    public function New()
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyChannels FROM AccessLevelGlobalPermissions
        LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
        WHERE AccessLevelGlobalPermissions.ModifyChannels = 1 AND Users.ID = :UserID");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

        if(!$HasAccess)
        {
            new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
            return;
        }

        $Data = json_decode(file_get_contents('php://input'), true);

        $AccessLevelsExist = true;

        foreach($Data['Permissions'] as $Permission)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Name FROM AccessLevels WHERE ID = :AccessLevelID LIMIT 1");
            $DBRequest->bindParam(":AccessLevelID", $Permission['ID']);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

            if($DBResponse['Name'] != $Permission['Name'])
            {
                $AccessLevelsExist = false;
            }
        }
        
        if(isset($Data['Name']) && isset($Data['Permissions']) && isset($Data['Type']) && $AccessLevelsExist)
        {
            $Type = $Data['Type'] == 'Text' ? 'T' : 'V';

            $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Channels (Name, Type) VALUES (:Name, :Type)");
            $DBRequest->bindParam(":Name", $Data['Name']);
            $DBRequest->bindParam(":Type", $Type);
            $DBRequest->execute();
            
            $ChannelID = $GLOBALS['DB']->Handler->lastInsertId();

            foreach($Data['Permissions'] as $Permission)
            { 
                $Modify = $Permission['Modify'] == true ? 1 : 0;
                $Access = $Permission['Access'] == true ? 1 : 0;

                $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO AccessLevelChannelPermissions (ChannelID, AccessLevelID, Modify, Access) VALUES (:ChannelID, :AccessLevelID, :Modify, :Access)");
                $DBRequest->bindParam(":ChannelID", $ChannelID);
                $DBRequest->bindParam(":AccessLevelID", $Permission['ID']);
                $DBRequest->bindParam(":Modify", $Modify);
                $DBRequest->bindParam(":Access", $Access);
                $DBRequest->execute();
            }

            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = array('OK');

            $Response->Respond();
        }
        else
        {
            new ErrorHandler()->Throw(array('Missing required parameters.'), 'Message', 400);
        }

    }

    public function DeleteChannel($ChannelID)
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyChannels FROM AccessLevelGlobalPermissions
        LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        LEFT JOIN AccessLevelChannelPermissions ON AccessLevelChannelPermissions.AccessLevelID = AccessLevels.ID
        LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
        WHERE AccessLevelGlobalPermissions.ModifyChannels = 1 OR AccessLevelChannelPermissions.Modify = 1 AND Users.ID = :UserID");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

        if(!$HasAccess)
        {
            new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
            return;
        }

        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM Channels WHERE ID = :ChannelID");
        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->execute();

        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM AccessLevelChannelPermissions WHERE ChannelID = :ChannelID");
        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->execute();

        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceChannelSessions WHERE ChannelID = :ChannelID");
        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->execute();

        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceData WHERE ChannelID = :ChannelID");
        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->execute();

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = array('OK');

        $Response->Respond();

    }

    public function DeleteActivity($ChannelID)
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

        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM ChannelActivity WHERE ChannelActivity.ChannelID = :ChannelID AND ChannelActivity.UserID = :UserID");
        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = array('OK');

        $Response->Respond();
    }

    public function GetActivity($ChannelID)
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

        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM ChannelActivity WHERE Time < NOW() - INTERVAL 30 MINUTE");
        $DBRequest->execute();

        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.Username, ChannelActivity.Type, ChannelActivity.Time FROM ChannelActivity INNER JOIN Users ON Users.ID = ChannelActivity.UserID WHERE ChannelActivity.ChannelID = :ChannelID");
        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->execute();

        $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = $DBResponse;

        $Response->Respond();
    }

    public function NewActivity($ChannelID)
    {
        $Data = json_decode(file_get_contents('php://input'), true);

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

        if($Data['Type'] == 'Typing')
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM ChannelActivity WHERE UserID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO ChannelActivity (Type, UserID, ChannelID, Time) VALUES ('Typing', :UserID, :ChannelID, NOW())");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = $DBResponse;

            $Response->Respond();
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid parameters.'), 'Message', 400);
        }          
    }

    public function Disconnect($ChannelID)
    {
        $Data = json_decode(file_get_contents('php://input'), true);

        if(isset($ChannelID))
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

            $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceChannelSessions WHERE UserID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceData WHERE UserID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
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

    public function Join($ChannelID)
    {
        $Data = json_decode(file_get_contents('php://input'), true);

        if(isset($ChannelID))
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

            $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceChannelSessions WHERE UserID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceData WHERE UserID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO VoiceChannelSessions (UserID, ChannelID, Status) VALUES (:UserID, :ChannelID, 'Connected')");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":ChannelID", $ChannelID);
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

    public function Get($ChannelID = null)
    {
        if(is_null($ChannelID) || $ChannelID == '')
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Channels.ID, Channels.Type, Channels.Name, AccessLevelChannelPermissions.Modify AS CanModify
            FROM Channels 
            LEFT JOIN AccessLevelChannelPermissions ON AccessLevelChannelPermissions.ChannelID = Channels.ID
            LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelChannelPermissions.AccessLevelID
            INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
            LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
            WHERE AccessLevelChannelPermissions.Access = 1 AND Users.ID = :UserID ORDER BY Channels.ID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();
        }
        else
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Channels.ID, Channels.Type, Channels.Name, AccessLevelChannelPermissions.Modify AS CanModify FROM Channels LEFT JOIN AccessLevelChannelPermissions ON AccessLevelChannelPermissions.ChannelID = Channels.ID
            LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelChannelPermissions.AccessLevelID
            INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
            LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
            WHERE AccessLevelChannelPermissions.Access = 1 AND Channels.ID = :ChannelID AND Users.ID = :UserID ORDER BY Channels.ID");
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();
        }

        $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

        foreach($DBResponse as &$Channel)
        {
            if($Channel['Type'] == 'V')
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE vcs FROM VoiceChannelSessions vcs INNER JOIN Users u ON u.ID = vcs.UserID WHERE u.LastActive < NOW() - INTERVAL 2 MINUTE");
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE vd FROM VoiceData vd LEFT JOIN VoiceChannelSessions vcs ON vd.UserID = vcs.UserID WHERE vcs.ID IS NULL");
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VideoSessions WHERE LastReceived < NOW() - INTERVAL 10 SECOND");
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT VoiceChannelSessions.Status, Users.ID AS UserID, AccessLevels.Colour, Users.Username, 
                (Users.LastSpokeAt > NOW() - INTERVAL 1 SECOND) AS IsSpeaking,
                (VideoSessions.LastReceived > NOW() - INTERVAL 5 SECOND) AS IsScreenSharing
                FROM 
                VoiceChannelSessions 
                LEFT JOIN Users ON Users.ID = VoiceChannelSessions.UserID 
                LEFT JOIN Channels ON Channels.ID = VoiceChannelSessions.ChannelID
                LEFT JOIN AccessLevelAssignments ON AccessLevelAssignments.UserID = Users.ID
                LEFT JOIN VideoSessions ON VideoSessions.UserID = Users.ID
                LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
                WHERE Channels.ID = :ChannelID");
                $DBRequest->bindParam(":ChannelID", $Channel['ID']);
                $DBRequest->execute();

                $Channel['ConnectedUsers'] = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

                foreach($Channel['ConnectedUsers'] as &$ConnectedUser)
                {
                    if($ConnectedUser['UserID'] == $GLOBALS['AccessToken']->UserID)
                    {
                        $ConnectedUser['Me'] = true;
                    }

                    $Directory = $GLOBALS['Config']['Service']['BackendPath'].'/VideoStreams/'.basename($ConnectedUser['Username']);

                    if($ConnectedUser['IsScreenSharing'] == null)
                    {
                        $ConnectedUser['IsScreenSharing'] = 0;
                    }
                    
                    if($ConnectedUser['IsScreenSharing'] == 0 && is_dir($Directory))
                    {
                        $Files = glob($Directory.'/*');

                        foreach($Files as $File)
                        {
                            if(is_file($File))
                            {
                                unlink($File);
                            }
                        }

                        rmdir($Directory);
                    }

                    unset($ConnectedUser['UserID']);
                }
            }
            else
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT LastViewedAt FROM ChannelViews WHERE UserID = :UserID AND ChannelID = :ChannelID");
                $DBRequest->bindParam(":ChannelID", $Channel['ID']);
                $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();

                $LastViewedAt = $DBRequest->fetchColumn();

                if($LastViewedAt)
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT COUNT(ID) FROM Messages WHERE ChannelID = :ChannelID AND Sent > :LastViewedAt");
                    $DBRequest->bindParam(":ChannelID", $Channel['ID']);
                    $DBRequest->bindParam(":LastViewedAt", $LastViewedAt);
                    $DBRequest->execute();

                    $Channel['Notifications'] = $DBRequest->fetchColumn();
                }
                else
                {
                    $Channel['Notifications'] = array();
                }
            }
        }

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = $DBResponse;

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
            if(isset($URI[1]))
            {
                if(isset($URI[2]))
                {
                    switch($URI[2])
                    {
                        case 'activity':
                            $this->GetActivity($URI[1]);
                        break;
                        
                        default:
                            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
                        break;
                    }
                }
                else
                {
                    $this->Get($URI[1]);
                }
            }
            else
            {
                $this->Get();
            }
        }
        else if($Method == 'POST')
        {
            if(isset($URI[1]))
            {
                if(isset($URI[2]))
                {
                    switch($URI[2])
                    {
                        case 'join':
                            $this->Join($URI[1]);
                        break;

                        case 'disconnect':
                            $this->Disconnect($URI[1]);
                        break;

                        case 'activity':
                            $this->NewActivity($URI[1]);
                        break;

                        default:
                            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
                        break;
                    }
                }
                else
                {
                    $this->Update($URI[1]);
                }
            }
            else
            {
                $this->New();
            }
        }

        else if($Method == 'DELETE')
        {
            if(isset($URI[1]))
            {
                if(isset($URI[2]))
                {
                    switch($URI[2])
                    {
                        case 'activity':
                            $this->DeleteActivity($URI[1]);
                        break;

                        default:
                            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
                        break;
                    }
                }
                else
                {
                    $this->DeleteChannel($URI[1]);
                }
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
            }
        }

        else if($Method == 'UPDATE')
        {
            
        }

    }
}