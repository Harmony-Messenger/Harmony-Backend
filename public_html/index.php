<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once('../Classes/DB.php');
require_once('../Classes/RequestHandler.php');

$DB = new DB();

if(!$DB->Connected)
{
    $DB = null;
}

if($DB == null && is_dir('../Setup'))
{
    include('../Setup/index.php');
}
else
{
    $RequestHandler = new RequestHandler($DB);
}