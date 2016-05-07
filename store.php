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
        localStorage.setItem('%s',info_token);
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
                echo sprintf($html_response,$info,$store);
            }
            else
            {
                echo json_encode($client->store_info());
            }
           
        }
        else
        {
            echo json_encode( (object)array( 'redirect' => $client->getRedirectUrl() ) );
        }
    }
}    
    
?>