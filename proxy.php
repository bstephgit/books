<?php
include_once "Log.php";

$action='';

if(isset($_GET['action']))
{
  $action=$_GET['action'];
}

if($action==='download')
{
  $url=base64_decode($_GET['url']);
  \Logs\logDebug($url);
  $headers=getallheaders();
  if(isset($headers['Authorization']) || isset($headers['authorization']))
  {
    $tokenid='';
    if(isset($headers["Store-Token"]))
    {
      $tokenid='Authorization: Bearer ' . $headers["Store-Token"];
    }
    if(isset($headers["store-token"]))
    {
      $tokenid='Authorization: Bearer ' . $headers["store-token"];
    }
    \Logs\logDebug('access_token=' . $tokenid);
    
    $curl = curl_init();
    //$out = fopen('php://output', 'w');
    $options=array(
        CURLOPT_AUTOREFERER    => true,

        // SSL options.
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_URL            => $url,
        CURLOPT_FOLLOWLOCATION => true,
        //CURLOPT_VERBOSE => 1 ,  
        //CURLOPT_STDERR => $out,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $tokenid
        )
    );

    curl_setopt_array($curl, $options);
    $result = curl_exec($curl);

    $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);

    if($code<400)
    {
      \Logs\logInfo($code);
    }
    else
    {
      $msg = sprintf('{"error":"opening url %s [%s]"}',$url,$tokenid);
      //$debug = ob_get_clean();
      //\Logs\logError('error: ' . $debug);
      \Logs\logError($msg);
    }
    //fclose($out);
    http_response_code($code);

    curl_close($curl);
    //$out=fopen('debug.txt','w');
    //fwrite($out,$debug);
    //fclose($out);
  }
  else
  {
    http_response_code(400);
    \Logs\logWarning("Authorisation header is missing");
    echo '{"error":"Authorisation header is missing"}';
  }
  
}
?>