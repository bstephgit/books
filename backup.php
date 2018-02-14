<html>
  <body>
<?php

  echo '<h2>file list:</h2>';
  
  function listfiles($prefix,$basedir,$zip)
  {
    $dir = $prefix . DIRECTORY_SEPARATOR . $basedir;
    $files = scandir($dir);
    if(is_bool($files)  && !$files)
    {
      echo "error folder '$dir' not found";
    }
    else
    {
      
      foreach($files as $key => $value)
      {
        if (!in_array($value,array(".","..")))
        {
           $path = $dir . DIRECTORY_SEPARATOR . $value;
           if(is_dir($path))
           {
              listfiles($prefix,$basedir . DIRECTORY_SEPARATOR . $value,$zip);
           }
          else
          {
            $relative_path = $basedir . DIRECTORY_SEPARATOR . $value;
            echo $relative_path . '<br>';
            $zip->addFile($path,$relative_path);
          }
        }
      }
    }
  }
  $backup = new ZipArchive();
  $name='./temp/backup.zip';
  $ok = $backup->open($name,ZIPARCHIVE::CREATE | ZipArchive::OVERWRITE);
  if ($ok === TRUE) {
    echo "<p><a href='$name' download='$name'>Download archive</a></p><p>";
    listfiles('.','temp',$backup);
    echo '<p>';
    if($backup->close())
    {
      echo 'archive properly closed';
    }
    else
    {
      echo 'problem closing archive';
    }
  }
  else
  {
    echo "<h3>Error opening archive: $ok</h3>";
    var_export($backup);
  }
?>
  </body>
</html>