<?php
ob_start("ob_gzhandler");
class Voice {
    public function Get($ChannelID = null)
    {
        if(!is_null($ChannelID))
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

            $LastProcessedID = $_GET['LastProcessedID'];

            $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastActive = NOW() WHERE ID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT VoiceData.UserID, Users.Username FROM VoiceData LEFT JOIN Users ON VoiceData.UserID = Users.ID WHERE VoiceData.ChannelID = :ChannelID AND VoiceData.UserID != :UserID AND VoiceData.ID > :LastProcessedID GROUP BY VoiceData.UserID");
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->bindParam(":LastProcessedID", $LastProcessedID);
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

            foreach ($DBResponse as &$Row) {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, Data FROM VoiceData WHERE ChannelID = :ChannelID AND UserID = :UserID AND ID > :LastProcessedID");
                $DBRequest->bindParam(":ChannelID", $ChannelID);
                $DBRequest->bindParam(":LastProcessedID", $LastProcessedID);
                $DBRequest->bindParam(':UserID', $Row['UserID']);
                $DBRequest->execute();

                $Row['VoiceData'] = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

                foreach($Row['VoiceData'] as &$Data)
                {
                    $Data['Data'] = base64_encode($Data['Data']);
                }

                unset($Row['UserID']);
            }

            header('Content-Type: application/json');

            echo json_encode($DBResponse);
            exit;
            
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Access', 404);
        }
    }

    public function New($ChannelID = null) {    

        if(!is_null($ChannelID))
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
            
            $Data = file_get_contents('php://input');

            $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastSpokeAt = NOW(), LastActive = NOW() WHERE ID = :UserID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO VoiceData (UserID, ChannelID, Data) VALUES (:UserID, :ChannelID, :Data)");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->bindParam(":Data", $Data);
            $DBRequest->execute();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM VoiceData WHERE ChannelID = :ChannelID AND UserID = :UserID ORDER BY ID DESC");
            $DBRequest->bindParam(":ChannelID", $ChannelID);
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

            if(count($DBResponse) > 5)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceData WHERE ID < :ID");
                $DBRequest->bindParam(":ID", $DBResponse[4]['ID']);
                $DBRequest->execute();
            }


            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = array('OK');

            $Response->Respond();
                
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Access', 404);
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
            $this->Get($URI[1]);
        }
        if($Method == 'POST')
        {
            $this->New($URI[1]);
        }        
    }
}