<style>
	h2 .success,
	h3 .success{
		color: green;
	}
	h2 .error,
	h3 .error{
		color: firebrick;
	}
	.clyc_form_note{
		font-size: 11px;
		color: gray;
	}
</style>
<?php
include_once('models/clyc.php');

// обрабатываем сабмит формы
$message = '';
if (!empty($_POST)) {
	if (isset($_POST['clyc_save_options'])) {
		$message = clyc_save_options($_POST);
	} elseif(isset($_POST['clyc_analyse_contents'])) {
		$message = clyc_analyse_contents();
	}
}

// получаем настройки плагина
$options = clyc_get_options();
$clyc_installed = get_option('clyc_installed');
?>
<div class="wrap">
	<h1>Content links YOURLS creator</h1>
	<?php if ($clyc_installed == 0): ?>
		<h2>Для начала работы плагина введите данные сервера YOURLS</h2>
	<?php endif; ?>
	<?=($message != '') ? "<h3>$message</h3>" : '';?>
	<table>
		<tr>
			<td>
				<form method="post" action="">
					<table>
						<tr>
							<td colspan="2"><h4>Общие настройки плагина</h4></td>
						</tr>
						<tr>
							<td colspan="2"><hr></td>
						</tr>
						<tr>
							<td>
								Домен YOURLS
							</td>
							<td>
								<input name="clyc_yourls_domain" type="text" value="<?=$options['clyc_yourls_domain']?>">
							</td>
						</tr>
						<tr>
							<td>
								Токен YOURLS
							</td>
							<td>
								<input name="clyc_yourls_token" type="text" value="<?=$options['clyc_yourls_token']?>">
							</td>
						</tr>
						<tr>
							<td colspan="2"><hr></td>
						</tr>
						<!-- показываем доп настройки только после утсановки свойст YOURLS -->
						<?php if ($clyc_installed == 1): ?>
							<tr>
								<td>
									Преобразовывать ссылки "на лету"
								</td>
								<td>
									<input name="clyc_create_on_fly" type="checkbox" <?echo ($options['clyc_create_on_fly'] == 1) ? 'checked' : ''?>>
								</td>
							</tr>
							<tr>
								<td>
									Список доменов:<div class="clyc_form_note">один или несколько, введённых через запятую</div>
								</td>
								<td>
									<textarea name="clyc_domains" cols="50" rows="4"><?=$options['clyc_domains']?></textarea>
								</td>
							</tr>
							<tr>
								<td colspan="2"><hr></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td align="center" colspan="2">
								<input type="submit" name="clyc_save_options"  value="Сохранить" />
							</td>
						</tr>
					</table>
				</form>
			</td>
			<td width="3%"></td>
			<td valign="top">
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
							<!-- показываем доп настройки только после установки свойст YOURLS -->
							<?php if ($clyc_installed == 1): ?>
								<!--Форма, содержащая единственную кнопку - очистки таблицы настроек плагина-->
								<form method="post" action="">
									<input disabled type="submit" name="clyc_analyse_contents" value="Проанализировать существующий контент"/>
								</form>
							<?php endif; ?>
						</td>
					</tr>

				</table>
			</td>
		</tr>
	</table>


</div>