<?php
require_once "Log.php";

function curl_uploaf($headers,$url)
{
  \Logs\logInfo(var_export($_FILES, true));
  \Logs\logInfo(var_export($_POST, true));

  $curl = curl_init();

  $out = fopen('./debug/curl.txt', 'w');

  if ($out===NULL)
  {
    \logs\logWarning("curl debug file descriptor is NULL");
  }
  $options=array(
    CURLOPT_AUTOREFERER    => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,


    // SSL options.
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_URL            => $url,
    CURLOPT_VERBOSE => true ,  
    CURLOPT_STDERR => $out,
    CURLOPT_HTTPHEADER => array(
           'Content-Length: ' . $headers['Content-Length']
           //'Content-Type: multipart/form-data; boundary=---------------------------198583554516041417397223037'
       ),
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_SAFE_UPLOAD => true
  );

  $post_fields = array();

  foreach ( $_POST as $key => $val )
  {
      $post_fields[$key] = $val;
  }

  if (count($_FILES) > 0) {
      $keys = array_keys($_FILES);
      if (count($keys)>1) {
          \Logs\logWarning('Nb of uploaded file > 1; only first will be processed');
      }
      $file = $_FILES[$keys[0]];

      /*array ( 'file' => array ( 'name' => 'ursaf.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/phpn7dNUz', 'error' => 0, 'size' => 21690, ), )*/


      if ($file['error'] == 0) {
          $post_fields[$keys[0]] = new CurlFile($file['tmp_name'], $file['type'], $file['name']);
      }
      else
      {
          \Logs\logError('Error ' . strval($file['error']) . ' while uploading file ' . $file['name']);
      }
  }
  else
  {
      \Logs\logError('Cannot find uploaded file');
  }

  if (count($post_fields)>0) {
      $post_fields['test'] = 'test';
      $options[CURLOPT_POSTFIELDS] = $post_fields;
  }

  if ($method == "POST" ) {
      $options[CURLOPT_POST] = true;
      \Logs\logDebug($method);

  } else if ($method == "PUT" ) {
      $options[CURLOPT_PUT] = true;
      \Logs\logDebug($method);

  }

  \Logs\logDebug(var_export($options,true));

  curl_setopt_array($curl, $options);
  $resp=curl_exec($curl);

  $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl); 
  fclose($out);


  \Logs\logDebug('http returned code=' . strval($http_code) . '; response data={' . strval($resp) . '}');

  http_response_code($http_code);
  echo strval($resp);

}

function log_request(){
  $fd_input = fopen('php://input','r');
  $fd_out = fopen('./debug/request', 'w');

  while(true)
  {
      $buffer = fgets($fd_input,4096);
      if(strlen($buffer)==0)
      {
        echo '{error: 1}';
        fclose($fd_input);
        fclose($fd_out);
        return;
      }
      fwrite($fd_out,$buffer);
  }
}
//if(!function_exists('getallheaders'))
//{
function _getallheaders() 
{
    $str='';
    foreach($_SERVER as $name => $value)
    {
        if(substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
        if (substr($name, 0, 7) == 'CONTENT') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))))] = $value;
        }
        //$str = $str . '[' . $name . ',' . $value . ']';
    }
    //\Logs\logDebug($str);
    return $headers;
}
//}

const BOUNDARY = 'boundary=';


