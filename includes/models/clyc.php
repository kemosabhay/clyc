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
	//$clyc_installed = get_option('clyc_installed');
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

	// сохраняем настрйоки в БД
	global $wpdb;
	$table = clyc_get_table();
	// обрабатываем пустоту полей
	if (isset($post['clyc_create_on_fly']) AND ($post['clyc_create_on_fly'] == 'on' OR $post['clyc_create_on_fly'] == 1)){
		$post['clyc_create_on_fly'] =  1;
	} else {
		$post['clyc_create_on_fly'] =  0;
	}
	$post['clyc_domains'] = isset($post['clyc_domains']) ? trim_string($post['clyc_domains']) : NULL;
	$post['clyc_shorten_link_types'] = isset($post['clyc_shorten_link_types']) ? trim_string($post['clyc_shorten_link_types']) : 'all';

	$sql = "UPDATE $table SET clyc_yourls_domain='%s', clyc_yourls_token='%s', clyc_create_on_fly='%s', clyc_domains='%s', clyc_shorten_link_types='%s' WHERE id = 1";
	$query = $wpdb->prepare($sql, $post['clyc_yourls_domain'], $post['clyc_yourls_token'], $post['clyc_create_on_fly'], $post['clyc_domains'], $post['clyc_shorten_link_types']);
	//pp($query);
	$result = $wpdb->query($query);

	if($result === false) {
		return '<span class="error">Settings saving error!</span>';
	}

	return '<span class="success">Settings successfully changed!</span>';
}

/**
 * Getting anchors in text, based on domains list from options,
 * replacing urls with yourls
 * if clyc_shorten_link_types = 'all' - replace all of urls in text
 * clyc_shorten_link_types = 'hrefs' - replace only urls inside href-param
 * clyc_shorten_link_types = 'aurls' - replace urls in anchors: both inside href and text of anchor
 * TODO: DRY
 *
 * @param $text - text to update
 * @param $options - array of options
 * @param bool|FALSE $onfly - key for mark if we update just text or post text. TRUE - post text
 * @return mixed
 */
