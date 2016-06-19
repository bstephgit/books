<?php
include "db.php";

session_start();

function encodePath($path)
{
  $count=strlen($path);
  $i=0;
  $folder='';
  $output='';
  while($i<$count)
  {
    if($path[$i]==='/')
    {
      $output .= rawurlencode($folder) . '/';
      $folder = '';
    }
    else
    {
      $folder .= $path[$i];
    }
    $i++;
  }
  $output .= rawurlencode($folder);
  return $output;
}

function sizeUnit($input)
{
    $size=0;
    $units=array('bytes','Kb','Mb','Gb','Tb');
    $formats=array(0,0,1,1,0);
    if(is_string($input))
    {
        $size=intval($input);
    }
    if(is_int($size))
    {
        $size=$input;
    }
    
    $pace=1024;

    for($i=0; i < count($units); $i++)
    {
        $next =  $size/$pace;
        if(intval($next)==0)
        {
            return number_format($size,$formats[$i]) . ' ' . $units[$i];
        }
        $size = $next;
    }
    return number_format(($size*$pace),$formats[count($units)-1]) . ' ' . $units[count($units)-1];
}
function print_subjects_tab($base)
{
    if($base && $base->is_connected())
    {
        $rec=$base->query('SELECT ID,NAME FROM IT_SUBJECT');
        echo '<table class="nav_element">';
        if($rec)
        {
            do {
                $cols=0;
                echo '<tr>';
                while($cols<5 && $rec->next()){
                    echo '<td><input type="checkbox" name="topic' . $rec->field_value('ID') . '">' . $rec->field_value('NAME') . '<td>';
                    $cols++;
                }
                while($cols<5){ echo '<td></td>'; $cols++; }
                echo '</tr>';
            }while($rec->is_valid());
        }
        echo '</table>';
    }
}

function print_book($rec,$subjects,$links)
{
    if($rec && $rec->next())
    {
        $title=$rec->field_value('TITLE');
        $year=$rec->field_value('YEAR');
        $descr=$rec->field_value('DESCR');
        $author=$rec->field_value('AUTHORS');
        $size=$rec->field_value('SIZE');
        $img_src=encodePath($rec->field_value('IMG_PATH'));
        echo "<div class='book_record'><table class='book_record'>";
        echo '<tr>';
        printf("<td width='210px' rowspan='2'><img src=\"%s\" class='book'></td>",/*str_replace("'","\\'",*/$img_src/*)*/);
        printf("<td colspan='2'><div class='book'><span class='book_title'>%s</span></div></td>",$title);
        echo '</tr>';
        echo '<tr>';
        echo '<td>';
        printf("<div class='book'>Auteur: <span>%s</span></div>",$author);
        printf("<div class='book'>Parution: <span>%s</span></div>",$year);
        printf("<div class='book'>File size: <span>%s</span></div>",sizeUnit($size));
        printf("<div class='book'><span class='book_descr'>%s</span></div>",$descr);
        if($links && $links->next())
        {
           $size=$links->field_value('FILE_SIZE');
           $vendor=$links->field_value('VENDOR');
           echo sprintf("<div class='book'><a class='nav_element' onclick='downloadFile(%s);'>Download</a> -- %s (%s)</div>",$_GET['bookid'],$size,$vendor);
           //echo sprintf("<div class='book'><a class='nav_element' href='upload.php?action=download&bookid=%s'>Download</a> -- %s (%s)</div>",$_GET['bookid'],$size,$vendor);
        }
        echo '</td>';
        echo '<td><div class="book"><ul class="book_tags"><LH>Tags</LH>';
        while($subjects->next())
        {
            printf('<li>%s</li>',$subjects->field_value('NAME'));
        }
        echo "</ul></div></td></tr>";
        echo "<tr><td>&nbsp;</td><td colspan='2'><div class='upload-out' id='upload-out' style='visibility: hidden; margin-left: 1cm'><div class='upload-in' id='upl-in1'><div></div></td></tr>";
        echo "</table></div>";
    }
}
?>
<html>
<head>
    
    <link type="text/css" rel="stylesheet" href="books.css"/>
    <script type='text/javascript' src='script.js'></script>
    <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/core-min.js'></script>
    <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/md5-min.js'></script>
    <script type='text/javascript' src='../pdf.js/build/pdf.js'></script>
    <script type='text/javascript'>
          
          function downloadFile(bookid)
          {
            var store = new Store('store.php?action=downloadLink&bookid='+bookid);

            store.onerror = function (err) {
              console.error(err);
              var msg=err.toString();
              if(err.status){ 
                var reader = new window.FileReader(); 
                reader.readAsDataURL(err.response); reader.onloadend = function() 
                {  var res = reader.result; msg = err.status + " " + atob(res.substr(res.indexOf(',')+1)); alert(msg); }
              }else{
                alert(msg);
              }
              
            };
            store.onlogin = function (obj) {
              store.download();
            };
            store.login();
          }
    </script>
