<?php
namespace utils;

include_once "db.php";
include_once "Log.php";

include_once "amazon_classdef.php";
include_once "box_classdef.php";
include_once "google_classdef.php";
include_once "onedrive_classdef.php";
include_once "pcloud_classdef.php";
include_once "hubic_classdef.php";


function createDriveClient($drive_code)
{
    switch($drive_code)
    {
        case 'GOOG': return new \GoogleDriveHelper();
        case 'MSOD': return new \MSOneDriveHelper();
        case 'AMZN': return new \AmazonCloudHelper();
        case 'BOX': return new \BoxDrive();
        case 'PCLD': return new \PCloudDrive();
        case 'HUB': return new \HubicDrive();
        default: throw new \Exception('Unknown code for store: ' . $drive_code);
    }
}

function checkLogin($vendor_store)
{
    \Logs\logDebug('checkLogin: start');
    $client=\utils\createDriveClient($vendor_store);
    \Logs\logDebug('checkLogin: client created');
    if(!$client->isLogged())
    {
        if($client->isExpired())
        {
            \Logs\logDebug('checkLogin: expired');
            try{
                $client->refreshToken();
                return $client;
            }
            catch(\Exception $e)
            {
                \Logs\logWarning($e->getMessage());
            }
        }
        \Logs\logDebug('checkLogin: redirect ' . $client->getRedirectUrl());
        echo json_encode( (object)array( 'redirect' => $client->getRedirectUrl() ) );
        exit;
    }
    \Logs\logDebug('checkLogin: return client');
    return $client;
}

function curl_request($url, $options = array())
{
  $curl = curl_init();
  $whole_options=array(
      // General options.
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_AUTOREFERER    => true,

      // SSL options.
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_URL            => $url
  );
  if($options)
  {
      $whole_options=$whole_options + $options;
  }
  curl_setopt_array($curl, $whole_options);
  $result = curl_exec($curl);

  if (false === $result) {
      $err_msg = curl_error($curl);
      curl_close($curl);
      throw new \Exception('\utils\curl_request curl_exec() failed: ' . $err_msg);
  }
  $httpcode = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
  curl_close($curl);
  
  return array( 'code' => $httpcode, 'body' => $result);
}

function curl_post($url,$body,$options=array())
{
    $post_options=array(
         CURLOPT_POST       => true,
         CURLOPT_POSTFIELDS => $body
     );
    if($options)
    {
        $post_options=$options+$post_options;
    }
    return curl_request($url,$post_options);
}

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

