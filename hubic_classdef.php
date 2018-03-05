<?php

include_once "drive_client_classdef.php";
include_once "Log.php";

define('HUBIC_','HUB');
define('_HUB_REDIRECT_URI_','https://' . $_SERVER['HTTP_HOST'] . '/books/hubic_drive.php');

class HubicDrive extends Drive\Client
{
  const CLIENT_ID="api_hubic_add0mLihwfGhhE1RAuFNa6S3lqK5TaxT";
	const CLIENT_SECRET="fj9T4CKKPcR6KOz9UKy0N48lhUQGj7RUOx327fKbb9nzziXEqWZgE4MQ6C6TWVeZ";
  const AUTH_URL='https://api.hubic.com/oauth/auth/';
  const TOKEN_URL = "https://api.hubic.com/oauth/token";
	const CREDENTIALS_URL = "https://api.hubic.com/1.0/account/credentials";

	private $openstack_data;
  
  public function __construct()
  {
      parent::__construct();
			if($this->isLogged())
			{
				$this->getOpenStackEndpoint($this->getAccessToken());
			}
  }

  public function getDriveVendorName()
  {
      return HUBIC_;
  }
  
  public function getRedirectUrl()
  {
    return self::AUTH_URL . '?' .
    'client_id=' . self::CLIENT_ID . 
    '&redirect_uri=' . urlencode(_HUB_REDIRECT_URI_) . '&' . 'scope=credentials.r,account.r,links.drw,usage.r' . '&' . 'response_type=code' . '&' . 'state=RandomString_fI6PHgoK5';
  }
  protected function uploadFile(){}
  protected function downloadFile(){}
  protected function deleteFile(){}
  public function isExpired()
  {
    try
    {
        if(!property_exists($this,'token') || $this->token==NULL) throw new \Exception('token not found');
        if(!property_exists($this->token,'expires_in') || $this->token->expires_in==NULL) throw new \Exception('expires_in not found');
        if(!property_exists($this->token,'created') || $this->token->created==NULL) throw new \Exception('created not found');
        return (($this->token->expires_in + $this->token->created)<=time());
    }
    catch(\Exception $e)
    {
        //\Logs\logErr(var_export($this,true));
        \Logs\logWarning($e->getMessage());
    }
    return false;
  }
  protected function onCode($code)
  {
    $body='code=' . $code . '&' .
        'redirect_uri=' . urlencode(_HUB_REDIRECT_URI_) . '&' .
        'grant_type=authorization_code' . '&' .
        'client_id=' . self::CLIENT_ID . '&' .
        'client_secret=' . self::CLIENT_SECRET;;
    
     $options = array ( CURLOPT_HTTPHEADER => array( 'Authorization: Basic ' . base64_encode( self::CLIENT_ID . '.' . self::CLIENT_SECRET) ) );
     $response=json_decode($this->curl_post(self::TOKEN_URL,$body,$options));
    /*
      {
      "access_token"       :"987654321gfedcba",
      "expires_in"         :"3600",
      "refresh_token"      :"123456789abcdefg",
      "token_type"         :"Bearer"
      }
      
      {
      "error"              :"invalid_request",
      "error_description"  :"Invalid code",
      }
    */
    if(property_exists( $response, 'error') )
    {
			\Logs\logErr(var_export($response,true));
      $e = new Exception( $response->error . ':' . $response->error_description );
      throw $e;
    }
    \Logs\logDebug(var_export($response,true));

    $this->set_token($response);
		$this->getOpenStackEndpoint($response->access_token);
  }
  protected function refreshToken()
	{
		
		/*
		refresh_token
				required 	The refresh token of the access token that needs to be refreshed
				refresh_token=123456789abcdefg
		grant_type
				required 	This parameter MUST be set to refresh_token.
				grant_type=refresh_token
		client_id
				optional 	Your client id, obtained when registering your application.
				client_id=api_hubic_1234567AzErTyUiOpQsDfGh
		client_secret
					optional 	Your client secret, obtained when registering your application.
					client_secret=AbCdEfGhIjKl
			*/
			if($this->token && $this->token->refresh_token)
			{
					$refresh_token = $this->token->refresh_token;
					$body = 'refresh_token=' . $refresh_token . '&' .
							'grant_type=refresh_token' . '&' .
							'client_id=' . self::CLIENT_ID . '&' .
							'client_secret=' . self::CLIENT_SECRET;
					\Logs\logDebug($body);
     			$options = array ( CURLOPT_HTTPHEADER => array( 'Authorization: Basic ' . base64_encode( self::CLIENT_ID . '.' . self::CLIENT_SECRET) ) );
					
					$response=json_decode($this->curl_post(self::TOKEN_URL,$body,$options));
					if($response->error)
					{
							throw new Exception($response->error . ':' . $response->error_description);
					}
					$response->refresh_token = $refresh_token;
					\Logs\logDebug(var_export($response,true));
					$this->set_token($response);
			}
			else
			{
					$this->set_token(null);
					throw new Exception('Refresh token not found');
			}
	}