function forward_post($method,$headers,$url)
{
  //'multipart/form-data; boundary=---------------------------6214740566044449941281900750'
  if (!isset($headers['Content-Type']))
    throw new \Exception('Content-Type not defined');

  $content_type = $headers['Content-Type'];
  if ( strstr($content_type,'multipart/form-data') && strstr($content_type,BOUNDARY))
  {
     $boundary = substr($content_type, strpos($content_type, BOUNDARY)+strlen(BOUNDARY));
     \Logs\logDebug('multipart boundary='. $boundary);

     $headers['Host'] = parse_url($url, PHP_URL_HOST);
     $header['Expect'] = '100-continue';

    
     \Logs\logDebug("headers: " . var_export($headers,true));o

     $scheme = parse_url($url,PHP_URL_SCHEME);
     $ctx = NULL;
     $fd_out = NULL;
     $errno = 0;
     $errstr = '';
     if ($scheme==='https')
     {
      $opts = array(
        'tls' => array(
            'verify_peer' => true,
            'verify_peer_name' => false
        ) 
      );
      //$ctx = stream_context_create( $opts );
      \Logs\logDebug('Open TLS connection');
      $fd_out = stream_socket_client('tls://' . $headers['Host'] . ':443',$errno,$errstr,10); //STREAM_CLIENT_CONNECT,$ctx


     }
     else if ($scheme === 'http')
     {
      $opts = array(
        'socket' => array(
            'bindto' => gethostbyname($headers['Host']) . '80'
        ) 
      );
      \Logs\logDebug('Open Socket connection');

      //$ctx = stream_context_create( $opts );
      $fd_out = stream_socket_client('tcp://' . gethostbyname($headers['Host']) . ':80',$errno,$errstr,10); //,STREAM_CLIENT_CONNECT,$ctx

     }
     else
     {
      throw new \Exception('Scheme "' . $scheme . '"" not supported');
     }

     if(!$fd_out)
     {
      throw new \Exception('Cannot open stream for upload. Errno=' . $errno . '. Errstr=' . $errstr);
     }

     $path_query = parse_url($url,PHP_URL_PATH) . '?' . parse_url($url,PHP_URL_QUERY);

     \Logs\logDebug('Url path/query: ' . $path_query);

     $crlf = "\r\n";

     fwrite($fd_out, $method . ' ' . $path_query . ' HTTP/1.1' . $crlf);
     foreach($headers as $hname => $hval)
     {
      fwrite($fd_out,$hname . ': ' . $hval . $crlf);
     }

     fwrite($fd_out,$crlf);

     $content_length = 0;
     foreach ($_FILES as $name => $data) 
     {

        /*array ( 'file' => array ( 'name' => 'ursaf.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/phpn7dNUz', 'error' => 0, 'size' => 21690, ), )*/

        if ($file['error'] == 0) {
            
            \Logs\logDebug('Sending file ' . $data['name'] . ' size=' . $data['size'] . '...');
            $fd_in = fopen(realpath($data['tmp_name']), 'rb');
            if($fd_in){
                /*
              -----------------------------93053841113494412371192488485
              Content-Disposition: form-data; name="file"; filename="ursaf.pdf"
              Content-Type: application/pdf
              */
              $content_length += fwrite($fd_out, $crlf . $boundary .$crlf . 'Content-Disposition: form-data; name="' . 
                $name . '"; filename="' . $data['name'] . '"' . $crlf . 'Content-Type: ' . $data['type'] . $crlf . $crlf );
              
              $bytes_sent = 0;
              /*while($bytes_sent < $data['size'])
              {
                $buffer = stream_get_contents($fd_in,4096,$bytes_read);

                $len = strlen($buffer);
                $bytes_read += $len;
                $r = fwrite($fd_out,$buffer,$len);
                if($r !== false)
                {
                  $bytes_sent += $r;
                }
                else
                {
                    throw new \Exception('Error reading file at byte ' . strval($bytes_sent));
                    //\Logs\logWarning('Error reading file at byte ' . strval($bytes_sent));
                }
              }
              while (false && $bytes_sent !=== false && $bytes_sent < $data['size'])
              {
                 $ret = stream_copy_to_stream($fd_in, $fd_out, $data['size'],$bytes_sent); 
                 if($ref === false)
                 {
                  $bytes_sent = $ret;
                 }
                 else
                 {
                  $bytes_sent += $ret; 
                 }
                 
              }
              
              if($bytes_sent === false)
              {
                fclose($fd_out);
                throw new \Exception('Error reading input stream');
              }*/
              \Logs\logDebug('File ' . $data['name'] .' sent. Bytes sent=' . strval($bytes_sent));
              $content_length += $bytes_sent;
              fclose($fd_in);
              $content_length += fwrite($fd_out, $crlf);

            }
            else
            {
              $msg = 'Error opening file ' . $data['name'] . ' at path ' . $data['tmp_name'];
              \Logs\logError($msg);
              fclose($fd_out);
              throw \Exception($msg);
            }

        }
        else
        {
            $msg = 'Error ' . strval($file['error']) . ' while uploading file ' . $file['name'];
            \Logs\logError($msg);
            fclose($fd_out);
            throw \Exception($msg);
        }
      }
      /*
      $content_length += fwrite($fd_out, $boundary . '--');

      $expected_length = $headers['Content-Length'];
      \Logs\logDebug(strval($content_length) . ' bytes sent. ' . $expected_length . ' bytes expected.');

      while($content_length < $expected_length)
      {
        $content_length += fwrite($fd_out, $crlf);
      }
      \Logs\logDebug('End sending data, ' . strval($content_length) . ' bytes sent.');
      */
      \Logs\logDebug('Waiting response...');

      $part = 0;
      $next;
      $pos = 0;
      $str = '';

      while(!feof($fd_out)){
        
        $str = fread($fd_out,1024);
        \Logs\logDebug($str);

        $next = strpos($str,$crlf,$pos);
        while($part < 2 && $next !== false)
        {
          $next+=strlen($crlf);
          $line = substr($str,$pos,$next-$pos);

          \Logs\logDebug('line read: "' . $line . '"');
          if ($part==0)
          {
            $token = strtok($line,' ');
            \Logs\logDebug('response 1st token:' . $token );
            if($token !== 'HTTP/1.1')
            {
              throw new \Exception('First line should be HTTP status line not ' . $line);
            }
            $token = strtok(' ');
            \Logs\logDebug('Http response code ' . $token);

            http_response_code(intval($token));

            $token = strtok($crlf);
            \Logs\logDebug('Http message ' . $token);
            $part += 1;
          }
          else if ($part == 1)
          {
            if ($line !== $crlf)
            {
              $h = substr($line,0,strlen($line)-2);
              \Logs\logDebug('Send header ' . $h);
              header($h);
            }
            else
            {
              $part += 1;
              $str = substr($str,$pos+2);
            }
          }
          $pos = $next;
          $next = strpos($str,$crlf,$pos);
        }
        \Logs\logDebug('Write response body "' . $str . '"');
        echo $str;
      }
      fclose($fd_out);
      \Logs\logDebug('End response');

    }
    else
    {
        $msg = 'Cannot find uploaded file';
        \Logs\logError($msg);
        throw \Exception($msg);
    }
  
}