function print_book($bookid,$rec,$subjects,$links)
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
          printf('<span class="tag label label-info"><span style="cursor: pointer" onclick="window.location=\'home.php?subject=%s\';">%s</span></span>',$subjects->field_value('ID'),$subjects->field_value('NAME'));
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
        printf("<div class='book'><span class='book_descr'>%s</span></div>",($descr));
        if($links && $links->next())
        {
           $size=$links->field_value('FILE_SIZE');
           $vendor=$links->field_value('VENDOR');
           $vcode=$links->field_value('VENDOR_CODE');
          if(true/*($vcode==='PCLD' || $vcode==='BOX')*/)
          {
            echo sprintf("<div class='book'><button id='btnDownload' class='nav_element' onclick='downloadFile(%s);'>Download</button> %s octets (%s)</div>",$_GET['bookid'],$size,$vendor);
          }
          else
          {
            $client=\utils\checkLogin($vcode);
            $fileid = $links->field_value('FILE_ID');
            $download_link = $client->downloadLink($fileid)['url'];
            echo sprintf("<div class='book'><button id='btnDownload' class='nav_element' onclick='javascript:window.open(\"%s\");'>Download</button> %s octets (%s)</div>",$download_link,$size,$vendor);
          }
           
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

function printFooterLinks($order)
{
    
    if(!isset($_GET['bookid']) || isset($_GET['page']) || isset($_GET['subject']) || isset($_GET['search']))
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
           $search_regex = $search_regex . $sep . escapeRegExpChars(mysqli_real_escape_string($dbase->con(),$tok));
           $sep = '[[:blank:]]+';
           $tok = strtok(' ');
         }
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

      \Logs\logInfo('count=' . strval($count));
      if($has_subject)
      {
        $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS WHERE ID IN (SELECT BOOK_ID FROM BOOKS_SUBJECTS_ASSOC WHERE SUBJECT_ID=$subject) ORDER BY ID $order LIMIT $nb_elem OFFSET $offset";
      }
      else if($has_search)
      {
        $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS WHERE TITLE REGEXP '$search_regex' ORDER BY ID $order LIMIT $nb_elem OFFSET $offset";
      }
      else
       $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS ORDER BY ID $order LIMIT $nb_elem OFFSET $offset";

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
             {
               $title= sprintf('<abbr title="%s">%s</abbr>...',$title,substr($title,0,$index));
             }
             printf('<table class="book_list"><tr><td><a href="home.php?bookid=%s"><img src="%s" class="book"></a></td></tr><tr><td><a class="nav_element" href="home.php?bookid=%s">%s</a></td></tr></table>',
                $rec->field_value('ID'),encodePath($rec->field_value('IMG_PATH')),$rec->field_value('ID'),$title);
           }
           echo '</div>';
       }
       $dbase->close();

       $page=min($page,$page_max);
       if($page>1)
       {
         $prev=$page-1;
         if($has_subject)
            echo "<a href='home.php?page=$prev&subject=$subject&order=$order'>previous</a>";
         else if($has_search)
           echo "<a href='home.php?page=$prev&search=$search_str&order=$order'>previous</a>";
         else
            echo "<a href='home.php?page=$prev&order=$order'>previous</a>";
       }

       printf("<input type='number' id='nbpage' value='%s' min='1' max='%s' size=3 onchange='changepage(event)'>",$page,$page_max);
       if($page<$page_max)
       {
         $next=$page+1;
         if($has_subject)
           echo "<a href='home.php?page=$next&subject=$subject&order=$order'>next</a>";
         else if($has_search)
           echo "<a href='home.php?page=$next&search=$search_str&order=$order'>next</a>";
         else
          echo "<a href='home.php?page=$next&order=$order'>next</a>";
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

function escapeJsonString($value) { # list from www.json.org: (\b backspace, \f formfeed)
    $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
    $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
    $result = str_replace($escapers, $replacements, $value);
    return $result;
}

function pdfGetImage($pdf_name,$img_content)
{
    $pdf_ext=".pdf";
    $pos=strpos($pdf_name,$pdf_ext);
    $img_name='';
    $img_basename='';
    if( $pos!=false && ($pos+strlen($pdf_ext))==strlen($pdf_name) )
    {
        $img_basename = '[' . basename($pdf_name,$pdf_ext) . ']';
    }
    else
    {
      \Logs\logInfo("'$pdf_name': not PDF file");
      if(strlen($pdf_name)>0)
      {
        $img_basename= '[' . pathinfo($pdf_name)['filename'] . ']';
      }
      else
      {
        $img_basename = uniqid ();
      }
    }
    try
    {
      $img_name = \utils\saveImageFile($img_basename,$img_content);
       \Logs\logInfo("img='$img_name'");
    }
    catch(\Exception $e)
    {
      $errid=\Logs\logException($e);
    }
   
    return $img_name;
}

function saveImageFile($dirname,$img_content)
{
  $img_name = '';
  if(strlen($img_content)>0)
  {
      if(!is_dir(\utils\img_dir($dirname)))
      {
          if(mkdir(\utils\img_dir($dirname))===false)
            throw new \Exception("cannot create directory");
      }
      else
      {
        \Logs\logWarning("dir '$dirname' already created");
      }
      $img_name = \utils\img_dir($dirname).'/img.png';
      file_put_contents($img_name,base64_decode($img_content));
  }
  return $img_name;
}

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

?>