<?
$tt_thumbs = new tt_thumbs;
class tt_thumbs {
    function __construct() {
        add_action('after_setup_theme', array(
            'tt_thumbs',
            'init'
        ) , 100);
        add_action('admin_bar_menu', array(
            'tt_thumbs',
            'admin_bar_menu'
        ), 150);
    }
    function init() {
        define('TT_ATTACHMENT_ID', 99999999);
        define('TT_JS', dirname(__FILE__) . '/thumbs.js');
        define('TT_JS_URL', WP_CONTENT_URL . substr(TT_JS, strlen(WP_CONTENT_DIR)));
        $options = tt_thumbs_main::$options;
        define('TT_RESIZER', $options['resizer']);
        define('TT_YOUTUBE', $options['youtube']);
        define('TT_CHILD', $options['child']);
        define('TT_DEFAULT_THUMB_URL', $options['default_thumb']);
        if (TT_RESIZER == 2) add_action('wp_print_scripts', array(
            'tt_thumbs',
            'jsloader'
        ));
        add_filter("get_attached_file", array(
            'tt_thumbs',
            "get_attached_file"
        ) , 100, 2);
        add_filter("wp_get_attachment_url", array(
            'tt_thumbs',
            "wp_get_attachment_url"
        ) , 100, 2);
        add_filter("image_downsize", array(
            'tt_thumbs',
            "image_downsize"
        ) , 100, 3);
        add_filter("get_post_metadata", array(
            'tt_thumbs',
            "get_post_metadata"
        ) , 100, 4);
        add_filter("post_thumbnail_html", array(
            'tt_thumbs',
            "post_thumbnail_html"
        ) , 10, 5);
/*
       add_filter("wp_get_attachment_image_attributes", array(
            'tt_thumbs',
            "wp_get_attachment_image_attributes"
        ) , 1, 2);
*/      
        tt_thumbs_main::check_timthumb_version();
    }
    function jsloader() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('tt_thumbs', TT_JS_URL, false, substr(md5(filemtime(TT_JS)) , -5) , true);
    }
    function admin_bar_menu() {
        global $wp_admin_bar;

        if (current_user_can('edit_theme_options')) {
            $menu_items = array(
                array(
                    'id' => 'thumbmaster',
                    'parent' => 'site-name',
                    'title' => __('Thumbnail'),
                    'href' => admin_url('themes.php?page=tt_thumbs_page')
                ),
            );

            foreach ($menu_items as $menu_item) {
                $wp_admin_bar->add_menu($menu_item);
            }
        }
    }
    //start filters
    function get_attached_file($file, $attachment_id) {
        if ($attachment_id < TT_ATTACHMENT_ID) return $file;
        if ($pos = strpos($file, 'http://', 1)) $file = substr($file, $pos); //remote image
        return $file;
    }
    function wp_get_attachment_url($file, $attachment_id) {
        if ($attachment_id < TT_ATTACHMENT_ID) return $file;
        if ($pos = strpos($file, WP_CONTENT_DIR)) $file = WP_CONTENT_URL . substr($file, $pos + strlen(WP_CONTENT_DIR)); //locally cached remote image
        else {
            $f = get_attached_file($attachment_id);
            if (substr($f, 0, 7) == 'http://') $file = $f; //remote image
            
        }
        return $file;
    }
    function image_downsize($dummy, $attachment_id, $size) {
        if (!$img_url = wp_get_attachment_url($attachment_id)) return false;
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!$size || $size == 'full') return array(
            $img_url,
            $meta['width'],
            $meta['height'],
            false
        );
        list($size, $width, $height) = self::get_size($size);
        if ($width == $meta['width'] && $height == $meta['height']) return array(
            $img_url,
            $meta['width'],
            $meta['height'],
            false
        );
        if ($sizes = $meta['sizes']) {
            if ($dims = $sizes[$size]) if ($dims[file]) return array(
                str_replace(wp_basename($img_url) , wp_basename($dims[file]) , $img_url) ,
                $dims[width],
                $dims[height],
                false
            );
            foreach ($sizes as $s => $dims) if ($dims[file]) if ($width == $dims[width] && $height == $dims[height]) return array(
                str_replace(wp_basename($img_url) , wp_basename($dims[file]) , $img_url) ,
                $dims[width],
                $dims[height],
                false
            );
        }
        if (TT_RESIZER == 1) $img_url = self::thumburl($img_url, $width, $height);
        return array(
            $img_url,
            $width,
            $height,
            false
        );
    }
    function get_post_metadata($dummy, $object_id, $meta_key, $single) {
        if (!$single || $meta_key != '_thumbnail_id') return;
        global $wp_object_cache;
        if (!$meta_cache = wp_cache_get($object_id, 'post_meta')) {
            $meta_cache = update_meta_cache('post', array(
                $object_id
            ));
            $meta_cache = $meta_cache[$object_id];
        }
        if (isset($meta_cache[$meta_key])) return maybe_unserialize($meta_cache[$meta_key][0]); //use existing thumbnail
        if (TT_CHILD) if($attachment_id=self::get_first_child($object_id)) return self::set_post_thumbnail($object_id, $meta_cache, $attachment_id );//use first attached image LONG DB QUERY
        if (!$src = self::get_the_post_thumbnail_src($object_id)) if (!$src = TT_DEFAULT_THUMB_URL) return self::set_post_thumbnail($object_id, $meta_cache, 0); //no image at all: trick metacache w/null thumbnail to prevent further timewasting
        //found first embedded image - the magic happens: inject as virtual attachment into the cache
        $attachment_id = TT_ATTACHMENT_ID + $object_id;//create a fake id
        self::set_post_thumbnail($object_id, $meta_cache, $attachment_id);
        if (substr(strtolower($src) , 0, 7) == 'http://') { //remote image
            $width=$height=NULL;
            $mimetype = 'image/';
            if (in_array(strtolower(substr($src, -4)) , array(
                '.gif',
                '.bmp',
                '.png'
            ))) $mimetype.= strtolower(substr($src, -3));
            else $mimetype.= 'jpeg';
        } else { //local image
            list($width, $height, $type) = getimagesize($src);
            $mimetype = image_type_to_mime_type($type);
        }
        $post = new stdClass; //create virtual attachment
        $post->ID = $attachment_id;
        $post->post_author = 1;
        $post->post_date = date('Y-m-d H:i:s');
        $post->post_date_gmt = gmdate('Y-m-d H:i:s');
        $post->post_password = '';
        $post->post_type = 'attachment';
        $post->post_status = 'published';
        $post->to_ping = '';
        $post->pinged = '';
        $post->comment_status = '';
        $post->ping_status = '';
        $post->post_pingback = '';
        $post->post_category = 1;
        $post->page_template = 'default';
        $post->post_parent = 0;
        $post->menu_order = 0;
        $post->post_content = '';
        $post->post_title = basename($src);
        $post->post_excerpt = '';
        $post->post_name = '';
        $post->post_mime_type = $mimetype;
        $post = sanitize_post($post, 'raw');
        $wp_object_cache->set($attachment_id, $post, 'posts'); //force update post cache
        $meta = array(
            '_wp_attached_file' => array(
                $src
            ) ,
            '_wp_attachment_metadata' => array(
                serialize(array(
                    'width' => $width,
                    'height' => $height,
                    'file' => $src,
                ))
            )
        );
        $wp_object_cache->set($attachment_id, $meta, 'post_meta'); //force update meta cache
        update_meta_cache('post', array(
            $attachment_id
        ));
        return $attachment_id;
    }
    function post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
        list($size, $width, $height) = self::get_size($size);
        if(!is_array($attr)) $attr=array();
        if (strpos($attr['class'], 'tt_resize') !== false) { //"tt_resize" class
            //      $html=preg_replace( '/(width|height)=\"\d*\"\s/i', "", $html );//remove dimension attributes
            if (TT_RESIZER != 1) { //force timthumb
                preg_match('/<img[\s]+[^>]*src\s*=\s*[\"\']?([^\'\">]+)[\'\">]/i', $html, $m);
                $html = str_replace($m[1], self::thumburl($m[1], $width, $height) , $html); //change src to timthumb
                
            }
        } elseif (TT_RESIZER == 0) { //no resizer - keep width
            $html = preg_replace('/height=\"\d*\"\s/i', "", $html); //remove height attribute
            
        } elseif (TT_RESIZER == 3) { //no resizer - keep height
            $html = preg_replace('/width=\"\d*\"\s/i', "", $html); //remove width attribute
            
        } elseif (TT_RESIZER == 4) { //no resizer - keep both and distort
            //      $html=preg_replace( '/(width|height)=\"\d*\"\s/i', "", $html );//remove dimension attributes
            
        } elseif (TT_RESIZER == 1) { //timthumb
            //      $html=preg_replace( '/(width|height)=\"\d*\"\s/i', "", $html );//remove dimension attributes
            
        } elseif (TT_RESIZER == 2) { //javascript
            //      $html=preg_replace( '/(width|height)=\"\d*\"\s/i', "", $html );//remove dimension attributes
            $html = str_replace('class="', 'class="tt_thumb-' . $width . 'x' . $height . ' ', $html); //add specific class
            
        }
        return $html;
    }
    /*
    function wp_get_attachment_image_attributes( $attr, $attachment ) {
    return $attr;
    }
    */
    //end filters
    function thumburl($img_url, $width, $height) {
        $siteurl = get_option('siteurl');
        if (substr($img_url, 0, strlen($_SERVER['HTTP_HOST']) + 7) == 'http://' . $_SERVER['HTTP_HOST']) $img_url = self::mu_path(substr($img_url, strlen($_SERVER['HTTP_HOST']) + 7));
        elseif (substr($img_url, 0, strlen($siteurl)) == $siteurl) $img_url = self::mu_path(substr($img_url, strlen($siteurl)));
        if(substr($img_url, 0, 7) != 'http://') {
           $img_path=substr($img_url,0,strrpos($img_url,'.')).'-'.$width.'x'.$height.substr($img_url,strrpos($img_url,'.'));
           if(file_exists(ABSPATH.$img_path)) return self::path2url($img_path);
           if($pos=strrpos($img_url,'-')) {
              $img_path=substr($img_url,0,$pos).'-'.$width.'x'.$height.substr($img_url,strrpos($img_url,'.'));
              if(file_exists(ABSPATH.$img_path)) return self::path2url($img_path);
           }
        }
        $img_url = TT_TIMTHUMB_URL . '?src=' . (substr($img_url, 0, 7) == 'http://' ? urlencode($img_url) : $img_url) . '&amp;w=' . $width . '&amp;h=' . $height . '&amp;zc=1';
        return $img_url;
    }
    function get_first_child($post=null) {
        if(is_numeric($post)) $post=(object)array('ID'=>$post);
        elseif(!is_object($post)) $post=$GLOBALS['post'];
        $args = array(
          'numberposts' => 1,
          'order'=> 'ASC',
          'post_mime_type' => 'image',
          'post_parent' => $post->ID,
          'post_status' => null,
          'post_type' => 'attachment'
        );
        list($attachment_id,$attachment) = each(get_children( $args ));
        return $attachment_id;
    }
    function set_post_thumbnail($object_id, $meta_cache, $attachment_id) {
        global $wp_object_cache;
        $meta_cache['_thumbnail_id'] = array(
            $attachment_id
        );
        $wp_object_cache->set($object_id, $meta_cache, 'post_meta'); //force update meta cache
        update_meta_cache('post', array(
            $object_id
        ));
        return $attachment_id;
    }
    function get_the_post_thumbnail_src($post = null) {
        if (is_numeric($post)) $post = get_post($post);
        elseif (!is_object($post)) $post = $GLOBALS['post'];
        $siteurl = get_option('siteurl');
        foreach (self::getimage($post->post_content) as $src) {
            if (substr(strtolower($src) , 0, strlen('http://' . $_SERVER['HTTP_HOST'])) == strtolower('http://' . $_SERVER['HTTP_HOST'])) $src = substr($src, strlen('http://' . $_SERVER['HTTP_HOST']));
            elseif (substr($src, 0, strlen($siteurl)) == $siteurl) $src = substr($src, strlen($siteurl));
            if (substr(strtolower($src) , 0, 8) == 'https://') {
                continue;
            } elseif (substr(strtolower($src) , 0, 7) == 'http://') { //remote image
                return $src;
            } else $src = ABSPATH . substr(self::mu_path($src) , 1); //local image
            if (file_exists($src)) list($width, $height, $type) = getimagesize($src);
            else continue;
            if (substr(image_type_to_mime_type($type) , 0, 6) != 'image/') continue;
            if ($width && $height) if ($width >= 80 && $height <= 3 * $width && $width <= 3 * $height) return $src;
        }
    }
    function mu_path($src) {
       static $paths;
       if(!isset($paths)) $paths=wp_upload_dir();
       $parts = parse_url($paths['baseurl']);
       if ($src[0] != '/') $src = '/' . $src;
       if(substr($src,0,strlen($parts['path']))==$parts['path']) $src = substr($paths['basedir'].substr($src,strlen($parts['path'])), strlen(ABSPATH) - 1);
       return $src;
    }
    function path2url($src) {
       static $paths;
       if(!isset($paths)) $paths=wp_upload_dir();
       if ($src[0] != '/') $src = '/' . $src;
       $basepath=substr($paths['basedir'],strlen(ABSPATH)-1);
       if(substr($src,0,strlen($basepath))==$basepath) $src = $paths['baseurl'].substr($src,strlen($basepath));
       else $src=get_option('siteurl').$src;
       return $src;
    }
    function get_size($size = 'thumbnail') {
        global $_wp_additional_image_sizes;
        if(is_string($size)) if(substr($size,0,5)=='post-') $size=substr($size,5);
        if(!$size) $size = 'thumbnail';
        if (is_array($size)) {
            list($width, $height) = $size;
            $size = join('x', $size);
        } elseif ($dims = $_wp_additional_image_sizes[$size]) {
            $width = $dims['width'];
            $height = $dims['height'];
            $crop = $dims['crop'];
        } elseif (($w = intval(get_option($size . '_size_w'))) && ($h = intval(get_option($size . '_size_h')))) {
            $width = $w;
            $height = $h;
        } else {
            $parts = explode('x', $size);
            if (count($parts) == 2) if (is_numeric($parts[0]) && is_numeric($parts[1])) list($width, $height) = $parts;
        }
        if ($height == 9999) $height = $width;
        return array(
            $size,
            $width,
            $height
        );
    }
    function getimage($html) {
        if (TT_YOUTUBE) $images = self::get_youtube($html);
        else $images = array();
        preg_match_all('/<img[\s]+[^>]*src\s*=\s*[\"\']?([^\'\">]+)[\'\">]/i', $html, $m);
        $siteurl = get_option('siteurl');
        foreach ($m[1] as $image) {
            $image = eregi_replace('https://', 'http://', $image);
            if (substr(strtolower($image) , 0, 7) != 'http://') $image = $siteurl . ($image[0] == '/' ? '' : '/') . $image;
            if (!self::checkimage($image)) continue;
            $image = str_replace('"', '', $image);
            $image = str_replace("'", '', $image);
            $image = trim($image);
            $image = str_replace(' ', '%20', $image);
            if (!in_array($image, $images)) $images[] = $image;
        }
        return $images;
    }
    function checkimage($image) {
        if (ereg('slideshare', $image) || ereg('youtube', $image)) return true;
        if (strpos($image, 'pagesinxt')) return false;
        if (strpos($image, '/ads/')) return false;
        if (eregi('tracker', $image)) return false;
        if (eregi('click.inn.co.il', $image)) return false;
        if (eregi('doubleclick', $image)) return false;
        if (eregi('pheedo', $image)) return false;
        if (eregi('spacer', $image)) return false;
        if (eregi('advert', $image)) return false;
        if (eregi('imageads', $image)) return false;
        if (eregi('player', $image)) return false;
        if (eregi('share', $image)) return false;
        if (eregi('feed', $image)) return false;
        if (eregi('icon', $image)) return false;
        if (eregi('plugins', $image)) return false;
        if (eregi('stats', $image)) return false;
        if (eregi('tweetmeme', $image)) return false;
        if (eregi('feedburner', $image)) return false;
        if (eregi('paidcontent', $image)) return false;
        if (eregi('twitter', $image)) return false;
        if (eregi('phpAds', $image)) return false;
        if (eregi('digg', $image)) return false;
        if (eregi('button', $image)) return false;
        if (eregi('gomb', $image)) return false;
        if (eregi('avatar', $image)) return false;
//        if (eregi('logo', $image)) return false;
        if (eregi('adview', $image)) return false;
        if (eregi('kapjot', $image)) return false;
        return true;
    }
    function get_youtube($html) {
        $images = array();
        if($html) {
           $html = rawurldecode($html);
//           foreach (self::match_youtube('/youtu\.be\/([^\/\?&#%"\'<> ]*)/i', $html) as $img) if (!in_array($img, $images)) $images[] = $img;
//           foreach (self::match_youtube('/youtube\.com\/watch\?v=([^&#%"\'<> ]*)/i', $html) as $img) if (!in_array($img, $images)) $images[] = $img;
           foreach (self::match_youtube('/(youtube\.com\/watch(.*)?[\?\&]v=|youtu\.be\/)([a-zA-Z0-9-_]+)/i', $html,3) as $img) if (!in_array($img, $images)) $images[] = $img;
//           foreach (self::match_youtube('/(youtube|ytimg)\.com\/(e|v|embed|vi)\/([^\/\?&#%"\'<> ]*)/i', $html, 3) as $img) if (!in_array($img, $images)) $images[] = $img;
           foreach (self::match_youtube('/(youtube|ytimg)\.com\/(e|v|embed|vi)\/([a-zA-Z0-9-_]+)/i', $html, 3) as $img) if (!in_array($img, $images)) $images[] = $img;
        }
        return $images;
    }
    function match_youtube($regex, $html, $i = 1) {
        $images = array();
        @preg_match_all($regex, $html, $matches, PREG_SET_ORDER);
        if (count($matches)) {
            foreach ($matches as $match) {
                if (!$id = $match[$i]) continue;
                if ($pos = strpos($id, 'endofvid')) $id = substr($id, 0, $pos);
                if ($id) $images[] = "http://img.youtube.com/vi/" . $id . "/0.jpg";
            }
        }
        return $images;
    }
} //class
