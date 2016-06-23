<?php
/*
Plugin Name: Content links YOURLS creator
Plugin URI:
Description: Generates YOURLS links from links in content.
Version: 0.1
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

	if( $wpdb->get_var("SHOW TABLES LIKE $table") != $table ){
		$sql = "CREATE TABLE IF NOT EXISTS `$table` (
					`id` int(11) NOT NULL,
					`clyc_yourls_domain` varchar(256) NULL,
					`clyc_yourls_token` varchar(256) NULL,
					`clyc_create_on_fly` tinyint(1) NULL,
					`clyc_domains` text NULL,
					PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$result =  $wpdb->query($sql);

		if($result === false){
			//echo '<br>'.$sql.'<br>';
			die('ошибка создания '.$table);
		}
		//TODO  подумать о более изящном хранении настроек
		//$sql = "INSERT INTO $table (id, clyc_yourls_domain, clyc_yourls_token, clyc_create_on_fly, clyc_domains) VALUES('%s', '%s', '%s', '%s', '%s')";
		//$query = $wpdb->prepare($sql, 1, 'http://yourls.test', '9ffa37b6569ffa37b656', 1, 'yandex.ru');
		//$result = $wpdb->query($query);
		//if($result === false){
		//	echo '<br>'.$query.'<br>';
		//	die('ошибка заполения '.$table);
		//}
		$sql = "INSERT INTO $table (id, clyc_yourls_domain, clyc_yourls_token, clyc_create_on_fly, clyc_domains) VALUES('%s', '%s', '%s', '%s', '%s')";
		$query = $wpdb->prepare($sql, 1, '', '', 1, NULL);
		$result = $wpdb->query($query);
		if($result === false){
			//echo '<br>'.$query.'<br>';
			die('ошибка заполения '.$table);
		}

	}
	// ключ для отметки что свойства YOURLS введены
	add_option('clyc_installed', '0');
	$clyc_dir =  get_clyc_dir();
	add_option('clyc_dir', $clyc_dir);
}

/**
 * Деактивацйия плагина
 */
function clyc_uninstall(){
	global $wpdb;
	$table = clyc_get_table();

	$sql = "DROP TABLE IF EXISTS $table";
	$wpdb->query($sql);
	delete_option('clyc_dir');
	delete_option('clyc_installed');
}

/**
 * Удаление плагина
 */
function clyc_delete(){
	global $wpdb;
	$table = clyc_get_table();

	$sql = "DROP TABLE IF EXISTS $table";
	$wpdb->query($sql);
	delete_option('clyc_dir');
	delete_option('clyc_installed');
}

register_activation_hook(__FILE__, 'clyc_install');
register_deactivation_hook(__FILE__, 'clyc_uninstall');
register_uninstall_hook(__FILE__, 'clyc_delete');


// подключаем обработку ссылок при сохранении контента на лету
add_filter('content_save_pre', 'clyc_pre_analyse_content');

/**
 * Register style sheet.
 */

// load css into the admin pages
function clyc_style() {
	//wp_enqueue_style( 'clyc-bootstrap', CLYC_URL.'assets/css/bootstrap.css');
	wp_enqueue_style( 'clyc-options-style', CLYC_URL.'assets/css/content-links-yourls-creator.css' );
	wp_enqueue_script( 'clyc-jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js' );

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
		return  clyc_shortyfy_text_links($content, $options, TRUE);
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