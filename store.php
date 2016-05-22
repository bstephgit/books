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
        case 'GOOG':
            {
                return new \GoogleDriveHelper();
            }
        case 'MSOD':
            {
                return new \MSOneDriveHelper();

            }
        case 'AMZN':
            {
                return new \AmazonCloudHelper();

            }
        case 'BOX':
            {
                return new \BoxDrive();

            }
        case 'PCLD':
            {
                return new \PCloudDrive();

            }
        default:
            throw new \Exception('Unknown code for store: ' . $drive_code);
    }
}


$html_response="<html><body>
    <script type='text/javascript'>
        var info_token='%s';
        window.opener.postMessage(info_token,window.location.origin);
        document.write(info_token);
        window.close();
    </script>
</body></html>";

session_start();

if(isset($_GET['store_code']))
{
    $store=$_GET['store_code'];
    //$url="drive_client.php?action=login&store_code=$store";

    $client=createDriveClient($store);

    if(isset($_GET['action']) && $_GET['action']==='login')
    {
        if($client->isLogged())
        {
            if(isset($_GET['html']))
            {
                $info=json_encode($client->store_info());
                \Logs\logDebug(sprintf($html_response,$info,$store));
                echo sprintf($html_response,$info,$store);
            }
            else
            {
                \Logs\logDebug('raw resp: ' . var_export($client->store_info(),true));
                echo json_encode($client->store_info());
            }
           
        }
        else
        {
            if($client->isExpired())
            {
                try{
                    \Logs\logDebug('refresh token');
                    $client->refreshToken();
                    echo json_encode($client->store_info());
                    exit;
                }
                catch(\Exception $e)
                {
                    \Logs\logWarning($e->getMessage());
                }
            }
            \Logs\logDebug('redirect');
            echo json_encode( (object)array( 'redirect' => $client->getRedirectUrl() ) );
        }
    }
}    

if(isset($_GET['bookid']))
{
  $bookid=$_GET['bookid'];
  $dbase=\Database\odbc()->connect();
  if($dbase)
  {
    $sql="SELECT FILE_ID,FILE_NAME,VENDOR_CODE FROM BOOKS_LINKS,FILE_STORE AS FS WHERE BOOK_ID=$bookid AND STORE_ID=FS.ID";
    $rec=$dbase->query($sql);
    if($rec->next())
    {
      $fileid=$rec->field_value('FILE_ID');
      $filename=$rec->field_value('FILE_NAME');
      $vendor=$rec->field_value('VENDOR_CODE');
      echo "{\"fileid\": \"$fileid\", \"filename\":\"$filename\", \"vendor_code\": \"$vendor\"}";
    }
    $dbase->close();
  }
  else
  {
    http_response_code(400);
    echo '{error:"cannot open databse"}';
  }
  exit;
}

?>