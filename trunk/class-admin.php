<?php
$tt_thumbs = new tt_thumbs;
class tt_thumbs {
    function __construct() {
        add_action('admin_menu', array(
            'tt_thumbs',
            'admin_add_menu'
        ));
    }
    public static function admin_add_menu() {
        register_setting('tt_options_form', 'tt_options', array(
            'tt_thumbs',
            'options_validate'
        ));
        //		add_options_page('ThumbMaster', 'ThumbMaster', 8, 'tt_thumbs', array('tt_thumbs', 'options'));
        add_theme_page(__('ThumbMaster') , __('Thumbnail') , 8, 'tt_thumbs_page', array(
            'tt_thumbs',
            'options'
        ));
        add_settings_section('tt_main_section', 'ThumbMaster Settings', array(
            'tt_thumbs',
            'section_text'
        ) , 'tt_thumbs_page');
        add_settings_field('tt_resizer_field', 'Resize thumbnails', array(
            'tt_thumbs',
            'option_resizer'
        ) , 'tt_thumbs_page', 'tt_main_section');
        add_settings_field('tt_default_thumb_field', 'Default fallback thumbnail URL', array(
            'tt_thumbs',
            'option_default_thumb'
        ) , 'tt_thumbs_page', 'tt_main_section');
        add_settings_field('tt_youtube_field', 'Prefer Youtube thumbs', array(
            'tt_thumbs',
            'option_youtube'
        ) , 'tt_thumbs_page', 'tt_main_section');
        add_settings_field('tt_child_field', 'Check attached images', array(
            'tt_thumbs',
            'option_child'
        ) , 'tt_thumbs_page', 'tt_main_section');        
        add_settings_field('tt_ttupdate_field', 'Automatic <a href="http://www.binarymoon.co.uk/projects/timthumb/" target="_blank">Timthumb</a> update', array(
            'tt_thumbs',
            'option_ttupdate'
        ) , 'tt_thumbs_page', 'tt_main_section');        
    }
    public static function section_text() {
?>
<p>Generates properly formatted post thumbnails on-the-fly for plugins and themes. Fallback thumbnails, remote images, Youtube videos supported.</p>
<?
    }
    public static function option_resizer($args) {
        $options = tt_thumbs_main::$options;
?>
<input type="radio" name="tt_options[resizer]" value="1" <? echo $options[resizer] == 1 ? 'checked' : '' ?>><? _e('Resize and crop server side (<a href="http://www.binarymoon.co.uk/projects/timthumb/" target="_blank">Timthumb</a>)') ?><br>
<input type="radio" name="tt_options[resizer]" value="2" <? echo $options[resizer] == 2 ? 'checked' : '' ?>><? _e('Resize and crop client side (Javascript)') ?><br>
<input type="radio" name="tt_options[resizer]" value="0" <? echo $options[resizer] == 0 ? 'checked' : '' ?>><? _e('Resize only using width') ?><br>
<input type="radio" name="tt_options[resizer]" value="3" <? echo $options[resizer] == 3 ? 'checked' : '' ?>><? _e('Resize only using height') ?><br>
<input type="radio" name="tt_options[resizer]" value="4" <? echo $options[resizer] == 4 ? 'checked' : '' ?>><? _e('Resize only using both dimensions (distorted)') ?><br>
You may override this setting by adding "tt_resize" class to image attributes in your template to force <a href="http://www.binarymoon.co.uk/projects/timthumb/" target="_blank">Timthumb</a> method for maximum compatibility (in Javascript slideshows, etc.)
<?
    }
    public static function option_default_thumb() {
        $options = tt_thumbs_main::$options;
        $builtin_thumb = WP_CONTENT_URL . substr(dirname(__FILE__) , strlen(WP_CONTENT_DIR)). '/'.TT_DEFAULT_THUMB;
?>
<input type="text" id="default_thumb" name="tt_options[default_thumb]" value="<? echo $options[default_thumb] ?>" size="60"><br>
You can upload your own default fallback thumbnail image via <a href="/wp-admin/media-new.php">Media Uploader</a><br>
OR use builtin default fallback thumbnail image: <a href="#" onclick="document.getElementById('default_thumb').value='<? echo $builtin_thumb ?>';"><? echo $builtin_thumb ?></a><br>
OR leave this field blank if you do not want to use default fallback thumbnails<br>
<?
    }
    public static function option_youtube() {
        $options = tt_thumbs_main::$options;
?>
<input type="radio" name="tt_options[youtube]" value="1" <? echo $options[youtube] ? 'checked' : '' ?>><? _e('Yes') ?><br>
<input type="radio" name="tt_options[youtube]" value="0" <? echo $options[youtube] ? '' : 'checked' ?>><? _e('No') ?><br>
Select Yes if you prefer extracting Youtube thumbnails from embedded videos and links<br>
<?
    }
    public static function option_child() {
        $options = tt_thumbs_main::$options;
?>
<input type="radio" name="tt_options[child]" value="1" <? echo $options[child] ? 'checked' : '' ?>><? _e('Yes') ?><br>
<input type="radio" name="tt_options[child]" value="0" <? echo $options[child] ? '' : 'checked' ?>><? _e('No') ?><br>
Select Yes if attached (children) post images also should be checked for missing thumbnails (may result slow database queries on large sites)<br>
<?
    }
    public static function option_ttupdate() {
        $options = tt_thumbs_main::$options;
?>
<input type="radio" name="tt_options[ttupdate]" value="1" <? echo $options[ttupdate] ? 'checked' : '' ?>><? _e('Yes') ?> (recommended)<br>
<input type="radio" name="tt_options[ttupdate]" value="0" <? echo $options[ttupdate] ? '' : 'checked' ?>><? _e('No') ?><br>
<?
    }
    public static function options_validate($input) {
    	$input[tt_lastcheck]=tt_thumbs_main::$options[tt_lastcheck];
        return $input;
    }
    public static function options() {
?>
		<div class="wrap">
			<form method="post" action="options.php">
                <? settings_fields('tt_options_form'); ?>
                <? do_settings_sections('tt_thumbs_page'); ?>
				<input type="submit" name="submitter" value="<?php esc_attr_e('Save Changes') ?>" class="button-primary" />
			</form>
            <p><a href="http://wordpress.org/extend/plugins/thumbmaster/" target="_blank">ThumbMaster</a> version: <? echo tt_thumbs_main::version() ?> | <a href="http://www.binarymoon.co.uk/projects/timthumb/" target="_blank">Timthumb</a> version: <a href="http://code.google.com/p/timthumb/" target="_blank"><? echo tt_thumbs_main::check_timthumb_version() ?></a> last checked: <? echo gmdate('Y-m-d H:i', tt_thumbs_main::$options[tt_lastcheck] + get_option('gmt_offset') * 3600) ?></p>
            <p><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=UV2FDM29SNY6W" target="_blank"><img src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif"></a></p>
		</div>
<?
    }
}
