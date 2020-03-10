

function recursiveProm(testf)
{
	
	return new Promise(function(fulfill){
		testf( function(done){
			fulfill(done);
		});
		
	}).then( function(done) { if(!done) recursiveProm(testf); } );
}

function storeUplForm(form)
{
    var subjects=[]; var index=1;
    while(true) { 
			var name='topic'+index; 
			if(form[name]) { if (form[name].checked) subjects.push(name);  } else  break;
			index++;
    }
    obj = {
        'title': form.title.value,
        'author': form.author.value,
        'file': form.file_upload.files[0],
        'year': form.year.value,
        'descr': form.descr.value,
        'store': form.store.value,
        'subjects': subjects
    };
    localStorage.setItem('upload', JSON.stringify(obj));
}

document.addEventListener("DOMContentLoaded", function() {
    
    var upload_form = document.getElementById('form_upload');
    var file_input = document.getElementById('file_upload');
 
    if(file_input)
    {
        file_input.onchange = onFilesSelected;
    }
    if(upload_form)
    {
        upload_form.onsubmit = onFormSubmit;
    }

     //* Loops through the selected files, displays their file name and size
     //* in the file list, and enables the submit button for uploading.

    var uploader;
    function onFilesSelected(e) {
        document.getElementById('upl-in1').style.width = '0%';
        var file = e.target.files[0];
        //uploader = new ChunkedUploader(file);
        document.getElementById('fname').value=file.name;
			  document.getElementById('fsize').value=file.size;
			
			//var hash=CryptoJS.MD5(charge.result).toString();
			
			var promise = new Promise( function( success, fail ){
					var charge=new FileReader();
					charge.readAsBinaryString(document.getElementById('file_upload').files[0]);

					charge.onerror = function(err){
						fail(err);
					};
					charge.onloadend = function(e){
						try{
							
							var hash=CryptoJS.algo.MD5.create();
							var start = 0;
							var pace = 10 * 1024 * 1024; //10 Mo
							
							recursiveProm( function(cb){
									var end = Math.min( file.size, start+pace ) ;
									var blob = file.slice( start, end );
									
									var readSlicer = new FileReader();
          				readSlicer.readAsArrayBuffer(blob);
																	
									readSlicer.onloadend = function()
									{
										var ui8Chunk=new Uint8Array(readSlicer.result);
										hash.update(CryptoJS.lib.WordArray.create(ui8Chunk));
										start = end;

										if (start >= file.size)
										{
											var md5res=hash.finalize().toString();
											console.log('hash file MD5',md5res);
											document.getElementById('hash').value=md5res;
											cb(true) ;
										}
										else
										{
											cb(false);	
										}
									};
									
							} );
														
							success();
						}catch(err){ fail(err); }
					};
				}).then(displayPreview,function(err){ alert(err.message); });
    }


     // Loops through all known uploads and starts each upload
     // process, preventing default form submission.

	function onFormSubmit(e) {

   			function doerror(err){
					console.error(err);
					if(err.message){
						error_modal(err.title || "Error",err.message);
          }
					else
					{
						var outputstr = '';
						for(var prop in err)
						{
							outputstr = prop + ' : ' + err[prop] + '\n';
						}
						error_modal("Error",outputstr);
					}
						
				}
			
				function submitForm()
				{
					console.log(arguments[0][0],arguments[0][1]);
																
					var status = arguments[0][0];
					var response = JSON.parse( arguments[0][1] );
					if(status < 400)
					{
						var id;
						if(response.id) id=response.id;
						if(response.fileids) id=response.fileids[0];
						if(response.entries) id=response.entries[0].id;

						document.getElementById('fileid').value=id;
						//add image
						var canvas = document.getElementById("preview");
            if(canvas.has_preview)
						{
							document.getElementById('imgfile').value=canvas.toDataURL("image/png").substr('data:image/png;base64,'.length);
						}
						upload_form.submit();
					}
					else
					{
						throw new Error(JSON.stringify(response));
					}	
				}
        var ok = true;
				// do_upload_file: true if upload mode, false if update mode
				var do_upload_file = (file_input!==null);
        if(do_upload_file && file_input.files.length===0)
        {
					alert('error: no file selected');
					ok = false;
        }
        if (ok && upload_form.title.value.length===0)
        {
            alert('error: no title');
            ok = false;
        }
        if(ok && upload_form.author.value.length===0)
        {
            alert('error: no author');
						ok = false;
        }
        
				do_upload_file = do_upload_file && ok;
				if(do_upload_file)
				{

						var submit_btn = document.getElementById('submit_btn');
						var file=file_input.files[0];
						var index=0;
					
						//prevent form to submit before upload
						e.preventDefault();
					
						for(; index < upload_form.store.length; index++) if(upload_form.store[index].checked) break;
						
						var store = new Store('/books/store.php?action=login&store_code='+upload_form.store[index].value);
					
						var promise = new Promise( function(fulfill,reject) {
							store.onlogin = function(resp){ fulfill(resp); };
							store.onerror = function(err){ submit_btn.disabled = false; reject(err); };	
							store.login();
						});
						
						store.onresponse = function(resp){ submit_btn.disabled = false; };
						//uploader.store = store;
						promise.catch(doerror).then( function() { return new Promise(function(fulfill,reject) {  
								store.onerror = function(err){ submit_btn.disabled = false; reject(err); };	
								store.onresponse = function(status,resp)  { submit_btn.disabled = false; fulfill([status,resp]) };
								store.upload(file);
						} ); }).catch(doerror).then(submitForm).catch(doerror);

						submit_btn.disabled = true;
				}
				else if (!ok) // problems, no update
				{
					e.preventDefault();
				}
				else
				{
					document.getElementById('imgfile').value=getImgContent(document.getElementById('preview'));
				}
	}
        
});

