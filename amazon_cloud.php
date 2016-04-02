<?php

include "drive_client.php";

define('AMAZON_','AMZN');


class AmazonCloudHelper extends DriveClient
{
	const CLIENT_ID="amzn1.application-oa2-client.4e00bfdaef2d4892846825fe546bf80d";
	const CLIENT_SECRET="852a445d0e389160932648d79b534ebe0eb3c6268bd4f0e5c823a4a9c3ed8733";
	const REDIRECT_URI='https://albertdupre.byethost13.com/books/amazon_cloud.php';

	const AUTH_URL = "https://www.amazon.com/ap/oa";
	const TOKEN_URL = "https://api.amazon.com/auth/o2/token";
    const API_URL = "https://drive.amazonaws.com";
    
	public function __construct()
	{
       parent::__construct();
	}

    public function getDriveVendorName()
    {
        return AMAZON_;
    }
    
    private function retrieve_parameters($response)
    {
        if(property_exists($response,'access_token'))
        {
            $_SESSION[AMAZON_]['access_token']=$response->access_token;
        }
        else 
        {
            throw new \Exception('access_token not found');
        }
        
        if(property_exists($response,'refresh_token'))
        {
            $_SESSION[AMAZON_]['refresh_token']=$response->refresh_token;                
        }
        else 
        {
            throw new \Exception('refresh_token not found');
        }
        if(property_exists($response,'expires_in'))
        {
            $_SESSION[AMAZON_]['expires_in']=time() + intval($response->expires_in);
        }
    }
	public function login()
	{
        if(isset($_SESSION[AMAZON_]['access_token']))
        {
            if(isset($_SESSION[AMAZON_]['expires_in']) && $_SESSION[AMAZON_]['expires_in']<=time())
            {
                $refresh_token=$_SESSION[AMAZON_]['refresh_token'];
                if($refresh_token==null)
                {
                    throw new \Exception('cannot find refresh token');
                }
                $body= 'grant_type=refresh_token' . '&' .
				'refresh_token=' . $refresh_token . '&' .
				'client_id=' . self::CLIENT_ID . '&' .
				'client_secret=' . self::CLIENT_SECRET ;

                $options=array(
                    CURLOPT_POST       => true,

                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/x-www-form-urlencoded'
                    ),

                    CURLOPT_POSTFIELDS => $body
                );
                $response=$this->curl_request(self::TOKEN_URL,$options);
                $response=json_decode($response);
                $this->retrieve_parameters($response);
            }
            return;
        }
		if(isset($_GET['code']))
		{
			$code=$_GET['code'];
			$body= 'grant_type=authorization_code' . '&' .
				'code=' . $code . '&' .
				'client_id=' . self::CLIENT_ID . '&' .
				'client_secret=' . self::CLIENT_SECRET . '&' .
				'redirect_uri=' . self::REDIRECT_URI;

			$options=array(
				CURLOPT_POST       => true,

				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/x-www-form-urlencoded'
            	),

            	CURLOPT_POSTFIELDS => $body
        	);
            $response=$this->curl_request(self::TOKEN_URL,$options);
			$response=json_decode($response);
            $this->retrieve_parameters($response);
		}
		else
		{
			$url = self::AUTH_URL
				. '?client_id=' . self::CLIENT_ID
				. '&scope=' . urlencode('clouddrive:read_document clouddrive:write')
				. '&response_type=code'
				. '&redirect_uri=' ;

			header('Location: '. $url . self::REDIRECT_URI);
		}
	}
	public function uploadFile()
	{
       $this->getEndPoint();
       $content_url=$_SESSION[AMAZON_]['contentUrl'];
       if($content_url!=null)
       {
           $book_dir=$this->getBookDir();
           
           $the_file= new CURLFile('temp/'.$this->getFileName());
           
           $metadata=json_encode(array( 'name' => $this->getFileName(), 'kind' => 'FILE', 'parents' => array($book_dir->id)));
           $body=array(
               'metadata' => $metadata,
               'content' => $the_file
           );
           $options=array(
				CURLOPT_POST       => true,

				CURLOPT_HTTPHEADER => array(
					'Authorization: Bearer ' . $this->getSessionVar('access_token'),
					'Content-Type: multipart/form-data'
            	),

            	CURLOPT_POSTFIELDS => $body
        	);
            $url=$this->getSessionVar('contentUrl') . '/nodes';
            $response=$this->curl_request($url,$options);
			$response=json_decode($response);
       }
       else
       {
           throw new \Exception('cannot get conntent url for Amazon cloud');
       }
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
        $result = curl_exec($curl);
        if (false === $result) {
            throw new \Exception('curl_exec() failed: ' . curl_error($curl));
        }
        
        return $result;
	}
    
    private function getEndPoint()
    {
        if(isset($_SESSION[AMAZON_]['contentUrl']))
        {
            return;
        }
        $url = self::API_URL . '/drive/v1/account/endpoint';
        
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $_SESSION[AMAZON_]['access_token'])
        );
        
        $response=$this->curl_request($url,$options);
        $response=json_decode( $response );
 
        if(property_exists($response,'customerExists'))
        {
            if($response->customerExists===false)
            {
                throw new \Exception('getEndPoint failed: customer does not exist');
            }
        }
        if(property_exists($response,'contentUrl'))
        {
            $_SESSION[AMAZON_]['contentUrl']=$response->contentUrl;
        }
        else 
        {
            throw new \Exception('contentUrl not found');
        }
        if(property_exists($response,'metadataUrl'))
        {
            $_SESSION[AMAZON_]['metadataUrl']=$response->metadataUrl;
        }
        else 
        {
            throw new \Exception('metadataUrl not found');
        }
    }
    
    private function getBookDir()
    {
        $books_folder=$this->getFolder('Books');
        if($books_folder==null)
        {
            $books_folder=$this->createFolder('Books');
            var_dump($books_folder);
        }
        return $books_folder;
    }
    
    private function getFolder($name)
    {
        $url = $this->getSessionVar('metadataUrl') . '/nodes?filters=kind:FOLDER AND name:' . $name;
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
            
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);

        if($reponse!=null && $response->data!=null)
        {
            foreach($response->$data as $node)
            {
                if($node->name===$name)
                {
                    return $node;
                }
            }
        }
        return null;
    }
    
    private function createFolder($name)
    {
        $url = $this->getSessionVar('metadataUrl') . '/nodes';
        $data=(object)array("name"=>"Books", "kind"=>"FOLDER","labels"=>array("BookStore"));
        $options= array(
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
           
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        return $response;
    }
    
    
}

session_start();

if(!isset($_SESSION[AMAZON_]) || !is_array($_SESSION[AMAZON_]))
{
	$_SESSION[AMAZON_]=array( 
        'code' => null , 
        'access_token' => null, 
        'refresh_token' => null, 
        'current_file' => null,
        'contentUrl' => null,
        'metadataUrl' => null,
        'current_file' => null,
        'expires_in' => 0 );
}

try
{
    $amzn_drive=new AmazonCloudHelper();
    $amzn_drive->login();
    $amzn_drive->uploadFile();
    header('Location: home.php');
}
catch(Exception $e)
{
    echo $e->getMessage();
}

?>