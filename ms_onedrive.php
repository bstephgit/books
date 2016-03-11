<?php

set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../onedrive-php-sdk/src/Krizalys/Onedrive/'));

require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Object.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Client.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/File.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Folder.php');

session_start();

define('CLIENT_ID','000000004018962B');
define('REDIRECT_URL','http://albertdupre.esy.es/books/ms_onedrive.php');
define('CLIENT_SECRET','XZxVArudOBTAcEvWlO4zlE4bBXCkfm5P');

class MSOneDriveHelper
{
	private $client;
	private $file_name;

	public function __construct()
	{
		if(isset($_SESSION['onedrive.client.state']))
		{
			$option = array('client_id' => CLIENT_ID,'state' => $_SESSION['onedrive.client.state']);
		}
		else
		{
			$option = array('client_id' => CLIENT_ID);
		}
		$this->client = new \Krizalys\Onedrive\Client($option);
	}
	public function setFileName($file_name)
	{
		$this->file_name=$file_name;
	}
	public function login()
	{
		if (!array_key_exists('code', $_GET))
		{
			$login_url = $this->client->getLogInUrl(array('wl.basic','wl.signin','wl.skydrive_update'),REDIRECT_URL);

			$_SESSION['OD_file']=$this->file_name;
			$_SESSION['onedrive.client.state']=$this->client->getState();
			//should be redirected after execution
			header('Location: '.$login_url);
		}
		else
		{
			$this->client->obtainAccessToken(CLIENT_SECRET,$_GET['code']);
		}

	}
	public function uploadFile()
	{
		$public_docs=$this->client->fetchDocs();
		$books_folder=NULL;
		foreach($public_docs->fetchChildObjects() as $abook)
		{
			//echo $abook->getName();
			if($abook->isFolder() && $abook->getName()=='books')
			{
				$books_folder=$abook;
				break;
			}
		}
		if($books_folder==NULL)
		{
			$books_folder=$public_docs->createFolder('Books');
		}
		return $books_folder->createFile($this->file_name,file_get_contents('temp/'.$this->file_name));
	}
}

if(isset($_GET['code']) && $_SESSION['OD_file'])
{
	try
	{
		$store=new MSOneDriveHelper();
		$store->setFileName($_SESSION['OD_file']);
		$store->login();
		$store->uploadFile();
	}
	catch(Exception $e)
	{
		//header('Location: home.php?error='.$e->getMessage());
		echo $e->getMessage();
		exit();
	}
	unset($_SESSION['OD_file']);
	unset($_SESSION['onedrive.client.state']);
}
?>