<?php
$SetupSuccess = false;
$Error = null;

if(isset($_GET['Page']))
{
    if($_GET['Page'] == 1)
    {
        if(isset($_GET['postback']))
        {
            $Config = parse_ini_file('../config.temp.ini', true);
            $Data = json_decode(file_get_contents('php://input'), true);

            if($Data['Type'] == 'Service')
            {
                if($Data['ServiceServerName'] != '' && $Data['ServicePublicURI'] != '' && $Data['ServiceAPIURI'] != '' && $Data['ServiceBackendPath'] != '')
                {
                    if($Data['SkipEmailActivation'] == '')
                    {
                        $Data['SkipEmailActivation'] = 0;
                    }
                    else
                    {
                        $Data['SkipEmailActivation'] = 1;
                    }
                    if(is_dir($Data['ServiceBackendPath']))
                    {
                        $Config['Service']['ServerName'] = $Data['ServiceServerName'];
                        $Config['Service']['PublicURI'] = $Data['ServicePublicURI'];
                        $Config['Service']['APIURI'] = $Data['ServiceAPIURI'];
                        $Config['Service']['BackendPath'] = $Data['ServiceBackendPath'];
                        $Config['Service']['SkipEmailActivation'] = $Data['SkipEmailActivation'];

                        $Output = '';

                        foreach($Config as $Key => $Value) 
                        {
                            if(is_array($Value))
                            {
                                $Output .= '['.$Key."]\n";

                                foreach($Value as $Subkey => $Subvalue)
                                {
                                    $Output .= $Subkey.' = "'.$Subvalue."\"\n";
                                }

                                $Output .= "\n";
                            }
                            else
                            {
                                $Output .= $Subkey.' = "'.$Subvalue."\"\n";
                            }
                        }

                        file_put_contents('../config.temp.ini', $Output);
                        echo json_encode(['success' => true, 'message' => 'Connection successful.']);
                        exit;
                    }
                    else
                    {
                        echo json_encode(['error' => true, 'message' => 'Backend path doesn\'t exist']);
                        exit;
                    }
                }
                else
                {
                    echo json_encode(['error' => true, 'message' => 'Missing fields']);
                    exit;
                }
            }
        }

    }
    else if($_GET['Page'] == 2)
    {
        if(isset($_GET['postback']))
        {
            $Config = parse_ini_file('../config.temp.ini', true);
            $Data = json_decode(file_get_contents('php://input'), true);

            if($Data['Type'] == 'Mail')
            {
                if($Data['MailServerName'] != '' && $Data['MailServerPort'] != '' && $Data['MailServerUser'] != '' && $Data['MailServerPassword'] != '' && $Data['MailServerFromName'] != '' && $Data['MailServerFromAddress'] != '')
                {
                    $Success = false;

                    try
                    {
                        $Mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $Mail->isSMTP();
                        $Mail->Host       = $Data['MailServerName'];  // was $Body
                        $Mail->Port       = (int)$Data['MailServerPort'];
                        $Mail->Username   = $Data['MailServerUser'];
                        $Mail->Password   = $Data['MailServerPassword'];
                        $Mail->SMTPAuth   = true;
                        $Mail->SMTPSecure = $Data['MailServerPort'] == 465
                            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $Mail->SMTPDebug  = 0;
                        $Mail->Timeout    = 5;

                        $Mail->smtpConnect();
                        $Mail->smtpClose();

                        $Success = true;
                    }
                    catch(Exception $e)
                    {
                        echo json_encode(['success' => false, 'message' => 'Connection failed: '.$e->getMessage()]);
                        exit;
                    }

                    if($Success)
                    {
                        $Config['Mail']['Server'] = $Data['MailServerName'];
                        $Config['Mail']['Port'] = $Data['MailServerPort'];
                        $Config['Mail']['Username'] = $Data['MailServerUser'];
                        $Config['Mail']['Password'] = $Data['MailServerPassword'];
                        $Config['Mail']['From'] = $Data['MailServerFromName'];
                        $Config['Mail']['FromAddress'] = $Data['MailServerFromAddress'];

                        $Output = '';

                        foreach($Config as $Key => $Value) 
                        {
                            if(is_array($Value))
                            {
                                $Output .= '['.$Key."]\n";

                                foreach($Value as $Subkey => $Subvalue)
                                {
                                    $Output .= $Subkey.' = "'.$Subvalue."\"\n";
                                }

                                $Output .= "\n";
                            }
                            else
                            {
                                $Output .= $Key.' = "'.$Value."\"\n";
                            }
                        }
                        
                        file_put_contents('../config.temp.ini', $Output);
                        echo json_encode(['success' => true, 'message' => 'Connection successful.']);
                        exit;
                    }                    
                }
                else
                {
                    echo json_encode(['error' => true, 'message' => 'Missing fields']);
                    exit;
                }
            }
        }
    }
    else if($_GET['Page'] == 3)
    {
        if(isset($_GET['postback']))
        {
            $Config = parse_ini_file('../config.temp.ini', true);
            $Data = json_decode(file_get_contents('php://input'), true);
            
            if(isset($Data['SuperAdminUsername']) && isset($Data['SuperAdminEmail']) && isset($Data['SuperAdminPassword']) && isset($Data['SuperAdminConfirmPassword']))
            {
                if(!preg_match('/^[a-zA-Z0-9_]+$/', $Data['SuperAdminUsername']))
                {
                    echo json_encode(['error' => true, 'message' => 'Invalid Username']);
                    exit;
                }

                if (!preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,63}$/i', $Data['SuperAdminEmail'])) 
                {
                    echo json_encode(['error' => true, 'message' => 'Invalid Email']);
                    exit;
                }

                if(strlen($Data['SuperAdminPassword']) <= 8)
                {
                    echo json_encode(['error' => true, 'message' => 'Password too short']);
                    exit;
                }

                if($Data['SuperAdminPassword'] != $Data['SuperAdminConfirmPassword'])
                {
                    echo json_encode(['error' => true, 'message' => 'Passwords do not match']);
                    exit;
                }

                echo json_encode(['success' => true, 'message' => 'Connection successful.']);
            }

            exit;
        }
    }
    else if($_GET['Page'] == 4)
    {
        if(isset($_POST['SuperAdminUsername']) && isset($_POST['SuperAdminEmail']) && isset($_POST['SuperAdminPassword']))
        {
            try
            {
                $Characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                
                $Config = parse_ini_file('../config.temp.ini', true);

                $Output = '';

                foreach($Config as $Key => $Value) 
                {
                    if(is_array($Value))
                    {
                        $Output .= '['.$Key."]\n";

                        foreach($Value as $Subkey => $Subvalue)
                        {
                            $Output .= $Subkey.' = "'.$Subvalue."\"\n";
                        }

                        $Output .= "\n";
                    }
                    else
                    {
                        $Output .= $Key.' = "'.$Value."\"\n";
                    }
                }

                $Key = '';

                for($i = 0; $i < 128; $i++)
                {
                    $Key .= $Characters[random_int(0, strlen($Characters) - 1)];
                }
                
                $Output .= "[Security]\nJWTKey = ".$Key."\n";
                
                file_put_contents('../config.temp.ini', $Output);

                $DB = new PDO('mysql:host='.$Config['Database']['Server'].';dbname='.$Config['Database']['Database'].';charset=utf8', $Config['Database']['Username'], $Config['Database']['Password']);

                $Statement = $DB->prepare("SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";
                START TRANSACTION;
                SET time_zone = \"+00:00\";

                CREATE TABLE `AccessLevelAssignments` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `AccessLevelID` int(11) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `AccessLevelChannelPermissions` (
                `ID` int(11) NOT NULL,
                `ChannelID` int(11) NOT NULL,
                `AccessLevelID` int(11) NOT NULL,
                `Modify` int(1) NOT NULL,
                `Access` int(1) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `AccessLevelGlobalPermissions` (
                `ID` int(11) NOT NULL,
                `AccessLevelID` int(11) NOT NULL,
                `ModifyChannels` int(1) NOT NULL,
                `BanUsers` int(1) NOT NULL,
                `DeleteMessages` int(1) NOT NULL,
                `ModifyAccess` int(1) NOT NULL,
                `ModifyProfiles` int(1) NOT NULL,
                `DeleteUsers` int(1) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `AccessLevels` (
                `ID` int(11) NOT NULL,
                `Name` varchar(150) NOT NULL,
                `Colour` varchar(7) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `ActivationCodes` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `Code` varchar(100) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `Bans` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `Reason` text NOT NULL,
                `End` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `ChannelActivity` (
                `ID` int(11) NOT NULL,
                `ChannelID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `Type` varchar(25) NOT NULL,
                `Time` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `Channels` (
                `ID` int(11) NOT NULL,
                `Name` text NOT NULL,
                `Type` varchar(1) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `ChannelViews` (
                `ID` int(11) NOT NULL,
                `ChannelID` int(11) NOT NULL,
                `LastViewedAt` datetime NOT NULL,
                `UserID` int(11) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `DirectMessages` (
                `ID` int(11) NOT NULL,
                `SessionID` int(11) NOT NULL,
                `FromUserID` int(11) NOT NULL,
                `ToUserID` int(11) NOT NULL,
                `Content` text NOT NULL,
                `FromUserPrivateKey` text DEFAULT NULL,
                `ToUserPrivateKey` text DEFAULT NULL,
                `IV` text DEFAULT NULL,
                `Sent` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `DirectMessageSessions` (
                `ID` int(11) NOT NULL,
                `RequesterID` int(11) NOT NULL,
                `ResponderID` int(11) NOT NULL,
                `RequesterPublicKey` text DEFAULT NULL,
                `ResponderPublicKey` text DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `DirectMessageViews` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `SessionID` int(11) NOT NULL,
                `LastViewedAt` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `Errors` (
                `ID` int(11) NOT NULL,
                `Type` varchar(50) NOT NULL,
                `ErrorMessage` text NOT NULL,
                `UserID` int(11) DEFAULT NULL,
                `Time` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `Messages` (
                `ID` int(11) NOT NULL,
                `Content` text NOT NULL,
                `UserID` int(11) NOT NULL,
                `ChannelID` int(11) NOT NULL,
                `Sent` datetime NOT NULL,
                `Edited` datetime DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `Profiles` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `Tagline` varchar(150) NOT NULL,
                `Bio` text NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `Users` (
                `ID` int(11) NOT NULL,
                `Username` varchar(100) NOT NULL,
                `Email` varchar(200) NOT NULL,
                `Password` text NOT NULL,
                `DateCreated` datetime NOT NULL,
                `LastActive` datetime NOT NULL,
                `IsOnline` tinyint(1) NOT NULL DEFAULT 0,
                `LastSpokeAt` datetime DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `VideoSessions` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `LastReceived` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `VideoSessionsConnectedUsers` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `SessionID` int(11) NOT NULL,
                `LastActive` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `VoiceChannelSessions` (
                `ID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `ChannelID` int(11) NOT NULL,
                `Status` varchar(50) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE `VoiceData` (
                `ID` int(11) NOT NULL,
                `ChannelID` int(11) NOT NULL,
                `UserID` int(11) NOT NULL,
                `Data` blob DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


                ALTER TABLE `AccessLevelAssignments`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `AccessLevelChannelPermissions`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `AccessLevelGlobalPermissions`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `AccessLevels`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `ActivationCodes`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `Bans`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `ChannelActivity`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `Channels`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `ChannelViews`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `DirectMessages`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `DirectMessageSessions`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `DirectMessageViews`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `Errors`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `Messages`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `Profiles`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `Users`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `VideoSessions`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `VideoSessionsConnectedUsers`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `VoiceChannelSessions`
                ADD PRIMARY KEY (`ID`);

                ALTER TABLE `VoiceData`
                ADD PRIMARY KEY (`ID`);


                ALTER TABLE `AccessLevelAssignments`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `AccessLevelChannelPermissions`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `AccessLevelGlobalPermissions`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `AccessLevels`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `ActivationCodes`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `Bans`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `ChannelActivity`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `Channels`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `ChannelViews`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `DirectMessages`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `DirectMessageSessions`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `DirectMessageViews`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `Errors`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `Messages`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `Profiles`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `Users`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `VideoSessions`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `VideoSessionsConnectedUsers`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `VoiceChannelSessions`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

                ALTER TABLE `VoiceData`
                MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;
                COMMIT;
                ");

                $Statement->execute();

                $Statement = $DB->prepare("INSERT INTO AccessLevels (ID, Name, Colour) VALUES (1, 'Admin', '#aa0000')");
                $Statement->execute();

                $Statement = $DB->prepare("INSERT INTO AccessLevels (ID, Name, Colour) VALUES (2, 'Users', '#ffffff')");
                $Statement->execute();

                $Statement = $DB->prepare("INSERT INTO AccessLevelGlobalPermissions (AccessLevelID, ModifyChannels, BanUsers, DeleteMessages, ModifyAccess, ModifyProfiles, DeleteUsers) VALUES (1, 1, 1, 1, 1, 1, 1)");
                $Statement->execute();

                $Statement = $DB->prepare("INSERT INTO AccessLevelGlobalPermissions (AccessLevelID, ModifyChannels, BanUsers, DeleteMessages, ModifyAccess, ModifyProfiles, DeleteUsers) VALUES (2, 0, 0, 0, 0, 0, 0)");
                $Statement->execute();

                $PasswordHash = password_hash($_POST['SuperAdminPassword'], PASSWORD_DEFAULT);
                $Statement = $DB->prepare("INSERT INTO Users (ID, Username, Email, Password, DateCreated) VALUES (1, :Username, :Email, :Password, NOW())");
                $Statement->bindParam(":Username", $_POST['SuperAdminUsername']);
                $Statement->bindParam(":Email", $_POST['SuperAdminEmail']);
                $Statement->bindParam(":Password", $PasswordHash);
                $Statement->execute();

                $Statement = $DB->prepare("INSERT INTO AccessLevelAssignments (UserID, AccessLevelID) VALUES (1, 1)");
                $Statement->execute();

                $Statement = $DB->prepare("INSERT INTO Profiles (UserID, Bio, Tagline) VALUES (1, '', '')");
                $Statement->execute();

                if(is_file($Config['Service']['BackendPath'].'/config.temp.ini'))
                {
                    unlink($Config['Service']['BackendPath'].'/config.ini');
                    copy($Config['Service']['BackendPath'].'/config.temp.ini', $Config['Service']['BackendPath'].'/config.ini');
                    unlink($Config['Service']['BackendPath'].'/config.temp.ini');
                }

                $SetupSuccess = true;
            }
            catch(Exception $e)
            {
                
                $SetupSuccess = false;

                $Error = $e;
            }


        }
    }
}
else
{
    if(isset($_GET['postback']))
    {
        $Config = parse_ini_file('../config.temp.ini', true);
        $Data = json_decode(file_get_contents('php://input'), true);

        if(isset($Data['DatabaseServerName']) && isset($Data['DatabaseName']) && isset($Data['DatabasePort']) && isset($Data['DatabaseUser']) && isset($Data['DatabasePassword']))
        {
            $Success = false;

            try {
                $DSN = "mysql:host={$Data['DatabaseServerName']};port={$Data['DatabasePort']};dbname={$Data['DatabaseName']};charset=utf8mb4";
                $PDO = new PDO($DSN, $Data['DatabaseUser'], $Data['DatabasePassword'], [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                $PDO->query("SELECT 1");
                $Success = true;
            }
            catch (PDOException $e) {
                http_response_code(200); 
                echo json_encode([
                    'success' => false,
                    'message' => match(true) {
                        str_contains($e->getMessage(), 'Access denied')  => 'Invalid username or password.',
                        str_contains($e->getMessage(), 'Unknown database') => 'Database does not exist.',
                        str_contains($e->getMessage(), 'Connection refused') => 'Could not reach database host.',
                        str_contains($e->getMessage(), 'Name or service not known') => 'Could not reach database host.',
                        default => 'Connection failed: ' . $e->getMessage()
                    }
                ]);

                $Success = false;
            }

            if($Success)
            {

                $Config['Database']['Server'] = $Data['DatabaseServerName'];
                $Config['Database']['Port'] = $Data['DatabasePort'];
                $Config['Database']['Database'] = $Data['DatabaseName'];
                $Config['Database']['Username'] = $Data['DatabaseUser'];
                $Config['Database']['Password'] = $Data['DatabasePassword'];

                $Output = '';

                foreach($Config as $Key => $Value) 
                {
                    if(is_array($Value))
                    {
                        $Output .= '['.$Key."]\n";

                        foreach($Value as $Subkey => $Subvalue)
                        {
                            $Output .= $Subkey.' = "'.$Subvalue."\"\n";
                        }

                        $Output .= "\n";
                    }
                    else
                    {
                        $Output .= $Key.' = "'.$Value."\"\n";
                    }
                }
                
                file_put_contents('../config.temp.ini', $Output);
                echo json_encode(['success' => true, 'message' => 'Connection successful.']);
                exit;
            }
        }
        else
        {
            echo 'Missing field';
        }

        exit;
    }
}

?>

<html>
<head>
    <style type="text/css">
        html, body, div, span, applet, object, iframe,
        h1, h2, h3, h4, h5, h6, p, blockquote, pre,
        a, abbr, acronym, address, big, cite, code,
        del, dfn, em, img, ins, kbd, q, s, samp,
        small, strike, strong, sub, sup, tt, var,
        b, u, i, center,
        dl, dt, dd, ol, ul, li,
        fieldset, form, label, legend,
        table, caption, tbody, tfoot, thead, tr, th, td,
        article, aside, canvas, details, embed, 
        figure, figcaption, footer, header, hgroup, 
        menu, nav, output, ruby, section, summary,
        time, mark, audio, video {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 100%;
            font: inherit;
            vertical-align: baseline;
        }
        /* HTML5 display-role reset for older browsers */
        article, aside, details, figcaption, figure, 
        footer, header, hgroup, menu, nav, section {
            display: block;
        }
        body {
            line-height: 1;
        }
        ol, ul {
            list-style: none;
        }
        blockquote, q {
            quotes: none;
        }
        blockquote:before, blockquote:after,
        q:before, q:after {
            content: '';
            content: none;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
        }

        html {
            background-color: rgb(30, 30, 30);
            overflow: hidden;
            color: rgb(240, 240, 240);
            font-family: Arial, helvetica, sans-serif;
        }

        #Main {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        #Main #TitleBar {
            height: 40px;
            padding: 0;
            border-style: solid;
            border-width: 0 0 1px 0;
            background: rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #Main #Content {
            height: 100%;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow: auto;
            align-items: center;
        }

        #Main #Content .Section {
            width: 700px;
            padding: 20px;
            border-radius: 20px;
            background-color: rgba(50, 50, 50);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        #Main #Content .Section p {
            padding: 0 10px;
            font-size: 0.8em;
        }

        #Main #Content .Section h2 {
            font-weight: bold;
        }

        #Main #Content .Section ul {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        #Main #Content .Section ul li {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #Main #Content .Section #State {
            opacity: 0;
            display: none;
            align-items: center;
            gap: 10px;
            text-shadow: 0 0 2px black;
            font-weight: bold;
            justify-content: center;
        }

        #Main #Content .Section #State .ErrorText, #Main #Content .Section #State .ErrorIcon {
            color: rgb(160, 0, 0);
        }

        #Main #Content .Section #State .SuccessText {
            color: rgb(0, 160, 0);
        }

        #Main #Content .Section #State .ErrorIcon {
            width: 10px;
            height: 10px;
            padding: 4px;
            border-style: solid;
            border-width: 2px;
            border-color: rgb(160, 0, 0);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #Main #Content .Section ul li.SkipEmailActivation input {
            width: 50%;
        }

        #Main #Content .Section ul li.SubmitButton {
            padding: 0 20px 0 0;
            display: flex;
            justify-content: right;
        }

        #Main #Content .Section ul li.SubmitButton input:disabled {
            background-color: rgb(30, 30, 30);
            color: rgb(200, 200, 200);
            opacity: 0.5;
        }

        #Main #Content .Section ul li.SubmitButton input:enabled {
            background-color: rgb(0, 80, 0);
        }

        #Main #Content .Section ul li.SubmitButton input:enabled:hover {
            cursor: pointer;
        }

        #Main #Content .Section ul li.SubmitButton input {
            padding: 10px 20px;
            border: 0;
            outline: 0;
            border-radius: 5px;
            color: rgb(255, 255, 255);
            font-weight: bold;
            box-shadow: 0 0 14px rgba(0, 0, 0, 0.3);
        }

        #Main #Content .Section ul li input[type="text"].Error, #Main #Content .Section ul li input[type="password"].Error {
            border-style: solid;
            border-width: 1px;
            border-color: red;
        }

        #Main #Content .Section ul li input[type="text"], #Main #Content .Section ul li input[type="password"] {
            width: 50%;
            padding: 10px;
            border-radius: 5px;
            outline: none;
            background-color: rgba(0, 0, 0, 0.4);
            color: rgb(255, 255, 255);
            align-content: center;
            font-size: 0.8em;
            border: none;
            resize: none;
        }

        #Main #Content .Section ul li .Description {
            width: 50%;
            font-size: 0.8em;
        }
    </style>

    <script type="text/javascript">
        function CheckField(Field)
        {
            const el = document.getElementById(Field);

            if(el.value == '')
            {
                el.className = 'Error';
            }
            else
            {
                el.className = '';
            }
        }

        function CheckForm(Type)
        {
            if(Type == 'Database')
            {
                if(document.getElementById('DatabaseServerName').value != ''
                    && document.getElementById('DatabaseName').value != ''
                    && document.getElementById('DatabasePort').value != ''
                    && document.getElementById('DatabaseUser').value != ''
                    && document.getElementById('DatabasePassword').value != '')
                {
                    document.getElementById('NextButton').disabled = false;
                }
                else
                {
                    document.getElementById('NextButton').disabled = true;
                }
            }

            else if(Type == 'Service')
            {
                if(document.getElementById('ServiceServerName').value != ''
                && document.getElementById('ServicePublicURI').value != ''
                && document.getElementById('ServiceAPIURI').value != ''
                && document.getElementById('ServiceBackendPath').value != '')
                {
                    document.getElementById('NextButton').disabled = false;
                }
                else
                {
                    document.getElementById('NextButton').disabled = true;
                }
            }

            else if(Type == 'Mail')
            {
                if(document.getElementById('MailServerName').value != ''
                && document.getElementById('MailServerPort').value != ''
                && document.getElementById('MailServerUser').value != ''
                && document.getElementById('MailServerPassword').value != ''
                && document.getElementById('MailServerFromName').value != ''
                && document.getElementById('MailServerFromAddress').value != '')
                {
                    document.getElementById('NextButton').disabled = false;
                }
                else
                {
                    document.getElementById('NextButton').disabled = true;
                }
            }

            else if(Type == 'SuperAdmin')
            {
                if(document.getElementById('SuperAdminUsername').value != ''
                && document.getElementById('SuperAdminEmail').value != ''
                && document.getElementById('SuperAdminPassword').value != ''
                && document.getElementById('SuperAdminConfirmPassword').value != '')
                {
                    document.getElementById('NextButton').disabled = false;
                }
                else
                {
                    document.getElementById('NextButton').disabled = true;
                }
            }
        }

        async function SubmitForm(Type)
        {
            if(Type == 'Database')
            {
                const Data = {
                    'Type': 'Database',
                    'DatabaseServerName': document.getElementById('DatabaseServerName').value,
                    'DatabaseName': document.getElementById('DatabaseName').value,
                    'DatabasePort': document.getElementById('DatabasePort').value,
                    'DatabaseUser': document.getElementById('DatabaseUser').value,
                    'DatabasePassword': document.getElementById('DatabasePassword').value
                }

                const response = await fetch('setup?postback=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Data)
                });

                const result = await response.json();

                if(result.success)
                {
                    document.getElementById('State').innerHTML = '<div class="SuccessIcon">✅</div><div class="SuccessText">'+result.message+'</div>';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;

                    document.getElementById('NextButton').disabled = false;
                    document.getElementById('NextButton').value = 'Next';
                    document.getElementById('NextButton').onclick = () => {
                        window.location.href = '?Page=1';
                    }
                }
                else
                {
                    document.getElementById('State').innerHTML = '<div class="ErrorIcon">!</div><div class="ErrorText">'+result.message+'</div>';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;
                }
            }

            else if(Type == 'Service')
            {
                const Data = {
                    'Type': 'Service',
                    'ServiceServerName': document.getElementById('ServiceServerName').value,
                    'ServicePublicURI': document.getElementById('ServicePublicURI').value,
                    'ServiceAPIURI': document.getElementById('ServiceAPIURI').value,
                    'ServiceBackendPath': document.getElementById('ServiceBackendPath').value,
                    'SkipEmailActivation': document.getElementById('SkipEmailActivation').checked
                }

                const response = await fetch('setup?Page=1&postback=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Data)
                });

                const result = await response.json();

                if(result.success)
                {
                    document.getElementById('State').innerHTML = '<div class="SuccessIcon">✅</div><div class="SuccessText">'+result.message+'</div>';
                    document.getElementById('NextButton').disabled = false;
                    document.getElementById('NextButton').value = 'Next';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;

                    if(document.getElementById('SkipEmailActivation').checked)
                    {
                        document.getElementById('NextButton').onclick = () => {
                            window.location.href = '?Page=3';
                        }
                    }
                    else
                    {
                        document.getElementById('NextButton').onclick = () => {
                            window.location.href = '?Page=2';
                        }
                    }
                }
                else
                {
                    document.getElementById('State').innerHTML = '<div class="ErrorIcon">!</div><div class="ErrorText">'+result.message+'</div>';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;
                }
            }

            else if(Type == 'Mail')
            {
                const Data = {
                    'Type': 'Mail',
                    'MailServerName': document.getElementById('MailServerName').value,
                    'MailServerPort': document.getElementById('MailServerPort').value,
                    'MailServerUser': document.getElementById('MailServerUser').value,
                    'MailServerPassword': document.getElementById('MailServerPassword').value,
                    'MailServerFromName': document.getElementById('MailServerFromName').value,
                    'MailServerFromAddress': document.getElementById('MailServerFromAddress').value
                }

                const response = await fetch('setup?Page=2&postback=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Data)
                });

                const result = await response.json();

                if(result.success)
                {
                    document.getElementById('State').innerHTML = '<div class="SuccessIcon">✅</div><div class="SuccessText">'+result.message+'</div>';
                    document.getElementById('NextButton').disabled = false;
                    document.getElementById('NextButton').value = 'Next';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;

                    document.getElementById('NextButton').onclick = () => {
                        window.location.href = '?Page=3';
                    }
                    
                }
                else
                {
                    document.getElementById('State').innerHTML = '<div class="ErrorIcon">!</div><div class="ErrorText">'+result.message+'</div>';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;
                }
            }

            else if(Type == 'SuperAdmin')
            {
                const Data = {
                    'Type': 'SuperAdmin',
                    'SuperAdminUsername': document.getElementById('SuperAdminUsername').value,
                    'SuperAdminEmail': document.getElementById('SuperAdminEmail').value,
                    'SuperAdminPassword': document.getElementById('SuperAdminPassword').value,
                    'SuperAdminConfirmPassword': document.getElementById('SuperAdminConfirmPassword').value,
                }

                const response = await fetch('setup?Page=3&postback=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Data)
                });

                const result = await response.json();

                if(result.success)
                {
                    document.getElementById('State').innerHTML = '<div class="SuccessIcon">✅</div><div class="SuccessText">'+result.message+'</div>';
                    document.getElementById('NextButton').disabled = false;
                    document.getElementById('NextButton').value = 'Finish setup';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;

                    document.getElementById('NextButton').onclick = () => {
                        const Form = document.createElement('form');
                        Form.method = 'POST';
                        Form.action = '?Page=4';

                        const PostData = {
                            SuperAdminUsername: document.getElementById('SuperAdminUsername').value,
                            SuperAdminEmail: document.getElementById('SuperAdminEmail').value,
                            SuperAdminPassword: document.getElementById('SuperAdminPassword').value
                        }

                        for(const Key in PostData)
                        {
                            const Input = document.createElement('input');
                            Input.type = 'hidden';
                            Input.name = Key;
                            Input.value = Data[Key];
                            Form.appendChild(Input);
                        }

                        document.body.appendChild(Form);
                        Form.submit();
                    }
                    
                }
                else
                {
                    document.getElementById('State').innerHTML = '<div class="ErrorIcon">!</div><div class="ErrorText">'+result.message+'</div>';
                    document.getElementById('State').style.display = 'flex';
                    document.getElementById('State').style.opacity = 1;
                }
            }
        }
    </script>
