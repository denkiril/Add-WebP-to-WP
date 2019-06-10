// console.log('admin awpwp!');
const awpwpContent = document.querySelector('#awpwp_content');
const awpwpSelect = document.querySelector('#awpwp_select');
const imgFilename = document.querySelector('#img_filename');
const awpwpLoader = document.querySelector('#awpwp_loader');

function addContent(text) {
    const markup = `<p>${text}</p>`;
    awpwpContent.insertAdjacentHTML('beforeend', markup);
}

function swowAjaxLoader(show=true) {
    awpwpLoader.style.display = show ? 'inline' : 'none';
}

jQuery(document).ready(function($) {
    
    function updateImgFilename() {
        const optionStr = awpwpSelect.options[awpwpSelect.selectedIndex].text;

        if (optionStr == 'ALL') {
            imgFilename.innerHTML = 'все картинки из списка выше';
        } else {
            $.ajax({
                // method: 'GET',
                url:    myajax.url,
                data:   { action: 'awpwp_get_filename_by_id', img_id: optionStr },
        
                success: function(response){
                    imgFilename.innerHTML = response;
                } 
            });
        }
    }

    function updateSelect(images=null) {
        if (images == null) {
            // console.log('updateSelect (images == null)');
            $.ajax({
                // method: 'GET',
                url:    myajax.url,
                data:   { action: 'awpwp_get_nowebp_images' },
        
                success: function(response){
                    const arr2 = jQuery.parseJSON(response);
                    images = arr2[0];
                    updateSelect(images);
                } 
            });
        } else {
            // console.log('updateSelect (images != null)');
            while (awpwpSelect.firstChild) {
                awpwpSelect.removeChild(awpwpSelect.firstChild);
            }
            let options = '<option>ALL</option>';
            images.forEach((id) => {
                options += `<option>${id}</option>`;
            });
            awpwpSelect.insertAdjacentHTML('beforeend', options);
            updateImgFilename();
        }
    }

    function updateNowebpImages() {
        $.ajax({
            // method: 'GET',
            url:    myajax.url,
            data:   { action: 'awpwp_get_nowebp_images' },
    
            success: function(response){
                const arr2 = jQuery.parseJSON(response);
                const images = arr2[0];
                const unnecessary = arr2[1];
                // console.log(images);
                const images_count = Object.keys(images).length;
                addContent('nowebp images count: ' + images_count);
                const images_str = images.join(', ');
                addContent('nowebp images ids: ' + images_str);
                updateSelect(images);
                const unnecessary_str = unnecessary.join(', ');
                addContent('unnecessary images: ' + unnecessary_str);
            } 
        });
    }

    $('#awpwp_update_btn').click(function() {
        updateNowebpImages();
    });

    $('#awpwp_optimize_btn').click(function() {
        const optionStr = awpwpSelect.options[awpwpSelect.selectedIndex].text;

        swowAjaxLoader();
        $.ajax({
            // method: 'GET',
            url:    myajax.url,
            data:   { action: 'awpwp_add_webp_images', img_id: optionStr },
    
            success: function(response){
                swowAjaxLoader(false);
                addContent(response);
                updateNowebpImages();
            } 
        });
    });

    $('#awpwp_select').change( function() {
        updateImgFilename();
    });

    updateSelect();

});
