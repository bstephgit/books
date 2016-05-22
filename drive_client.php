<?php

include_once "drive_client_classdef.php";

function redirectDriveClient($drive_code)
{
    unset($_GET['store_code']);

    switch($drive_code)
    {
        case 'GOOG':
            {
                header( 'Location: google_drive.php?' . http_build_query($_GET));
                break;
            }
        case 'MSOD':
            {
                header( 'Location: ms_onedrive.php?' . http_build_query($_GET));
                break;
            }
        case 'AMZN':
            {
                header( 'Location: amazon_cloud.php?' . http_build_query($_GET));
                break;
            }
        case 'BOX':
            {
                header( 'Location: box_drive.php?' . http_build_query($_GET));
                break;
            }
        case 'PCLD':
            {
                header( 'Location: pcloud_drive.php?' . http_build_query($_GET));
                break;
            }
        default:
            throw new \Exception('Unknown code for store: ' . $drive_code);
    }
}

session_start();


if(isset($_GET['action']) && isset($_GET['store_code']))
{
    try{
        redirectDriveClient($_GET['store_code']);
    }
    catch(\Exception $e)
    {
        http_send_status(400);
        echo $e->getMessage();
    }
}



?>