</head>
<body>
<div class="container">

<div class="nav">
    <div class="banner"></div>
    <div class='internal'>
        <div class='navtitle'><h3>Menu</h3></div>
        <div class="nav_elements">
            <a class="nav_element" href="home.php">home</a><br>
            <?php if(isset($_GET['bookid'])) {
                printf( '<a class="nav_element" href="upload.php?action=book_delete&bookid=%s">delete book</a><br>',$_GET['bookid']);
                printf( '<a class="nav_element" href="home.php?edit=%s">edit book</a><br>',$_GET['bookid']);
            } ?>
            <a class="nav_element" href="home.php?upload=1">uploader</a><br>
        </div>
        
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Rechercher</h3></div>
        <div class="nav_elements">
            <form method="POST" action="">
                <p><input type='text'></p>  
                <p><input type='submit' value='rechercher'></p>
            </form>
        </div>
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Sujets</h3></div>
        <div class="nav_elements">
            <div>
            <?php 
                $base=Database\odbc()->connect();
                if($base && $base->is_connected())
                {
                    $rec = $base->query('SELECT ID,NAME FROM IT_SUBJECT');
                    if($rec)
                    {
                        $sep="";
                        while($rec->next()){
                            echo $sep . '<a class="nav_element" href="home.php?subject=' . $rec->field_value('ID') . '">' .  $rec->field_value('NAME') . "</a>";
                            $sep=", ";
                        }
                    }
                    else {
                        echo mysql_error();
                    }
                    $base->close();
                }
            ?>
            </div>
        </div>
    </div>
    
</div>

