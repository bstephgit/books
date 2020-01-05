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
									var jsonstr = JSON.parse(info);
									if(jsonstr.error)
									{
										 throw new Error(jsonstr.error);
									}
                                    self.store_info = jsonstr;
									doresponse(jsonstr);
                                }
								else
									throw new Error('login info in message is null');
                            }
                            catch (err)
                            {
								console.error(err);
								doerror(err);
                            }
                        }, false);
                       
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
			var err = new Error('Login failed:\n Status error [' + request.status + ']\n' + request.statusText);
			err.status = request.status; err.response = request.statusText;
			doerror(err);
        }
    };
    request.send();
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
	function doresponse(req)
	{
		var callback = self.onresponse;
		if(callback) callback.apply(self,req);
	}
	
	var req = new XMLHttpRequest();
	req.upload.onprogress = function(e){
		var percentComplete = (e.loaded / e.total)*100;
		//console.log('%o',e,percentComplete);
		document.getElementById('upl-in1').style.width = percentComplete + '%';
	};
	req.onload = function(){
		doresponse(req);
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
				formData.append(dataname, file, file.name);
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
	self.total_loaded=0;
	var download = this.store_info.downloadLink;
	
	var url = download.url;
	
	function doerror(err)
	{
		var callback=self.onerror;
		document.getElementById('upload-out').style.visibility = 'hidden';
		document.getElementById('btnDownload').disabled = false;
		if(callback) 
		{
			console.error((typeof err).toString());
			if (typeof err !== "Error"){
				console.error(err);
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
		//var total=0.0; 
		//if(e.total>0) { total = e.total; } else {  total = filesize; }
		//upload_bar.style.width = ((e.loaded / total)*100).toString() + "%";
		upload_bar.style.width = (((self.total_loaded+e.loaded) / filesize)*100).toString() + "%";
		//console.log('%o',e, percent , e.loaded, e.total);
	}
	
	req.addEventListener("progress", onprogress, false);
	req.addEventListener("error",doerror,false);
	
	function handler()
	{
		function savefile(blob_array)
		{
			var a = document.createElement('a');
			var mime_type = getMimeType(filename);
			
			//refresh UI
			document.getElementById('btnDownload').disabled = false;
			document.getElementById('upload-out').style.visibility = 'hidden';
			
    	a.href = window.URL.createObjectURL(new Blob(blob_array,{type: mime_type}));
    	a.download = filename; // Set the file name.
    	a.style.display = 'none';
			a.type=mime_type;
    	document.body.appendChild(a);
    	a.click();
		}
		if(req.status<400)
		{
			if(req.status===206)//partial
			{
				var content_length = 0;
				var range_left=0;
				var range_right=0;
				var total_length=0;
				
				if(!this.ranges)
				{
					this.ranges=[];
				}
				
				if(req.getResponseHeader('Content-Length'))
				{
					content_length = parseInt(req.getResponseHeader('Content-Length'));
					self.total_loaded += content_length;
				}
				if(req.getResponseHeader('Content-Range'))
				{
					var regx = /bytes\s+(\d+)-(\d+)\/(\d+)/;
					var match = req.getResponseHeader('Content-Range').match(regx);
					range_left=parseInt(match[1]);
					range_right=parseInt(match[2]);
					total_length=parseInt(match[3]);
				}
				console.log('Range: ',range_left,range_right,'/',total_length);
				
				this.ranges.push(req.response);
				
				if(range_right+1===total_length)
				{
					console.log( 'Nb ranges', this.ranges.length);
					savefile(this.ranges);
				}
				else
				{
					var download = self.store_info.downloadLink;
					var range = 'bytes=' + (range_right+1).toString() + '-' + (Math.min(range_right+content_length,total_length-1)).toString();
					var url = download.url;
					
					console.log('send request for Range', range);
					req.open( download.method, url, true );

					console.log('download url',url);

					if(download.headers)
					{
						for(var i in download.headers)
						{
							console.log('set header', download.headers[i]);
							var parts = download.headers[i].split(':');
							req.setRequestHeader(parts[0],parts[1]);
						}
					}
					req.setRequestHeader('Range', range);
					req.send();
					return;
				}
			}
			else
			{
				savefile([req.response]);
			}
		}
		else
		{
			doerror(req);
		}
	}
	
	if(/*vendor==='PCLD'*/ false)
	{
		req.onload = function()
		{
			if(req.status<400)
			{
				console.dir(req.response);
				
				var download_url = JSON.parse(req.response);
				
				if(download_url.error){	alert(download_url.error);	doerror(req); return; }
				
				window.open( 'https://' + download_url.hosts[0] + download_url.path );
				
			}
			else	doerror(req);
		};
	}
	else
	{
		req.onload = handler;
		req.responseType = 'blob'; // xhr.response is a blob
		upload_bar.style.width = '0%';
		document.getElementById('upload-out').style.visibility = 'visible';
		document.getElementById('btnDownload').disabled = true;
	}
	
	
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