</head>
<body>
    <div id="Main">
        <div id="TitleBar">
            Harmony
        </div>

        <div id="Content">

        <?php
        if(!isset($_GET['Page']))
        {
        ?>
        <div class="Section">
            <h2>Database Settings</h2>
            <ul>
                <li>
                    <input type="text" id="DatabaseServerName" placeholder="Server Name" onblur="CheckForm('Database'); CheckField('DatabaseServerName');" />
                    <div class="Description">The connection name for your database server (eg. localhost)</div>
                </li>

                <li>
                    <input type="text" id="DatabaseName" placeholder="Database Name" onblur="CheckForm('Database'); CheckField('DatabaseName');" />
                    <div class="Description">The database name for Harmony.  NOTE: This must already exist and have read/write permissions granted to the user you use to log in (eg. harmony_db)</div>
                </li>

                <li>
                    <input type="text" id="DatabasePort" placeholder="Port" onblur="CheckForm('Database'); CheckField('DatabasePort');" />
                    <div class="Description">The port number of your database server (eg. 3306)</div>
                </li>

                <li>
                    <input type="text" id="DatabaseUser" placeholder="Username" onblur="CheckForm('Database'); CheckField('DatabaseUser');" />
                    <div class="Description">Username for your database (eg. harmony_user)</div>
                </li>

                <li>
                    <input type="password" id="DatabasePassword" placeholder="Password" autocomplete="off" onblur="CheckForm('Database'); CheckField('DatabasePassword');" />
                    <div class="Description">The password for your database user</div>
                </li>

                <div id="State">

                </div>

                <li class="SubmitButton">
                    <input type="submit" value="Test" id="NextButton" onclick="SubmitForm('Database');" disabled />
                </li>
            </ul>
        </div>
        <?php
        }
        else
        {
            if($_GET['Page'] == 1)
            {
        ?>
            <div class="Section">
                <h2>Service Settings</h2>
                <ul>
                    <li>
                        <input type="text" placeholder="Server Name" id="ServiceServerName" onblur="CheckForm('Service'); CheckField('ServiceServerName')" />
                        <div class="Description">The friendly name of this server (eg. Harmony Server)</div>
                    </li>

                    <li>
                        <input type="text" placeholder="Public URI" id="ServicePublicURI" onblur="CheckForm('Service'); CheckField('ServicePublicURI')" />
                        <div class="Description">The public facing URI of your frontend (eg. https://harmony-server.com)</div>
                    </li>

                    <li>
                        <input type="text" placeholder="API URI" id="ServiceAPIURI" onblur="CheckForm('Service'); CheckField('ServiceAPIURI')" />
                        <div class="Description">The public facing URI of your backend API (eg. https://harmony-server.com/api)</div>
                    </li>

                    <li>
                        <input type="text" placeholder="Backend Path" id="ServiceBackendPath"  onblur="CheckForm('Service'); CheckField('ServiceBackendPath')" />
                        <div class="Description">The backend path of your API service.  NOTE:  This should not be in a location that is NOT publicly exposed by your web server (eg. /var/www/backend)</div>
                    </li>

                    <li class="SkipEmailActivation">
                        <div class="Input">
                            <label for="SkipEmailActivation">Skip Email Activation</label>
                            <input type="checkbox" name="SkipEmailActivation" id="SkipEmailActivation" />
                        </div>

                        <div class="Description">Enable if you want to disable email activation.  NOTE:  You will have to manually activate and set passwords on each new account</div>
                    </li>

                    <div id="State">

                    </div>

                    <li class="SubmitButton">
                        <input type="submit" value="Test" id="NextButton" onclick="SubmitForm('Service');" disabled />
                    </li>
                </ul>
            </div>

            <?php
            }
            else if($_GET['Page'] == 2)
            {
            ?>

            <div class="Section">
                <h2>Mail Settings</h2>
                <ul>
                    <li><input type="text" placeholder="Server" onblur="CheckForm('Mail'); CheckField('MailServerName')" id="MailServerName" /><div class="Description">The address of your mail server (eg. mail.harmony-server.com)</div></li>
                    <li><input type="text" placeholder="Port" onblur="CheckForm('Mail'); CheckField('MailServerPort')" id="MailServerPort" /><div class="Description">The port for your mail server (eg. 25 or 587)</div></li>
                    <li><input type="text" placeholder="Username" onblur="CheckForm('Mail'); CheckField('MailServerUser')" id="MailServerUser" /><div class="Description">The username to authenticate to your mail server</div></li>
                    <li><input type="password" placeholder="Password" onblur="CheckForm('Mail'); CheckField('MailServerPassword')" id="MailServerPassword" /><div class="Description">The password to authenticate to your mail server</div></li>
                    <li><input type="text" placeholder="From" onblur="CheckForm('Mail'); CheckField('MailServerFromName')" id="MailServerFromName" /><div class="Description">The friendy "from" name for any system mails (eg. Harmony Admin)</div></li>
                    <li><input type="text" placeholder="From Address" onblur="CheckForm('Mail'); CheckField('MailServerFromAddress')" id="MailServerFromAddress" /><div class="Description">The "from address" for any system mails (eg. no-reply@harmony-server.com)</div></li>

                    <div id="State">

                    </div>

                    <li class="SubmitButton">
                        <input type="submit" value="Test" id="NextButton" onclick="SubmitForm('Mail');" disabled />
                    </li>
                </ul>
            </div>
            <?php
            }
            else if($_GET['Page'] == 3)
            {
            ?>
            <div class="Section">
                <h2>Super Admin Setup</h2>
                <p>This will be the "main" administrator account.  It cannot be removed from the Admins role and has full permissions to everything.</p>

                <p>It is recommended that you set a strong password on this account and only use it when you need to perform admin tasks.</p>
                <ul>
                    <li><input type="text" placeholder="Username" id="SuperAdminUsername" onblur="CheckForm('SuperAdmin'); CheckField('SuperAdminUsername')" /><div class="Description">The username to log in with.  Can only contain numbers, letters and underscores</div></li>
                    <li><input type="text" placeholder="Email Address" id="SuperAdminEmail" onblur="CheckForm('SuperAdmin'); CheckField('SuperAdminEmail')" /><div class="Description">The email address associated with your account</div></li>
                    <li><input type="password" placeholder="Password" id="SuperAdminPassword" onblur="CheckForm('SuperAdmin'); CheckField('SuperAdminPassword')" /><div class="Description">The password you log in with.  Ideally this should be a phrase, length is most important</div></li>
                    <li><input type="password" placeholder="Confirm Password" id="SuperAdminConfirmPassword" onblur="CheckForm('SuperAdmin'); CheckField('SuperAdminConfirmPassword')" /><div class="Description">Confirm the password above</div></li>

                    <div id="State">

                    </div>

                    <li class="SubmitButton">
                        <input type="submit" value="Test" id="NextButton" onclick="SubmitForm('SuperAdmin');" disabled />
                    </li>
                </ul>
            </div>
            <?php
            }
            else if($_GET['Page'] == 4)
            {
                if($SetupSuccess)
                {
                ?>
                    <div class="Section">
                        <p>Setup is complete.  Make sure that <?php echo $Config['Service']['BackendPath'];?>/Setup has been removed.  The setup should have automatically taken care of this but it is a security risk if it remains so please manually confirm.</p>

                        <p>If you've already setup your front end then head on over to <a href="<?php echo $Config['Service']['PublicURI']; ?>"><?php echo $Config['Service']['PublicURI']; ?></a> to get started.</p>
                    </div>
                <?php
                unlink($Config['Service']['BackendPath'].'/Setup/index.php');
                rmdir($Config['Service']['BackendPath'].'/Setup');

                }
                else
                {
                    ?>
                    <div class="Section">
                        <p>Something went wrong with the setup and any database changes were rolled back.  The error that triggered this is output below.</p>

                        <p><?php print_r($Error) ?></p>
                    </div>
                    <?php
                }
            }
        }
        ?>
        </div>
    </div>
</body>
</html>