<div class="main">
    <div class="banner"><h1 class="title">BOOK STORE</h1></div>

        <div class='internal'>
        <div class="nav_elements" style="border-radius: 15px">
            <?php  if (isset($_GET['upload']) || isset($_GET['edit'])){   
                
                $upload = isset($_GET['upload']) && $_GET['upload']==='1';
                $edit = isset($_GET['edit']);
                $base=Database\odbc()->connect();
                
                if($edit)
                {
                    $id=$_GET['edit'];
                    $sql="SELECT * FROM BOOKS WHERE ID=$id";
                    $rec_book=$base->query($sql);
                    $sql="SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID=$id";
                    $rec_subjects=$base->query($sql);
                    $book=array();
                    $subjects=array();
                    if($rec_book->next())
                    {
                        $book['ID']=$rec_book->field_value('ID');
                        $book['TITLE']=$rec_book->field_value('TITLE');
                        $book['YEAR']=$rec_book->field_value('YEAR');
                        $book['AUTHORS']=$rec_book->field_value('AUTHORS');
                        $book['DESCR']=$rec_book->field_value('DESCR');
                    }
                    while($rec_subjects->next())
                    {
                        $subjects[ sprintf('topic%s',$rec_subjects->field_value('SUBJECT_ID')) ]=1;
                    }
                }
            ?>
            <form method="POST" action="upload.php" id="form_upload">
                <table class="nav_element" cellpadding="15">
                    <?php if ($upload) { ?>
                  <tr><td><input type="file" id="file_upload"></td><td colspan="2"><div class='upload-out'><div class='upload-in' id='upl-in1'></div></div></td></tr>
                    <?php } ?>
                    <tr><td>Titre<br> <input type="text" name="title" size="40"> </td>
                    <td>Auteur<br> <input type="text" name="author"> </td>
                    <td>Ann&eacute;e de parution<br> <input type="text" name="year"></td></tr> 
                    <tr><td colspan="3">Description<br><textarea rows="5" cols="90" name="descr"></textarea></td></tr>
                   
                </table>
                <?php if($upload){ ?>
                  <div id="hidden_fields">
                    <input type='hidden' name='file_name' id='fname'>
                    <input type='hidden' name='file_size' id='fsize'>
                    <input type='hidden' name='action' value='book_create'>                
                    <input type='hidden' name='fileid' id='fileid'>                
                    <input type='hidden' name='hash' id='hash'>
                    <input type='hidden' name='imgfile' id='imgfile'>
                  </div>

                <?php } if($edit){ ?>
                    <input type='hidden' name='action' value='book_update'>
                    <input type='hidden' name='bookid' value=''>
                <?php } if($upload){ ?>
                
                    <table class="nav_element">
                      <tr>
                        <td>
                          <div class="nav_elements">
                            <p>Store<br>
                            <input type="radio" name="store" value="GOOG" checked> Google drive<br>
                            <input type="radio" name="store" value="MSOD"> MS OneDrive<br>
                            <input type="radio" name="store" value="AMZN"> Amazon<br>
                            <input type="radio" name="store" value="BOX"> Box.com<br>
                            <input type="radio" name="store" value="PCLD"> pCloud  </p>
                          </div>
                       </td>
                        <td width='500' align='right'><canvas id="preview" width="250" height="280" style="border:1px solid #000000;"></canvas></td>
                   </tr>
                 </table>
                <?php } ?>
                <div class="nav_elements">
                    <?php  if($base){ print_subjects_tab($base); $base->close();}?>
                </div>
                <p><input type="submit" value='uploader' id='submit_btn'></p>
            </form>
            <?php
                if($edit)
                {
                    $script=sprintf(
                            "var upload_form = document.getElementById('form_upload');
                            upload_form.title.value='%s';
                            upload_form.author.value='%s';
                            upload_form.year.value='%s';
                            upload_form.descr.value='%s';
                            upload_form.bookid.value='%s';
                            upload_form.submit_btn.value='update';", 
                            $book['TITLE'], $book['AUTHORS'], $book['YEAR'], $book['DESCR'], $book['ID']);
                    
                    echo '<script type="text/javascript">';
                        echo $script;
                        foreach ($subjects as $key => $value) {
                            printf('upload_form.%s.checked=true;',$key);
                        }
                    echo '</script>';
                }
                $base->close();
             }  
            ?>
            
            <?php  
                    if (isset($_GET['bookid']))
                    { 
                        $id=$_GET['bookid'];
                        $dbase = Database\odbc()->connect();
                        if($dbase->is_connected())
                        {
                            $query_str="SELECT TITLE,YEAR,DESCR,AUTHORS,SIZE,IMG_PATH FROM BOOKS WHERE ID=$id";
                            $rec=$dbase->query($query_str);
                            $subjects=$dbase->query('SELECT NAME FROM IT_SUBJECT WHERE ID IN (SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID='.$id.')');
                            $links=$dbase->query('SELECT lnks.FILE_ID,lnks.FILE_SIZE,fs.VENDOR FROM BOOKS_LINKS AS lnks, FILE_STORE AS fs WHERE lnks.BOOK_ID=' . $id . ' AND fs.ID=lnks.STORE_ID');
                            print_book($rec,$subjects,$links);
                            $dbase->close();
                        }

                    } 
             ?>
             <?php 
                if(count($_GET)==0 || isset($_GET['page']) || isset($_GET['subject']))
                {
                   
                   $page=1;
                   $nb_elem=6;
                   if(isset($_GET['page']))
                   {
                     $page=intval($_GET['page']);
                   }
                   $offset=(($page-1)*$nb_elem);
                  
                   $dbase = Database\odbc()->connect();
                   $has_subject=false;
                   $subject='';
                   if(isset($_GET['subject']))
                   {
                     $has_subject=true;
                     $subject=$_GET['subject'];
                     $sql="SELECT COUNT(*) FROM BOOKS WHERE ID IN (SELECT BOOK_ID FROM BOOKS_SUBJECTS_ASSOC WHERE SUBJECT_ID=$subject)";
                   }
                   else
                   {
                     $sql='SELECT COUNT(*) FROM BOOKS';
                   }
                  
                   $rec=$dbase->query($sql);
                   $rec->next(true);
                   $count=$rec->field_value(0);
                   
                   $page_max=intval(ceil($count/$nb_elem));
                   $page=min($page,$page_max);
                 
                  if($has_subject)
                  {
                    $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS WHERE ID IN (SELECT BOOK_ID FROM BOOKS_SUBJECTS_ASSOC WHERE SUBJECT_ID=$subject) ORDER BY ID LIMIT $nb_elem OFFSET $offset";
                  }
                  else
                   $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS ORDER BY ID LIMIT $nb_elem OFFSET $offset";
                   
                   $rec=$dbase->query($sql);
                   if($rec)
                   {
                       echo '<div class="nav_elements">';
                       while($rec->next())
                       {
                         $title=$rec->field_value('TITLE');
                         $charcount=0;$index=0;
                         
                         while($index < strlen($title) && $charcount<=28)
                         {
                           if(ctype_upper($title[$index++]))
                             $charcount += 1.5;
                           else
                             $charcount += 1;
                         }
                         if($index<strlen($title))
                          $title= sprintf('<abbr title="%s">%s</abbr>...',$title,substr($title,0,$index));
                         printf('<table class="book_list"><tr><td><img src="%s" class="book"></td></tr><tr><td><a class="nav_element" href="home.php?bookid=%s">%s</a></td></tr></table>',
                            encodePath($rec->field_value('IMG_PATH')),$rec->field_value('ID'),$title);
                       }
                       echo '</div>';
                   }
                   $dbase->close();
                   if($page>1)
                   {
                     $prev=$page-1;
                     if($has_subject)
                        echo "<a href='home.php?page=$prev&subject=$subject'>previous</a>";
                     else
                        echo "<a href='home.php?page=$prev'>previous</a>";
                   }
                   if($page<$page_max)
                   {
                     if($page>1) echo '|';
                     $next=$page+1;
                     if($has_subject)
                       echo "<a href='home.php?page=$next&subject=$subject'>next</a>";
                     else
                      echo "<a href='home.php?page=$next'>next</a>";
                   }
                }
             ?>
            <?php
                if($_GET['errid'])
                {
                    $dbase = Database\odbc()->connect();
                    if($dbase)
                    {
                        $errid=$_GET['errid'];
                        $rec=$dbase->query("SELECT * FROM LOGS WHERE ID=$errid");
                        if($rec && $rec->next())
                        {
                            $file=$rec->field_value('FILE');
                            $line=$rec->field_value('LINE');
                            $func=$rec->field_value('FUNCTION');
                            $msg=$rec->field_value('MSG');
                            echo "<h2>ERR: $msg</h2><br>";
                            echo "<h3>FILE: $file</h3><br><h3>FUNCTION: $func</h3><br> <h3>LINE: $line</h3>";
                        }
                        $dbase->close();
                    }
                }
            ?>
        </div>
    </div>
</div>
</div>
</body>
</html>
