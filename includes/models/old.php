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

//function my_mce_buttons_2( $buttons ) {
//	/**
//	 * Add in a core button that's disabled by default
//	 */
//
//	//$buttons[] = 'superscript';
//	//$buttons[] = 'subscript';
//	return $buttons;
//}
//add_filter( 'mce_buttons_2', 'my_mce_buttons_2' );

//function clyc_shortyfy_urls($text, $options, $onfly = FALSE){
//	//echo '<textarea style="height: 252px;" cols="200" rows="200">';
//	//pp(stripslashes($text));
//	//echo '</textarea>';
//	// getting domains
//	$domains = is_array($options['clyc_domains']) ? $options['clyc_domains'] : explode(',', $options['clyc_domains']);
//
//	if ($options['clyc_shorten_link_types'] == 'all'){
//
//		/** 1 step -- getting URLS from HREF attrs - symbols including  spaces **/
//		// searching links
//		//$reg_exUrl = "/([\w]+:\/\/[\w-?&;#\(\)\[\]~=\.\/\@]+[\w\/])/i";
//
//		//if we are updating text onfly - clear framed slashes
//		if ($onfly){
//			$text = stripslashes ($text);
//		}
//
//		$reg_exUrl = '/href=(\'|\")((((https?|ftp|file):\/\/)|(www.))[-A-Z0-9 \(\)\[\]+&@#\/%?=~_|!:,.;]*[-A-Z0-9 +\(\)\[\]&@#\/%=~_|])(\'|\")/i';
//		preg_match_all($reg_exUrl, $text, $matches);
//		$links = array_unique($matches[0]);
//		//var_dump($links);
//
//		$i=0;
//		$clycable = array(); // array container of urls and their yourls
//		foreach($links as $url){
//			// remove href frames
//			$url = str_replace('href="', '', $url);
//			$url = str_replace("href='", '', $url);
//			$url = substr_replace($url, "", -1);
//
//			// checking founded urls on containing domains from options
//			foreach ($domains as $domain) {
//				$pos = strripos($url, trim_string($domain));
//				if ($pos !== false) {
//					// if url contains domain - changing it
//					$cleanUrl = clyc_clean_url($url);
//					$clycable[] =  array(
//							'url' => $url,
//							'clean' => $cleanUrl,
//							'yourl' => ''
//					);
//				}
//			}
//			//$link = str_replace($url, "%url-$i%", $link);
//			$i++;
//		}
//		//var_dump($clycable);
//
//		// getting yourls for our urls
//		$shorten_urls = clyc_get_yourls($clycable, $options);
//		//var_dump($shorten_urls);
//
//		// replacing urls
//		foreach ($shorten_urls as $pair){
//			if( ! empty($pair['url']) AND ! empty($pair['yourl'])){
//				while (strripos($text, trim_string($pair['url']))) {
//					$text = str_replace($pair['url'], $pair['yourl'], $text);
//				}
//			}
//		}
//		//pp($text);
//		//echo '<textarea style="height: 252px;" cols="200" rows="200">';
//		//pp(htmlspecialchars($text));
//		//echo '</textarea>';
//		//echo '<hr>';
//
//		/** 2 step -- getting othres URLS **/
//
//		//$reg_exUrl = "/([\w]+:\/\/[\w-?&;#\(\)\[\]~=\.\/\@]+[\w\/])/i";
//		$reg_exUrl2 = '/((((https?|ftp|file):\/\/)|(www.))[-A-Z0-9\(\)\[\]+&@#\/%?=~_|!:,.;]*[-A-Z0-9+\(\)\[\]&@#\/%=~_|])/i';
//
//		preg_match_all($reg_exUrl2, $text, $matches2);
//		$links2 = array_unique($matches2[0]);
//		//echo '$links2:';
//		//var_dump($links2);
//
//		$i=0;
//		$clycable2 = array(); // array container of urls and their yourls
//		foreach($links2 as $url){
//			// if we are updating text onfly - clear framed slashes
//			if ($onfly){
//				$url = stripslashes ($url);
//			}
//			// remove href frames
//			//$url = str_replace('href="', '', $link);
//			//$url = str_replace("href='", '', $url);
//			//$url = substr_replace($url, "", -1);
//
//			// checking founded urls on containing domains from options
//			foreach ($domains as $domain) {
//				$pos = strripos($url, trim_string($domain));
//				if ($pos !== false) {
//					// if url contains domain - changing it
//					$cleanUrl = clyc_clean_url($url);
//					$clycable2[] =  array(
//							'url' => $url,
//							'clean' => $cleanUrl,
//							'yourl' => ''
//					);
//				}
//			}
//			//$link = str_replace($url, "%url-$i%", $link);
//			$i++;
//		}
//		//echo '$clycable2:';
//		//var_dump($clycable2);
//
//		// getting yourls for our urls
//		$shorten_urls2 = clyc_get_yourls($clycable2, $options);
//		//var_dump($shorten_urls2);
//
//		foreach ($shorten_urls2 as $pair){
//			if( ! empty($pair['url']) AND ! empty($pair['yourl'])){
//				while (strripos($text, trim_string($pair['url']))) {
//					$text = str_replace($pair['url'], $pair['yourl'], $text);
//				}
//			}
//		}
//
//		//pp($text2);
//		//echo '<textarea style="height: 252px;" cols="200" rows="200">';
//		//pp(htmlspecialchars($text2));
//		//echo '</textarea>';
//	} else {
//		$clycable = array(); // array container of urls and their yourls
//		$new_links = array(); // array of final links
//
//		// getting <a> links from text
//		$reg_exUrl = "/<a ([\r\n\w+\W+].*?)>([\r\n\w+\W+].*?)<\/a>/i";
//		preg_match_all($reg_exUrl, $text, $matches);
//		$links = array_unique($matches[0]);
//
//		// updating links
//		foreach($links as $url){
//			// if we are updating text onfly - clear framed slashes
//			if ($onfly){
//				$url = stripslashes ($url);
//			}
//
//			// getting from link text from href-param
//			preg_match("/href=(\"|')[^\"\']+(\"|')/i", $url, $result);
//			if ( ! empty($result[0])){
//				$url = str_replace("href='", "", $result[0]);
//				$url = str_replace('href="', "", $url);
//				$url = substr_replace($url, "", -1);
//			}
//
//			// clearing url
//			$cleanUrl = clyc_clean_url($url);
//			$clycable[] =  array(
//					'url' => $url,
//					'clean' => $cleanUrl,
//					'yourl' => ''
//			);
//		}
//		// getting yourls for our urls
//		$shorten_urls = clyc_get_yourls($clycable, $options);
//		//var_dump($links);
//		//var_dump($shorten_urls);
//
//		// replacing inside links url to yourls
//		foreach($links as $anchor){
//
//			foreach ($shorten_urls as $pair){
//				$pos = strripos(trim_string($anchor),$pair['url']);
//				// if anchor contains url
//				if ($pos !== false) {
//					if ($options['clyc_shorten_link_types'] == 'hrefs') {
//						// replace only href url
//						if ($onfly){
//							$anchor = stripslashes ($anchor);
//						}
//
//						$link = str_replace("href='".$pair['url'], "href='".$pair['yourl'], $anchor);
//						$link = str_replace('href="'.$pair['url'], 'href="'.$pair['yourl'], $link);
//						$link = str_replace("href=".$pair['url'], "href=".$pair['yourl'], $link);
//					} else {
//						// replace href and text of anchor
//						$link = str_replace($pair['url'], $pair['yourl'], $anchor);
//					}
//					//getting array of new anchors
//					$new_links[] = $link;
//				}
//			}
//		}
//		//var_dump($new_links);
//		// replacing old anchors to new
//		for($i=0; $i<count($new_links); $i++){
//			$text = str_replace($links[$i], $new_links[$i], $text);
//		}
//	}
//	return $text;
//}
/**TODO:
 * ссылки внутри href d <a>
 * ссылки в тексте , какс протоколом так и просто упоминания из списка
 * bbcode-ссылки [url=https://ru.wikipedia.org]wikipedia[/url]
 * bbcode-ссылки [url]https://ru.wikipedia.org[/url]
 */
