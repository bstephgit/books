<?php

include_once "hubic_classdef.php";
//include_once "Log.php";

session_start();


$hubic_helper= new HubicDrive();
$hubic_helper->execute();

/*

define('_REDIRECT_URI_','https://' . $_SERVER['HTTP_HOST'] . '/books/hubic_drive.php');
define('CLIENT_ID',"api_hubic_add0mLihwfGhhE1RAuFNa6S3lqK5TaxT");
define('CLIENT_SECRET',"fj9T4CKKPcR6KOz9UKy0N48lhUQGj7RUOx327fKbb9nzziXEqWZgE4MQ6C6TWVeZ");
define('AUTH_URL','https://api.hubic.com/oauth/auth/');
define('TOKEN_URL', "https://api.hubic.com/oauth/token");



if(isset($_GET['code']))
{
  $code = $_GET['code'];
  
  \Logs\logInfo('code provided=' . $code);
  
  //$hubic_helper->onCode($code);
  
  $body='code=' . $code . '&' .
        'redirect_uri=' . urlencode(_REDIRECT_URI_) . '&' .
        'grant_type=authorization_code' . '&' .
        'client_id=' . CLIENT_ID . '&' .
        'client_secret=' . CLIENT_SECRET;
  
  \Logs\logInfo('body for authorization token={' . $body . '}');
    
  $post_options=array(
             CURLOPT_POST       => true,
             CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER    => true,

            // SSL options.
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL            => TOKEN_URL,
            CURLOPT_HTTPHEADER => array( 'Authorization: Basic ' . base64_encode( CLIENT_ID . '.' . CLIENT_SECRET) )
         );
     $curl = curl_init();
     curl_setopt_array($curl, $post_options);
  
     $result = curl_exec($curl);
    curl_close($curl);
    echo $result;
}
else
{
  $url = $hubic_helper->getRedirectUrl();
  \Logs\logInfo('redirect to ' . $url);
  header('Location: ' . $url);
}

*/
?>