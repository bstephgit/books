33333<?php

include_once "dbTransactions.php";
include_once "db.php";
include_once "Log.php";

session_start();


function temp_dir($subpath=null)
{
    $temp_dir='temp';
    if($subpath==null)
        return $temp_dir;
    return $temp_dir . '/' . $subpath;
}

function img_dir($subpath=null)
{
    $img_dir='img';
    if($subpath==null)
        return $img_dir;
    return $img_dir . '/' . $subpath;
}

if(!is_dir(temp_dir()))
{
    mkdir(temp_dir());
}
if(!is_dir(img_dir()))
{
    mkdir(img_dir());
}

function pdfGetImage($pdf_name)
{
    $pdf_ext=".pdf";
    $pos=strpos($pdf_name,$pdf_ext);
    $img_name='';

    if( $pos!=false && ($pos+strlen($pdf_ext))==strlen($pdf_name) )
    {
        $img_basename = '[' . basename($pdf_name,$pdf_ext) . ']';
        
        if(!is_dir(img_dir($img_basename)))
        {
            if(mkdir(img_dir($img_basename))===false)
              throw new \Exception("cannot create directory");
        }
        else
        {
          \Logs\logWarning("dir '$img_basename' already created");
        }
        
        if(isset($_POST['imgfile']) && strlen($_POST['imgfile'])>0)
        {
          $img_name = img_dir($img_basename).'/img.png';
          file_put_contents($img_name,base64_decode($_POST['imgfile']));
        }
        else
        {
          throw new \Exception('no post data for image');
        }
    }
    else
    {
      \Logs\logInfo("'$pdf_name': not PDF file");
    }
    \Logs\logInfo("img='$img_name'");
    return $img_name;
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
    if(isset($headers['content-range']) || isset($headers['Content-Range']))
    {
        $range_start=0;
        $range_end=0;
        $file_size=0;
        $filename = $_FILES['upfile']['name'];
        $filepath=temp_dir($filename);

        $hr='content-range';
        if(!isset($headers[$hr])) 
        {
            $hr='Content-Range';
        }
        sscanf($headers[$hr],'bytes %d-%d/%d',$range_start,$range_end,$file_size);
        if($range_start==0 && is_file($filepath))
        {
            unlink($filepath);
        }
        $hfile = fopen($filepath,'ab');
        if($hfile)
        {
            fwrite($hfile,file_get_contents($_FILES['upfile']['tmp_name']));
            fclose($hfile);
            http_response_code(200);
            exit();
        }
        else
        {
            header('HTTP/ 500 ' . error_get_last());
        }
    }
    else 
    {
        $resultat = move_uploaded_file($_FILES['upfile']['tmp_name'],temp_dir($_FILES['upfile']['name']));
        $img=pdfGetImage($_FILES['upfile']['name']);
        
    }
}

if(isset($_POST['action']) && $_POST['action']==='book_create')
{
    $dbt=new \Database\Transactions\CreateBook();

    $dbt->title=$_POST['title'];$dbt->author=$_POST['author'];$dbt->descr=$_POST['descr'];
    $dbt->file_size=$dbt->size=$_POST['file_size'];$dbt->year=$_POST['year'];
    //$dbt->hash=hash_file('md5',temp_dir($_POST['file_name']));
    $dbt->hash=$_POST['hash'];
    try{
      $img=pdfGetImage($_POST['file_name']);
    }catch(\Exception $e)
    {
      \Logs\logException($e);
      $img=''; 
    }
    if(strlen($img)==0)
    {
      $img=img_dir('book250x250.png');
    }  
    
    $dbt->img=$img;
    $dbt->vendor=$_POST['store'];
    $dbt->filename=$_POST['file_name'];
    $dbt->file_id=$_POST['fileid'];

    $dbase=\Database\odbc()->connect();
    if($dbase)
    {
        $res=$dbase->query('SELECT ID FROM IT_SUBJECT');
        while($res->next(true))
        {
            $subject=$res->field_value(0);
            $code=sprintf('topic%d',$subject);
            if(isset($_POST[$code]) && $_POST['file_name'])
            {
                $dbt->subjects = $subject;
            }
        }

        $dbase->close();
    }

    //\Database\storeTransaction($dbt);
    //$drive_url=sprintf('drive_client.php?action=upload&store_code=%s&file_name=%s',$_POST['store'],urlencode($_POST['file_name']));
    //header('Location: ' . $drive_url);
  try
  {
    \Logs\logDebug('commit transaction');
    $dbt->commit();
    header('Location: home.php?bookid=' . $dbt->bookid);
  }
  catch(\Exception $e)
  {
    $errid=\Logs\logException($e);
    header('Location: home.php?errid=' . $errid);
  }
}


