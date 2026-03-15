<?php
class Notifications
{
    public function Get($Type)
    {
        if($Type == 'channel')
        {

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, LastViewedAt FROM ChannelViews WHERE UserID = :UserID ORDER BY LastViewedAt DESC LIMIT 1");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $LastChannel = $DBRequest->fetch(PDO::FETCH_ASSOC);

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT COUNT(Messages.ID) AS Total, Messages.ChannelID AS ID 
            FROM Messages
            INNER JOIN ChannelViews ON Messages.ChannelID = ChannelViews.ChannelID AND ChannelViews.UserID = :UserID
            INNER JOIN AccessLevelChannelPermissions ON Messages.ChannelID = AccessLevelChannelPermissions.ChannelID
            INNER JOIN AccessLevelAssignments ON AccessLevelChannelPermissions.AccessLevelID = AccessLevelAssignments.AccessLevelID
            WHERE Messages.Sent > ChannelViews.LastViewedAt
            AND AccessLevelChannelPermissions.Access = 1
            AND AccessLevelAssignments.UserID = :UserID2
            GROUP BY Messages.ChannelID");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":UserID2", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);
        }
        else if($Type == 'dm')
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT COUNT(DirectMessages.ID) AS Total, DirectMessages.SessionID AS ID,
            CASE 
            WHEN DirectMessageSessions.RequesterID = :UserID THEN ToUser.Username
            ELSE FromUser.Username
            END AS Username
            FROM DirectMessages
            INNER JOIN DirectMessageViews ON DirectMessages.SessionID = DirectMessageViews.SessionID AND DirectMessageViews.UserID = :UserID2
            INNER JOIN DirectMessageSessions ON DirectMessages.SessionID = DirectMessageSessions.ID
            INNER JOIN Users AS FromUser ON DirectMessageSessions.RequesterID = FromUser.ID
            INNER JOIN Users AS ToUser ON DirectMessageSessions.ResponderID = ToUser.ID
            WHERE DirectMessages.Sent > DirectMessageViews.LastViewedAt
            GROUP BY DirectMessages.SessionID, Username");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":UserID2", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();

            $LastChannelViewed = $DBRequest->fetchColumn();

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT COUNT(ID) FROM Messages WHERE ChannelID != :ChannelID AND Sent > NOW() GROUP BY ChannelID");
            $DBRequest->bindParam(":ChannelID", $LastChannelViewed);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Me', 404);
            return;
        }

        $Response = new ResponseHandler();
        $Response->Code = 200;

        if($DBResponse)
        {
            $Response->Data = $DBResponse;
        }
        else
        {   
            $Response->Data = array();
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
            if(isset($URI[1]))
            {
                $this->Get($URI[1]);
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Me', 404);
            }
            
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid endpoint.'), 'Me', 404);
        }
    }
}