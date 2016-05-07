<?php

include "amazon_classdef.php";

session_start();

$amzn_drive=new AmazonCloudHelper();
$amzn_drive->execute();

?>