function clyc_shortyfy_urls($text, $options, $onfly = FALSE){
	//echo '<textarea style="height: 252px;" cols="200" rows="200">';
	//pp(stripslashes($text));
	//echo '</textarea>';
	// getting domains
	$domains = is_array($options['clyc_domains']) ? $options['clyc_domains'] : explode(',', $options['clyc_domains']);

	if ($options['clyc_shorten_link_types'] == 'all'){

		/** 1 step -- getting URLS from HREF attrs - symbols including  spaces **/
		// searching links
		//$reg_exUrl = "/([\w]+:\/\/[\w-?&;#\(\)\[\]~=\.\/\@]+[\w\/])/i";

		//if we are updating text onfly - clear framed slashes
		if ($onfly){
			$text = stripslashes ($text);
		}

		$reg_exUrl = '/href=(\'|\")((((https?|ftp|file):\/\/)|(www.))[-A-Z0-9 \(\)\[\]+&@#\/%?=~_|!:,.;]*[-A-Z0-9 +\(\)\[\]&@#\/%=~_|])(\'|\")/i';
		preg_match_all($reg_exUrl, $text, $matches);
		$links = array_unique($matches[0]);
		//var_dump($links);

		$i=0;
		$clycable = array(); // array container of urls and their yourls
		foreach($links as $url){
			// remove href frames
			$url = str_replace('href="', '', $url);
			$url = str_replace("href='", '', $url);
			$url = substr_replace($url, "", -1);

			// checking founded urls on containing domains from options
			foreach ($domains as $domain) {
				$pos = strripos($url, trim_string($domain));
				if ($pos !== false) {
					// if url contains domain - changing it
					$cleanUrl = clyc_clean_url($url);
					$clycable[] =  array(
							'url' => $url,
							'clean' => $cleanUrl,
							'yourl' => ''
					);
				}
			}
			//$link = str_replace($url, "%url-$i%", $link);
			$i++;
		}
		//var_dump($clycable);

		// getting yourls for our urls
		$shorten_urls = clyc_get_yourls($clycable, $options);
		//var_dump($shorten_urls);

		// replacing urls
		foreach ($shorten_urls as $pair){
			if( ! empty($pair['url']) AND ! empty($pair['yourl'])){
				while (strripos($text, trim_string($pair['url']))) {
					$text = str_replace($pair['url'], $pair['yourl'], $text);
				}
			}
		}
		//pp($text);
		//echo '<textarea style="height: 252px;" cols="200" rows="200">';
		//pp(htmlspecialchars($text));
		//echo '</textarea>';
		//echo '<hr>';

		/** 2 step -- getting othres URLS **/

		//$reg_exUrl = "/([\w]+:\/\/[\w-?&;#\(\)\[\]~=\.\/\@]+[\w\/])/i";
		$reg_exUrl2 = '/((((https?|ftp|file):\/\/)|(www.))[-A-Z0-9\(\)\[\]+&@#\/%?=~_|!:,.;]*[-A-Z0-9+\(\)\[\]&@#\/%=~_|])/i';

		preg_match_all($reg_exUrl2, $text, $matches2);
		$links2 = array_unique($matches2[0]);
		//echo '$links2:';
		//var_dump($links2);

		$i=0;
		$clycable2 = array(); // array container of urls and their yourls
		foreach($links2 as $url){
			// if we are updating text onfly - clear framed slashes
			if ($onfly){
				$url = stripslashes ($url);
			}
			// remove href frames
			//$url = str_replace('href="', '', $link);
			//$url = str_replace("href='", '', $url);
			//$url = substr_replace($url, "", -1);

			// checking founded urls on containing domains from options
			foreach ($domains as $domain) {
				$pos = strripos($url, trim_string($domain));
				if ($pos !== false) {
					// if url contains domain - changing it
					$cleanUrl = clyc_clean_url($url);
					$clycable2[] =  array(
							'url' => $url,
							'clean' => $cleanUrl,
							'yourl' => ''
					);
				}
			}
			//$link = str_replace($url, "%url-$i%", $link);
			$i++;
		}
		//echo '$clycable2:';
		//var_dump($clycable2);

		// getting yourls for our urls
		$shorten_urls2 = clyc_get_yourls($clycable2, $options);
		//var_dump($shorten_urls2);

		foreach ($shorten_urls2 as $pair){
			if( ! empty($pair['url']) AND ! empty($pair['yourl'])){
				while (strripos($text, trim_string($pair['url']))) {
					$text = str_replace($pair['url'], $pair['yourl'], $text);
				}
			}
		}

		//pp($text2);
		//echo '<textarea style="height: 252px;" cols="200" rows="200">';
		//pp(htmlspecialchars($text2));
		//echo '</textarea>';
	} else {
		$clycable = array(); // array container of urls and their yourls
		$new_links = array(); // array of final links

		// getting <a> links from text
		$reg_exUrl = "/<a ([\r\n\w+\W+].*?)>([\r\n\w+\W+].*?)<\/a>/i";
		preg_match_all($reg_exUrl, $text, $matches);
		$links = array_unique($matches[0]);

		// updating links
		foreach($links as $url){
			// if we are updating text onfly - clear framed slashes
			if ($onfly){
				$url = stripslashes ($url);
			}

			// getting from link text from href-param
			preg_match("/href=(\"|')[^\"\']+(\"|')/i", $url, $result);
			if ( ! empty($result[0])){
				$url = str_replace("href='", "", $result[0]);
				$url = str_replace('href="', "", $url);
				$url = substr_replace($url, "", -1);
			}

			// clearing url
			$cleanUrl = clyc_clean_url($url);
			$clycable[] =  array(
					'url' => $url,
					'clean' => $cleanUrl,
					'yourl' => ''
			);
		}
		// getting yourls for our urls
		$shorten_urls = clyc_get_yourls($clycable, $options);
		//var_dump($links);
		//var_dump($shorten_urls);

		// replacing inside links url to yourls
		foreach($links as $anchor){

			foreach ($shorten_urls as $pair){
				$pos = strripos(trim_string($anchor),$pair['url']);
				// if anchor contains url
				if ($pos !== false) {
					if ($options['clyc_shorten_link_types'] == 'hrefs') {
						// replace only href url
						if ($onfly){
							$anchor = stripslashes ($anchor);
						}

						$link = str_replace("href='".$pair['url'], "href='".$pair['yourl'], $anchor);
						$link = str_replace('href="'.$pair['url'], 'href="'.$pair['yourl'], $link);
						$link = str_replace("href=".$pair['url'], "href=".$pair['yourl'], $link);
					} else {
						// replace href and text of anchor
						$link = str_replace($pair['url'], $pair['yourl'], $anchor);
					}
					//getting array of new anchors
					$new_links[] = $link;
				}
			}
		}
		//var_dump($new_links);
		// replacing old anchors to new
		for($i=0; $i<count($new_links); $i++){
			$text = str_replace($links[$i], $new_links[$i], $text);
		}
	}
	return $text;
}

/**
 * Shorten array of links with YOURLS API
 * trying to send multiple links with bulkshortener
 * if not - shorten every link one by one
 *
 * TODO доработать под каждый вид замены ссылок
 * TODO привести в порядок оформление
 * @param $clycable - array of links
 * @param $options
 * @return mixed
 */

function clyc_get_yourls($clycable, $options) {
	// trying to send multiple links with bulkshortener
	//building url with params
	$urls = array();
	foreach($clycable as $row){
		$urls[] = $row['clean'];
	}
	$params = array(
			'action'   => 'bulkshortener',
			'signature' => $options['clyc_yourls_token'],
			'urls' => $urls
	);
	$url = $options['clyc_yourls_domain'].'/yourls-api.php' . '?' . http_build_query($params);
	//echo '$url:'.$url;
	// Init the CURL session
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);            // No header in the result
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return, do not echo result

	$data['content'] = curl_exec($ch);
	$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT); // Заголовки запроса получаем только так http://php.net/manual/ru/function.curl-getinfo.php
	$data['requestHeaders'] = $headers;
	$info = curl_getinfo($ch);
	$data['curlInfo'] = $info;
	$data['httpCode'] = $info['http_code'];
	curl_close($ch);

	// if bulkshortener installed on server and server back links - return them
	if ($data['httpCode'] != 400){
		//echo '<br> USE bulkshortener';
		$data = explode("\n", trim_string($data['content']));
		$i=0;
		foreach($data as $row){
			$clycable[$i]['yourl'] =  $row;
			$i++;
		}
	} else {
		//echo '<br> DO NOT USE bulkshortener';
		// if bulkshortener is not installed - shorten every link by one call
		foreach($clycable as &$row){
			$row['yourl'] =  clyc_shortify_url($row['clean'], $options);
		}
	}
	return $clycable;
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