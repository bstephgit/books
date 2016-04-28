function ChunkedUploader(file, options) {
    if (!this instanceof ChunkedUploader) {
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

            console.log('http status code:', self.upload_request.status);
            console.log('http response:', self.upload_request.responseText);
            // TODO
            // From the looks of things, jQuery expects a string or a map
            // to be assigned to the "data" option. We'll have to use
            // XMLHttpRequest object directly for now...
            /*$.ajax(self.options.url, {
            data: chunk,
            type: 'PUT',
            mimeType: 'application/octet-stream',
            headers: (self.range_start !== 0) ? {
            'Content-Range': ('bytes ' + self.range_start + '-' + self.range_end + '/' + self.file_size)
            } : {},
            success: self._onChunkComplete
            });*/
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
    
    /**
     * Loops through the selected files, displays their file name and size
     * in the file list, and enables the submit button for uploading.
     */
    var uploader;
    function onFilesSelected(e) {
        document.getElementById('upl-in1').style.width = '0%';
        var file = e.target.files[0];
        uploader = new ChunkedUploader(file);
        document.getElementById('fname').value=file.name;
        document.getElementById('fsize').value=file.size;
    }
 
    /**
     * Loops through all known uploads and starts each upload
     * process, preventing default form submission.
     */
    function onFormSubmit(e) {
        var ok = true;
        
        if(ok && upload_form.file_upload.files.length==0)
        {
            alert('error: no file selected');
            ok = false;
        }
        if (ok && upload_form.title.value.length==0)
        {
            alert('error: no title');
            ok = false;
        }
        if(ok && upload_form.author.value.length==0)
        {
            alert('error: no author');
            ok = false;
        }
        // Prevent default form submission
        e.preventDefault();
        if(ok)
        {
            if(uploader)
            {
                var submit_btn = document.getElementById('submit_btn');
                uploader.start();        
                submit_btn.disabled = true;
            }
            else if(file_input)
            {
                alert('uploader is not defined');
            }
            else
            {
                upload_form.submit();
            }
        }
        
    }
});