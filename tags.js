function getList()
{
  if(this.entries===undefined)
   {
     this.entries = [];
   }
  return this.entries;
}
function searchEntries(input,callback)
{
  var entries=getList.call(this);
  
  console.log('searchEntries',entries);
  if (input.length > 0) {
  
    var buildlist = function ()
    {
      var res = [];
      var input_lower = input.toLowerCase();
      for (var i in entries) 
      { 
          var idxof = entries[i].toLowerCase().indexOf(input_lower); 
          if(idxof>-1)
          { 
              res.push(entries[i]); 
          } 
      }
      return res;
    };
    
    if(entries.length===0)
    {
      var req = new XMLHttpRequest();
      req.method = 'GET';
      req.onerror = function (e) {
        console.log('erreur\n' + e);
      };

      var url = 'store.php?action=taglist&tag=' + encodeURIComponent(input);
      console.log('request',url);
      req.open('GET', url, true);
      req.onload = function(e){
        var list = JSON.parse(req.response);
        
        for(var i in list)
        {
          entries.push(list[i].name);
        }
        console.log(entries);
        callback(buildlist());
      };
      req.send();
    }
    else
    {
       callback(buildlist());
    }
  }
  else
  {
    console.log('clear entries');
    entries.length=0;
  }
}
function remove(_aref) {
      var span = _aref.parentElement; 
      var div = span.parentElement; div.removeChild(span);
}
function validate(event)
{
  if(event.keyCode===13)
  {
      var editable = document.getElementById('currenteditable');
      var focused = getFocusedEntry();
      if(focused===undefined)
      {
          addTag(editable.innerText);
      }
      else
      {
          addTag(focused.innerText);
      }
      return false;
  }

  return true;
}
function keyup(event)
{   
  var focused;
  switch(event.keyCode)
  {
      case 40:
      {
          focused = getFocusedEntry();
          if(focused===undefined)
          {
              if(document.getElementById('dropdown-content'))
              {
                  console.log('set first child focused');
                  document.getElementById('dropdown-content').firstChild.className = 'focused';
              }
          }
          else if(focused.nextSibling)
          {
              console.log('set next sibling focused');
              focused.className = '';
              focused.nextSibling.className = 'focused';
          }
      }
      break;
      case 38:
      {
          focused = getFocusedEntry();
          if(focused!==undefined)
          {
              if(focused.previousSibling)
              {
                  focused.className = '';
                  focused.previousSibling.className = 'focused';
              }
          }
      }
      break;
      default:
      {
          buildEntries(document.getElementById('currenteditable').innerText);
      }
  }
}
function getFocusedEntry()
{
  if(document.getElementById('dropdown-content'))
  {
      var elem = document.getElementById('dropdown-content').firstChild;
      while(elem)
      {
          console.log('get focused',elem.innerText,elem.className);
          if(elem.className==='focused')
          {
              return elem;
          }
          elem = elem.nextSibling;
      }
  }

  return undefined;
}
function entry_keys(event)
{
  console.log('entry',event.keyCode);

  event.stopPropagation();

  if(event.keyCode==38)
  {
      if(this.previousSibling)
      {
          this.previousSibling.focus();
      }
  }
  else if(event.keyCode==40)
  {
      if(this.nextSibling)
      {
          this.nextSibling.focus();
      }
  }
}
function buildEntries(input)
{
  console.log('buildEntries');
  var dropdown=document.getElementById('dropdown-content');
  
  while( dropdown.firstChild) {
      dropdown.removeChild( dropdown.firstChild);
  }
  
  var cb = function(list){
    console.log('buildentries',list);
    for(var i in list)
    {
        var a = document.createElement('a');
        a.innerText = list[i];
        a.href='#';
        a.onclick = function(){
            addTag(this.innerText); 
            hide_entries(); 
            };
        dropdown.appendChild(a);
    }
    dropdown.style.display='block';
  }
  
  searchEntries.call(dropdown.parentElement,input,cb);
}

function hide_entries()
{
  var content = document.getElementById('dropdown-content');
  if(content!==null)
  {
      content.style.display='none';
  }
  document.getElementById('currenteditable').focus();
}

function addTag(tag)
{
  if(tag.length>0)
  {
      var editable = document.getElementById('currenteditable');
      var span=document.createElement('span');
      span.className = 'tag label label-info';
      span.innerHTML= '<span class>' + tag + '</span><a onclick=\'remove(this)\'><i class="remove glyphicon glyphicon-remove-sign glyphicon-white"></i></a> ';
      editable.parentElement.insertBefore(span,editable);
      editable.innerText='';

      var list = getList.call(editable.parentElement);
      if(list.indexOf(tag)===-1)
      {
          console.log('add entry',tag);
          list.push(tag);
      }
  }
}