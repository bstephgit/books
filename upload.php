<?php
include "db.php";
include "google_drive.php";


if(isset($_FILES) && isset($_FILES['upfile']))
{
    
    //$_FILES['upfile']['name']     //Le nom original du fichier, comme sur le disque du visiteur (exemple : mon_icone.png).
    //$_FILES['upfile']['type']     //Le type du fichier. Par exemple, cela peut être « image/png ».
    //$_FILES['upfile']['size']     //La taille du fichier en octets.
    //$_FILES['upfile']['tmp_name'] //L'adresse vers le fichier uploadé dans le répertoire temporaire.
    //$_FILES['upfile']['error']    //Le code d'erreur, qui permet de savoir si le fichier a bien été uploadé.

    if ($_FILES['upfile']['error'] > 0)
    {
         header('Location: home.php?erreur='.$_FILES['upfile']['error']);
         exit();
    }

    $temp_dir='temp';
    if(!is_dir($temp_dir))
    {
        mkdir($temp_dir);
    }
    $resultat = move_uploaded_file($_FILES['upfile']['tmp_name'],$temp_dir.'/'.$_FILES['upfile']['name']);
    $dbase = new DB();
    $dbase->connect("localhost","590_albertdupre","Isa150771?","590_albertdupre");
    if($dbase->is_connected())
    {
        $title=$_POST['title'];$author=$_POST['author'];$pub=$_POST['pub'];$descr=$_POST['descr'];
        $dbase->query("INSERT INTO BOOKS (TITLE,PUB,DESCR,AUTHORS) VALUES('$title','$pub','$descr','$author')");
    }
	try{
		$ggl_drive_client=new GoogleDriveHelper();
		$ggl_drive_client->setFileName($_FILES['upfile']['name']);
		$ggl_drive_client->login();
		if(!$ggl_drive_client->uploadFile())
		{
			header('Location: home.php?erreur=UPLOADGOOGLEERROR');
		}
	}
	catch(Exception $e)
	{
		//header('Location: home.php?erreur='.urlencode($e->getMessage()));
		echo $e->getMessage();
		//echo $e->getFile();                    // Source filename
		//echo $e->getLine();                    // Source line
    	//echo $e->getTraceAsString();           // Formated string of trace
		exit();
	}
    //header('Location: home.php');
}
?>