  public function store_info()
	{
		if($this->isLogged())
    {
				$openstack_endpoint=$this->openstack_data->endpoint;
				$xtoken = $this->openstack_data->token;
				$url = $openstack_endpoint .'/default/Documents/Books/{filename}';
				$headers = array ( "X-Auth-Token: $xtoken", "Access-Control-Expose-Headers: Access-Control-Allow-Origin", "Access-Control-Allow-Origin: *" );
				return (object) array (
						'access_token' => $xtoken,
						'urls' => (object)array('download' => (object)array( 'method' => 'GET', 'url' => $url, 'headers' => (object)$headers ),
																		'upload' => (object)array( 'method' => 'PUT', 'url' => $url, 'headers' => (object)$headers, 'body' => '{filecontent}' ),
																		'delete' => (object)array('method' => 'DELETE', 'url' => $url, 'headers' => (object)$headers )
						 ) );
		}
		throw new \Exception('not logged');
	}
  public function downloadLink($fileid)
	{
		if($this->isLogged())
		{
			$openstack_endpoint=$this->openstack_data->endpoint;
			$xtoken = $this->openstack_data->token;
			$url = $openstack_endpoint .'/default/Documents/Books/{filename}';
			$headers = array ( "X-Auth-Token: $xtoken" );
			
			return array( 'method' => 'GET', 'url' =>  $url, 'headers' => $headers);
		}
		else
		{
				throw new \Exception('not logged');
		}
	}
	private function getOpenStackEndpoint($access_token)
	{
		try{
			$option = array( CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $access_token ) );
			$this->openstack_data=json_decode($this->curl_request(self::CREDENTIALS_URL,$option));
			$this->initBookContainer();
		}
		catch(\Exception $e)
		{
			\Logs\logException($e);
		}
	}
	private function initBookContainer()
	{
		$token = $this->openstack_data->token;
		$curl = curl_init();
		$url='https://lb9911.hubic.ovh.net/v1/AUTH_773ddc9402d55f6dd47e9c536c826de3/default/Documents/Books/';
		$headers = array('X-Auth-Token: ' . $token);
 
		$options=array(
			// General options.
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_AUTOREFERER    => true,

			// SSL options.
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_URL            => $url,
			CURLOPT_HTTPHEADER	   => $headers
		);

		curl_setopt_array($curl, $options);
		$result = curl_exec($curl);
		$code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
		
		curl_close($curl);
		
		if($code===404) //does not exist => create it
		{
			\Logs\logInfo('Create Hubic Books root container');
			array_push($headers,'X-Container-Meta-Access-Control-Allow-Origin: *');
			try{
				$this->curl_request($url,array( CURLOPT_PUT => true , CURLOPT_HTTPHEADER => $headers));
			}catch(Exception $e)
			{
				\Logs\logError('Failed to create Books root conatainer on Hubic platform.');
			}
		}
	}
}
?>