//echo '<textarea style="height: 252px;" cols="200" rows="200">';
//pp(stripslashes($text));
//echo '</textarea>';
// getting domains

// в тексте заменяем хэши на исходные урлы или их yourls-аналоги
//foreach($elements as $el){
//	// елси элемент из списка преобразуемых - заменяем хэш на yourl
//	if ($el['needY'] AND $el['yourl'] != '') {
//		while (strripos($text, trim_string($el['hash']))) {
//			$text = str_replace($el['hash'], $el['yourl'], $text);
//		}
//	} else {
//		// елси элемент не из списка преобразуемых - заменяем хэш на исходный код элемента
//		while (strripos($text, trim_string($el['hash']))) {
//			$text = str_replace($el['hash'], $el['elem'], $text);
//		}
//	}
//}



$text = '
[url=https://ru.wikipedia.org][img]https://upload.wikimedia.org/wikipedia/commons/6/63/Wikipedia-logo.png[/img][/url]
[url]https://ru.wikipedia.org[/url]
[URL]https://BIG.wikipedia.org[/URL]
[url=https://ru.wikipedia.org]wikipedia[/url]
<a href="https://waak.net/im60ahdbeluq.html">https://www.turbobit.net/im60ahdbeluq.html</a>
<a href="http://waak.net/im60ahdbeluq.html">ссылка</a>
<iframe src="https://iframe.co/embed/4Fe5bnUaARE/25343tergdfvcx_32.wmv" ></iframe>
<img src="https://image.co/embed/4Fe5bnUaARE/25343tergdfvcx_32.png" class=\'dd\'/>
[IMG]https://wikimedia.org/wikipedia/commons/6/63/Wikipedia-logo.png[/IMG]
foo@demo.net	bar.ba@test.co.uk www.demo.com	http://foo.co.uk/
Добро пожаловать в http://linux.net/ WordPress.<br>Это
Отредактируйте или удалите <a href="http://www.yandex.ru/">http://yandex.ru/</a> её, затем
https://raka.rak пишите! <a href="www.example.com/hello.html?ho#t-t_hy">www.example.com/hello.html?ho#t-t_hy</a>
<a href="http://waper.ru">http://waper.ru</a><img src="http://waka.img/embed/4Fe5bnUaARE/25343tergdfvcx_32.png" class="sss"/>
<a rel="external noopener noreferrer" target="_blank" data-wpel-link="external" href="http://www.datafile.com/d/TWpBeU16VTBNalEF9/Pregnantmary 11.mp4">Pregnantmary 11.mp4</a>';

pp($text);

$ntext = clyc_shortyfy_urls($text, $options);
pp($ntext);