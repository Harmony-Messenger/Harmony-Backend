<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthenticationHandler {
    public bool $Authenticated;
    public ?string $Token;

    public function __construct($Token)
    {
        $this->Token = $Token;
        $this->Authenticated = false;
    }

    public function Authenticate()
    {
        try
        {
            if($this->Token == null)
            {
                $this->Authenticated = false;
            }
            else
            {
                $GLOBALS['AccessToken'] = JWT::decode($this->Token, new Key($GLOBALS['Config']['Security']['JWTKey'], 'HS512'));
                
                $this->Authenticated = true;
            }
        }
        catch(Exception $e)
        {
            $this->Authenticated = false;
        }
        
    }

}