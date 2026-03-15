<?php
require_once('ResponseHandler.php');

class ErrorHandler {
    public ?array $Message = null;
    public ?string $Type = null;

    public function Throw($Message, $Type, $Code = null)
    {
        $this->Message = $Message;
        $this->Type = $Type;

        $Response = new ResponseHandler();

        if(!is_null($Code))
        {
            $Response->Code = $Code;
        }
        else
        {
            $Response->Code = 500;
        }
        
        $Response->Data = $this->Message;
        $Response->Respond();
        die();
    }

}