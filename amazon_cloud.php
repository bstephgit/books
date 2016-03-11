<?php

start_session();

if(!isset($_SESSION['AMZN']))
{
	$_SESSION['AMZN']=array( 'code' => null , 'access_token' => null , 'current_file' => null );
}

class AmazonCloudHelper
{
	const $client_id="amzn1.application-oa2-client.4e00bfdaef2d4892846825fe546bf80d";
	const $client_secret="852a445d0e389160932648d79b534ebe0eb3c6268bd4f0e5c823a4a9c3ed8733";
	const $redirect_uri="http://albertdupre.esy.es/books/amazon_cloud.php";

	const AUTH_URL = "https://www.amazon.com/ap/oa";
	const TOKEN_URL="https://api.amazon.com/auth/o2/token";

	public function __construct()
	{

	}

	public function setFileName($file_name)
	{

	}
	public function login()
	{
		if(isset($_GET['code']))
		{
			$_SESSION['AMZN']['code']=$_GET['code'];
			$body=array(
				'grant_type' => 'authorization_code' ,
				'code' => $_SESSION['AMZN']['code'] ,
				'client_id' => urlencode($this->client_id) ,
				'client_secret' => urlencode($this->client_secret) ,
				'redirect_uri' => urlencode($this->redirect_uri)
				);

			$options=array(
				CURLOPT_POST       => true,

				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/x-www-form-urlencoded'
            	),

            	CURLOPT_POSTFIELDS => $body
        	);
			$this->curl_request(self::TOKEN_URL,$options);
		}
		else
		{
			$url = self::AUTH_URL
				. '?client_id=' . urlencode($this->client_id)
				. '&scope=' . urlencode('clouddrive:read_all clouddrive:write')
				. '&response_type=code'
				. '&redirect_uri=' . urlencode($redirect_uri);

			header('Location: '.$url);
		}

        return $url;
	}
	public function uploadFile()
	{

	}

	private function curl_request($url, $options = array())
	{
		$curl = curl_init();

        $defaultOptions=array(
            // General options.
            CURLOPT_RETURNTRANSFER => true,
            //CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,

            // SSL options.
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL            => $url
        );

		curl_setopt_array($curl,$defaultOptions + $options);
        return curl_exec($curl);
	}
}

?>