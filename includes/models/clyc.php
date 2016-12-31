<?php
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
	return ( ! empty($result[0])) ? $result[0] : FALSE;
}

/**
 * сохраняет настройки плагина
 * @param $post - массив $_POST
 * @return bool
 */
function clyc_save_options($post){
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

	// сохраняем настрйоки в БД
	global $wpdb;
	$table = clyc_get_table();
	// обрабатываем пустоту полей
	//if (isset($post['clyc_create_on_fly']) AND ($post['clyc_create_on_fly'] == 'on' OR $post['clyc_create_on_fly'] == 1)){
	//	$post['clyc_create_on_fly'] =  1;
	//} else {
	//	$post['clyc_create_on_fly'] =  0;
	//}
	//$post['clyc_shorten_link_types'] = isset($post['clyc_shorten_link_types']) ? trim_string($post['clyc_shorten_link_types']) : 'all';

	$post['clyc_domains'] = isset($post['clyc_domains']) ? trim_string($post['clyc_domains']) : NULL;

	$post['clyc_create_on_fly'] =  1;
	$post['clyc_shorten_link_types'] = 'all';

	$sql = "UPDATE $table SET clyc_yourls_domain='%s', clyc_yourls_token='%s', clyc_create_on_fly='%s', clyc_domains='%s', clyc_shorten_link_types='%s' WHERE id = 1";
	$query = $wpdb->prepare($sql, $post['clyc_yourls_domain'], $post['clyc_yourls_token'], $post['clyc_create_on_fly'], $post['clyc_domains'], $post['clyc_shorten_link_types']);
	//pp($query);
	$result = $wpdb->query($query);

	if ($result === false) {
		return '<span class="error">Settings saving error!</span>';
	}
	return '<span class="success">Settings successfully changed!</span>';
}

/**
 * Getting anchors in text, based on domains list from options,
 * replacing urls with yourls
 *
 * @param $text - text to update
 * @param $options - array of options
 * @param bool|FALSE $onfly - key for mark if we update just text or post text. TRUE - post text
 * @return mixed
 */
function clyc_shortyfy_urls($text, $options, $onfly = FALSE){
	// массив-контейнер для урлов, их hash, чистых форм и yourl-аналогов
	$elements = array();

	/**
	 * $elements[] =  array(
		'type' - тип элемента: url - урл, который нужно обработать, buff - буферный элемент, сокращению не подлежит
		'needY' - булев ключ, нужно ли получить для ссылки yourl - елси TRUE -  ссылка содержит домен из настроек, нужно сокращать
		'elem' - html или и bbcode - кусок, полученный регуляркой из текста
		'hash' - хэш на который мы заменили элемнет в тексте (потом по этим хэшам буду вставляться боратно либо yourls ссылки либо элементы без изменений)
		'clean' - чистая версия урла, без www и лишних слешей - оригинальный урл, котрый мы отдаём на обработку в yourls
		'yourl' - yourl-версия урла
	);*/

	// если работаем "на лету" очищаем заэкранированные символы
	if ($onfly){
		$text = stripslashes ($text);
	}

	/** #1 собираем в массив элементы которые не нужно менять (<img> and [img]). Меняем их в тексте на хэши */
	$images = array();
	//html-картинки
	preg_match_all('/<img[^>]+>/i',$text, $result);
	foreach($result[0] as $img) {
		$images[] = $img;
	}
	//bbcode-картинки
	preg_match_all("/\[img\](.+?)\[\/img\]/i",$text, $result);
	foreach($result[0] as $img) {
		$images[] = $img;
	}

	// меняем в тексте на хэши
	$i=0;
	foreach ($images as $img) {
		$hash = '%img_'.$i.'%';
		$text = str_replace($img, $hash, $text);

		// добавляю элементы в массив
		$elements[] =  array(
			'type'  => 'buff',
			'needY'  => FALSE,
			'elem'  => $img,
			'hash'  => $hash,
			'clean' => '',
			'yourl' => ''
		);
		$i++;
	}

	/** #2 собираем урлы из HREF атрибутов. Собираем их отдельно чтобы включить урлы с пробелами */
	$reg_exUrl = '/href=(\'|\")((((https?|ftp|file):\/\/)|(www.))[-A-Z0-9 \(\)\[\]+&@#\/%?=~_|!:,.;]*[-A-Z0-9 +\(\)\[\]&@#\/%=~_|])(\'|\")/i';
	preg_match_all($reg_exUrl, $text, $hrefs);

	$i=0;
	foreach($hrefs[0] as $url){
		// очищаем урлы от мусора
		$url = str_replace('href="', '', $url);
		$url = str_replace("href='", '', $url);
		$url = substr_replace($url, "", -1);
		$cleanUrl = clyc_clean_url($url);

		// меняем в тексте на хэши
		$hash = '%href_'.$i.'%';
		$text = str_replace($url, $hash, $text);

		// добавляем элементы в массив
		$elements[] =  array(
				'type'  => 'href',
				'needY'  => FALSE,
				'elem'  => $url,
				'hash'  => $hash,
				'clean' => $cleanUrl,
				'yourl' => ''
		);
		$i++;
	}

	/** #3 получаем и обрабатываем остальные урлы **/
	$reg_exUrl = '/(http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])/i';
	preg_match_all($reg_exUrl, $text, $links);
	$i=0;
	foreach($links[0] as $url){
		// if we are updating text onfly - clear framed slashes
		if ($onfly){
			$url = stripslashes ($url);
		}

		// очищаем урлы от мусора
		$cleanUrl = clyc_clean_url($url);

		// меняем в тексте на хэши
		$hash = '%url_'.$i.'%';
		$text = str_replace($url, $hash, $text);

		// добавляем элементы в массив
		$elements[] =  array(
				'type'  => 'url',
				'needY'  => FALSE,
				'elem'  => $url,
				'hash'  => $hash,
				'clean' => $cleanUrl,
				'yourl' => ''
		);
		$i++;
	}

	/** #4 последний проход **/
	$reg_exUrl = '/((((https?|ftp|file):\/\/)|(www.))[-A-Z0-9\(\)\[\]+&@#\/%?=~_|!:,.;]*[-A-Z0-9+\(\)\[\]&@#\/%=~_|])/i';
	preg_match_all($reg_exUrl, $text, $links);

	$i=0;
	foreach($links[0] as $url){
		// if we are updating text onfly - clear framed slashes
		if ($onfly){
			$url = stripslashes ($url);
		}
		// очищаем урлы от мусора
		$cleanUrl = clyc_clean_url($url);

		// меняем в тексте на хэши
		$hash = '%lurl_'.$i.'%';
		$text = str_replace($url, $hash, $text);

		// добавляем элементы в массив
		$elements[] =  array(
				'type'  => 'lurl',
				'needY'  => FALSE,
				'elem'  => $url,
				'hash'  => $hash,
				'clean' => $cleanUrl,
				'yourl' => ''
		);
		$i++;
	}

	// получаем список доменов для замены
	$domains = is_array($options['clyc_domains']) ? $options['clyc_domains'] : explode(',', $options['clyc_domains']);
	// отмечаем в массиве элементы, которые нуждаются в yourls-обработке
	foreach($elements as &$el){
		// обрабатываем только урлы
		if ($el['type']  != 'buff'){
			foreach ($domains as $domain) {
				//TODO при реальной обработке не определяются домену в ссылках
				$pos = strripos($el['elem'], trim_string(stripslashes($domain)));
				//echo '<br> $domain:'.$domain.' $el[elem]:'.$el['elem'].' pos'.$pos;
				if ($pos !== false) {
					$el['needY'] = TRUE;
				}
			}
		}
	}
	$elements = clyc_get_yourls($elements, $options);
	for($i=0; $i < count($elements); $i++){
		// елси элемент из списка преобразуемых - заменяем хэш на yourl
		if ($elements[$i]['needY'] AND $elements[$i]['yourl'] != '') {
			while (strripos($text, trim_string($elements[$i]['hash']))) {
				$text = str_replace($elements[$i]['hash'], $elements[$i]['yourl'], $text);
			}
		} else {
			// елси элемент не из списка преобразуемых - заменяем хэш на исходный код элемента
			while (strripos($text, trim_string($elements[$i]['hash']))) {
				$text = str_replace($elements[$i]['hash'], $elements[$i]['elem'], $text);
			}
		}
	}
	return $text;
}

