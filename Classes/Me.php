<?php
class Me {
    public function Get()
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.Username, AccessLevels.Name AS Role, 
        AccessLevelGlobalPermissions.ModifyChannels AS CanModifyChannels, 
        AccessLevelGlobalPermissions.BanUsers as CanBanUsers, 
        AccessLevelGlobalPermissions.DeleteMessages,
        AccessLevelGlobalPermissions.DeleteMessages AS CanDeleteUsers,
        AccessLevelGlobalPermissions.ModifyAccess AS CanModifyAccess FROM Users 
        LEFT JOIN AccessLevelAssignments ON AccessLevelAssignments.UserID = Users.ID
        LEFT JOIN AccessLevels ON AccessLevelAssignments.AccessLevelID = AccessLevels.ID
        LEFT JOIN AccessLevelGlobalPermissions ON AccessLevelGlobalPermissions.AccessLevelID = AccessLevels.ID
        WHERE Users.ID = :UserID");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

        if(!$DBResponse)
        {
            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Me', 404);
        }
        else
        {
            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = $DBResponse;
            $Response->Respond();
        }
        
    }
    
    public function ProcessRequest()
    {
        $Method = $_SERVER['REQUEST_METHOD'];

        if($Method == 'GET')
        {
            $this->Get();
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Me', 404);
        }
    }
}