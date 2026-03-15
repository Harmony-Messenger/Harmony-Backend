<?php
require_once('ErrorHandler.php');

class DB {
	
	private string $Server;
	private string $Database;
	private string $Username;
	private string $Password;
	private int $Port;
	public bool $Connected;
	public PDO $Handler;
	public string $ErrorState;

	public function __construct()
	{
		if(isset($GLOBALS['Config']['Database']['Server']) && $GLOBALS['Config']['Database']['Server'] != ''
		&& isset($GLOBALS['Config']['Database']['Database']) && $GLOBALS['Config']['Database']['Database'] != '' 
		&& isset($GLOBALS['Config']['Database']['Username']) && $GLOBALS['Config']['Database']['Username'] != '' 
		&& isset($GLOBALS['Config']['Database']['Password']) && $GLOBALS['Config']['Database']['Password'] != ''
		&& isset($GLOBALS['Config']['Database']['Port']) && $GLOBALS['Config']['Database']['Port'] != '')
		{
			$this->Server = $GLOBALS['Config']['Database']['Server'];
			$this->Database = $GLOBALS['Config']['Database']['Database'];		
			$this->Username = $GLOBALS['Config']['Database']['Username'];
			$this->Password = $GLOBALS['Config']['Database']['Password'];
			$this->Port = $GLOBALS['Config']['Database']['Port'];

			try {
				$this->Handler = new PDO('mysql:host='.$this->Server.';dbname='.$this->Database.';charset=utf8', $this->Username, $this->Password);
				$this->Connected = true;
			}
			catch(PDOException $e)
			{
				$this->Connected = false;
			}
		}
		else
		{
			$this->Connected = false;
		}
	}
}