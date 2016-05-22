
function storeUplForm(form)
{
    var subjects=[];
    var index=1;
    while(true)
    {
        var name='topic'+index;
        if(form[name])
        {
            if(form[name].checked)
            {
                subjects.push(name);
            }
        }
        else
        {
            break;
        }
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

function Store(name)
{
    console.log('create store');
    this.name = name;
    this.store_info = null;
}

Store.prototype.login = function (callback)
{
    var self = this;
    var request = new XMLHttpRequest();
    var url = '/books/store.php?action=login&store_code=' + self.name;


    request.onerror = function (e) {
        console.log('erreur\n' + e);
    };

    console.log('login url:',url);
    request.open('GET', url, true); 

    request.onload = function (e) {

        if (request.status === 200)
        {
            try
            {
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
                                if(info!=null)
                                {
                                    self.store_info = JSON.parse(info);
                                    callback(self.store_info);
                                }
															else
																throw new Error('login info in message is null');
                            }
                            catch (err)
                            {
                                callback(err);
                            }
                        }
												, false);
                       
								}
                else if(obj.access_token)
                {
									self.store_info=obj;
                  callback(obj);
                }
            }
            catch (err)
            {
                //console.log('%o',err);
                callback(err);
            }
        }
        else
        {
            console.log('request returned status',request.status,'text',request.statusText);
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

Store.prototype.upload = function (file,callback) {

  if(!this.isLogged())
  {
		throw new Error('not logged');
	}

	var req = new XMLHttpRequest();
	req.upload.onprogress = function(e){
		var percentComplete = (e.loaded / e.total)*100;
		//console.log('%o',e,percentComplete);
		document.getElementById('upl-in1').style.width = percentComplete + '%';
	};
	req.onload = function(){
		callback(req.status,req.response);
	};
	req.onerror = function(e){
		callback(e);

	};
	var book_folder=this.store_info.book_folder;
	var token = this.store_info.access_token;
	var upload_service = this.store_info.urls.upload;
	var body = upload_service.body;
	var method = upload_service.method;
	var url = upload_service.url;

	var replace_tab = { '{parentid}': book_folder, '{filename}': file.name, '{accesstoken}': token };
	for(var key in replace_tab)
	{
		if(url.indexOf(key)>-1)
		{
			url = url.replace(key,encodeURI(replace_tab[key]));
		}
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
				if (body[dataname].data) data = body[dataname].data.toString();
				else data = body[dataname].toString();
				console.log(dataname,data);

				for(key in replace_tab)
				{
					if(data.indexOf(key)>-1)
					{
						data = data.replace(key,replace_tab[key]);
					}
				}
				var blob;
				if(body[dataname].type) blob=new Blob([data] , { 'type' : body[dataname].type });
				else blob=data;
				formData.append(dataname, blob);
				console.log(dataname,data);
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

Store.prototype.download = function(fileid,filename)
{
	console.log(this);
	if(!this.isLogged()) throw new Error('not logged');
	
	var req = new XMLHttpRequest();
	req.onload = function()
	{
		console.log(req.getAllResponseHeaders());
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
			alert(req.response);
		}
	};
	var download = this.store_info.urls.download;
	var token = this.store_info.access_token;
	
	var url = download.url.replace('{fileid}',fileid).replace('{accesstoken}',token);
	req.open( download.method, url );
	if(download.headers)
	{
		for(var i in download.headers)
		{
			var parts = download.headers[i].split(':');
			req.setRequestHeader(parts[0],parts[1].replace('{accesstoken}',token));
		}
	}
	req.responseType = 'blob';
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
			
				//var thumbSize = 264;
				//var canvas = document.getElementById("canvas");
				var canvas = document.getElementById("preview");
			/*
				canvas.width = thumbSize;
				canvas.height = thumbSize;
				var c = canvas.getContext("2d");
				var img = new Image();
				img.onload = function(e) {
						alert('img loaded');
						c.drawImage(this, 0, 0, thumbSize, thumbSize);
						document.getElementById("preview").src = canvas.toDataURL("image/png");
				};
				img.src = window.URL.createObjectURL(file);
				*/
				PDFJS.getDocument(window.URL.createObjectURL(file)).then(function(pdf) {
					pdf.getPage(1).then(function(page) {
						var scale = 1;
						var viewport = page.getViewport(scale);


						var context = canvas.getContext('2d');
						//canvas.height = viewport.height;
						//canvas.width = viewport.width;
						scale = ( (canvas.height/viewport.height) + (canvas.width/viewport.width)  ) / 2;
						viewport = page.getViewport(scale);
						
						var renderContext = {
							canvasContext: context,
							viewport: viewport
						};
						page.render(renderContext);
					});
				});
    }


     // Loops through all known uploads and starts each upload
     // process, preventing default form submission.

	function onFormSubmit(e) {
   
        var ok = true;
        if(ok && upload_form.file_upload.files.length===0)
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
        }
        //e.preventDefault();
        e.preventDefault();
        
				if(ok)
				{

						var submit_btn = document.getElementById('submit_btn');
						var index=0;
						for(; index < upload_form.store.length; index++)
						{
								if(upload_form.store[index].checked)
								{
									break;
								}
						}
						var store = new Store(upload_form.store[index].value);
						//uploader.store = store;
						store.login(
								function(obj)
								{
										if (obj instanceof Error)
										{
												console.error(obj);
												alert(obj.toString());
										}
										else
										{
											store.store_info=obj;
											console.log('login callback %o', obj);
											store.upload(document.getElementById('file_upload').files[0],
													function(){

															if(arguments.length>1)
															{
																console.log(arguments[0],arguments[1]);
																

																submit_btn.disabled = false;
																var status = arguments[0];
																var response = JSON.parse( arguments[1] );
																if(status < 400)
																{
																	var id;
																	if(response.id)
																	{
																		id=response.id;
																	}
																	if(response.fileids)
																	{
																		id=response.fileids[0];
																	}
																	
																	//add image
																	var imgfile=document.getElementById('imgfile');
																	if(imgfile)
																	{
																		var canvas = document.getElementById("preview");
																		
																		imgfile.value=canvas.toDataURL("image/png").substr('data:image/png;base64,'.length);
																	}
																	document.getElementById('fileid').value=id;
																	
																	var charge=new FileReader();
																	charge.readAsBinaryString(document.getElementById('file_upload').files[0]);
		
																	charge.onloadend = function(e){
																			document.getElementById('hash').value=CryptoJS.MD5(charge.result).toString();
																			upload_form.submit();
																	}
																}
																else
																{
																	alert(response);
																}
															}
															else
															{
																console.log(arguments[0]);
																alert(arguments[0]);
															}
											});
										}
								});
						//uploader.start();        
						submit_btn.disabled = true;
				}
				/*else if(file_input)
				{
						alert('uploader is not defined');
				}
				else
				{
						upload_form.submit();
				}*/
	}
        
});
		