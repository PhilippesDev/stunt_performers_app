function uploadImage() {
    var file = document.getElementById('imageUpload').files[0];
    if (!file) return;  // Si aucun fichier n'est sélectionné, ne rien faire

    var formData = new FormData();
    formData.append("image", file);

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "upload.php", true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            var percentComplete = (e.loaded / e.total) * 100;
            document.getElementById('progressBar').value = percentComplete;
            document.getElementById('status').innerHTML = Math.round(percentComplete) + '% uploaded';
        }
    };

    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('status').innerHTML = 'Upload complete!';
            var uploadedImageUrl = window.URL.createObjectURL(file);
            document.getElementById('uploadedImage').src = uploadedImageUrl;
            document.getElementById('uploadedImage').style.display = 'block';
        } else {
            document.getElementById('status').innerHTML = 'Upload failed. Server returned an error: ' + xhr.statusText;
        }
    };

    xhr.send(formData);
}