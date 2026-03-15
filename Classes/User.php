<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User {
    public function Get($UserID = null)
    {
        if(is_null($UserID) || $UserID == '')
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyAccess, AccessLevelGlobalPermissions.BanUsers FROM AccessLevelGlobalPermissions
            INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
            INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
            INNER JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
            WHERE AccessLevelGlobalPermissions.BanUsers = 1 AND Users.ID = :UserID");

            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->execute();
            $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

            if($HasAccess)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.ID, Users.Username, Users.LastActive,  
                CASE
                    WHEN Users.LastActive > (NOW() - INTERVAL 30 MINUTE) THEN 1 ELSE 0
                END AS IsOnline, AccessLevels.Name AS AccessLevelName, AccessLevels.ID AS AccessLevelID, AccessLevels.Colour AS AccessLevelColour,
                Profiles.Tagline, Profiles.Bio,
                CASE WHEN EXISTS (SELECT 1 FROM ActivationCodes WHERE ActivationCodes.UserID = Users.ID) THEN 1 ELSE 0 END AS IsPendingActivation,
                CASE WHEN EXISTS (SELECT 1 AS IsBanned FROM Bans WHERE Bans.UserID = Users.ID) THEN 1 ELSE 0 END AS IsBanned
                FROM Users 
                INNER JOIN AccessLevelAssignments ON Users.ID = AccessLevelAssignments.UserID 
                INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
                INNER JOIN Profiles ON Users.ID = Profiles.UserID
                ORDER BY IsBanned ASC, IsPendingActivation ASC, IsOnline DESC, Users.Username");
                $DBRequest->execute();
            }
            else
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.ID, Users.Username, Users.LastActive,  
                CASE
                    WHEN Users.LastActive > (NOW() - INTERVAL 30 MINUTE) THEN 1 ELSE 0
                END AS IsOnline, AccessLevels.Name AS AccessLevelName, AccessLevels.ID AS AccessLevelID, AccessLevels.Colour AS AccessLevelColour,
                Profiles.Tagline, Profiles.Bio FROM Users 
                LEFT JOIN AccessLevelAssignments ON Users.ID = AccessLevelAssignments.UserID 
                LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
                INNER JOIN Profiles ON Users.ID = Profiles.UserID
                WHERE NOT EXISTS (SELECT 1 FROM Bans WHERE Bans.UserID = Users.ID)
                AND NOT EXISTS (SELECT 1 FROM ActivationCodes WHERE ActivationCodes.UserID = Users.ID) ORDER BY IsOnline DESC, Users.Username");
                $DBRequest->execute();
            }
        }
        else
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.ID, Users.Username, Users.LastActive, CASE
                WHEN Users.LastActive > (NOW() - INTERVAL 30 MINUTE) THEN 1 ELSE 0
            END AS IsOnline, AccessLevels.Name AS AccessLevelName, AccessLevels.ID AS AccessLevelID, AccessLevels.Colour AS AccessLevelColour, Profiles.Tagline, Profiles.Bio 
            FROM Users 
            LEFT JOIN AccessLevelAssignments ON Users.ID = AccessLevelAssignments.UserID 
            LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID 
            INNER JOIN Profiles ON Users.ID = Profiles.UserID
            WHERE Username = :Username");
            $DBRequest->bindParam(":Username", $UserID);
            $DBRequest->execute();
        }

        $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

        foreach($DBResponse as &$Item)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT COUNT(DirectMessages.ID) FROM DirectMessageSessions 
            INNER JOIN DirectMessages ON DirectMessages.SessionID = DirectMessageSessions.ID
            INNER JOIN DirectMessageViews ON DirectMessageViews.SessionID = DirectMessageSessions.ID
            WHERE DirectMessages.ToUserID = :UserID
            AND DirectMessages.FromUserID = :FromUserID
            AND DirectMessages.Sent > DirectMessageViews.LastViewedAt");
            $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
            $DBRequest->bindParam(":FromUserID", $Item['ID']);
            $DBRequest->execute();

            $Item['Notifications'] = $DBRequest->fetchColumn();

            if($Item['ID'] == $GLOBALS['AccessToken']->UserID)
            {
                $Item['Me'] = true;
            }

            $Item['Profile']['Tagline'] = $Item['Tagline'];
            $Item['Profile']['Bio'] = $Item['Bio'];

            unset($Item['Tagline']);
            unset($Item['Bio']);
            unset($Item['ID']);
        }

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = $DBResponse;

        $Response->Respond();
    }

    private function Login()
    {
        try {
            $Data = json_decode(file_get_contents('php://input'), true);

            if(isset($Data['Username']) && isset($Data['Password']) && !is_null($Data['Username']) && !is_null($Data['Password']))
            {
                if($Data['Username'] == '' || $Data['Password'] == '')
                {
                    new ErrorHandler()->Throw(array('Invalid username/password.'), 'Login', 401);
                }
                else
                {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.ID AS ID, Users.Username AS Username, ActivationCodes.ID AS ActivationCodeID, Bans.ID AS BanID, Bans.Reason, Bans.End FROM Users LEFT JOIN ActivationCodes ON Users.ID = ActivationCodes.UserID LEFT JOIN Bans ON Users.ID = Bans.UserID WHERE Users.Username = :Username LIMIT 1");
                    $DBRequest->bindParam(":Username", $Data['Username']);
                    $DBRequest->execute();

                    $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

                    if($DBResponse['BanID'] != null)
                    {
                        $Message['Status'] = 'Banned';
                        $Message['Reason'] = $DBResponse['Reason'];
                        $Message['End'] = $DBResponse['End'];

                        new ErrorHandler()->Throw($Message, 'Login', 401);
                    }
                    else if($DBResponse['ActivationCodeID'] != null)
                    {
                        new ErrorHandler()->Throw(array('Account not activated.'), 'Login', 401);
                    }
                    else
                    {
                        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID, Username, Password FROM Users WHERE ID = :UserID LIMIT 1");
                        $DBRequest->bindParam(":UserID", $DBResponse['ID']);
                        $DBRequest->execute();

                        $DBResponse = $DBRequest->fetch(PDO::FETCH_ASSOC);

                        if(password_verify($Data['Password'], $DBResponse['Password']))
                        {
                            $JWTData['UserID'] = $DBResponse['ID'];
                            $JWTData['Username'] = $DBResponse['Username'];

                            $Date = new DateTimeImmutable();
                            $ExpiresAt = $Date->modify('+30 days')->getTimestamp();
                            
                            $JWTData['iat'] = $Date->getTimestamp();
                            $JWTData['iss'] = $GLOBALS['Config']['Service']['PublicURI'];
                            $JWTData['nbf'] = $Date->getTimestamp();
                            $JWTData['exp'] = $ExpiresAt;

                            $jwt = JWT::encode($JWTData, $GLOBALS['Config']['Security']['JWTKey'], 'HS512');

                            $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET LastActive = NOW() WHERE ID = :UserID");
                            $DBRequest->bindParam(":UserID", $DBResponse['ID']);
                            $DBRequest->execute();

                            $ResponseData['Server'] = $GLOBALS['Config']['Service']['PublicURI'];
                            $ResponseData['AuthToken'] = $jwt;
                            $ResponseData['Status'] = 'OK';

                            $Response = new ResponseHandler();
                            $Response->Code = 200;
                            $Response->Data = $ResponseData;

                            $Response->Respond();
                        }
                        else
                        {
                            new ErrorHandler()->Throw(array('Invalid username/password.'), 'Login', 401);
                        }
                    }
                        
                }
            }
        }
        catch(Exception $e)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Errors (Type, ErrorMessage, Time) VALUES ('Login', :ErrorMessage, NOW())");
            $DBRequest->bindParam(":ErrorMessage", json_encode($e));
            $DBRequest->execute();
            
            new ErrorHandler()->Throw(array('Something went really wrong.'), 'Login', 500);
        }
    }

    private function Activate()
    {
        try 
        {
            if(isset($_GET['email']))
            {
                $Data['Email'] = strip_tags($_GET['email']);
            }
            else
            {
                $Data['Email'] = '';
            }

            if(isset($_GET['email']))
            {
                $Data['Code'] = strip_tags($_GET['code']);
            }
            else
            {
                $Data['Code'] = '';
            }

            if($Data['Email'] == '' || $Data['Code'] == '')
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Errors (Type, ErrorMessage, UserID, Time) VALUES ('Activation', :ErrorMessage, NOW())");
                $DBRequest->bindParam(":ErrorMessage", 'Empty username/activation code.');
                $DBRequest->execute();

                new ErrorHandler()->Throw(array('Denied.'), 'Registration', 401);
            }
            else
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT Users.ID, Users.Email, ActivationCodes.Code FROM Users LEFT JOIN ActivationCodes ON Users.ID = ActivationCodes.UserID WHERE Users.Email = :Email");
                $DBRequest->bindParam(":Email", $Data['Email']);
                $DBRequest->execute();

                $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

                if(!$DBResponse)
                {
                    new ErrorHandler()->Throw(array('No user.'), 'Registration', 400);
                }

                if($DBResponse[0]['Code'] == $Data['Code'])
                {
                    $ErrorState = '';

                    if(isset($_GET['postback']))
                    {
                        if($_GET['postback'] == 1)
                        {
                            if(isset($_POST['Password']) && isset($_POST['ConfirmPassword']))
                            {
                                if($_POST['Password'] == $_POST['ConfirmPassword'])
                                {
                                    if(strlen($_POST['Password']) >= 8)
                                    {
                                        $PasswordHash = password_hash($_POST['Password'], PASSWORD_DEFAULT);

                                        $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET Password = :Password WHERE ID = :UserID");
                                        $DBRequest->bindParam(":Password", $PasswordHash);
                                        $DBRequest->bindParam(":UserID", $DBResponse[0]['ID']);
                                        $DBRequest->execute();

                                        $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM ActivationCodes WHERE UserID = :UserID");
                                        $DBRequest->bindParam(":UserID", $DBResponse[0]['ID']);
                                        $DBRequest->execute();

                                        $SetPasswordTemplate = file_get_contents($GLOBALS['Config']['Service']['BackendPath'].'/Templates/Set-Password-Success.html');
                                        echo $SetPasswordTemplate;

                                        die();
                                    }
                                    else
                                    {
                                        $ErrorState = '<div id="Error">Password too short.</div>';
                                    }
                                }
                                else
                                {
                                    $ErrorState = '<div id="Error">Passwords didn\'t match.</div>';
                                }
                            }
                            else
                            {
                                $ErrorState = '<div id="Error">Passwords didn\'t match.</div>';
                            }
                    
                        }
                    }

                    $SetPasswordTemplate = file_get_contents($GLOBALS['Config']['Service']['BackendPath'].'/Templates/Set-Password.html');
                    $SetPasswordTemplate = str_replace('@@POSTBACKURI@@', $GLOBALS['Config']['Service']['APIURI'].'/user/actions/activate/?email='.$Data['Email'].'&code='.$Data['Code'].'&postback=1', $SetPasswordTemplate);
                    $SetPasswordTemplate = str_replace('@@ERRORSTATE@@', $ErrorState, $SetPasswordTemplate);

                    echo $SetPasswordTemplate;
                    
                }
                else
                {
                    new ErrorHandler()->Throw(array('Denied.'), 'Registration', 401);
                }
            }
        }
        catch(Exception $e)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Errors (Type, ErrorMessage, Time) VALUES ('Activation', :ErrorMessage, NOW())");
            $DBRequest->bindParam(":ErrorMessage", json_encode($e));
            $DBRequest->execute();

            new ErrorHandler()->Throw(array('Something went really wrong.'), 'Login', 500);
        }
    }

    public function Update($Username)
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.ModifyAccess, AccessLevelGlobalPermissions.ModifyProfiles, AccessLevelGlobalPermissions.BanUsers FROM AccessLevelGlobalPermissions
        LEFT JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        LEFT JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
        WHERE AccessLevelGlobalPermissions.BanUsers = 1 AND Users.ID = :UserID");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();

        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

        $Data = json_decode(file_get_contents('php://input'), true);

        if(isset($Data['BanUser']) && isset($Username) && isset($Data['EndDate']) && $HasAccess['BanUsers'] == 1)
        {
           
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            if($UserID != 1)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Bans (UserID, Reason, End) VALUES (:UserID, :Reason, :EndDate)");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->bindParam(":Reason", $Data['Reason']);
                $DBRequest->bindParam(":EndDate", $Data['EndDate']);
                $DBRequest->execute();
            }

            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = ['OK'];
            $Response->Respond();
        }

        if(isset($Data['UnbanUser']) && isset($Username) && $HasAccess['BanUsers'] == 1)
        {
           
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            if($UserID != 1)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM Bans WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();
            }

            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = ['OK'];
            $Response->Respond();
        }

        if(isset($Data['ActivateUser']) && isset($Username) && $HasAccess['ModifyAccess'] == 1)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            if($UserID != 1)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM ActivationCodes WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();
            }

            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = ['OK'];
            $Response->Respond();
        }

        if(isset($Data['ResetPassword']) && isset($Username))
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            if($UserID != 1)
            {
                if($UserID == $GLOBALS['AccessToken']->UserID || $HasAccess['ModifyAccess'])
                {
                    if(isset($Data['Password']) && isset($Data['ConfirmPassword']))
                    {
                        if($Data['Password'] == $Data['ConfirmPassword'])
                        {
                            if(strlen($Data['Password']) >= 8)
                            {
                                $PasswordHash = password_hash($Data['Password'], PASSWORD_DEFAULT);

                                $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET Password = :Password WHERE ID = :UserID");
                                $DBRequest->bindParam(":Password", $PasswordHash);
                                $DBRequest->bindParam(":UserID", $UserID);
                                $DBRequest->execute();
                            }
                            else
                            {
                                new ErrorHandler()->Throw(array('Invalid password length.'), 'Access', 400);
                            }
                        }
                        else
                        {
                            new ErrorHandler()->Throw(array('Passwords don\'t match.'), 'Access', 400);
                        }
                    }
                    else
                    {
                        new ErrorHandler()->Throw(array('Missing required parameters.'), 'Access', 400);
                    }
                }
                else
                {
                    new ErrorHandler()->Throw(array('Access denied.'), 'Access', 401);
                }
            }
            else
            {
                if($UserID == $GLOBALS['AccessToken']->UserID)
                {
                    if(isset($Data['Password']) && isset($Data['ConfirmPassword']))
                    {
                        if(strlen($Data['Password']) >= 8)
                        {
                            $PasswordHash = password_hash($Data['Password'], PASSWORD_DEFAULT);

                            $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Users SET Password = :Password WHERE ID = :UserID");
                            $DBRequest->bindParam(":Password", $PasswordHash);
                            $DBRequest->bindParam(":UserID", $UserID);
                            $DBRequest->execute();
                        }
                        else
                        {
                            new ErrorHandler()->Throw(array('Invalid password length.'), 'Access', 400);
                        }
                    }
                    else
                    {
                        new ErrorHandler()->Throw(array('Missing required parameters.'), 'Access', 400);
                    }
                }
                else
                {
                    new ErrorHandler()->Throw(array('Access denied.'), 'Access', 401);
                }
            }

            $Response = new ResponseHandler();
            $Response->Code = 200;
            $Response->Data = ['OK'];
            $Response->Respond();
        }

        if(isset($Data['UpdateProfile']))
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $DBRequest->fetchColumn();

            if($UserID == $GLOBALS['AccessToken']->UserID)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Profiles SET Bio = :Bio, Tagline = :Tagline WHERE UserID = :UserID");
                $DBRequest->bindParam(":Bio", $Data['Profile']['Bio']);
                $DBRequest->bindParam(":Tagline", $Data['Profile']['Tagline']);
                $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
                $DBRequest->execute();

                $Response = new ResponseHandler();
                $Response->Code = 200;
                $Response->Data = ['OK'];
                $Response->Respond();
            }
            else if($HasAccess['ModifyProfiles'])
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("UPDATE Profiles SET Bio = :Bio, Tagline = :Tagline WHERE UserID = :UserID");
                $DBRequest->bindParam(":Bio", $Data['Profile']['Bio']);
                $DBRequest->bindParam(":Tagline", $Data['Profile']['Tagline']);
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $Response = new ResponseHandler();
                $Response->Code = 200;
                $Response->Data = ['OK'];
                $Response->Respond();
            }
            else
            {
                new ErrorHandler()->Throw(array('Access denied.'), 'Access', 401);
            }
        }
    }

    public function Delete($Username)
    {
        $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT AccessLevelGlobalPermissions.DeleteUsers FROM AccessLevelGlobalPermissions
        INNER JOIN AccessLevels ON AccessLevels.ID = AccessLevelGlobalPermissions.AccessLevelID
        INNER JOIN AccessLevelAssignments ON AccessLevels.ID = AccessLevelAssignments.AccessLevelID
        INNER JOIN Users ON AccessLevelAssignments.UserID = Users.ID       
        WHERE AccessLevelGlobalPermissions.DeleteUsers = 1 AND Users.ID = :UserID");

        $DBRequest->bindParam(":UserID", $GLOBALS['AccessToken']->UserID);
        $DBRequest->execute();
        $HasAccess = $DBRequest->fetch(PDO::FETCH_ASSOC);

        if($HasAccess)
        {
            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Username);
            $DBRequest->execute();

            $UserID = $HasAccess = $DBRequest->fetchColumn();

            if($UserID)
            {
                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM Users WHERE ID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM AccessLevelAssignments WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM ActivationCodes WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM Bans WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM ChannelActivity WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM DirectMessages WHERE FromUserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM DirectMessages WHERE ToUserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM DirectMessageSessions WHERE RequesterID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM DirectMessageSessions WHERE ResponderID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM Messages WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM Profiles WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VideoSessions WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VideoSessionsConnectedUsers WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceChannelSessions WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $DBRequest = $GLOBALS['DB']->Handler->prepare("DELETE FROM VoiceData WHERE UserID = :UserID");
                $DBRequest->bindParam(":UserID", $UserID);
                $DBRequest->execute();

                $Response = new ResponseHandler();
                $Response->Code = 200;
                $Response->Data = array('OK');
                $Response->Respond();
            }
            else
            {
                new ErrorHandler()->Throw(array('No user'), 'User', 400);
            }
        }
        else
        {
            new ErrorHandler()->Throw(array('Access denied'), 'Access', 401);
        }
    }

    private function Register()
    {
        $Data = json_decode(file_get_contents('php://input'), true);

        if(isset($Data['Username']) && isset($Data['Email']))
        {
            if(!preg_match('/^[a-zA-Z0-9_]+$/', $Data['Username']))
            {
                $ErrorState = true;
                new ErrorHandler()->Throw(array('Username'), 'Access', 400);
            }

            if (!preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,63}$/i', $Data['Email'])) 
            {
                $ErrorState = true;
                new ErrorHandler()->Throw(array('Email'), 'Access', 400);
            }

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Username = :Username LIMIT 1");
            $DBRequest->bindParam(":Username", $Data['Username']);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

            if(count($DBResponse) > 0)
            {
                $ErrorState = true;
                new ErrorHandler()->Throw(array('Username'), 'Registration', 400);
            }

            $DBRequest = $GLOBALS['DB']->Handler->prepare("SELECT ID FROM Users WHERE Email = :Email");
            $DBRequest->bindParam(":Email", $Data['Email']);
            $DBRequest->execute();

            $DBResponse = $DBRequest->fetchAll(PDO::FETCH_ASSOC);

            if(count($DBResponse) > 0)
            {
                $ErrorState = true;
                new ErrorHandler()->Throw(array('Email'), 'Registration', 400);
            }

            if(!isset($ErrorState))
            {
                $Password = '';

                for($i = 0; $i <= 60; $i++)
                {
                    $Character = chr(rand(0, 25) + 65);
                    $Password = $Password.$Character;
                }

                $Password = password_hash($Password, PASSWORD_DEFAULT);

                try {
                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Users (Username, Email, Password, DateCreated, LastActive) VALUES (:Username, :Email, :Password, NOW(), NOW())");
                    $DBRequest->bindParam(":Username", $Data['Username']);
                    $DBRequest->bindParam(":Email", $Data['Email']);
                    $DBRequest->bindParam(":Password", $Password);
                    $DBRequest->execute();

                    $UserID = $GLOBALS['DB']->Handler->lastInsertId();

                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO AccessLevelAssignments (UserID, AccessLevelID) VALUES (:UserID, 2)");
                    $DBRequest->bindParam(":UserID", $UserID);
                    $DBRequest->execute();

                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO Profiles (UserID, Tagline, Bio) VALUES (:UserID, '', '')");
                    $DBRequest->bindParam(":UserID", $UserID);
                    $DBRequest->execute();

                    $ActivationCode = '';

                    for($i = 0; $i <= 99; $i++)
                    {
                        $Character = chr(rand(0, 25) + 65);
                        $ActivationCode = $ActivationCode.$Character;
                    }

                    $DBRequest = $GLOBALS['DB']->Handler->prepare("INSERT INTO ActivationCodes (UserID, Code) VALUES (:UserID, :Code)");
                    $DBRequest->bindParam(":UserID", $UserID);
                    $DBRequest->bindParam(":Code", $ActivationCode);
                    $DBRequest->execute();

                    $ActivationLink = $GLOBALS['Config']['Service']['APIURI'].'/user/actions/activate/?email='.$Data['Email'].'&code='.$ActivationCode;

                    $ActivationEmail = file_get_contents($GLOBALS['Config']['Service']['BackendPath'].'/Templates/Register-Email-Template.html');
                    $ActivationEmail = str_replace('@@USERNAME@@', $Data['Username'], $ActivationEmail);
                    $ActivationEmail = str_replace('@@PUBLICURI@@', $GLOBALS['Config']['Service']['PublicURI'], $ActivationEmail);
                    $ActivationEmail = str_replace('@@APIURI@@', $GLOBALS['Config']['Service']['APIURI'], $ActivationEmail);
                    $ActivationEmail = str_replace('@@REGISTERURI@@', $ActivationLink, $ActivationEmail);
                    $ActivationEmail = str_replace('@@SERVERNAME@@', $GLOBALS['Config']['Service']['ServerName'], $ActivationEmail);

                    if($GLOBALS['Config']['Service']['SkipEmailActivation'] != 1)
                    {
                        $SMTP = new PHPMailer(true);

                        //Server settings
                        //$SMTP->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                        $SMTP->isSMTP();                                            //Send using SMTP
                        $SMTP->Host       = $GLOBALS['Config']['Mail']['Server'];                     //Set the SMTP server to send through
                        $SMTP->SMTPAuth   = true;                                   //Enable SMTP authentication
                        $SMTP->Username   = $GLOBALS['Config']['Mail']['Username'];                     //SMTP username
                        $SMTP->Password   = $GLOBALS['Config']['Mail']['Password'];                               //SMTP password
                        //$SMTP->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                        $SMTP->Port       = $GLOBALS['Config']['Mail']['Port'];                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                        //Recipients
                        $SMTP->setFrom($GLOBALS['Config']['Mail']['FromAddress'], $GLOBALS['Config']['Mail']['From']);
                        $SMTP->addAddress($Data['Email'], $Data['Username']);     //Add a recipient

                        //Content
                        $SMTP->isHTML(true);                                  //Set email format to HTML
                        $SMTP->Subject = 'New User - Activate Account';
                        $SMTP->Body    = $ActivationEmail;
                        $SMTP->AltBody = 'Hi '.$Data['Username'].',

    Someone (hopefully you) signed up for an Entropy account at '.$GLOBALS['Config']['Service']['PublicURI'].'

    If this was you then please click this link to create a password and activate your account: '.$ActivationLink.'

    Kind Regards,

    '.$GLOBALS['Config']['Service']['ServerName'];

                        $SMTP->send(); 
                    }

                    
                    $Response = new ResponseHandler();
                    $Response->Code = 200;
                    $Response->Data = ['OK'];
                    $Response->Respond();
                }
                catch(Exception $e)
                {
                    new ErrorHandler()->Throw(array('Something went wrong.'), 'Registration', 500);
                }
                
            }
 
            
        }
        else
        {
            new ErrorHandler()->Throw(array('Missing required parameter.'), 'Registration', 400);
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
                if(isset($URI[2]))
                {
                    if($URI[1] == 'actions' && $URI[2] == 'activate')
                    {
                        $this->Activate();
                    }
                    else
                    {
                        new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
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
                    if($URI[1] == 'actions' && $URI[2] == 'register')
                    {
                        $this->Register();
                    }
                    else if($URI[1] == 'actions' && $URI[2] == 'activate')
                    {
                        $this->Activate();
                    }
                    else if($URI[1] == 'actions' && $URI[2] == 'login')
                    {
                        $this->Login();
                    }
                    else
                    {
                        new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
                    }
                }
                else
                {
                    $this->Update($URI[1]);
                }
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
            }
        }
        else if($Method == 'DELETE')
        {
            if(isset($URI[1]))
            {
                $this->Delete($URI[1]);
            }
            else
            {
                new ErrorHandler()->Throw(array('Invalid endpoint.'), 'User', 404);
            }
        }
        else
        {
            new ErrorHandler()->Throw(array('Invalid method.'), 'User', 500);
        }
    }
}