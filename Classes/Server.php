<?php
class Server {
    public function GetStatus()
    {
        $Data['ServerName'] = $GLOBALS['Config']['Service']['ServerName'];
        $Data['SkipEmailActivation'] = $GLOBALS['Config']['Service']['SkipEmailActivation'];

        $Response = new ResponseHandler();
        $Response->Code = 200;
        $Response->Data = $Data;
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
            if($URI[1] == 'status')
            {
                $this->GetStatus();
            }
        }
    }
}