if(isset($_GET['action']) && $_GET['action']==='book_delete')
{
    $dbase = \Database\odbc()->connect();
    $dbt = new \Database\Transactions\DeleteBook();
    $id=$dbt->bookid=$_GET['bookid'];
    

    $sql_query="SELECT VENDOR_CODE FROM FILE_STORE WHERE ID IN (SELECT STORE_ID FROM BOOKS_LINKS WHERE BOOK_ID=$id)";
    $rec=$dbase->query($sql_query);
    $vendor=null;
    if($rec->next())
    {
        $vendor=$rec->field_value('VENDOR_CODE');
    }

    $sql_query="SELECT IMG_PATH FROM BOOKS WHERE ID=$id";
    $rec=$dbase->query($sql_query);
    if($rec->next())
    {
      $img_path=$rec->field_value('IMG_PATH');
      if($img_path!==img_dir('book250x250.png'))
      {
        unlink($img_path);
        rmdir(dirname($img_path));
      }
    }
    $dbase->close();
    if($vendor)
    {
        \Database\storeTransaction($dbt);
        $drive_url=sprintf('drive_client.php?action=delete&store_code=%s&bookid=%s',$vendor,$id);
        header('Location: ' . $drive_url);
    }
    else
    {
        $dbt->commit();
        header('Location: home.php');
    }
}

if(isset($_POST['action']) && $_POST['action']==='book_update')
{
    $dbase = \Database\odbc()->connect();
    $con=$dbase->con();
    $title=mysqli_real_escape_string($con,$_POST['title']);
    $author=mysqli_real_escape_string($con,$_POST['author']);
    $pub=$_POST['pub'];
    $descr=mysqli_real_escape_string($con,$_POST['descr']);
    $year=$_POST['year'];
    $id=$_POST['bookid'];
    
    $res=$dbase->query("UPDATE BOOKS SET TITLE='$title',DESCR='$descr',AUTHORS='$author',YEAR='$year' WHERE ID=$id");
    
    $dbase->query("DELETE FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID=$id");
    
    $res=$dbase->query('SELECT ID FROM IT_SUBJECT');
    while($res->next(true))
    {
        $subject=$res->field_value(0);
        $code=sprintf('topic%d',$subject);
        echo $code;
        if(isset($_POST[$code]) && $_POST[$code]==='on')
        {
            $dbase->query("INSERT INTO BOOKS_SUBJECTS_ASSOC (SUBJECT_ID,BOOK_ID) VALUES($subject,$id)");
        }
    }
    $dbase->close();
    header('Location: home.php?bookid='.$id);
}

if(isset($_GET['action']) && $_GET['action']==='download')
{
    if(isset($_GET['bookid']))
    {
        $dbase = \Database\odbc()->connect();
        $bookid=$_GET['bookid'];
        $sql="SELECT VENDOR_CODE FROM FILE_STORE WHERE ID IN (SELECT STORE_ID FROM BOOKS_LINKS WHERE BOOK_ID=$bookid)";
        $rec=$dbase->query($sql);
        if($rec && $rec->next())
        {
            $store=$rec->field_value('VENDOR_CODE');
            $dbase->close();

            $drive_url=sprintf('drive_client.php?action=download&bookid=%s&store_code=%s',$bookid,$store);
            header('Location: ' . $drive_url);
        }
        else
        {
            $dbase->close();
            $errid=\Logs\logErr('cannot get vendor code from database');
            header('Location: home.php?errid=' . $errid);
        }
    }
    else
    {
        header('Location: home.php');
    }
}

if(isset($_GET['action']) && $_GET['action']==='login')
{
    if($_GET['store'])
    {
        $url = sprintf('drive_client.php?action=login&store_code=%s',$_GET['store']);
        header('Location: '. $url);
    }
    else
    {
        echo 'error: store code missing';
    }
}
?>