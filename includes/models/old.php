<?php
/**
 * Created by IntelliJ IDEA.
 * User: keemo
 * Date: 14.07.16
 * Time: 19:25
 */

/**
 * находит в переданном тексте урлы, сравнивает их со списком доменов из опций плагина
 * если находит среди ссылок домены - преобразует урлы с помощью clyc_shortify_url()
 *
 * @param $text - анализируемый текст
 * @param $options - настройки плагина
 * @param $onfly - ключ, отемчающий происходит ли анализ при сохранении поста
 * @return mixed
 */
function clyc_shortyfy_text_urls($text, $options, $onfly = FALSE){
	$domains = is_array($options['clyc_domains']) ? $options['clyc_domains'] : explode(',', $options['clyc_domains']);

	$reg_exUrl = "/([\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/i";
	preg_match_all($reg_exUrl, $text, $matches);
	$links = array_unique($matches[0]);

	$clycable = array();

	foreach($links as $url){
		// елси преобразование происходит на лету, подчищаем ссылку от экранирования
		if ( $onfly ){
			$url = stripslashes ($url);
		}
		// checking founded urls on containing domains from options
		foreach ($domains as $domain) {
			$pos = strripos($url, trim($domain));
			if ($pos !== false) {
				// if url cntains domain - changing it
				$cleanUrl = $url;
				if (substr($url, -1) == '/'){
					$cleanUrl = substr($url,0, strlen($url)-1);
				}
				$cleanUrl = str_replace("www.", "", $cleanUrl);

				// getting new yourl
				$yourl = clyc_shortify_url($cleanUrl, $options);

				$clycable[] =  array(
				'url' => $url,
				'yourl' => $yourl
				);
			}
		}
	}
	foreach ($clycable as $pair){
		while (strripos($text, trim($pair['url']))) {
			$text = str_replace($pair['url'], $pair['yourl'], $text);
		}
	}
	return $text;
}


/**
 * находит в переданном тексте ссылки, сравнивает их со списком доменов из опций плагина
 * если находит среди ссылок домены - преобразует ссылки с помощью clyc_shortify_link()
 * TODO: проверить все ли возможные варианты ссылок парсятся
 *
 * @param $text - анализируемый текст
 * @param $options - настройки плагина
 * @param $onfly - ключ, отемчающий происходит ли анализ при сохранении поста
 * @return mixed
 */
function clyc_shortyfy_anchor_urls($text, $options, $onfly = FALSE) {
	//echo '<br>text before';echo $text.'<br>';
	// паттерн для поиска ссылок
	$regex = '/<a ([\r\n\w+\W+].*?)>([\r\n\w+\W+].*?)<\/a>/';

	// анализируем переданный текст
	$replaced_text = preg_replace_callback(
	$regex,
	function($matches) use ($options, $onfly) {
		$domains = is_array($options['clyc_domains']) ? $options['clyc_domains'] : explode(',', $options['clyc_domains']);
		//pp($domains);

		// получаем ссылку
		$link = $matches[0];
		//echo "<hr> analyze link: $link";

		if ($link != ''){
			// елси преобразование происходит на лету, подчищаем ссылку от экранирования
			if ( $onfly ){
				$link = stripslashes ($link);
			}

			// вытаскиваем из ссылки url - содержимое параметра href
			preg_match("/href=(\"|')[^\"\']+(\"|')/i", $link, $result);
			//echo "<br>preg match";
			//pp($result);
			if ( ! empty($result[0])){
				$url = str_replace("href='", "", $result[0]);
				$url = str_replace('href="', "", $url);
				$url = substr_replace($url, "", -1);
			}
			if( ! empty($url)){
				//echo "<br>got url: $url";
				//echo "<br>checking domains";

				// проверяем поученный урл на совпадение с доменами из настроек плагина
				foreach ($domains as $domain) {
					$pos = strripos($url, trim($domain));
					//echo "<br>seek domain: $domain in $url result: $pos";
					if ($pos !== false) {
						// елси урл совпал с доменом - преобразуем урл
						$link = clyc_shortify_link($url, $link, $options);
					}
				}
			}
		}
		return  $link;
	},
	$text
	);
	//echo '<br>text after';
	return $replaced_text;
}
/**
 * Получает список постов и страниц,
 * прогоняет их контент через преобразователь ссылок clyc_shortyfy_anchor_urls()
 */
