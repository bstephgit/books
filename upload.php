<?php
include "db.php";
include "drive_client.php";

loadOdbc();

$temp_dir='temp';
$img_dir='img';

function pdfGetImage($pdf_name)
{
    $pdf_ext=".pdf";
    $pos=strpos($pdf_name,$pdf_ext);
    $file_img_name='';
    
    //if( $pos!=false && ($pos+strlen($pdf_ext))==strlen($pdf_name) )
    {
        $file_img_name = basename($pdf_name,$pdf_ext).'jpeg';
        $output = '';
        //exec('gs -q -o ' . $img_dir . '/' . $file_img_name . ' -sDEVICE=jpeg -dPDFFitPage -g200x400 "' . $pdf_name . '" > gs.log');
        exec('ls -l > gs.log',$output);
        sleep(2);
        var_dump($output);
        
    } 
    return $file_img_name;
}

if(!is_dir($temp_dir))
{
    mkdir($temp_dir);
}
if(!is_dir($img_dir))
{
    mkdir($img_dir);
}
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

    $headers = getallheaders();
    $resultat=false;
    if(isset($headers['Content-Range']))
    {
        $range_start=0;
        $range_end=0;
        $file_size=0;
        $filename = $_FILES['upfile']['name'];
        $filepath='temp/'.$filename;
        
        sscanf($headers['Content-Range'],'bytes %d-%d/%d',$range_start,$range_end,$file_size);
        if($range_start==0 && is_file($filepath))
        {
            unlink($filepath);
        }
        $hfile = fopen($filepath,'ab');
        if($hfile)
        {
            fwrite($hfile,file_get_contents($_FILES['upfile']['tmp_name']));
            fclose($hfile);
        }
    }
    else 
    {
        $resultat = move_uploaded_file($_FILES['upfile']['tmp_name'],$temp_dir.'/'.$_FILES['upfile']['name']);
        $img=pdfGetImage($_FILES['upfile']['name']);
        
    }
	//createDriveClient($_POST['store'],$_FILES['upfile']['name']);
}

if(isset($_POST['file_action']) && $_POST['file_action']=='file_insert')
{
    $dbase = odbc_connectDatabase();
    if($dbase!=null)
    {
        $title=$_POST['title'];$author=$_POST['author'];$pub=$_POST['pub'];$descr=$_POST['descr'];
        $size=$_POST['file_size'];$year=$_POST['year'];
        $hash=hash_file('md5',$temp_dir . '/' . $_FILES['file_name']);
        $img=pdfGetImage($_FILES['file_name']);
        if(strlen($img)==0)
        {
            $img=$img_dir . '/book250x250.png';
        }
        
        $res=$dbase->query("INSERT INTO BOOKS (TITLE,DESCR,AUTHORS,SIZE,YEAR,HASH,IMG_PATH) VALUES('$title','$descr','$author',$size,'$year','$hash','$img')");
        $dbase->close();
        if(!$res)
        {
            echo 'Book not inserted in DB: ' . mysql_error() . '<BR>';
            exit();
        }
    }
    else {
        # code...
        echo 'database is not connected<BR>';
    }
}
?>