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
