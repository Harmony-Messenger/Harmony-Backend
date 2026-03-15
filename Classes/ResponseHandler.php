<?php
class ResponseHandler {
    public int $Code;
    public array $Data;

    public function Respond()
    {
        if(isset($this->Code) && isset($this->Data))
        {
            header("Access-Control-Allow-Origin: *");
            http_response_code($this->Code);
            echo json_encode($this->Data);
        }
        else
        {
            http_response_code(500);
            echo json_encode("Response Handler went really wrong.");
        }
    }
}