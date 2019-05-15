loadArchiveFormats(['rar'], () => console.log('Uncompress: modules loaded'));

function previewPDF(file,canvas){
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
          canvas.has_preview=true;
        });
  });
}

function previewEPUB(file,canvas){
  var onerror = function(err){
    console.error(err);
  };
  var processZipEntries = function (entries,reader){
    var some_error;
    try{
          if(!Array.isArray(entries))
          {
            var msg = "EPUB file reading error: entry list not an array";
            console.error(msg , entries);
            throw msg + " (" + typeof(entries) + ")";
          }
          var XPathObject = function(content,mime_type){
                try{
                  var oParser = new DOMParser();
                  var o = { };
                  o._oDom = oParser.parseFromString(content, mime_type);
                  o._fnNsResolver = (function (element) {  var nsResolver = element.ownerDocument.createNSResolver(element), defaultNamespace = element.getAttribute('xmlns');
                                        return function (prefix) { return nsResolver.lookupNamespaceURI(prefix) || defaultNamespace; };
                                    } (o._oDom.documentElement));
                  o._oResult = null;
                  return Promise.resolve(o);
                }
                catch(err)
                {
                  return Promise.reject(err);
                }
          };
          var doXPathQuery = function () {
            var sQuery = arguments[0];
            var oXPath = arguments[1];
            var result_type = arguments[2] || XPathResult.ANY_TYPE;
            var oDom  = oXPath._oDom;
            var fnResolver = oXPath._fnNsResolver;
            oXPath._oResult = oDom.evaluate(sQuery, oDom, fnResolver, result_type, null);
            return Promise.resolve(oXPath);
          };
          var searchEntry = function(list,pred){
              return new Promise((resolve,reject) => {
                list.forEach( (entry) => {
                  if(pred(entry))
                  {
                    resolve(entry);
                    return;
                  }
                });
                return reject("Not found");  
              });
          };
          var getData = function (reader,entry){
            return new Promise( 
              (resolve) => { entry.getData( reader, (data) =>  resolve(data)); }
            );
          };
          var raiseError = function (o,msg){
            o._err = msg;
            throw o;
          };
          var doNext = (o) => Promise.resolve(o);

          var cover_img,cover_mime_type;
          //Get EPUB cover image: http://idpf.org/forum/topic-715
          searchEntry(entries,(entry) => entry.filename.toLowerCase().match(/.opf$/))
              .then( (entry)  => getData( new zip.TextWriter(), entry ) )
              .then( (epub_content) => {

                        var promise = XPathObject(epub_content, "application/xml");

                        return promise.then( (oxp) => doXPathQuery("string(/ns:package/ns:metadata/ns:meta[@name='cover']/@content)", oxp))
                        .then((oxp) => {if (oxp._oResult.stringValue.length === 0)  raiseError(oxp,"Cover image not found in metadata"); return doNext(oxp); })
                        .then((oxp) => doXPathQuery("/ns:package/ns:manifest/ns:item[@id='" + oxp._oResult.stringValue + "']", oxp, XPathResult.ANY_UNORDERED_NODE_TYPE ))
                        .catch((oxp) => doXPathQuery("/ns:package/ns:manifest/ns:item[@properties='cover-image']", oxp, XPathResult.ANY_UNORDERED_NODE_TYPE ))
                        .then((oxp) =>  { if(!oxp._oResult.singleNodeValue) raiseError(oxp,"OPF cover Item not found"); return doNext(oxp); } )
                        .then((oxp) => {
                             cover_img = oxp._oResult.singleNodeValue.getAttribute('href');
                             cover_mime_type = oxp._oResult.singleNodeValue.getAttribute('media-type');
                             return doNext(null);
                        })
                        .catch((oxp) =>{
                          //try the hard way: indirectly through xhtml file referenced in OPF guide section, containing image href
                           var err_msg = "Cannot found EPUB cover image";
                           return doXPathQuery("string(/ns:package/ns:guide/ns:reference[@type='cover']/@href)",oxp)
                             .then((oxp) => { if(oxp._oResult.stringValue.length) return doNext(oxp._oResult.stringValue); else throw err_msg; })
                             .then((file_name) => { 
                                    return searchEntry(entries, (entry) => entry.filename.match( new RegExp(file_name+"$") )) 
                              })
                            .then((entry) => getData( new zip.TextWriter(), entry ))
                            .then( (xhtml_content) => XPathObject(xhtml_content,'application/xhtml+xml') )
                            .then((oxp) => doXPathQuery("string(//ns:img/@src)",oxp) )
                            .then( (oxp) => { if(oxp._oResult.stringValue.length) return doNext(oxp); throw "Exception => exit"; } )
                            .then((oxp) => { cover_img = oxp._oResult.stringValue; cover_mime_type = getMimeType(cover_img);  } )
                        })
                        .then(() => searchEntry(entries, (entry) => entry.filename.match( new RegExp(cover_img+"$") )) )
                        .then( (entry) => getData( new zip.BlobWriter(cover_mime_type), entry ) )
                        .then( function (blob_data){
                               var img = new Image(canvas.width,canvas.height);
                               img.onload = function(){ canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height); canvas.has_preview=true; };
                               img.src = window.URL.createObjectURL(blob_data);
                        }).catch(() => { throw 'EPUB Format error: cannot find cover image item'; } );

                        //return promise;

                  }).catch((err) => console.error(err));
    }
    catch(err)
    {
      console.log(err); 
    }
    finally{
        reader.close();
    }
  }

  //window.URL.createObjectURL(file);
  zip.useWebWorkers=false;
  zip.createReader(new zip.BlobReader(file.slice()),function(reader){
    reader.getEntries( 
      function (entries) {
        processZipEntries(entries,reader);
      }
      , onerror );
  },onerror);
}

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
		
				function displayPreview()
				{
					//var thumbSize = 264;
					var canvas = document.getElementById("preview");
					canvas.has_preview=false;
					if(file.type==='application/pdf')
					{
						previewPDF(file,canvas);
					}
          else if(file.type==='application/epub+zip')
          {
            previewEPUB(file,canvas);
          }
          else if(getMimeType(file.name)===getMimeType('.rar'))
          {
                  var p = new Promise( (yes,no) => { archiveOpenFile(file, '', function(archive, err) { if(err) no(err); else yes(archive); } ); } )
                    .then( (archive) => {
                      var mime; 
                      
                      var index = archive.entries.findIndex(function(entry) { 
                        mime = getMimeType(entry.name);
                        return mime===getMimeType('.pdf') || mime===getMimeType('.epub');
                      });
                      if(index>-1){
                        var entry = archive.entries[index];
                        console.log('RAR entry name,',entry.name, 'size compressed', entry.size_compressed, 'size uncompressed', entry.size_uncompressed, ' is file:', entry.is_file );
                        var map = { pdf: previewPDF, epub: previewEPUB };
                        map[getMimeType('.pdf')] = previewPDF;
                        map[getMimeType('.epub')] = previewEPUB;
                        
                        if(index===-1) throw "Bad file type: " + entry.name + ". Only EPUB or PDF supported.";
                        var p = new Promise( (yes,no) => {  
                         entry.readData(function(data, err) {
                           if(err) no(err); else yes(data); } );
                          }).then((data) => map[mime](new File([data],"file", {type: mime}),canvas) ); 
                        return p;
                      }
                      throw "file type (PDF or EPUB) not found in RAR archive";
                   }).catch((err) => console.log(err));
          }
					else
					{
            console.log(file.type);
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
						alert(err.message);
					else
					{
						var outputstr = '';
						for(var prop in err)
						{
							outputstr = prop + ' : ' + err[prop] + '\n';
						}
						alert(outputstr);
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
						promise.then( function() { return new Promise(function(fulfill,reject) {  
								store.onerror = function(err){ submit_btn.disabled = false; reject(err); };	
								store.onresponse = function(status,resp)  { submit_btn.disabled = false; fulfill([status,resp]) };
								store.upload(file);
						} ); }).then(submitForm).catch(doerror);

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