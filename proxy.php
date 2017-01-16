<?php


$action='';

if(isset($_GET['action']))
{
  $action=$_GET['action'];
}

if($action==='download')
{
  $url=urldecode($_GET['url']);
  $headers=getallheaders();
  if(isset($headers['Authorization']) || isset($headers['authorization']))
  {
    $tokenid='';
    if(isset($headers['Authorization']))
    {
      $tokenid=$headers['Authorization'];
    }
    if(isset($headers['authorization']))
    {
      $tokenid=$headers['authorization'];
    }
    $curl = curl_init();
    $options=array(
        CURLOPT_AUTOREFERER    => true,

        // SSL options.
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_URL            => $url,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $tokenid
        )
    );

    curl_setopt_array($curl, $options);
    $result = curl_exec($curl);
    if($result)
    {
      $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
      http_response_code(500);
    }
    else
    {
      http_response_code(500);
      echo sprintf('{"error":"opening url %s [%s]"}',$url,$tokenid);
    }
    
    curl_close($curl);
  }
  else
  {
    http_response_code(400);
    echo '{"error":"Authorisation header is missing"}';
  }
  
}
?>