/**
 * сокращает массив ссылок с помощью YOURLS API
 * пробует сократить массив ссылок через yourls-плагин bulkshortener
 * если нет - сокращает ссылки поодиночке
 *
 * @param $elements - составной массив оббрабатываемых ссылок
 * @param $options - настройки WP
 * @return $elements - массив с сокращёнными ссылками
 */

function clyc_get_yourls($elements, $options) {
	$urls = array();
	$tmpUrls = array();
	// формируем темповый массив урлов
	foreach ($elements as $el){
		if ($el['needY']){
			$tmpUrls[] = $el['clean'];
		}
	}
	$tmpUrls = array_unique($tmpUrls);
	foreach($tmpUrls as $tu){
		$urls[] = $tu;
	}

	// формируем параметры для отправки в bulkshortener
	$params = array(
			'action'   => 'bulkshortener',
			'signature' => $options['clyc_yourls_token'],
			'urls' => $urls
	);
	// фоормируем урл для отправки
	$url = $options['clyc_yourls_domain'].'/yourls-api.php' . '?' . http_build_query($params);
	//echo '$url '.$url;

	// Инициируем CURL
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// отправка
	$data['content'] = curl_exec($ch);
	$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT); // Заголовки запроса получаем только так http://php.net/manual/ru/function.curl-getinfo.php
	$data['requestHeaders'] = $headers;
	$info = curl_getinfo($ch);
	$data['curlInfo'] = $info;
	$data['httpCode'] = $info['http_code'];
	curl_close($ch);

	// если на сервере установлен bulkshortener
	// и мы получаем успешный ответ со строкой ссылорк - обрабатываем их.
	if ($data['httpCode'] != 400){
		$data = explode("\n", trim_string($data['content']));
		if (count($data) > 0 ) {
			// составляем массив пар  урлов и их yourl-аналогов
			$i=0; $pairs = array();
			foreach($data as $row){
				$pairs[$i] = array(
					'url' => (! empty($urls[$i])) ? $urls[$i] : '',
					'yourl' => $row
				);
				$i++;
			}
			// подставляем в $elements соответствующие урлам yourls-аналоги
			foreach ($elements as &$el){
				if ($el['needY']){
					foreach($pairs as $pair){
						if ($el['clean'] == $pair['url']){
							$el['yourl'] = $pair['yourl'];
						}
					}
				}
			}
		}
	} else {
		// обработка ссылко без bulkshortener
		foreach($elements as &$el) {
			if ($el['needY']) {
				$el['yourl'] = clyc_shortify_url($el['clean'], $options);
			}
		}
	}
	return $elements ;
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
 * Cleans url from semicolons, spaces and 'www'
 *
 * @param $url
 * @return mixed|string
 */
function clyc_clean_url($url) {
	$cleanUrl = $url;
	if (substr($url, -1) == '/'){
		$cleanUrl = substr($url,0, strlen($url)-1);
	}
	$cleanUrl = str_replace("www.", "", $cleanUrl);
	return $cleanUrl;
}

/**
 * trims string from semicolons and spaces
 * used in edit domains' list
 * @param $str
 * @return string
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