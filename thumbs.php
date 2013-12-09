<?
/*
Plugin Name: ThumbMaster
Version: 1.1.1
Plugin URI: http://wordpress.org/extend/plugins/thumbmaster/
Description: Generates properly formatted post thumbnails on-the-fly for plugins and themes. Fallback thumbnails, external images, Youtube videos supported.
Author: Nathan Schlesinger
Author URI: http://www.jewpi.com/
*/

if(!function_exists('rrmdir')) {function rrmdir($path) { return is_file($path) ? @unlink($path) : array_map('rrmdir',glob($path.'/*'))==@rmdir($path);}}

if(!class_exists('tt_thumbs_main')) {
class tt_thumbs_main {
    public static $options = '';
    function __construct() {
    	$default_options = array(
            'resizer' => 2,
            'default_thumb' => '',
            'youtube' => 0,
            'child' => 0,
            'ttupdate' => 1,
            'tt_lastcheck' => 0,
        );
        self::$options = array_merge($default_options,get_option('tt_options',$default_options));
        $config = dirname(__FILE__) . '/config.php';
        if (file_exists($config)) require_once ($config);
        if (!defined('TIMTHUMB_PATH')) define('TIMTHUMB_PATH', dirname(__FILE__) . '/t.php');
        define('TT_DEFAULT_THUMB','ttDefault.jpg');
        define('TT_TIMTHUMB', (substr(TIMTHUMB_PATH, 0, strlen(WP_CONTENT_DIR)) == WP_CONTENT_DIR) ? TIMTHUMB_PATH : dirname(__FILE__) . '/t.php');
        define('TT_TIMTHUMB_URL', WP_CONTENT_URL . substr(TT_TIMTHUMB, strlen(WP_CONTENT_DIR)));
        define('TT_TIMTHUMB_CACHE_DIR',WP_CONTENT_DIR.'/timthumb-cache');
        add_theme_support('post-thumbnails');
        if (strpos(dirname(__FILE__) , WP_PLUGIN_DIR) !== false) {//check if running as plugin or template addon
            register_activation_hook(__FILE__, array(
                'tt_thumbs_main',
                'activate'
            ));
            register_deactivation_hook(__FILE__, array(
                'tt_thumbs_main',
                'deactivate'
            ));
            register_uninstall_hook(__FILE__, array(
                'tt_thumbs_main',
                'uninstall'
            ));
        }
        if (is_admin()) require_once (dirname(__FILE__) . '/class-admin.php');
        else require_once (dirname(__FILE__) . '/class-functions.php');
    }
    public static function activate() {
        self::check_timthumb_version(true);
    }
    public static function deactivate() {
    }
    public static function uninstall() {
        delete_option('tt_options');
        if(is_dir(TT_TIMTHUMB_CACHE_DIR)) rrmdir(TT_TIMTHUMB_CACHE_DIR);
    }
    public static function version() {
       preg_match('/Version:\s*([^\n|\r]*)/i',file_get_contents(__FILE__),$match);
       return $match[1];
    }
    public static function check_timthumb_version($force = false) {
    	if(!file_exists(TT_TIMTHUMB) && TT_TIMTHUMB!=dirname(__FILE__) . '/t.php') copy(dirname(__FILE__) . '/t.php',TT_TIMTHUMB);
        if ($cont = @file_get_contents(TT_TIMTHUMB)) {
           preg_match("~define\s*\(\s*[\'|\"]VERSION[\'|\"],\s*[\'|\"]([^\'|\"]*)~", $cont, $match);
           if($match[1]) $ttversion = $match[1];
        }
        if (!$ttversion || (self::$options['ttupdate'] && (($force && self::$options['tt_lastcheck'] < time() - 3600) || self::$options['tt_lastcheck'] < time() - 24 * 3600))) { //check daily
            if ($cont = self::file_read('http://timthumb.googlecode.com/svn/trunk/timthumb.php')) {
                preg_match("~define\s*\(\s*[\'|\"]VERSION[\'|\"],\s*[\'|\"]([^\'|\"]*)~", $cont, $match);
                if ($match[1]) if (self::versioncheck($match[1],$ttversion)) { //higher version found
/*
                    $cont=strtr($cont,array(//patch
                       'if(NOT_FOUND_IMAGE && $this->is404()){' => 'if(NOT_FOUND_IMAGE && $this->is404()){ if($_GET["src"]!=NOT_FOUND_IMAGE) {$_GET["src"]=NOT_FOUND_IMAGE;return $this->start();}',
                       'if(ERROR_IMAGE){' => 'if(ERROR_IMAGE){ if($_GET["src"]!=ERROR_IMAGE) {$_GET["src"]=ERROR_IMAGE;return $this->start();}',
                    ));
*/
                    file_put_contents(TT_TIMTHUMB, $cont);
                    $ttversion = $match[1];
                }
           }
           self::$options['tt_lastcheck'] = time();
           update_option('tt_options', self::$options);
        }
        $ttconfig = dirname(TT_TIMTHUMB) . '/timthumb-config.php';
        $config = dirname(__FILE__) . '/config.php';
        if ($force || !file_exists($ttconfig) || (file_exists($config) && file_exists($ttconfig) && @filemtime($config) > @filemtime($ttconfig))) self::update_ttconfig();
        return $ttversion;
    }
    public static function versioncheck($latest,$current) {
       $latestar=explode('.',$latest);
       $currentar=explode('.',$current);
       for($i=0;$i<count($latestar);$i++) {
          if($latestar[$i]>$currentar[$i]) return true;
          elseif($latestar[$i]<$currentar[$i]) return false;
       }
    }
    public static function file_read($url,$timeout=10) {
       $parts=parse_url($url);
       if(!$fp = fsockopen($parts[host], $parts[port] ? $parts[port] : 80, $errno, $errstr, $timeout)) return;
       fclose($fp);
       if($handle = fopen($url, "rb")) {
          stream_set_timeout($handle, $timeout); 
          $contents = @stream_get_contents($handle);
          fclose($handle);
          return $contents;
       }
    }
    public static function update_ttconfig() {
    	if(file_exists(dirname(__FILE__).'/timthumb-config.inc')) rename(dirname(__FILE__).'/timthumb-config.inc',dirname(__FILE__).'/timthumb-config.inc.php');
        $out = "<" . "?php\n";
        $out.= "//DO NOT EDIT THIS FILE AS WILL BE AUTOMATICALLY OVERWRITTEN\n";
        $out.= "//you may override default timthumb options in timthumb-config.inc.php\n";
        $out.= "//ThumbMaster options may also be set in config.php\n";
//        $out.= "if(file_exists('".(dirname(__FILE__).'/timthumb-config.inc')."')) rename('".(dirname(__FILE__).'/timthumb-config.inc')."','".(dirname(__FILE__).'/timthumb-config.inc.php')."');\n";
        $out.= "if(file_exists('".(dirname(__FILE__).'/timthumb-config.inc.php')."')) require_once('".(dirname(__FILE__).'/timthumb-config.inc.php')."');\n";
        $out.= "if(!defined('ALLOW_ALL_EXTERNAL_SITES')) define ('ALLOW_ALL_EXTERNAL_SITES', true);\n";
        $out.= "if(!defined('MEMORY_LIMIT')) define ('MEMORY_LIMIT', '64M');\n";
        $out.= "if(!defined('NOT_FOUND_IMAGE')) define ('NOT_FOUND_IMAGE', '" . (dirname(__FILE__) . '/'.TT_DEFAULT_THUMB) . "');\n";
        $out.= "if(!defined('ERROR_IMAGE')) define ('ERROR_IMAGE', '" . (dirname(__FILE__) . '/'.TT_DEFAULT_THUMB) . "');\n";
//        $out.= "if(!defined('FILE_CACHE_DIRECTORY')) define ('FILE_CACHE_DIRECTORY', false);\n";
        $out.= "if(!defined('FILE_CACHE_DIRECTORY')) define ('FILE_CACHE_DIRECTORY', '".TT_TIMTHUMB_CACHE_DIR."');\n";
        file_put_contents(dirname(TT_TIMTHUMB) . '/timthumb-config.php', $out);
    }
} //class
$tt_thumbs_main = new tt_thumbs_main;
}