function clyc_analyse_contents() {
	// получаем настройки плагина
	//$options = clyc_get_options();
	//$options['clyc_domains'] = explode(',', $options['clyc_domains']);
	//
	//// прогоняем через преобразователь ссылок посты
	//$post_list = get_posts();
	//foreach ( $post_list as $post ) {
	//	$new_content = clyc_shortyfy_anchor_urls($post->post_content, $options);
	//	clyc_save_post($post->ID, $new_content);
	//}
	//// прогоняем через преобразователь ссылок страницы
	//$pages_list = get_pages();
	//foreach ( $pages_list as $page ) {
	//	$new_content = clyc_shortyfy_anchor_urls($page->post_content, $options);
	//	clyc_save_post($page->ID, $new_content);
	//}
	//return '<span class="success">Content links shortening done!</span>';
	return;
}



/**
 * Отправляет ссылку на преобрзование в YOURLS  и поставляет полученное значение в текст ссылки link
 * @param $url - урл
 * @param $link - содержащая урл ссылка
 * @param $options - настройки плагина
 * @return mixed
 */
function clyc_shortify_link($url, $link, $options) {
	$yourl = $url;

	$cleanUrl = $url;
	if (substr($url, -1) == '/'){
		$cleanUrl = substr($url,0, strlen($url)-1);
	}
	$cleanUrl = str_replace("www.", "", $cleanUrl);

	//getting new yourl
	$data = clyc_send_yourls_curl($options['clyc_yourls_domain'], $options['clyc_yourls_token'], $cleanUrl);
	if ( ! empty($data->shorturl)) {
		$yourl = $data->shorturl;
	}

	if ($url != $yourl) {
		if ($options['clyc_shorten_link_types'] == 'hrefs') {
			// replase only href url
			$link = str_replace("href='".$url, "href='".$yourl, $link);
			$link = str_replace('href="'.$url, 'href="'.$yourl, $link);
			$link = str_replace("href=".$url, "href=".$yourl, $link);
		} else {
			//$options['clyc_shorten_link_types'] == 'aurls'
			$link = str_replace($url, $yourl, $link);
		}
	}
	return $link;
}

/**
 * getting new YOURL for url link
 * @param $url
 * @param $options
 * @return mixed
 */
function clyc_shortify_url($url, $options) {
	$data = clyc_send_yourls_curl($options['clyc_yourls_domain'], $options['clyc_yourls_token'], $url);
	$link = $url;
	if ( ! empty($data->shorturl)) {
		$link = $data->shorturl;
	}
	return $link;
}
/**
 * getting shortified YOURLS link by curl
 *
 * @param $yourls_domain
 * @param $yourls_token
 * @param $url
 * @return array|mixed|object
 */
function clyc_send_yourls_curl($yourls_domain, $yourls_token, $url) {
	$api_url =  $yourls_domain.'/yourls-api.php';

	// Init the CURL session
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_url);
	curl_setopt($ch, CURLOPT_HEADER, 0);            // No header in the result
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return, do not echo result
	curl_setopt($ch, CURLOPT_POST, 1);              // This is a POST request
	curl_setopt($ch, CURLOPT_POSTFIELDS, array(     // Data to POST
	'url' => $url,
	'format'   => 'json',
	'action'   => 'shorturl',
	'signature' => $yourls_token
	));

	// Fetch and return content
	$data = curl_exec($ch);
	curl_close($ch);

	// Do something with the result. Here, we echo the long URL
	$data = json_decode( $data );
	//pp($data);
	return $data;
}
/**
 * Сохраняет контент поста / страницы
 * TODO - не ли встроенной безопасной функции WP?
 * @param $post_id - ид строки
 * @param $post_content - контент  поста / страницы
 * @return bool
 */
function clyc_save_post($post_id, $post_content) {
	global $wpdb;
	$sql = "UPDATE wp_posts SET post_content='%s' WHERE id = '%s'";
	$query = $wpdb->prepare($sql, $post_content, $post_id);
	$result = $wpdb->query($query);
	if($result === false) {
		die('Ошибка сохранения настроек');
	}
	return TRUE;
}