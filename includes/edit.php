<?php
include_once('models/clyc.php');

// обрабатываем сабмит формы
$message = '';
if ( ! empty($_POST)) {
	//pp($_POST);
	if (isset($_POST['clyc_save_options'])) {
		$message = clyc_save_options($_POST);
	} /*elseif(isset($_POST['clyc_analyse_contents'])) {
		$message = clyc_analyse_contents();
	}*/
}

// получаем настройки плагина
$options = clyc_get_options();
$clyc_domains = ( ! empty($options['clyc_domains'])) ? explode(',', $options['clyc_domains']) : array();
$clyc_installed = get_option('clyc_installed');

//$page = 'Добро пожаловать в http://linux.net/ WordPress. Это ваша первая <a href="https://www.turbobit.net/im60ahdbeluq.html">https://www.turbobit.net/im60ahdbeluq.html</a> запись. Отредактируйте или удалите <a href="http://yandex.ru/">http://yandex.ru/</a>её, затем  https://raka.rak пишите! <a href="http://waper.ru">http://waper.ru</a> ';
//if($options['clyc_shorten_link_types'] == 'all'){
//	echo clyc_shortyfy_text_urls($page, $options, FALSE);
//} else {
//	echo clyc_shortyfy_anchor_urls($page, $options, FALSE);
//}

?>
<script>
	/**
	 * проверяет ваидность домена
	 * @param str
	 * @returns {boolean}
	 */
	function isURL(str) {
		var pattern = new RegExp('^(https?:\\/\\/)?'+ // protocol
			'((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.?)+[a-z]{2,}|'+ // domain name
			'((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
			'(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
			'(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
			'(\\#[-a-z\\d_]*)?$','i'); // fragment locator
		return pattern.test(str);
	}

	/**
	 * добавление нового домена в список
	 */
	function addDomain() {
		var message = ''; // error string
		$('.clyc_domains_error').remove();

		// getting domain from input and clear trash
		var domain = $('#add_domain').val();
		domain = domain.toLowerCase();
		if (domain.slice(-1) == '/' || domain.slice(-1) == ',') {
			domain = domain.substring(0,domain.length-1);
			domain = domain.trim();
		}

		// making array
		var domainArr = [];
		if(domain.indexOf(",") > 0){
			domainArr = domain.split(",");
		} else {
			domainArr = [domain];
		}
		// checking and adding one by one
		var i;
		for (i = 0; i < domainArr.length; ++i) {
			var url = domainArr[i].trim();

			//console.log(domainArr[index].trim());
			if(isURL(url)){
				// clear
				url = url.replace("http://", "");
				url = url.replace("https://", "");
				url = url.replace("www.", "");

				// check if domain is already in a list
				var domains = $('#clyc_domains').val();
				if (domains.indexOf(url) != -1){
					message = 'This domain is already in a list';
				} else {
					var html ='<div class="clyc_domain_tag"><div class="clyc_domain_name">'+url+'</div><div class="clyc_domain_del"></div></div>';
					$('#clyc_domain_container').append(html);
					$('#clyc_domains').val(domains+','+url);
				}
			} else {
				message = 'Incorrect URL!';
			}
			if (message != ''){
				$('.clyc_domains_td').prepend("<div class='clyc_domains_error'>"+message+"</div>");
				return false;
			}
		}
		// clear input
		$('#add_domain').val('');
	}

	$( document ).ready(function() {
		/**
		 * обработчики клика по плюсу и клавиши ENTER в в поле добавления домена
		 */
		$( "#add_domain_btn" ).on('click', function() {
			addDomain();
		});
		$('#add_domain').on('keypress', function(e) {
			var keyCode = e.keyCode || e.which;
			if (keyCode === 13) {
				e.preventDefault();
				addDomain();
				return false;
			}
		});

		/**
		 * Удаление домена из списка
		 */
		$(document).on('click', ".clyc_domain_del", function() {
			var domain = $(this).siblings('.clyc_domain_name').html();
			var domains = $('#clyc_domains').val();
			domains = domains.replace(domain+',', "");
			domains = domains.replace(','+domain, "");

			$('#clyc_domains').val(domains);
			$(this).parent('.clyc_domain_tag').remove();
		});
	});
