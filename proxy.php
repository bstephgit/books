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
  \Logs\logDebug('headers= ' . var_export($headers,true));
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
    
    $forward_headers = array(
            'Authorization: ' . $tokenid
        );
    
    $max_length = 6 * 1024 * 1024;
    if(isset($_GET['filesize']))
    {
      \Logs\logDebug('file size=' . $_GET['filesize']);
      $filesize=intval($_GET['filesize']);
      if($filesize>$max_length)
      {
        $has_range=false;
        $range='Range: ';
        if(isset($headers['Range']))
        {
          $range = $range . $headers['Range']; 
          $has_range=true;
        }
        if(isset($headers['range']))
        {
          $range = $range . $headers['range']; 
          $has_range=true;
        }
        if(!$has_range)
        {
          $range = $range . 'bytes=0-' . strval($max_length-1);
        }
        array_push($forward_headers, $range);
        \Logs\logDebug('Add header ' . $range);
        
        http_response_code(206);
        
        $range_min = 0;
        $range_max = 0;
        sscanf($range,"Range: bytes=%d-%d",$range_min,$range_max);
        $content_length='Content-Length: ' . strval($range_max-$range_min+1);
        \Logs\logDebug('Send header ' . $content_length);
        header($content_length);
        
        $content_range='Content-Range: bytes ' . strval($range_min) . '-' . strval($range_max) . '/' . $filesize;
        \Logs\logDebug('Send header ' . $content_range);
        header($content_range);
      }
      else if($filesize===0)
      {
        \Logs\logError('Bad file size parameter: \'' . $_GET['filesize'] . '\'');
      }
      else
      {
        \Logs\logInfo('Content-Length: ' . strval($filesize));
        header('Content-Length: ' . strval($filesize));
      }
    }
    else
    {
      \Logs\logWarning('No file size provided in url parameter.');
    }
    \Logs\logDebug('access_token=' . $tokenid);
    
    header('Content-Type: application/octet-stream');
    
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
        CURLOPT_HTTPHEADER => $forward_headers
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
      \Logs\logErr($msg);
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