<?php

/*
Plugin Name: Content links YOURLS creator
Plugin URI:
Description: Generates YOURLS links from links in content. Allows to shrotify urls from texts, and urls from anchor tags. Allows shortening of multiple URLs with use <a href='https://github.com/tdakanalis/bulk_api_bulkshortener'>Bulk URL shortener</a> plugin
Version: 0.3
*/
include_once('includes/models/clyc.php');
define( 'CLYC_VERSION', '0.1' );
define( 'CLYC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Установка плагина
 */
function clyc_install(){
	global $wpdb;
	$table = clyc_get_table();

	// try to create new plugin table
	$sql = "CREATE TABLE IF NOT EXISTS $table (
	  `id` int(11) NOT NULL,
	  `clyc_yourls_domain` varchar(256) DEFAULT NULL,
	  `clyc_yourls_token` varchar(256) DEFAULT NULL,
	  `clyc_create_on_fly` tinyint(1) DEFAULT NULL,
	  `clyc_domains` text,
	  `clyc_shorten_link_types` varchar(54) DEFAULT 'all',
	  PRIMARY KEY (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	$result =  $wpdb->query($sql);
	if($result === false){
		die('Create error for '.$table);
	}

	// check if table not empty
	$query = "SELECT * FROM {$table}";
	$res = $wpdb->get_results($query, ARRAY_A);

	if(count($res) == 0){
		// if empty, FIRST ACTIVATION - add plugin settings data
		$sql = "INSERT INTO $table (id, clyc_yourls_domain, clyc_yourls_token, clyc_create_on_fly, clyc_domains) VALUES('%s', '%s', '%s', '%s', '%s')";
		$query = $wpdb->prepare($sql, 1, '', '', 1, NULL);
		$result = $wpdb->query($query);

		if($result === false){
			//echo '<br>'.$query.'<br>';
			die('Insert error for '.$table);
		}
		add_option('clyc_installed', '0');// means if YOURLS settings set
		add_option('clyc_instaslled', '0');// means if YOURLS settings set
		add_option('clyc_dir', get_clyc_dir());
	} else {
		//re-activation
		add_option('clyc_installed', '1');// means if YOURLS settings set
		add_option('clyc_dir', get_clyc_dir());
	}

	// UPDATING HACK
	// check if table have new fields
	$sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'wpworks' AND TABLE_NAME = 'wp_clyc'AND COLUMN_NAME = 'clyc_shorten_link_types';";
	$result =  $wpdb->query($sql);
	if($result != 1){
		$sql = "ALTER TABLE `wp_clyc` ADD `clyc_shorten_link_types` varchar(54) DEFAULT 'all';";
		$wpdb->query($sql);
	}
}

/**
 * Деактивация плагина
 */
function clyc_deactivate(){}

/**
 * Удаление плагина
 */
function clyc_uninstall(){
	global $wpdb;
	$table = clyc_get_table();

	$sql = "DROP TABLE IF EXISTS $table";
	$wpdb->query($sql);

	//$sql = "DROP TABLE IF EXISTS {$table}_urls";
	//$wpdb->query($sql);

	delete_option('clyc_dir');
	delete_option('clyc_installed');
}

register_activation_hook(__FILE__, 'clyc_install');
register_deactivation_hook(__FILE__, 'clyc_deactivate');
register_uninstall_hook(__FILE__, 'clyc_uninstall');


// подключаем обработку ссылок при сохранении контента на лету
add_filter('content_save_pre', 'clyc_pre_analyse_content');
	
function my_mce_buttons_2( $buttons ) {
	/**
	 * Add in a core button that's disabled by default
	 */

	//$buttons[] = 'superscript';
	//$buttons[] = 'subscript';
	return $buttons;
}
add_filter( 'mce_buttons_2', 'my_mce_buttons_2' );

/**
 * Register style sheet.
 */

// load css into the admin pages
function clyc_style() {
	//wp_enqueue_style( 'clyc-bootstrap', CLYC_URL.'assets/css/bootstrap.css');
	wp_enqueue_style( 'clyc-options-style', CLYC_URL.'assets/css/content-links-yourls-creator.css' );
	//wp_enqueue_script( 'clyc-jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js' );
	//wp_enqueue_script('jquery');
}
add_action( 'admin_enqueue_scripts', 'clyc_style');


/**
 * анализирует и преобразует ссылки контента перед его сохранением в БД
 * @param $content
 * @return mixed
 */
function clyc_pre_analyse_content($content){
	$options = clyc_get_options();
	// если задано в условиях - преобразуем ссылки
	if ($options['clyc_create_on_fly'] == 1) {
		$options['clyc_domains'] = explode(',', $options['clyc_domains']);
		return  clyc_shortyfy_urls($content, $options, TRUE);
	} else {
		return $content;
	}
}

/**
 * Добавляем в меню пункт настроек
 */
function clyc_admin_menu(){
	$clyc_dir = get_option('clyc_dir');
	if (empty($clyc_dir)){
		$clyc_dir =  get_clyc_dir();
		add_option('clyc_dir', $clyc_dir);
	}
	add_menu_page('clYc settings', 'clYc settings', 'manage_options', basename(__FILE__), 'clyc_editor', "/wp-content/plugins/{$clyc_dir}/assets/img/scissors.png"); // меню
}

/**
 * получает имя папки, где хранится плагин
 */
function get_clyc_dir(){
	return str_replace('/content-links-yourls-creator.php', '', plugin_basename( __FILE__ ));
}

/**
 * отображение страницы настроек
 */
function clyc_editor(){
	include_once("includes/edit.php");
}
add_action('admin_menu', 'clyc_admin_menu');