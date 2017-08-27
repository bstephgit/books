function getList()
{
  if(this.entries===undefined)
   {
     this.entries = [];
   }
  return this.entries;
}
function make_request(options)
{
  var req = new XMLHttpRequest();
  try{
    req.open(options.method,options.url);
    req.onload = options.onload;
    req.onerror=options.onerror;
  }
  catch(err)
  {
    console.error(err);
    return null;
  }
  return req;
}
function searchEntries(input,callback)
{
  var entries=getList.call(this);
  
  if (input.length > 0) {
  
    var buildlist = function ()
    {
      var res = [];
      var input_lower = input.toLowerCase();
      for (var i in entries) 
      { 
          var idxof = entries[i].name.toLowerCase().indexOf(input_lower); 
          if(idxof>-1) res.push(entries[i]); 
      }
      return res;
    };
    
    if(entries.length===0)
    {
      console.log('searchEntries entries.length == 0',input);
      var self=this;
      if(self._req===undefined)
      {
         var req = make_request({ method:'GET',url:'store.php?action=taglist&tag=' + encodeURIComponent(input),
                               onload: function(e){ delete self._req; entries.length=0; Array.prototype.push.apply(entries,JSON.parse(req.response)); callback(buildlist()); },
                               onerror:  function (e) {delete self._req; console.error(e); } });
        self._req=req;
        req.send(); 
      }
    }
    else
    {
       console.log('searchEntries entries',input,entries);
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
      var tag = _aref.previousSibling.innerText;
      var hidden = document.getElementById('tags');
      var tags=hidden.value.split('%0D');
      var index = tags.indexOf(tag);
      if(index>-1)
      {
        tags.splice(index,1);
        hidden.value=tags.join('%0D');
      }
      console.log(hidden);
      var span = _aref.parentElement; 
      var div = span.parentElement; div.removeChild(span);
}
function validate(event)
{
  console.log('validate(event)');
  if(event.keyCode===13)
  {
      var editable = document.getElementById('currenteditable');
      var focused = getFocusedEntry();
      console.log(focused);
      if(focused===undefined){
        var tag = editable.innerText.replace(/\s+\n+$/,''); 
        addTag(tag,'new');
      }
      else {
        addTag(focused.innerText,focused.id.replace(/^#/,'')); 
        focused.className = '';
      }
      hide_entries();
      return false;
  }
  return true;
}
function onkey(event)
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
          if(focused!==undefined && focused.previousSibling)
          {
              focused.className = '';
              focused.previousSibling.className = 'focused';
          }
      }
      break;
    case 13:
      {
        //validate(event);
        return false;
      }
      break;
      default:
      {
          console.log('keyup',event.keyCode);
          buildEntries(document.getElementById('currenteditable').innerText);
      }
  }
  return true;
}
function getFocusedEntry()
{
  if(document.getElementById('dropdown-content'))
  {
      var elem = document.getElementById('dropdown-content').firstChild;
      while(elem) { if(elem.className==='focused') return elem; elem = elem.nextSibling; }
  }
  return undefined;
}

function buildEntries(input)
{
  console.log('buildEntries',input);
  var dropdown=document.getElementById('dropdown-content');
  
  while( dropdown.firstChild) {
      dropdown.removeChild( dropdown.firstChild);
  }
  
  var cb = function(list){
    console.log('buildentries',input,list);
    for(var i in list)
    {
        var a = document.createElement('a');
        a.innerText = list[i].name;
        a.href = 'javascript:void(0)';
        a.id = '#' + list[i].id;
        a.onclick = function(){
            addTag(this.innerText,this.id.replace(/^#/,'')); 
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
  console.log('addTag',tag);
  
  if(tag.length>0)
  {
    var editable = document.getElementById('currenteditable');
    var span=document.createElement('span');
    
    tag = tag.toUpperCase();
    
    span.className = 'tag label label-info';
    span.innerHTML= '<span>' + tag + '</span><a onclick=\'remove(this)\'><i class="remove glyphicon glyphicon-remove-sign glyphicon-white"></i></a> ';
    console.log('parent',editable.parentElement.parentElement,'element',span);
    editable.parentElement.parentElement.insertBefore(span,editable.parentElement);
    editable.innerText='';
    
    var hidden = document.getElementById('tags');
    var tags=hidden.value.split('%0D');
    if(tags.indexOf(tag)===-1)
    {
      tags.push(tag);
      hidden.value=tags.join('%0D');
    }
    console.log(hidden);
    getList.call(editable.parentElement).length=0;
  }
}