$action='';

if(isset($_GET['action'])) {
    $action=$_GET['action'];
}

if($action==='download') {
    $url=base64_decode($_GET['url']);
    \Logs\logDebug($url);
    $headers=_getallheaders();

    \Logs\logDebug('headers= ' . var_export($headers, true));
    if(isset($headers['Authorization']) || isset($headers['authorization'])) {
        $tokenid='';
        if(isset($headers["Store-Token"])) {
            $tokenid='Authorization: Bearer ' . $headers["Store-Token"];
        }
        if(isset($headers["store-token"])) {
            $tokenid='Authorization: Bearer ' . $headers["store-token"];
        }
    
        $forward_headers = array(
            'Authorization: ' . $tokenid
        );
    
        $max_length = 6 * 1024 * 1024;
        if(isset($_GET['filesize'])) {
            \Logs\logDebug('file size=' . $_GET['filesize']);
            $filesize=intval($_GET['filesize']);
            if($filesize>$max_length) {
                $has_range=false;
                $range='Range: ';
                if(isset($headers['Range'])) {
                    $range = $range . $headers['Range']; 
                    $has_range=true;
                }
                if(isset($headers['range'])) {
                    $range = $range . $headers['range']; 
                    $has_range=true;
                }
                if(!$has_range) {
                    $range = $range . 'bytes=0-' . strval($max_length-1);
                }
                array_push($forward_headers, $range);
                \Logs\logDebug('Add header ' . $range);
        
                http_response_code(206);
        
                $range_min = 0;
                $range_max = 0;
                sscanf($range, "Range: bytes=%d-%d", $range_min, $range_max);
                $content_length='Content-Length: ' . strval($range_max-$range_min+1);
                \Logs\logDebug('Send header ' . $content_length);
                header($content_length);
        
                $content_range='Content-Range: bytes ' . strval($range_min) . '-' . strval($range_max) . '/' . $filesize;
                \Logs\logDebug('Send header ' . $content_range);
                header($content_range);
            }
            else if($filesize===0) {
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

        $code=curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if($code<400) {
            \Logs\logInfo($code);
        }
        else
        {
            $msg = sprintf('{"error":"opening url %s [%s]"}', $url, $tokenid);
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

if ($action === "upload") {
  
    try{


        $headers=_getallheaders();
        \Logs\logDebug(var_export($headers, true));

        $url=urldecode($headers['Xforward-To-Url']);
        $url= $url . '?' . $headers['Xforward-Query'];

        \Logs\logDebug($url);

        unset($headers['Xforward-To-Url']);
        unset($headers['Xforward-Query']);

        $method = $_SERVER['REQUEST_METHOD'];

        if($method=='POST')
        {
          forward_post($method,$headers,$url);
        }
        else
        {
          throw new \Exception('Only POST method implemented. Not ' . $method);
        }

    }
    catch(\Exception $e)
    {
        \Logs\logException($e);
        $err = (object) array('error' => $e->getMessage());
        http_response_code(500);

        echo json_encode($err);
    }
  
}
?>