function loadimage(file_input)
{
	
	var file = file_input.files[0];		
	if(file.type==='image/jpeg' || file.type=='image/png')
	{
		var imgpreview = document.getElementById('preview');
		if(imgpreview)
		{
			imgpreview.src = window.URL.createObjectURL(file);
		}
		document.getElementById('uploadflag').value='true';
	}
	else
	{
		document.getElementById('uploadflag').value='false';
		alert('bad img file type: ' + file.type);
	}
}

function browseimage()
{
	var file_input=document.getElementById('imginput');
	if(file_input)
	{
		file_input.click();
		console.log('file clicked');
	}
}

function getImgContent(imgobj)
{
	var content='';
	if(imgobj.nodeName.toUpperCase()==='IMG')
	{
		var canvas = document.createElement("canvas");
    canvas.width = imgobj.naturalWidth;
    canvas.height = imgobj.naturalHeight;
		console.log('image',imgobj.width,imgobj.height);
		var context = canvas.getContext("2d");
		context.drawImage(imgobj,0,0);
		content=canvas.toDataURL("image/png").substr('data:image/png;base64,'.length);
	}
	return content;
}

function getMimeType(filename)
{
	var ext='';
	var index = filename.lastIndexOf('.');
	if(index>-1)
	{
		ext=filename.toString().substring(index);
	}
	switch(ext.toLowerCase())
	{
		case '.pdf':
			return 'application/pdf';
		case '.epub':
			return 'application/epub+zip';
		case '.chm':
			return 'application/x-chemdraw';
		case '.rar':
			return 'application/x-rar-compressed, application/octet-stream';
		case '.zip':
			return 'application/zip, application/octet-stream';
		case '.htm':
		case '.html':
			return 'text/html';
    case '.xhtml':
      return 'application/xhtml+xml';
    case '.jpg':
    case '.jpeg':
      return 'image/jpeg';
    case '.png':
      return 'image/png';
    case '.gif':
      return 'image/gif';
		default:
			return 'application/octet-stream';
	}
}

function get_modal(){
  return document.getElementById('modal_id');
}

function set_modal_title(str)
{
  document.getElementById("modal_title").innerHTML = str;
}

function set_modal_content(str)
{
  document.getElementById("modal_content").innerHTML = str;
}

function show_modal()
{
    get_modal().style.display = "block";
}

function error_modal(title,error_msg)
{
  set_modal_title('<table class=\'nav_element\' style=\'margin: 10px\'><tr><td><img src=\'error.png\'></td><td><h3 style=\'margin-top: 15px\'>' + title + '</h3></td></tr></table>');
  set_modal_content(error_msg);
  show_modal();
}