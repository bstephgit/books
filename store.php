<?php

include_once "amazon_classdef.php";
include_once "box_classdef.php";
include_once "google_classdef.php";
include_once "onedrive_classdef.php";
include_once "pcloud_classdef.php";
include_once "db.php";

function createDriveClient($drive_code)
{
    switch($drive_code)
    {
        case 'GOOG': return new \GoogleDriveHelper();
        case 'MSOD': return new \MSOneDriveHelper();
        case 'AMZN': return new \AmazonCloudHelper();
        case 'BOX': return new \BoxDrive();
        case 'PCLD': return new \PCloudDrive();
            
        default: throw new \Exception('Unknown code for store: ' . $drive_code);
    }
}


$html_response="<html><body>
    <script type='text/javascript'>
        var result='%s';
        window.opener.postMessage(result,window.location.origin);
        document.write(result);
        window.close();
    </script>
</body></html>";

session_start();


function checkLogin($vendor_store)
{
    \Logs\logDebug('checkLogin: start');
    $client=createDriveClient($vendor_store);
    \Logs\logDebug('checkLogin: client created');
    if(!$client->isLogged())
    {
        if($client->isExpired())
        {
            \Logs\logDebug('checkLogin: expired');
            try{
                $client->refreshToken();
                return $client;
            }
            catch(\Exception $e)
            {
                \Logs\logWarning($e->getMessage());
            }
        }
        \Logs\logDebug('checkLogin: redirect');
        echo json_encode( (object)array( 'redirect' => $client->getRedirectUrl() ) );
        exit;
    }
    \Logs\logDebug('checkLogin: return client');
    return $client;
}

$action='';
$bookid='';
$store='';
$html='';
$tag='';

if(isset($_GET['action'])) $action=$_GET['action'];
if(isset($_GET['bookid'])) $bookid=$_GET['bookid'];
if(isset($_GET['store_code'])) $store=$_GET['store_code'];
if(isset($_GET['html'])) $html=$_GET['html'];
if(isset($_GET['tag'])) $tag=$_GET['tag'];

if($action==='login')
{
    if(strlen($store)==0)
    {
      http_response_code(400);
      echo '{"error":"no vendor store code"}';
      exit;
    }
    $client=checkLogin($store);
    if($client)
    {
      $info=json_encode($client->store_info(),JSON_UNESCAPED_SLASHES );
      if($html==='true') //browser is wating html code to process login info
      {
          $info=sprintf($html_response,$info);
          \Logs\logDebug($info);
          echo $info;
      }
      else
      {
          \Logs\logDebug('raw resp: ' . $info);
          echo $info;
      }
    }
}


if($action==='downloadLink')
{
  if(strlen($bookid)==0)
  {
    http_response_code(400);
    \Logs\logError("book id null");
    echo '{"error" : "book id null" }';
    exit;
  }
  
  $dbase=\Database\odbc()->connect();
  if($dbase)
  {
    $sql="SELECT FILE_ID,FILE_NAME,FILE_SIZE,VENDOR_CODE FROM BOOKS_LINKS,FILE_STORE AS FS WHERE BOOK_ID=$bookid AND STORE_ID=FS.ID";
    $rec=$dbase->query($sql);
    if($rec->next())
    {
      $fileid=$rec->field_value('FILE_ID');
      $filename=$rec->field_value('FILE_NAME');
      $file_size=$rec->field_value('FILE_SIZE');
      $vendor=$rec->field_value('VENDOR_CODE');
      
      $client=checkLogin($vendor);
      if($client)
      {
        $access_token=$client->getAccessToken();

        $link=(object)$client->downloadLink($fileid);
        if($client->useDownloadProxy())
        {
          $link->url='proxy.php?action=download&url=' . base64_encode($link->url) . '&filesize=' . $file_size;
          array_push($link->headers,"Authorization: Basic " . base64_encode('b13_17778490:tecste1'));
          array_push($link->headers,"Store-Token: $access_token");
        }
        
        $link=json_encode($link);
        
        $result = "{\"access_token\": \"$access_token\", \"fileid\": \"$fileid\", \"filename\":\"$filename\", \"filesize\": $file_size, \"vendor_code\": \"$vendor\", \"downloadLink\": $link }";

        \Logs\logInfo(var_export($result,true));
        if($html==='true')
        {
          echo sprintf($html_response,$result);
        }
        else
        {
          echo $result;
        }
      }

    }
    else {
      \Logs\logError("book not found in database");
      http_response_code(400); 
      echo '{ "error": "book not found in database" }'; 
    }
    
    $dbase->close();
  }
  else
  {
    http_response_code(400);
    \Logs\logError("cannot open database");
    echo '{"error":"cannot open database"}';
  }
}

if($action==='taglist' && strlen($tag)>0)
{
  \Logs\logDebug('tag=' . $tag);
  $dbase=\Database\odbc()->connect();
  $sql="SELECT ID,NAME FROM IT_SUBJECT WHERE NAME REGEXP '$tag' ORDER BY NAME";
  $rec=$dbase->query($sql);
  $res=array();
  while($rec->next())
  {
    \Logs\logDebug('tag' . $rec->field_value('NAME'));
  array_push($res, (object)array('id'=>$rec->field_value('ID'),'name'=>$rec->field_value('NAME')));
  }
  $dbase->close();
  \Logs\logDebug(json_encode($res));
  echo json_encode($res);
}
if($action==='newtag' && strlen($tag)>0)
{
  $dbase=\Database\odbc()->connect();
 
  $res='';
  $tag=strtoupper($tag);
  $sql='SELECT ID FROM IT_SUBJECT WHERE NAME=\'' . $tag . '\'';
  $rec=$dbase->query($sql);
  if($rec->next())
  {
    $res = json_encode((object)array('id' => $rec->field_value('ID'),'name' => $rec->field_value('NAME')));
    \Logs\logWarning('Tag \'' . $tag . '\' already created');
  }
  else
  {
    $dbase->query('INSERT INTO IT_SUBJECT (NAME) VALUES(\''. $tag .'\')');
    $rec=$dbase->query($sql);
    \Logs\logInfo('Create tag \'' . $tag . '\'');
    $res = json_encode((object)array('id' => $rec->field_value('ID'),'name' => $rec->field_value('NAME')));
  }
  $dbase->close();
  echo $res;
}
?>