</script>
<div class="wrap">
	<h1>Content links YOURLS creator</h1>
	<?php if ($clyc_installed == 0): ?>
		<h2>Before start please fill out YOURLS settings</h2>
	<?php endif; ?>
	<?=($message != '') ? "<h3>$message</h3>" : '';?>
	<table width="100%">
		<tr>
			<td width="50%">
				<form method="post" action="">
					<table>
						<tr>
							<td colspan="2"><h2>Plugin settings</h2></td>
						</tr>
						<tr>
							<td colspan="2"><hr></td>
						</tr>
						<tr>
							<td>
								YOURLS domain
							</td>
							<td width="70%">
								<input name="clyc_yourls_domain" type="text" value="<?=$options['clyc_yourls_domain']?>" />
							</td>
						</tr>
						<tr>
							<td>
								YOURLS token
							</td>
							<td>
								<input name="clyc_yourls_token" type="text" value="<?=$options['clyc_yourls_token']?>" />
							</td>
						</tr>
						<tr>
							<td colspan="2"><hr></td>
						</tr>
						<!-- показываем доп настройки только после установки свойст YOURLS -->
						<?php if ($clyc_installed == 1): ?>
							<tr>
								<td>
									Shorten links "on fly"
								</td>
								<td>
									<input name="clyc_create_on_fly" type="checkbox" <?=($options['clyc_create_on_fly'] == 1) ? 'checked' : '';?> />
								</td>
							</tr>
							<tr>
								<td colspan="2"><br></td>
							</tr>
							<tr>
								<td>
									Shorten link types
								</td>
								<td>
									<input type="radio" name="clyc_shorten_link_types"  <?=($options['clyc_shorten_link_types'] == 'all') ? 'checked' : '';?> value="all"> All urls <div class="clyc_form_note">(both text urls and urls inside &lt;a&gt; tags)</div><br>
									<input type="radio" name="clyc_shorten_link_types" <?=($options['clyc_shorten_link_types'] == 'aurls') ? 'checked' : '';?> value="aurls"> Only in links <div class="clyc_form_note">(urls inside &lt;a&gt; tags)</div><br/>
									<input type="radio" name="clyc_shorten_link_types" <?=($options['clyc_shorten_link_types'] == 'hrefs') ? 'checked' : '';?> value="hrefs"> Only hrefs <div class="clyc_form_note">(only urls inside links' href attribute &lt;a href='..'&gt; )</div>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									&nbsp;
								</td>
							</tr>
							<tr>
								<td valign="top">
									Domains list:<br><div class="clyc_form_note">one or more comma-separated</div>
								</td>
								<td valign="top" class="clyc_domains_td">
									<input id="add_domain" class="add_domain" type="text" value=""><div id="add_domain_btn" class="add_domain_btn" ></div>
									<div class="clear"></div>

									<div id ='clyc_domain_container'>
										<?php foreach ($clyc_domains as $domain) : ?>
											<div class="clyc_domain_tag" data-id="<?=$domain?>">
												<div class="clyc_domain_name"><?=$domain?></div>
												<div class="clyc_domain_del"></div>
											</div>
										<?php endforeach;?>
									</div>
									<input name="clyc_domains" id="clyc_domains" type="hidden" value="<?=$options['clyc_domains']?>">
								</td>
							</tr>

							<tr>
								<td colspan="2"><hr></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td align="center" colspan="2">
								<input type="submit" name="clyc_save_options"  value="Save" />
							</td>
						</tr>
					</table>
				</form>
			</td>
			<td width="3%"></td>
			<td valign="top">
				<!-- показываем доп настройки только после установки свойст YOURLS -->
				<?//php if ($clyc_installed == 1): ?>
				<?php if (FALSE): ?>
					<table>
						<tr>
							<td colspan="2"><h4>Преобразовать ссылки в существующем контенте</h4></td>
						</tr>
						<tr>
							<td colspan="2"><hr></td>
						</tr>
						<tr>
							<td colspan="2">
								<p>
									Можно проанализировать и сократить ссылки в существующих постах и страницах
								</p>
								<p>
									(возможность появится в ближайшее время)
								</p>

							</td>
						</tr>
						<tr>
							<td colspan="2" align="left">
								<!--Форма, содержащая единственную кнопку - очистки таблицы настроек плагина-->
								<form method="post" action="">
									<input disabled type="submit" name="clyc_analyse_contents" value="Проанализировать существующий контент"/>
								</form>

							</td>
						</tr>
					</table>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</div>