<?php

include "drive_client.php";

define('BOX_COM_','BOX');

class BoxDrive extends DriveClient
{
    const CLIENT_ID="2en9g8pt7jgu5kgvyss7qbrxgk783212";
	const CLIENT_SECRET="t0nY1UF8AkmKZZp7qPEHWU8i2OG2pZwD";
	const REDIRECT_URI='https://' . $_SERVER['HTTP_HOST'] . '/books/box_drive.php';

	const AUTH_URL = "https://account.box.com/api/oauth2/authorize";
	const TOKEN_URL = "https://api.amazon.com/auth/o2/token";
    const API_URL = "https://drive.amazonaws.com";
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function getDriveVendorName()
    {
        return BOX_COM_;
    }
    
    public function login() 
    {
        $url= 'response_type=code' . '&'
        'client_id=' . self::CLIENT_ID . '&'
        'redirect_uri=' urlencode(self::REDIRECT_URI);
    }
    public function uploadFile() 
    {
        
    }
}


?>