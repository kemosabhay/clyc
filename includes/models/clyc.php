<?php
/**
 * очищает строку от лишних символов в начале и конце
 * используем при редактировании доменов
 * @param $str
 */
function trim_string($str) {
	$str = rtrim(rtrim($str), ",");
	$str = trim(trim($str), ",");
	return $str;
}
/**
 * дебажная функция для удобного вывода массива
 * @param $arr
 */
function pp($arr) {
	echo '<pre>';
		print_r($arr);
	echo '</pre>';
}

/**
 * Возвращает дескриптор таблицы настроек плагина
 */
function clyc_get_table() {
	global $wpdb;
	return $wpdb->prefix . 'clyc';
}

/**
 * получает массив настроек плагина
 * @return mixed массив полей
 */
function clyc_get_options(){
	global $wpdb;
	$table = clyc_get_table();
	$query = "SELECT * FROM $table WHERE id = '1'";
	$result = $wpdb->get_results($query, ARRAY_A);
	return $result[0];
}

/**
 * сохраняет настройки плагина
 * TODO добавить проверки данных
 * TODO добавить проверку данных YOURLS -- ПРИВЕСТИ В УДОБНЫЙ ВИМД
 * @param $post - массив $_POST
 * @return bool
 */
function clyc_save_options($post){
	// обрабатываем первоначальную настройку плагина
	$clyc_installed = get_option('clyc_installed');

	//if($clyc_installed == 0) {
		// елси получены YOURLS данные - пробуем сделать тестовое преобразование урла
		if ( ! empty($post['clyc_yourls_domain']) AND ! empty($post['clyc_yourls_token']) ){
			$data = clyc_send_yourls_curl($post['clyc_yourls_domain'], $post['clyc_yourls_token'], 'http://yandex.ru');
			// если получен ответ - сохраняем данные, меняем  свойство
			if ( ! empty($data->shorturl)) {
				update_option('clyc_installed', 1);
				$post['clyc_create_on_fly'] = 1;
			} else {
				return  '<span class="error">Incorrect YOURLS settings!</span>';
			}
		} else {
			return  '<span class="error">Incorrect YOURLS settings!</span>';
		}
	//}

	// сохраняем натсрйоки в БД
	global $wpdb;
	$table = clyc_get_table();
	// обрабатываем пустоту полей
	if (isset($post['clyc_create_on_fly']) AND ($post['clyc_create_on_fly'] == 'on' OR $post['clyc_create_on_fly'] == 1)){
		$post['clyc_create_on_fly'] =  1;
	} else {
		$post['clyc_create_on_fly'] =  0;
	}
	$post['clyc_domains'] = isset($post['clyc_domains']) ? trim_string($post['clyc_domains']) : NULL;

	$sql = "UPDATE $table SET clyc_yourls_domain='%s', clyc_yourls_token='%s', clyc_create_on_fly='%s', clyc_domains='%s' WHERE id = 1";
	$query = $wpdb->prepare($sql, $post['clyc_yourls_domain'], $post['clyc_yourls_token'], $post['clyc_create_on_fly'], $post['clyc_domains']);
	//pp($query);
	$result = $wpdb->query($query);

	if($result === false) {
		return '<span class="error">Settings saving error!</span>';
	}

	return '<span class="success">Settings successfully changed!</span>';
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
function clyc_shortyfy_text_links($text, $options, $onfly = FALSE) {
	//echo $text.'<br>';
	// паттерн для поиска ссылок
	$regex = '/<a ([\r\n\w+\W+].*?)>([\r\n\w+\W+].*?)<\/a>/';

	// анализируем переданный текст
	$replaced_text = preg_replace_callback(
			$regex,
			function($matches) use ($options, $onfly) {
				$domains = $options['clyc_domains'];
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
					//echo "preg match";
					//print_r($result);
					$url = '';
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
							//echo "<br>seek domain: $domain in $url result: $pos";
							$pos = strripos($url, trim($domain));
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
	return $replaced_text;
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
		die('Ошибка сохранения натстроек');
	}
	return TRUE;
}

/**
 * Получает список постов и страниц,
 * прогоняет их контент через преобразователь ссылок clyc_shortyfy_text_links()
 */
function clyc_analyse_contents() {
	// получаем настройки плагина
	$options = clyc_get_options();
	$options['clyc_domains'] = explode(',', $options['clyc_domains']);

	// прогоняем через преобразователь ссылок посты
	$post_list = get_posts();
	foreach ( $post_list as $post ) {
		$new_content = clyc_shortyfy_text_links($post->post_content, $options);
		clyc_save_post($post->ID, $new_content);
	}
	// прогоняем через преобразователь ссылок страницы
	$pages_list = get_pages();
	foreach ( $pages_list as $page ) {
		$new_content = clyc_shortyfy_text_links($page->post_content, $options);
		clyc_save_post($page->ID, $new_content);
	}
	return '<span class="success">Content links shortening done!</span>';
}

/**
 * Отправляет ссылку на преобрзование в YOURLS  и поставляет полученное значение в текст ссылки link
 * @param $url - урл
 * @param $link - содержащая урл ссылка
 * @param $options - настройки плагина
 * @return mixed
 */
function clyc_shortify_link($url, $link, $options) {
	$data = clyc_send_yourls_curl($options['clyc_yourls_domain'], $options['clyc_yourls_token'], $url);
	if ( ! empty($data->shorturl)) {
		$link = str_replace($url, $data->shorturl, $link);
	}
	return $link;
}

/**
 * Отправляет ссылку на преобрзование в YOURLS  по curl
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