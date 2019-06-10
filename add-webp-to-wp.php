<?php
/*
 * Plugin Name: Add WebP to WP
 * Description: Add WebP versions of jpeg and png files and write them to attachment metadata. Has function to generate picture markup.
 * Author: Denis Kirilyuk
 * Version: 0.8
 * Author URI: https://github.com/denkiril
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$AWPWP_FULL_IMG_SIZE_NAME   = 'webp_full';
$AWPWP_PLACEHOLDER_URL      = plugins_url('/assets/img/placeholder_3x2.png', __FILE__);
$AWPWP_LOADER_URL          = plugins_url('/assets/img/ajax-loader.gif', __FILE__);

function awpwp_plugin_admin_scripts() {
    wp_enqueue_style('awpwp_admin', plugins_url('/assets/css/admin_style.css', __FILE__));
    wp_enqueue_script('awpwp_admin-js', plugins_url('/assets/js/admin_script.js', __FILE__), array('jquery'), null, true);
    wp_localize_script( 'awpwp_admin-js', 'myajax', array( 'url' => admin_url('admin-ajax.php') ) );
}

// action function for above hook
function awpwp_add_pages() {
    // Add a new submenu under Manage:
    add_management_page('Add WebP to WP', 'Add WebP to WP', 8, 'add-webp-to-wp', 'awpwp_manage_page');
}

// Hook for adding admin menus
add_action('admin_menu', 'awpwp_plugin_admin_scripts');
add_action('admin_menu', 'awpwp_add_pages');

function awpwp_manage_page() {
    global $AWPWP_LOADER_URL;
    // $args = array( 'post_type' => 'attachment', 'posts_per_page' => -1, 'post_status' => null, 'post_parent' => null );
    $args = array( 'post_type' => 'attachment', 'posts_per_page' => -1, 'post_status' => null );
    $attachments = get_posts( $args );
    $att_count = count($attachments);
    $args['post_mime_type'] = 'image/jpeg';
    $jpeg_atts = get_posts( $args );
    $args['post_mime_type'] = 'image/webp';
    $webp_atts = get_posts( $args );
    
    $upload_dir = wp_get_upload_dir();
    $directory = $upload_dir['basedir'];
    $subdir = $upload_dir['subdir'];
    $files = glob($directory.$subdir.'/*');
    $file_count = $files ? count($files) : 0;
    $files = glob($directory.$subdir.'/*.{jpg,jpeg,png}', GLOB_BRACE);
    $img_count = $files ? count($files) : 0;
    $files = glob($directory.$subdir.'/*.webp');
    $webp_count = $files ? count($files) : 0;
    list($nowebp_images_ids, $unnecessary_images) = awpwp_get_nowebp_images_ids(true);

    ?>

    <div class="wrap">
        <h2>Welcome to "Add WebP to WP" Plugin</h2>
        <p>Attachments count: <?=$att_count?></p>
        <p>jpeg count: <?=count($jpeg_atts)?></p>
        <p>webp count: <?=count($webp_atts)?></p>
        <p>Upload dir: <?=$directory?> Upload subdir: <?=$subdir?></p>
        <p>Files count: <?=$file_count?></p>
        <p>jpg,jpeg,png files count: <?=$img_count?></p>
        <p>webp files count: <?=$webp_count?></p>
        <p>nowebp images count: <?=count($nowebp_images_ids)?></p>
        <p>nowebp images ids: <?=implode(', ', $nowebp_images_ids)?></p>
        <p>unnecessary images: <?=implode(', ', $unnecessary_images)?></p>
        <button id="awpwp_update_btn">Обновить данные</button>
        <span>Оптим-ть картинку id: </span>
        <select id="awpwp_select"></select>
        <span id="img_filename">filename</span>
        <button id="awpwp_optimize_btn">Оптимизировать!</button>
        <img id="awpwp_loader" src=<?=$AWPWP_LOADER_URL?> />
        <div id="awpwp_content"></div>

    </div>

<?php 
}

// generate_webp_name
function _awpwp_gen_webp_name($filename) {
    return $filename . '.webp';
}

// search filename.webp in meta
function _awpwp_search_in_meta($meta_sizes, $filename) {
    foreach ($meta_sizes as $size) {
        if ($size['file'] == $filename) {
            return true;
        }
    }
    return false;
}

// return nowebp_images_ids (& unnecessary_images)
function awpwp_get_nowebp_images_ids( $get_unnecessary_images=false ) {
    global $AWPWP_FULL_IMG_SIZE_NAME;
    $nowebp_images_ids = [];
    $unnecessary_images = [];

    $args = array( 
        'post_type' => 'attachment', 
        'posts_per_page' => -1, 
        'post_status' => null, 
        // 'post_parent' => null,
        'post_mime_type' => ['image/jpeg', 'image/png'],
    );
    $attachments = get_posts( $args );
    $upload_dir = wp_get_upload_dir();
    // $att_count = count($attachments);
    foreach ($attachments as $post) {
        $metadata = wp_get_attachment_metadata( $post->ID );
        if (!isset($metadata['sizes_webp'])) {
            $nowebp_images_ids[] = $post->ID;
            // continue;
        } elseif ($get_unnecessary_images) {
            // перебираем все размеры файла
            $file = $upload_dir['basedir'] . $upload_dir['subdir'] . '/' . $metadata['file'];
            $filename_webp = _awpwp_gen_webp_name($metadata['file']);
            if (file_exists(_awpwp_gen_webp_name($file)) && !_awpwp_search_in_meta($metadata['sizes_webp'], $filename_webp)) {
                $unnecessary_images[] = $filename_webp .' ('.$post->ID.')';
            }
            foreach ($metadata['sizes'] as $size) {
                $file = $upload_dir['basedir'] . $upload_dir['subdir'] . '/' . $size['file'];
                $filename_webp = _awpwp_gen_webp_name($size['file']);
                if (file_exists(_awpwp_gen_webp_name($file)) && !_awpwp_search_in_meta($metadata['sizes_webp'], $filename_webp)) {
                    $unnecessary_images[] = $filename_webp .' ('.$post->ID.')';
                }
            }            
        }
    }
    
    if ($get_unnecessary_images) {
        return [ $nowebp_images_ids, $unnecessary_images ];
    } else {
        return $nowebp_images_ids;
    }
}

function _awpwp_generate_webp($image, $quality, $filesize, $file, $img_data, $img_size, &$metadata) {
    ob_start();
    imagewebp($image, null, $quality); // сохраняем webp изображение в оперативную память
    $new_file_size = ob_get_length();
    ob_end_clean();
    // если webp-версия меньше исходного файла, создаем её на диске и добавляем информацию в мета
    if ($new_file_size < $filesize) {
        imagewebp($image, _awpwp_gen_webp_name($file), $quality);
        $webp_data = array (
            'file'      => _awpwp_gen_webp_name($img_data['file']),
            'width'     => $img_data['width'],
            'height'    => $img_data['height'],
            'mime-type' => 'image/webp',
        );
        $metadata['sizes_webp'][$img_size] = $webp_data;
    }
}

function awpwp_image_optimization_and_webp_generation($metadata) {
    $uploads = wp_upload_dir(); // получает папку для загрузки медиафайлов
	$quality = 80; // imagewebp() default is 80, imagejpeg() ~75 

    $file = $uploads['basedir'] . $uploads['subdir'] . '/' . $metadata['file']; // получает исходный файл
	$ext = wp_check_filetype($file); // получает расширение файла
	$filesize = filesize($file); // размер файла в байтах
	
	if ( !(($ext['type'] == 'image/jpeg' || $ext['type'] == 'image/png') && $filesize) ) {
        $metadata = 'fail';
        return $metadata; // работаем только с jpeg и png 
    }

	// оптимизируем (уменьшаем качество и вес) исходное изображение
    if ( $ext['type'] == 'image/jpeg' ) { // в зависимости от расширения обрабатаывает файлы разными функциями
		$image = imagecreatefromjpeg($file); // создает изображение из jpg
		if ($image) {
			ob_start();
			imagejpeg($image, null, $quality); // сохраняем оптим. изображение в оперативную память
			$new_file_size = ob_get_length();
			ob_end_clean();
			if ($new_file_size < $filesize) {
				imagejpeg($image, $file, $quality); // сохраняем оптим. изображение на место исходного
			}
		}
    } elseif ( $ext['type'] == 'image/png' ) {
		$image = imagecreatefrompng($file); // создает изображение из png
		if ($image) {
			imagepalettetotruecolor($image); // восстанавливает цвета
			imagealphablending($image, false); // выключает режим сопряжения цветов
			imagesavealpha($image, true); // сохраняет прозрачность
			
			ob_start();
			imagepng($image); // оптимизируем, 0...9, #define Z_DEFLATED 8 - сохр. в оперативную память
			$new_file_size = ob_get_length();
			ob_end_clean();
			if ($new_file_size < $filesize) {
				imagepng($image, $file); // сохраняем оптим. изображение на место исходного
			}
		}
	}

	// делаем webp-версию исх. изображения
    global $AWPWP_FULL_IMG_SIZE_NAME;
	if ($image) {
        _awpwp_generate_webp($image, $quality, $filesize, $file, $metadata, $AWPWP_FULL_IMG_SIZE_NAME, $metadata);
	}

	// перебирает все размеры файла и также сохраняет в webp
    foreach ($metadata['sizes'] as $size_name => $size) {
		$file = $uploads['basedir'] . $uploads['subdir'] . '/' . $size['file'];
		$sizes_file_size = filesize($file);
		
		// если вес к.-л. (меньшего) размера больше исходного файла, удаляем этот размер
		if ($sizes_file_size && $sizes_file_size > $filesize) {
			unlink($file);
			unset($metadata['sizes'][$size_name]);
			continue;
		}
		
        $ext = $size['mime-type'];
        if ( $ext == 'image/jpeg' ) {
            $image = imagecreatefromjpeg($file); 
            
        } elseif ( $ext == 'image/png' ) {
            $image = imagecreatefrompng($file);
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
		}
		
        if ($image) {
            if ($size_name != $AWPWP_FULL_IMG_SIZE_NAME) {
                _awpwp_generate_webp($image, $quality, $filesize, $file, $size, $size_name, $metadata);
            }
		}
    }

    return $metadata;
}

add_filter('wp_generate_attachment_metadata', 'awpwp_image_optimization_and_webp_generation');

// подключаем AJAX обработчики, только когда в этом есть смысл
if( wp_doing_ajax() ){
	add_action('wp_ajax_awpwp_add_webp_images', 'awpwp_add_webp_images');
	add_action('wp_ajax_nopriv_awpwp_add_webp_images', 'awpwp_add_webp_images');
}

function awpwp_add_webp_images() {
    $echo = '';
    
    if (!empty( $_GET['img_id'])) {
        $img_id = $_GET['img_id'];
        
        if ($img_id == 'ALL') {
            $images_ids = awpwp_get_nowebp_images_ids();
            $errors = [];
            foreach ($images_ids as $img_id) {
                $metadata = wp_get_attachment_metadata($img_id);
                $metadata = awpwp_image_optimization_and_webp_generation($metadata);
                $result = wp_update_attachment_metadata($img_id, $metadata);
                if (!$result) {
                    $errors[] = $img_id;
                }
            }
            if (empty($errors)) {
                $echo = 'this images not updated: ' . implode(', ', $errors);
            } else {
                $echo = 'all images successfully updated!';
            }
        } else {
            $metadata = wp_get_attachment_metadata($img_id);
            $metadata = awpwp_image_optimization_and_webp_generation($metadata);
            $result = wp_update_attachment_metadata($img_id, $metadata) ? 'ok' : 'no ok';
            $echo .= 'update of attach. no. ' . $img_id . ' is ' . $result;
            // $echo .= '<pre>' . print_r($metadata, true) . '</pre>';
            // $echo = 'can update attach. no. ' . $img_id . ': ' . $metadata['file'];
            // $echo .= json_encode($metadata);
        }
    }

    echo $echo;

    wp_die();
}

// подключаем AJAX обработчики, только когда в этом есть смысл
if( wp_doing_ajax() ){
	add_action('wp_ajax_awpwp_get_nowebp_images', 'awpwp_get_nowebp_images');
	add_action('wp_ajax_nopriv_awpwp_get_nowebp_images', 'awpwp_get_nowebp_images');
}

function awpwp_get_nowebp_images() {	
	$nowebp_images = awpwp_get_nowebp_images_ids(true);

	echo json_encode( $nowebp_images );

	wp_die();
}

if( wp_doing_ajax() ){
	add_action('wp_ajax_awpwp_get_filename_by_id', 'awpwp_get_filename_by_id');
	add_action('wp_ajax_nopriv_awpwp_get_filename_by_id', 'awpwp_get_filename_by_id');
}

function awpwp_get_filename_by_id() {	
    $filename = '';

	if (!empty( $_GET['img_id'])) {
        $img_id = $_GET['img_id'];
        $metadata = wp_get_attachment_metadata( $img_id );
        $filename = $metadata['file'];
    }

	echo $filename;

	wp_die();
}

/*
	awpwp_get_attachment_picture() is mod of standart wp_get_attachment_image() 
*/
function awpwp_get_attachment_picture( $attachment_id, $size='thumbnail', $icon=false, $attr='', $lazy=true, $placeholder=false ) {
	$html = '';
	global $AWPWP_PLACEHOLDER_URL;
	// $image = wp_get_attachment_image_src( $attachment_id, $size, $icon );
	// anti image_constrain_size_for_editor() to $content_width 
	$is_image = wp_attachment_is_image( $attachment_id );
	if ( $is_image ) {
		$img_url			= wp_get_attachment_url( $attachment_id );
		$image_meta 		= wp_get_attachment_metadata( $attachment_id );
		$width  			= $image_meta['width'];
		$height 			= $image_meta['height'];
		$img_url_basename 	= wp_basename( $img_url );
		// try for a new style intermediate size
		if ($intermediate = image_get_intermediate_size($attachment_id, $size)) {
			$img_url        = str_replace( $img_url_basename, $intermediate['file'], $img_url );
			$width          = $intermediate['width'];
			$height         = $intermediate['height'];
		}
		// list($src, $width, $height) = $image;
		$src				= $img_url;
		$hwstring           = image_hwstring( $width, $height );
		$size_class         = $size;
		if (is_array($size_class)) {
			$size_class = join( 'x', $size_class );
		}
		$attachment   = get_post($attachment_id);
		$default_attr = array(
			'src' 		=> $src,
			'class' 	=> "attachment-$size_class size-$size_class",
			'alt' 		=> trim( strip_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ),
		);

		if ($lazy) {
			$def_attr = wp_parse_args($attr, $default_attr);

			$default_attr = array_merge($default_attr, array(
				'src'		=> $AWPWP_PLACEHOLDER_URL, // for validation 
				'data-src' 	=> $src,
			));
		}

		$attr = wp_parse_args($attr, $default_attr);

		// Generate <source>'s
		// Retrieve the uploads sub-directory from the full size image.
		$dirname = _wp_get_attachment_relative_path($image_meta['file']);
	
		if ($dirname) {
			$dirname = trailingslashit($dirname);
		}
	
		$upload_dir    = wp_get_upload_dir();
		$image_baseurl = trailingslashit($upload_dir['baseurl']) . $dirname;
	
		/*
		 * If currently on HTTPS, prefer HTTPS URLs when we know they're supported by the domain
		 * (which is to say, when they share the domain name of the current request).
		 */
		if ( is_ssl() && 'https' !== substr( $image_baseurl, 0, 5 ) && parse_url( $image_baseurl, PHP_URL_HOST ) === $_SERVER['HTTP_HOST'] ) {
			$image_baseurl = set_url_scheme( $image_baseurl, 'https' );
		}

		$size_array = array( absint($width), absint($height) );
		$sizes 		= wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id );
		if ($sizes) {
            $sizes_html = $lazy ? 'data-sizes="'.$sizes.'"' : 'sizes="'.$sizes.'"';
        }

		$html = '<picture>';

        $srcset = _awpwp_generate_image_srcset( $image_meta, $image_baseurl, true );
        if( $srcset ){
            $srcset_html = $lazy ? 'data-srcset="'.$srcset.'"' : 'srcset="'.$srcset.'"';
            $html .= '<source type="image/webp" '.$srcset_html.' '.$sizes_html.'>';
        }

		$srcset = _awpwp_generate_image_srcset($image_meta, $image_baseurl);
		if ($srcset) {
			$srcset_html = $lazy ? 'data-srcset="'.$srcset.'"' : 'srcset="'.$srcset.'"';
			$html .= '<source '.$srcset_html.' '.$sizes_html.'>';
		}

		$attr = array_map('esc_attr', $attr);
		$html .= rtrim("<img $hwstring");
		foreach ($attr as $name => $value) {
			$html .= " $name=" . '"' . $value . '"';
		}
		$html .= ' /></picture>';

		// <noscript> for JS-off clients 
		if ($lazy) {
			$def_attr = array_map('esc_attr', $def_attr);
			$html .= rtrim("<noscript><img $hwstring");
			foreach ($def_attr as $name => $value) {
				$html .= " $name=" . '"' . $value . '"';
			}
			$html .= ' /></noscript>';
		}
	} elseif ($placeholder) {
		$html = '<img src="'.$AWPWP_PLACEHOLDER_URL.'" alt="no image" />';
	}

	return $html;
}

function _awpwp_generate_image_srcset( $image_meta, $image_baseurl, $webp=false ){
	$srcset = '';
	$image_sizes = $webp ? $image_meta['sizes_webp'] : $image_meta['sizes'];

	if (!is_array($image_sizes)) {
		return $srcset;
	}

	$strings = array();
	foreach ($image_sizes as $image) {
		// Check if image meta isn't corrupted.
		if (!is_array($image)) {
			continue;
		}

		if (isset($image['file'])) {
			$file_width = $image['width'];
			// if( $file_width < 300 ) continue;

			$image_url 	= $image_baseurl . $image['file'];
			$string 	= $image_url.' '.$file_width.'w';
			array_push($strings, $string);
		}  
	}

    if (!$webp) {
        $image_basename = wp_basename( $image_meta['file'] );
        $image_url 	= $image_baseurl . $image_basename; // full_image_url
        $file_width = (int) $image_meta['width']; // full_image_width
        $string 	= $image_url.' '.$file_width.'w';
        array_push($strings, $string);
    }

	$srcset = implode(', ', $strings);

	return $srcset;
}
