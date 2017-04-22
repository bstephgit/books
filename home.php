<?php
include_once "db.php";
include_once "Log.php";
include_once "utils.php";

session_start();

if(isset($_GET["page"]) || isset($_GET["subject"]) || isset($_GET["search"]))
{
  \Logs\logInfo('Set Cookie \'browse_backlink\' ' . $_SERVER['REQUEST_URI']);
  setcookie("browse_backlink",$_SERVER['REQUEST_URI']);
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
      function changepage(event)
      {
         var dochange = (event.type==='change') || (event.type==='keyup' && event.keyCode===13);
         if(dochange)
         {
           var pagectrl = document.getElementById('nbpage');
           var uobj = new URL(window.location.href);
           var rx = /page=\d+/;
           var url = uobj.protocol + '//' + uobj.hostname + uobj.pathname;
           var pageindex = Math.min(pagectrl.value,pagectrl.max);
           if(rx.test(uobj.search))
           {
              url = url + uobj.search.replace(rx,'page=' + pageindex);    
           }
           else
           {
              if(uobj.search.length===0)
              {
                url += '?'
              }
             else
             {
               url += uobj.search + '&';
             }
             url += 'page=' + pageindex;
           }
           window.location.href = url;
         }
      }
      function searchQuery()
      {
        var querystring = document.getElementById('searchstring').value;
        var url = new URL (window.location);
        window.location = url.origin + url.pathname + "?search=" + encodeURIComponent(querystring) ;
        return false;
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
                printf( '<a class="nav_element" href="upload.php?action=book_delete&bookid=%s" onclick="return confirm(\'delete book?\');">delete book</a><br>',$_GET['bookid']);
                printf( '<a class="nav_element" href="home.php?edit=%s">edit book</a><br>',$_GET['bookid']);
                printf( '<a class="nav_element" href="%s">back</a><br>',$_COOKIE['browse_backlink']);
            } ?>
            <a class="nav_element" href="home.php?upload=1">uploader</a><br>
        </div>
        
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Rechercher</h3></div>
        <div class="nav_elements">
           <form method="POST" action="" onsubmit='return searchQuery();'>
            <p><input type='text' id="searchstring"></p>  
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
                    $rec = $base->query('SELECT ID,NAME FROM IT_SUBJECT ORDER BY NAME');
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
                        $book['IMG_PATH']=$rec_book->field_value('IMG_PATH');
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
                    <?php echo '<img id="preview" width="250" height="280" src="' . $book['IMG_PATH'] . '" style="cursor: hand" onclick="browseimage()"/>'; ?>
                    <input type='file' name="imginput" id='imginput' style='visibility: hidden' onchange='loadimage(this)'>
                    <input type='hidden' name='uploadflag' id='uploadflag' value='false'>
                    <input type='hidden' name='imgfile' id='imgfile'>
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
                    <?php  if($base){ \utils\print_subjects_tab($base); $base->close();}?>
                </div>
                <p><input type="submit" value='uploader' id='submit_btn'></p>
            </form>
            <?php
                if($edit)
                {
                    $script=sprintf(
                            "var upload_form = document.getElementById('form_upload');
                            upload_form.title.value=\"%s\";
                            upload_form.author.value=\"%s\";
                            upload_form.year.value=\"%s\";
                            upload_form.descr.value=\"%s\";
                            upload_form.bookid.value=\"%s\";
                            upload_form.submit_btn.value='update';", 
                            $book['TITLE'], $book['AUTHORS'], $book['YEAR'], str_replace(array("\r\n",'"','\''),array("<BR>",'\\"','\\\''),$book['DESCR']), $book['ID']);
                    
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
                            \utils\print_book($rec,$subjects,$links);
                            $dbase->close();
                        }

                    } 
             ?>
             <?php 
                \utils\printFooterLinks();
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
