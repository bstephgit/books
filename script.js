function recursiveProm(testf)
{
	
	return new Promise(function(fulfill){
		testf( function(done){
			fulfill(done);	
		} );
		
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

function Store(url)
{
    this.url = url;
    this.store_info = null;
		this.onerror = undefined;
		this.onresponse = undefined;
		this.onlongin = undefined;
}

Store.prototype.login = function ()
{
    var self = this;
    var request = new XMLHttpRequest();
    var url = self.url;
		
		function doerror(err)
		{
				var callback = self.onerror;
				if(callback) callback.call(self,err);
		}
		function doresponse(obj)
		{
				var callback = self.onlogin;
				if(callback) callback.call(self,obj);
		}
	
    request.onerror = function (e) {
        console.log('erreur\n' + e);
				doerror(e);
    };

    console.log('login url:',url);
    request.open('GET', url, true); 

    request.onload = function (e) {

        if (request.status === 200)
        {
            try
            {
								console.log(request.response);
                var obj = JSON.parse(request.response);
                if(obj.redirect)
                {
                    var popup = window.open(obj.redirect, "", "width=750,height=420");

                    window.addEventListener("message", function(event) {

														if(event.origin!==window.location.origin) 
															throw new Error('message not same origin: ' + event.origin);
														window.removeEventListener("message",this,false);
                            try
                            {
                                var info = event.data;
																console.log('got message:',info);
                                if(info!=null)
                                {
                                    self.store_info = JSON.parse(info);
																		doresponse(self.store_info);
                                }
															else
																throw new Error('login info in message is null');
                            }
                            catch (err)
                            {
															console.error(info);
															doerror(err);
                            }
                        }
												, false);
                       
								}
                else if(obj.access_token)
                {
									self.store_info=obj;
                  doresponse(obj);
                }
								else
								{
									console.error(obj);
									throw new Error('unexpected error');
								}
            }
            catch (err)
            {
							console.error(request.response);
              doerror(err);
            }
        }
        else
        {
            console.log('request returned status',request.status,'text',request.statusText);
						var err = new Error('status error');
						err.status = request.status; err.response = request.statusText;
						doerror(err);
        }
    };
    request.send();
    this.getRequest = function()
    {
       return request;
    }
	}

Store.prototype.isLogged = function () {
	
    if(this.store_info)
    {
				console.log('%o',this.store_info);
        return this.store_info.access_token.length > 0;
    }
    return false;
}

Store.prototype.upload = function (file) {

  if(!this.isLogged())
  {
		throw new Error('not logged');
	}
	var self = this;
	function doerror(err)
	{
			var callback = self.onerror;
			if(callback) callback.call(self,err);
	}
	function doresponse()
	{
			var callback = self.onresponse;
			var args = Array.from(arguments);
			if(callback) callback.apply(self,args);
	}
	
	var req = new XMLHttpRequest();
	req.upload.onprogress = function(e){
		var percentComplete = (e.loaded / e.total)*100;
		//console.log('%o',e,percentComplete);
		document.getElementById('upl-in1').style.width = percentComplete + '%';
	};
	req.onload = function(){
		doresponse(req.status,req.response);
	};
	req.onerror = function(e){
		doerror(e);
	};
	var book_folder=this.store_info.book_folder;
	var token = this.store_info.access_token;
	var upload_service = this.store_info.urls.upload;
	var body = upload_service.body;
	var method = upload_service.method;
	var url = upload_service.url;

	var replace_tab = { '{parentid}': book_folder, '{filename}': file.name, '{accesstoken}': token };
	for(var key in replace_tab) {
		if(url.indexOf(key)>-1)
			url = url.replace(key,encodeURI(replace_tab[key]));
	}
	
	console.log('url:',method,url);
	
	var formData;
	if(body==='{filecontent}')
	{
		formData=file;
	}
	else
	{
		formData=new FormData();
	
		for(var dataname in body)
		{
			if(body[dataname]==='{filecontent}')
			{
				formData.append(dataname, file);
			}
			else
			{
				var data;
				if (body[dataname].data) data = JSON.stringify(body[dataname].data);
				else data = JSON.stringify(body[dataname]);
				console.log('before replace data =>',dataname,data);

				for(key in replace_tab)
				{
					if(data.indexOf(key)>-1) {
						data = data.replace(key,replace_tab[key]);
					}
				}
				var blob;
				if(body[dataname].type) blob=new Blob([data] , { 'type' : body[dataname].type });
				else blob=data;
				formData.append(dataname, blob);
				console.log('after replace data =>',dataname,data);
			}
		}
	}

	req.open(method, url);
		
	if(upload_service.hasOwnProperty('headers')){
		for(var i in upload_service.headers)
		{
			var parts = upload_service.headers[i].split(':');
			for(key in replace_tab)
			{
				if(parts[1].indexOf(key)>-1)
				{
					parts[1]=parts[1].replace(key,replace_tab[key]);
				}
			}
			
			req.setRequestHeader(parts[0],parts[1]);
			console.log('set header',parts[0],parts[1]);
		}
	}
	
	req.send(formData);
	
	this.getRequest = function(){
		 return req;
  }
}; 

Store.prototype.download = function()
{
	if(!this.isLogged()) throw new Error('not logged');
	
	var filename=this.store_info.filename;
	var filesize=this.store_info.filesize;
	var vendor=this.store_info.vendor_code;
	var req = new XMLHttpRequest();
	var upload_bar = document.getElementById('upl-in1');
	var token = this.store_info.access_token;
	var self=this;
	
	function doerror(err)
	{
		var callback=self.onerror;
		if(callback) 
		{
			if (typeof err !== "Error"){
				console.error(req.response);
				var err_ = new Error("error downloading file");
				
				if(err.status!==undefined) { err_.status = err.status; err_.response=err.response; }
				else err_.err=err;
				
				callback(err_);
			}
			else callback(err);
		}
		else console.error(err);
	}
	
	function onprogress(e)
	{
		var total=0.0; 
		if(e.total>0) { total = e.total; } else {  total = filesize; }
		upload_bar.style.width = ((e.loaded / total)*100).toString() + "%";
		
		//console.log('%o',e, percent , e.loaded, e.total);
	}
	
	req.addEventListener("progress", onprogress, false);
	req.addEventListener("error",doerror,false);
	
	function handler()
	{
		document.getElementById('upload-out').style.visibility = 'hidden';
		
		if(req.status<400)
		{
			var a = document.createElement('a');
    	a.href = window.URL.createObjectURL(req.response); // xhr.response is a blob
    	a.download = filename; // Set the file name.
    	a.style.display = 'none';
    	document.body.appendChild(a);
    	a.click();
		}
		else
		{
			doerror(req);
		}
	}
	
	if(/*vendor==='PCLD'*/false)
	{
		req.onload = function()
		{
			if(req.status<400)
			{
				console.log(req.response);
				var download_url = JSON.parse(req.response);

				if(download_url.error){	alert(download_url.error);	return; }
				
				//window.open( 'https://' + download_url.hosts[0] + download_url.path );
				window.open( download_url.url );
				
			}
			else	doerror(req);
		}
	}
	else
	{
		req.onload = handler;
		req.responseType = 'blob';
		upload_bar.style.width = '0%';
		document.getElementById('upload-out').style.visibility = 'visible';
	}
	
	var download = this.store_info.downloadLink;
	
	var url = download.url;
	req.open( download.method, url, true );
	
	console.log('download url',url);
	
	if(download.headers)
	{
		for(var i in download.headers)
		{
			var parts = download.headers[i].split(':');
			req.setRequestHeader(parts[0],parts[1]);
		}
	}	
	req.send();

}

/////////////////////////////////////////////////////////////////////////////////////
function ChunkedUploader(file, options) {
    
	if (!(this instanceof ChunkedUploader)) {
        return new ChunkedUploader(file, options);
    }
 
    console.log('create chunk uploader');
    this.file = file;
    
    this.options = Object.assign({
        url: '/books/upload.php'
    }, options);
 
    this.file_size = this.file.size;
    this.chunk_size = (1024 * 100); // 100KB
    this.range_start = 0;
    this.range_end = this.chunk_size;
 
    if ('mozSlice' in this.file) {
        this.slice_method = 'mozSlice';
    }
    else if ('webkitSlice' in this.file) {
        this.slice_method = 'webkitSlice';
    }
    else {
        this.slice_method = 'slice';
    }
 
    this.upload_request = new XMLHttpRequest();
    var self=this;
    this.upload_request.onload = function (){ self._onChunkComplete(); };

    this.upload_request.onerror = function(e){ 
        alert('erreur\n'+e); 
    };
}

ChunkedUploader.prototype = {

    // Internal Methods __________________________________________________

    _upload: function () {
        var self = this,
            chunk;

        // Slight timeout needed here (File read / AJAX readystate conflict?)
        setTimeout(function () {
            // Prevent range overflow
            if (self.range_end > self.file_size) {
                self.range_end = self.file_size;
            }

            chunk = self.file[self.slice_method](self.range_start, self.range_end);

            console.log('upload to ' + self.options.url + ' range=(' + self.range_start + ',' + self.range_end + ')');

            self.upload_request.open('POST', self.options.url, true);
            self.upload_request.overrideMimeType('application/octet-stream');

            self.upload_request.setRequestHeader('Content-Range', 'bytes ' + self.range_start + '-' + self.range_end + '/' + self.file_size);

            var formData = new FormData();
            var data = new Blob([chunk]);
            formData.append("upfile", data, self.file.name);
            self.upload_request.send(formData);
            
        }, 20);
    },

    // Event Handlers ____________________________________________________

    _onUploadComplete: function () {
        var upload_form = document.getElementById('form_upload');
        upload_form.submit();
        //alert('Upload complete');
        var submit_btn = document.getElementById('submit_btn');
        submit_btn.disabled = false;
    },

    _onChunkComplete: function () {
        // If the end range is already the same size as our file, we
        // can assume that our last chunk has been processed and exit
        // out of the function.
        document.getElementById('upl-in1').style.width = (100 * this.range_end / this.file_size) + '%';
        console.log('chunk complete (' + this.range_start + ',' + this.range_end + ')');
        if (this.range_end === this.file_size) {
            this._onUploadComplete();
            return;
        }

        // Update our ranges
        this.range_start = this.range_end;
        this.range_end = this.range_start + this.chunk_size;

        // Continue as long as we aren't paused
        if (!this.is_paused) {
            this._upload();
        }
    },

    // Public Methods ____________________________________________________

    start: function () {
        this._upload();
    },

    pause: function () {
        this.is_paused = true;
    },

    resume: function () {
        this.is_paused = false;
        this._upload();
    }
};

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
		
				function displayPreview()
				{
					//var thumbSize = 264;
					var canvas = document.getElementById("preview");
					//alert(file.type);
					if(file.type==='application/pdf')
					{
						PDFJS.getDocument(window.URL.createObjectURL(file)).then(function(pdf) {
							pdf.getPage(1).then(function(page) {
								var scale = 1;
								var viewport = page.getViewport(scale);

								var context = canvas.getContext('2d');
								scale = canvas.height / viewport.height;
								canvas.width = scale * viewport.width;
								viewport = page.getViewport(scale);

								var renderContext = {
									canvasContext: context,
									viewport: viewport
								};
								page.render(renderContext);
								
							});
						});
					}
					else
					{
						imgfile.value='';
					}
				}
    }


     // Loops through all known uploads and starts each upload
     // process, preventing default form submission.

	function onFormSubmit(e) {

   			function doerror(err){
					console.error(err);
					if(err.message)
						alert("message:"+err.message);
					else
					{
						var outputstr = '';
						for(var prop in err)
						{
							outputstr = prop + ' : ' + err[prop] + '\n';
						}
						//alert(outputstr);
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
						if(file_input.files[0].type=='application/pdf')
						{
							var canvas = document.getElementById("preview");
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