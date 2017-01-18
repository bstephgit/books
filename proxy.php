<?php


$action='';

if(isset($_GET['action']))
{
  $action=$_GET['action'];
}

if($action==='download')
{
  $url=base64_decode($_GET['url']);
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
    if($result)
    {
      $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
      http_response_code($code);
    }
    else
    {
      $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
      http_response_code($code);
      echo sprintf('{"error":"opening url %s [%s]"}',$url,$tokenid);
    }
    //fclose($out);
    curl_close($curl);
    //$debug = ob_get_clean();
    //$out=fopen('debug.txt','w');
    //fwrite($out,$debug);
    //fclose($out);
  }
  else
  {
    http_response_code(400);
    echo '{"error":"Authorisation header is missing"}';
  }
  
}
?>