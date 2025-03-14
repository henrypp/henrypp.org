<!DOCTYPE html>

<html lang="en">
	<head>
		<title><?php echo isset($aPage['title_html']) ? $aPage['title_html'] : $cms->get_cfg('author'); ?></title>

		<meta charset="utf-8" />
		<meta name="description" content="<?php echo !empty($cms->description) ? strip_tags($cms->description) : $cms->get_cfg('description'); ?>" />
		<meta name="keywords" content="<?php echo $cms->get_cfg('keywords'); ?>" />
		<meta name="author" content="<?php echo $cms->get_cfg('author'); ?>" />

		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
		<meta name="color-scheme" content="dark light">

		<link rel="stylesheet" href="<?php echo $cms->get_url(); ?>/thirdparty/normalize.css" />
		<link rel="stylesheet" href="<?php echo $cms->get_url(); ?>/style.css" />

		<link rel="alternate" type="application/rss+xml" href="<?php echo $cms->get_url(); ?>/rss.xml" />
		<link rel="sitemap" type="application/xml" title="Sitemap" href="<?php echo $cms->get_url(); ?>/sitemap.php" />
	</head>

	<body>
		<div class="container">
			<header class="header">
				<div class="head-title">
					<h1><a href="<?php echo $cms->get_url(); ?>"><?php echo $cms->get_cfg('author'); ?></a></h1>
					<p><?php echo isset($aPage['title_html']) ? $aPage['title_html'] : 'News'; ?></p>
				</div>

				<div class="head-nav">
					<a href="#" id="nav"></a>
				</div>
			</header>

			<aside class="lside" id="lside">
				<div class="block">
					<div class="title">Navigation</div>

					<ul>
<?php
					$array = [];
					$cms->generate_tree('page', $array, ['column' => ['children'], 'where' => ['show_on_header' => 1], 'with_url' => TRUE, 'depth' => 0]);
?>
						<li><a href="<?php echo$cms->get_url(); ?>">News</a></li>
<?php

					foreach($array as $val)
					{
?>
						<li><a href="<?php echo $val['url']; ?>"><?php echo $val['title']; ?></a></li>
<?php
					}

					unset ($array, $val);
?>
					</ul>
				</div>

				<div class="block">
					<div class="title">Products</div>

					<ul>
<?php
					$array = [];
					$cms->generate_tree('product', $array, ['order' => 'title', 'with_url' => TRUE, 'depth' => 0]);

					foreach($array as $val)
					{
						printf('<li><a href="%s">%s</a></li>', $val['url'], $val['title']);
					}

					unset($array, $val);
?>
					</ul>
				</div>

				<div class="block">
					<div class="title">Contacts</div>

					<ul>
						<li><a href="<?php echo $cms->get_cfg('github'); ?>" class="clr-white underline">GitHub</a></li>
						<li><a href="<?php echo $cms->get_cfg('telegram'); ?>" class="clr-white underline">Telegram</a></li>
					</ul>
				</div>

				<div class="block">
					<div class="title">Donation</div>

					<ul>
						<li><a href="<?php echo $cms->get_cfg('bitcoin'); ?>" rel="nofollow" title="Bitcoin">Bitcoin (BTC)</a></li>
						<li><a href="<?php echo $cms->get_cfg('paypal'); ?>" rel="nofollow" title="Paypal">Paypal (USD)</a></li>
						<li><a href="<?php echo $cms->get_cfg('yoomoney'); ?>" rel="nofollow" title="Yandex Money">Yandex Money (ALL)</a></li>
					</ul>
				</div>
			</aside>

			<div class="space"></div>

			<main class="content">