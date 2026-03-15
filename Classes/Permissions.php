<?php
class Permissions 
{
    public function UpdateAccessLevels()
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyAccess FROM AccessLevelGlobalPermissions
        INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        INNER JOIN Users ON AccessLevelAssignments.UserID = Users.ID 
        WHERE Users.ID = :UserID AND AccessLevelGlobalPermissions.ModifyAccess = 1");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);
    
        if(!$HasAccess)
        {
            new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
            return;
        }

        $Data = json_decode(file_get_contents('php://input'), true);

        if(count($Data) > 0)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, Name FROM AccessLevels");
            $DBRequest->execute();

            $ExistingAccessLevels = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

            foreach($ExistingAccessLevels as $ExistingAccessLevel)
            {
                $Exists = false;

                foreach($Data as $Row)
                {
                    if(!isset($Row['New']))
                    {
                        if($ExistingAccessLevel['ID'] == $Row['ID'])
                        {
                            $Exists = true;
                        }
                    }
                }

                if(!$Exists && !isset($Row['New']))
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM AccessLevelAssignments WHERE AccessLevelID = :AccessLevelID");
                    $DBRequest->bindParam(":AccessLevelID", $ExistingAccessLevel['ID']);
                    $DBRequest->execute();

                    $AssignedUsers = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

                    if($AssignedUsers != false || $ExistingAccessLevel['ID'] == 1 || $ExistingAccessLevel['ID'] == 2)
                    {
                        new ErrorHandler()->Throw(array('Users assigned to role attemping to be deleted.'), 'Message', 400);
                    }
                    else
                    {
                        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM AccessLevels WHERE ID = :AccessLevelID");
                        $DBRequest->bindParam(":AccessLevelID", $ExistingAccessLevel['ID']);
                        $DBRequest->execute();

                        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM AccessLevelGlobalPermissions WHERE AccessLevelID = :AccessLevelID");
                        $DBRequest->bindParam(":AccessLevelID", $ExistingAccessLevel['ID']);
                        $DBRequest->execute();

                        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM AccessLevelChannelPermissions WHERE AccessLevelID = :AccessLevelID");
                        $DBRequest->bindParam(":AccessLevelID", $ExistingAccessLevel['ID']);
                        $DBRequest->execute();
                    }
                }
            }

            foreach($Data as $Row)
            {
                if($Row['GlobalPermissions']['ModifyChannels'] == true)
                {
                    $Row['GlobalPermissions']['ModifyChannels'] = 1;
                }
                else
                {
                    $Row['GlobalPermissions']['ModifyChannels'] = 0;
                }

                if($Row['GlobalPermissions']['BanUsers'] == true)
                {
                    $Row['GlobalPermissions']['BanUsers'] = 1;
                }
                else
                {
                    $Row['GlobalPermissions']['BanUsers'] = 0;
                }

                if($Row['GlobalPermissions']['ModifyAccess'] == true)
                {
                    $Row['GlobalPermissions']['ModifyAccess'] = 1;
                }
                else
                {
                    $Row['GlobalPermissions']['ModifyAccess'] = 0;
                }

                if($Row['GlobalPermissions']['DeleteUsers'] == true)
                {
                    $Row['GlobalPermissions']['DeleteUsers'] = 1;
                }
                else
                {
                    $Row['GlobalPermissions']['DeleteUsers'] = 0;
                }

                if(isset($Row['New']))
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO AccessLevels (Name, Colour) VALUES (:Name, :Colour)");
                    $DBRequest->bindParam(":Name", $Row['Name']);
                    $DBRequest->bindParam(":Colour", $Row['Colour']);
                    $DBRequest->execute();

                    $AccessLevelID = $GLOBALS['DB']->Handler->lastInsertId();


      
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO AccessLevelGlobalPermissions (ModifyChannels, BanUsers, DeleteMessages, ModifyAccess, ModifyProfiles, DeleteUsers, AccessLevelID) VALUES (:ModifyChannels, :BanUsers, 0, :ModifyAccess, 0, :DeleteUsers, :AccessLevelID)");
                    $DBRequest->bindParam(":ModifyChannels", $Row['GlobalPermissions']['ModifyChannels']);
                    $DBRequest->bindParam(":BanUsers", $Row['GlobalPermissions']['BanUsers']);
                    $DBRequest->bindParam(":ModifyAccess", $Row['GlobalPermissions']['ModifyAccess']);
                    $DBRequest->bindParam(":DeleteUsers", $Row['GlobalPermissions']['DeleteUsers']);
                    $DBRequest->bindParam(":AccessLevelID", $AccessLevelID);
                    $DBRequest->execute();
                    
                }
                else
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE AccessLevels SET Name = :Name, Colour = :Colour WHERE ID = :AccessLevelID");
                    $DBRequest->bindParam(":Name", $Row['Name']);
                    $DBRequest->bindParam(":Colour", $Row['Colour']);
                    $DBRequest->bindParam(":AccessLevelID", $Row['ID']);
                    $DBRequest->execute();

                    if($Row['ID'] != 1)
                    {
                        $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE AccessLevelGlobalPermissions SET ModifyChannels = :ModifyChannels, BanUsers = :BanUsers, ModifyAccess = :ModifyAccess, DeleteUsers = :DeleteUsers WHERE AccessLevelID = :AccessLevelID");
                        $DBRequest->bindParam(":ModifyChannels", $Row['GlobalPermissions']['ModifyChannels']);
                        $DBRequest->bindParam(":BanUsers", $Row['GlobalPermissions']['BanUsers']);
                        $DBRequest->bindParam(":ModifyAccess", $Row['GlobalPermissions']['ModifyAccess']);
                        $DBRequest->bindParam(":DeleteUsers", $Row['GlobalPermissions']['DeleteUsers']);
                        $DBRequest->bindParam(":AccessLevelID", $Row['ID']);
                        $DBRequest->execute();
                    }
                                       
                }
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

    public function UpdateRoleAssignment($Username = null)
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyAccess FROM AccessLevelGlobalPermissions
        INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        INNER JOIN Users ON AccessLevelAssignments.UserID = Users.ID 
        WHERE Users.ID = :UserID AND AccessLevelGlobalPermissions.ModifyAccess = 1");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);
    
        if(!$HasAccess)
        {
            new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
            return;
        }

        if($Username != null)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE AccessLevelAssignments SET AccessLevelID = :AccessLevelID, UserID = :UserID WHERE UserID = :UserID");
            $DBRequest->bindParam(":UserID", $UserID);
            $DBRequest->execute();

        }

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = array('OK');

        $Response->Respond();
    }

    public function ChangeUserRole($Username = null)
    {
        if(!is_null($Username))
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyAccess FROM AccessLevelGlobalPermissions
            INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
            INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
            INNER JOIN Users ON AccessLevelAssignments.UserID = Users.ID 
            WHERE Users.ID = :UserID AND AccessLevelGlobalPermissions.ModifyAccess = 1");

            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);
        
            if(!$HasAccess)
            {
                new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
                return;
            }

            $Data = json_decode(file_get_contents('php://input'), true);

            if(isset($Data['Role']))
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
                $DBRequest->bindParam(":Username", $Username);
                $DBRequest->execute();

                $UserID = $DBRequest->fetchColumn();

                if(($Data['Role']['ID'] == 1 && $GLOBALS['AccessToken']->UserID != 1) || $UserID == 1)
                {
                    new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
                }
                else
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE AccessLevelAssignments SET AccessLevelID = :AccessLevelID WHERE UserID = :UserID");
                    $DBRequest->bindParam(":UserID", $UserID);
                    $DBRequest->bindParam(":AccessLevelID", $Data['Role']['ID']);
                    $DBRequest->execute();
                }
                
                $Response = new ResponseHandler();
                $Response->Code = 200;
                $Response->Data = array('OK');

                $Response->Respond();
            }
            else
            {
                new ErrorHandler()->Throw(array('Missing parameters.'), 'Message', 400);
            }
        }
        else
        {
            new ErrorHandler()->Throw(array('Missing parameters.'), 'Message', 400);
        }
    }

    public function GetAccessLevels()
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Name, ID, Colour FROM AccessLevels");
        $DBRequest->execute();

        $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

        if(isset($_GET['WithGlobalPermissions']))
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyAccess FROM AccessLevelGlobalPermissions
            INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
            INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
            INNER JOIN Users ON AccessLevelAssignments.UserID = Users.ID 
            WHERE Users.ID = :UserID AND AccessLevelGlobalPermissions.ModifyAccess = 1");

            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);
        
            if(!$HasAccess)
            {
                new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
                return;
            }

            foreach($DBResponse as &$Item)
            {
                
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ModifyChannels, BanUsers, DeleteMessages, ModifyAccess, ModifyProfiles, DeleteUsers FROM AccessLevelGlobalPermissions WHERE AccessLevelID = :AccessLevelID");
                $DBRequest->bindParam(":AccessLevelID", $Item['ID']);
                $DBRequest->execute();
                $Item['GlobalPermissions'] = $DBRequest->fetch(PDO::FETCH_ASSOC);
            }
            
        }

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = $DBResponse;

        $Response->Respond();
    }

    public function GetChannelAccessLevels($ChannelID)
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelChannelPermissions.Modify FROM AccessLevelChannelPermissions
        LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelChannelPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID 
        WHERE Users.ID = :UserID");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

        if(!$HasAccess)
        {
            new ErrorHandler()->Throw(array('Access Denied.'), 'Message', 401);
            return;
        }

        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevels.ID, AccessLevels.Name, AccessLevelChannelPermissions.Access, AccessLevelChannelPermissions.Modify FROM AccessLevels
        LEFT JOIN AccessLevelChannelPermissions ON AccessLevels.ID = AccessLevelChannelPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        LEFT JOIN Channels ON AccessLevelChannelPermissions.ChannelID = Channels.ID 
        WHERE Channels.ID = :ChannelID GROUP BY AccessLevels.Name");

        $DBRequest->bindParam(":ChannelID", $ChannelID);
        $DBRequest->execute();

        $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

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
                switch(trim(strtok($URI[1], '?')))
                {
                    case 'access-levels':
                        $this->GetAccessLevels();
                    break;

                    case 'channel-access':
                        $this->GetChannelAccessLevels($URI[2]);
                    break;

                    default:
                        new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Message', 404);
                    break;
                }
            }
            else
            {
                $this->Get($URI[2]);
            }
        }
        if($Method == 'POST')
        {
            if($URI[1] == 'access-levels')
            {
                $this->UpdateAccessLevels();
            }

            else if($URI[1] == 'role-assignment')
            {
                $this->UpdateRoleAssignment($URI[2]);
            }
            
            else if($URI[1] == 'change-role')
            {
                $this->ChangeUserRole($URI[2]);
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Message', 404);
            }
            
        }
    }
}