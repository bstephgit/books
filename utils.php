<?php
namespace utils;

include_once "db.php";
include_once "Log.php";

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
        $rec=$base->query('SELECT ID,NAME FROM IT_SUBJECT ORDER BY NAME');
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

function print_subjects_tags($base,$subjects)
{
    if($base && $base->is_connected())
    {
        $tag_ids='(';
        $sep='';
        foreach($subjects as $val)
        {
          $tag_ids = $tag_ids . $sep . $val;
          $sep=',';
        }
        $tag_ids = $tag_ids . ')';
       
        \Logs\logDebug($tag_ids);
        $rec=$base->query('SELECT ID,NAME FROM IT_SUBJECT WHERE ID IN ' . $tag_ids . ' ORDER BY NAME');
        echo '<div class="tags edit" onclick=\'hide_entries()\'>';
        $tags=array();
        
        if($rec)
        {
            while($rec->next()){
               echo '<span id=#' . $rec->field_value('ID')  . ' class="tag label label-info">';
               echo '<input type=\'hidden\' name=\'topic' . $rec->field_value('ID') . '\' value=\'1\'>';
               echo '<span>' . $rec->field_value('NAME') . '</span>';
               echo '<a onclick=\'remove(this)\'><i class="remove glyphicon glyphicon-remove-sign glyphicon-white"></i></a>';
               echo '</span>';
               //echo '<td><input type="checkbox" name="topic' . $rec->field_value('ID') . '">' . $rec->field_value('NAME') . '<td>';
              array_push( $tags, $rec->field_value('NAME'));
            }
        }
        else
        {
          \Logs\logWarning('tags: record object null');
        }
        echo "<span class='dropdown'>";
        echo "<span id='currenteditable' class='editable' contenteditable onkeypress='return validate(event);' onkeyup='return onkey(event);'></span>";
        echo "<div id='dropdown-content' class='dropdown-content'></div>";
        echo '</span></div>';
        printf( '<input type=\'hidden\' name=\'tags\' id=\'tags\' value=\'%s\'>', implode('%0D',$tags));
    }
    else
    {
      \Logs\logWarning('database not connected');
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
        printf("<td width='210px' valign='top' rowspan='2'><img src=\"%s\" class='book'><div class='book'>Tags:</div><div class='tags display'>",/*str_replace("'","\\'",*/$img_src/*)*/);
        while($subjects->next())
        {
          printf('<span class="tag label label-info"><span style="cursor: hand" onclick="window.location=\'home.php?subject=%s\';">%s</span></span>',$subjects->field_value('ID'),$subjects->field_value('NAME'));
        }
        echo "</div></td>";
        //printf("<td width='210px' valign='top'><img src=\"%s\" class='book'></td>",/*str_replace("'","\\'",*/$img_src/*)*/);
        //printf("<td colspan='2'><div class='book'><span class='book_title'>%s</span></div></td>",$title);
        printf("<td><div class='book'><span class='book_title'>%s</span></div></td>",$title);
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
           echo sprintf("<div class='book'><button class='nav_element' onclick='downloadFile(%s);'>Download</button> %s octets (%s)</div>",$_GET['bookid'],$size,$vendor);
           //echo sprintf("<div class='book'><a class='nav_element' href='upload.php?action=download&bookid=%s'>Download</a> -- %s (%s)</div>",$_GET['bookid'],$size,$vendor);
        }
        echo '</td>';
        /*echo '<td><div class="book"><ul class="book_tags"><LH>Tags</LH>';
        while($subjects->next())
        {
            printf('<li>%s</li>',$subjects->field_value('NAME'));
        }*/
        //echo "</ul></div></td></tr>";
        echo "</tr>";
        echo "<tr><td>&nbsp;</td><td colspan='2'><div class='upload-out' id='upload-out' style='visibility: hidden; margin-left: 1cm'><div class='upload-in' id='upl-in1'><div></div></td></tr>";
        echo "</table></div>";
    }
}

function printFooterLinks()
{
    if(count($_GET)==0 || isset($_GET['page']) || isset($_GET['subject']) || isset($_GET['search']))
    {

       $page=1;
       $nb_elem=6;
       if(isset($_GET['page']))
       {
         $page=intval($_GET['page']);
       }
       $offset=(($page-1)*$nb_elem);

       $dbase = \Database\odbc()->connect();
       $has_subject=false;
       $has_search=false;
       $subject='';
       $search_str='';
       $search_regex='';
       if(isset($_GET['subject']))
       {
         $has_subject=true;
         $subject=$_GET['subject'];
         $sql="SELECT COUNT(*) FROM BOOKS WHERE ID IN (SELECT BOOK_ID FROM BOOKS_SUBJECTS_ASSOC WHERE SUBJECT_ID=$subject)";
       }
       else if(isset($_GET['search']))
       {
         $search_str=urlencode($_GET['search']);
         $has_search=true;
         $searchstring=$_GET['search'];
         $tok = strtok($searchstring,' ');
         $sep='';
         while($tok!=false)
         {
           $search_regex = $search_regex . $sep . $tok;
           $sep = '|';
           $tok = strtok(' ');
         }
         $search_regex=escapeRegExpChars (mysqli_real_escape_string($dbase->con(),$search_regex));
         $sql="SELECT COUNT(*) FROM BOOKS WHERE TITLE REGEXP '$search_regex'";
         \Logs\logInfo($sql);
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
      else if($has_search)
      {
        $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS WHERE TITLE REGEXP '$search_regex' ORDER BY ID LIMIT $nb_elem OFFSET $offset";
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

       $page=min($page,$page_max);
       if($page>1)
       {
         $prev=$page-1;
         if($has_subject)
            echo "<a href='home.php?page=$prev&subject=$subject'>previous</a>";
         else if($has_search)
           echo "<a href='home.php?page=$prev&search=$search_str'>previous</a>";
         else
            echo "<a href='home.php?page=$prev'>previous</a>";
       }

       printf("<input type='number' id='nbpage' value='%s' min='1' max='%s' size=3 onchange='changepage(event)'>",$page,$page_max);
       if($page<$page_max)
       {
         $next=$page+1;
         if($has_subject)
           echo "<a href='home.php?page=$next&subject=$subject'>next</a>";
         else if($has_search)
           echo "<a href='home.php?page=$next&search=$search_str'>next</a>";
         else
          echo "<a href='home.php?page=$next'>next</a>";
       }
    }
}
function escape($char)
{
  return '\\'.$char;
}
function escapeRegExpChars($input)
{
  $output=str_replace(array('+','[',']','\\','{','}'),array(escape('+'),escape('['),escape(']'),escape('\\'),escape('{'),escape('}')),$input);
  return $output;
}

function selectOrCreateSubject($db,$subject_name)
{
  if( $db && $db->is_connected())
  {
    $sql='SELECT ID FROM IT_SUBJECT WHERE NAME=\'' . $subject_name . '\'';
    $rec=$db->query($sql);
    if($rec->next())
    {
      return $rec->field_value('ID');
    }
    $sql_create='INSERT INTO IT_SUBJECT(NAME) VALUES(\'' . $subject_name . '\')';
    $db->query($sql_create);
    $rec=$db->query($sql);
    if($rec->next())
    {
      return $rec->field_value('ID');
    }
    
    \Logs\logWarning('cannot get subject\'' . $subject_name . '\' from database.');
    return 0;
  }
}

?>