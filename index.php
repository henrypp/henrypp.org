<?php
	require_once('include/cms_engine.php');

	if($cms->is_page())
	{
		$aBreadcrumb = [];
		$aPage = $cms->get_page(['id', 'timestamp', 'lastmod', 'lastmod_beta', 'children', 'changelog', 'architecture', 'os', 'url', 'url_link', 'version', 'version_beta', 'license', 'lang', 'is_portable', 'is_i18n', 'is_binaries', 'is_installer', 'is_checksum', 'is_gpg'], NULL, $aBreadcrumb);

		if($aPage)
		{
			require_once('include/cms_header.php');

			printf('<div itemscope itemtype="http://schema.org/%s">', $aPage['table'] === 'product' ? 'SoftwareApplication' : 'Article');
			printf('<h1 itemprop="name">%s</h1>', $aPage['title']);

			if($aPage['children'])
			{
				$array = [];
				$cms->generate_tree($aPage['children'], $array, ['with_media' => FALSE, 'with_text' => TRUE, 'column' => ['timestamp', 'url'], 'order' => 'timestamp', 'order_desc' => TRUE]);

				print($aPage['text']);
				print('<hr />');

				foreach($array as $val)
				{
?>
				<div class="list-item">
					<h2><a href="<?php echo $cms->get_url(); ?>/product/<?php echo $val['url']; ?>"><?php echo $val['title']; ?></a></h2>
					<p class="date" title="<?php echo date('r', $val['timestamp']); ?>"><?php echo date('j F Y', $val['timestamp']); ?></p>
					<p><?php echo $val['description']; ?></p>
				</div>
<?php
				}

				unset($array, $val);
			}
			else if($aPage['table'] === 'product')
			{
?>
				<div>Version: <span itemprop="softwareVersion"><?php echo $aPage['version']; ?></span></div>
				<div>Author: <span itemprop="author"><?php echo $cms->get_cfg('author'); ?></span></div>
				<div>First release: <time itemprop="pubdate" datetime="<?php echo date('Y-m-d H:i:s', $aPage['timestamp']); ?>" title="<?php echo date('r', $aPage['timestamp']); ?>"><?php echo date('j F Y', $aPage['timestamp']); ?></time></div>
				<div>Last updated: <time itemprop="dateModified" datetime="<?php echo date('Y-m-d H:i:s', $aPage['lastmod']); ?>" title="<?php echo date('r', $aPage['lastmod']); ?>"><?php echo date('j F Y', $aPage['lastmod']); ?></time></div>
				<div>Language: <span><?php echo $aPage['lang']; ?></span></div>
				<div>Platform architecture: <span itemprop="operatingSystem"><?php echo empty($aPage['architecture']) ? $cms->get_cfg('architecture') : $aPage['architecture']; ?></span></div>
				<div>Supported OS: <span itemprop="operatingSystem"><?php echo empty($aPage['os']) ? $cms->get_cfg('os') : $aPage['os']; ?></span></div>

				<hr />

				<h2 id="donate">Donation</h2>

				<p>Development is powered by your donations!</p>

				<div class="donate-block">
					<div class="donate" title="Bitcoin"><a href="<?php echo $cms->get_cfg('bitcoin'); ?>" rel="nofollow">Bitcoin <span>BTC</span></a></div>
					<div class="donate" title="Ethereum"><a href="<?php echo $cms->get_cfg('ethereum'); ?>" rel="nofollow">Ethereum <span>ETH</span></a></div>
					<div class="donate" title="Paypal"><a href="<?php echo $cms->get_cfg('paypal'); ?>" rel="nofollow">Paypal <span>USD</span></a></div>
					<div class="donate" title="Yandex Money"><a href="<?php echo $cms->get_cfg('yoomoney'); ?>" rel="nofollow">Yandex<span>RUB</span></a></div>
					<div class="donate" title="IBAN"><a href="javascript:alert('<?php echo $cms->get_cfg('iban'); ?>');" rel="nofollow">IBAN <span>ALL</span></a></div>
				</div>

				<hr />

				<div class="preview">
					<img itemprop="screenshot" alt="screenshot" src="<?php echo $cms->get_url (); ?>/images/<?php echo $aPage['url']; ?>.png" />
				</div>

				<h2 id="description">Description</h2>

				<p itemprop="about"<?php echo !empty($aPage['text']) ? ' hidden' : ''; ?>><?php echo $aPage['description']; ?></p>
<?php
				if(!empty($aPage['text']))
				{
					print($aPage['text']);
				}

				if($aPage['is_portable'])
				{
?>
				<h2>Portable mode</h2>
				<p>To activate portable mode, create <mark><?php echo $aPage['url']; ?>.ini</mark> in application folder, or move it from <mark>%APPDATA%\Henry++\<?php echo $aPage['title']; ?></mark>.</p>
<?php
				}
?>

				<h2 id="download">Download</h2>
				<ul>
<?php
					if(!empty($aPage['is_binaries']) && $aPage['is_binaries'])
					{
?>
					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s-bin.zip', $cms->get_cfg ('github'), $aPage['url'], $aPage['version'], $aPage['url'], $aPage['version']); ?>"><?php printf ('%s-%s-bin.zip', $aPage['url'], $aPage['version']); ?></a></li>
<?php
					}

					if(!empty($aPage['is_installer']) && $aPage['is_installer'])
					{
?>
					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s-setup.exe', $cms->get_cfg ('github'), $aPage['url'], $aPage['version'], $aPage['url'], $aPage['version']); ?>"><?php printf ('%s-%s-setup.exe', $aPage['url'], $aPage['version']); ?></a></li>
<?php
					if($aPage['is_gpg'])
					{
?>
					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s-setup.exe.sig', $cms->get_cfg ('github'), $aPage['url'], $aPage['version'], $aPage['url'], $aPage['version']); ?>"><?php printf ('%s-%s-setup.exe.sig', $aPage['url'], $aPage['version']); ?></a></li>
<?php
					}

					}
?>
<?php
					if(!empty($aPage['is_checksum']) && $aPage['is_checksum'])
					{
?>
					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s.sha256', $cms->get_cfg ('github'), $aPage['url'], $aPage['version'], $aPage['url'], $aPage['version']); ?>"><?php printf ('%s-%s.sha256', $aPage['url'], $aPage['version']); ?></a></li>
<?php
					}
?>
				</ul>

				<p><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/latest', $cms->get_cfg ('github'), $aPage['url'], $aPage['version'], $aPage['url'], $aPage['version']); ?>">Latest stable release is always here</a></p>
<?php
				if (!empty ($aPage['version_beta']) && version_compare ($aPage['version'], $aPage['version_beta']) == -1)
				{
?>
				<h2 id="beta">Download (beta)</h2>

				<p>Beta releases may be unstable and contain unreported bugs.</p>

				<ul>
					<?php $aPage['version_beta'] = preg_replace ("/[^0-9,.]/", '', $aPage['version_beta']); ?>

					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s-bin.zip', $cms->get_cfg ('github'), $aPage['url'], $aPage['version_beta'], $aPage['url'], $aPage['version_beta']); ?>"><?php printf ('%s-%s-bin.zip', $aPage['url'], $aPage['version_beta']); ?></a></li>
<?php
					if(!empty($aPage['is_installer']) && $aPage['is_installer'])
					{
?>
					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s-setup.exe', $cms->get_cfg ('github'), $aPage['url'], $aPage['version_beta'], $aPage['url'], $aPage['version_beta']); ?>"><?php printf ('%s-%s-setup.exe', $aPage['url'], $aPage['version_beta']); ?></a></li>
<?php
					if($aPage['is_gpg'])
					{
?>
					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s-setup.exe.sig', $cms->get_cfg ('github'), $aPage['url'], $aPage['version_beta'], $aPage['url'], $aPage['version_beta']); ?>"><?php printf ('%s-%s-setup.exe.sig', $aPage['url'], $aPage['version_beta']); ?></a></li>
<?php
					}

					}

					if(!empty($aPage['is_checksum']) && $aPage['is_checksum'])
					{
?>
					<li><a itemprop="downloadUrl" href="<?php printf ('%s/%s/releases/download/v.%s/%s-%s.sha256', $cms->get_cfg ('github'), $aPage['url'], $aPage['version_beta'], $aPage['url'], $aPage['version_beta']); ?>"><?php printf ('%s-%s.sha256', $aPage['url'], $aPage['version_beta']); ?></a></li>
<?php
					}
?>
				</ul>
<?php
				}

				if($aPage['is_gpg'])
				{
?>
				<h2 id="gpg">GPG Signature</h2>
				<p>Binaries have GPG signature <mark><?php echo $aPage['url']; ?>.exe.sig</mark> in application folder.</p>

				<ul>
					<li>Public key: <a href="<?php echo $cms->get_cfg('gpg_keyfile'); ?>" download>pubkey.asc</a> (<a href="<?php echo $cms->get_cfg('gpg_keyfile_mirror'); ?>" download><?php echo parse_url($cms->get_cfg('gpg_keyfile_mirror'),  PHP_URL_HOST); ?></a>)</li>
					<li>Key ID: <mark><?php echo $cms->get_cfg('gpg_keyid'); ?></mark></li>
					<li>Fingerprint: <mark><?php echo $cms->get_cfg('gpg_fingerprint'); ?></mark></li>
				</ul>
<?php
				}

				if(!empty ($aPage['is_i18n']) && $aPage['is_i18n'])
				{
?>
					<h2 id="i18n">Languages</h2>
					<p>Put <mark><?php echo $aPage['url']; ?>.lng</mark> file into application directory and restart the program.</p>
					<ul>
						<li><a href="<?php echo $cms->get_cfg('github'); ?>/<?php echo $aPage['url']; ?>/raw/master/bin/<?php echo $aPage['url']; ?>.lng" download="<?php echo $aPage['url']; ?>.lng">Download language</a></li>
						<li><a href="<?php echo $cms->get_cfg('github'); ?>/<?php echo $aPage['url']; ?>/raw/master/bin/i18n/!example.txt">Read instruction to create your own localization</a></li>
					</ul>
<?php
				}
?>
				<h2>Links</h2>
				<ul>
					<li><a itemprop="sameAs" href="<?php echo $cms->get_cfg('github') .'/'. $aPage['url']; ?>" class="underline">GitHub</a></li>
					<li><a itemprop="releaseNotes" href="<?php echo $cms->get_cfg('github') .'/'. $aPage['url']; ?>/blob/master/CHANGELOG.md" class="underline">Changelog</a></li>
					<li><a itemprop="license" href="<?php echo $cms->get_cfg('github') .'/'. $aPage['url'] ?>/blob/master/LICENSE" class="underline">License agreement</a></li>
				</ul>

				<h2>Support</h2>
				<ul>
					<li><a href="<?php echo $cms->get_cfg('github') .'/'. $aPage['url']; ?>/issues" class="underline">Report issue</a></li>
					<li><a href="<?php echo $cms->get_cfg('github') .'/'. $aPage['url']; ?>/pulls" class="underline">Pull requests</a></li>
				</ul>
<?php
			}
			else
			{
				print('<div itemprop="articleBody">');

				if($aPage['url'] == 'donate')
				{
?>
				<p>Development is powered by your donations!</p>

				<div class="donate-block">
					<div class="donate" title="Bitcoin"><a href="<?php echo $cms->get_cfg('bitcoin'); ?>" rel="nofollow">Bitcoin <span>BTC</span></a></div>
					<div class="donate" title="Ethereum"><a href="<?php echo $cms->get_cfg('ethereum'); ?>" rel="nofollow">Ethereum <span>ETH</span></a></div>
					<div class="donate" title="Paypal"><a href="<?php echo $cms->get_cfg('paypal'); ?>" rel="nofollow">Paypal <span>USD</span></a></div>
					<div class="donate" title="Yandex Money"><a href="<?php echo $cms->get_cfg('yoomoney'); ?>" rel="nofollow">Yandex<span>RUB</span></a></div>
					<div class="donate" title="IBAN"><a href="javascript:alert('<?php echo $cms->get_cfg('iban'); ?>');" rel="nofollow">IBAN <span>ALL</span></a></div>
				</div>
<?php
				}
				else
				{
					print($aPage['text']);
				}

				print('</div>');
			}

			print('</div>');

			require_once ('include/cms_footer.php');
		}
		else
		{
			$aPage['title_html'] = '404 Not Found';

			require_once ('include/cms_header.php');
?>
			<h1>404 Not Found</h1>
			<p>Sorry, requested page does not exist!</p>
<?php
			require_once ('include/cms_footer.php');
		}
	}
	else
	{
		require_once('include/cms_header.php');

		$array = [];
		$array_sorted = [];
		$lastmod_timestamp = 0;
		$cms->generate_tree('product', $array, ['with_media' => FALSE, 'with_text' => TRUE, 'column' => ['lastmod', 'url', 'version', 'version_beta', 'lastmod_beta'], 'order' => 'lastmod', 'order_desc' => TRUE]);

		foreach ($array as $val)
		{
			// add beta version
			if (!empty ($val['version_beta']) && version_compare ($val['version'], $val['version_beta']) == -1)
			{
				$array_sorted[] = [
					'title' => $val['title'],
					'version' => $val['version_beta'],
					'lastmod' => $val['lastmod_beta'],
					'description' => $val['description'],
					'url' => $val['url'] .'#beta',
				];

				if ($lastmod_timestamp < $val['lastmod_beta'])
					$lastmod_timestamp = $val['lastmod_beta'];
			}

			// add stable version
			$array_sorted[] = [
				'title' => $val['title'],
				'version' => $val['version'],
				'lastmod' => $val['lastmod'],
				'description' => $val['description'],
				'url' => $val['url'],
			];

			if ($lastmod_timestamp < $val['lastmod'])
				$lastmod_timestamp = $val['lastmod'];
		}

		unset($array, $val);

		// sort array by lastmod subkey
		usort ($array_sorted, function (array $a, array $b) {
			if ($a['lastmod'] < $b['lastmod'])
				return 1;
			else if ($a['lastmod'] > $b['lastmod'])
				return -1;

			return 0;
		});

		echo PHP_EOL;

		foreach ($array_sorted as $val)
		{
?>
				<div class="list-item">
					<h2><a href="<?php echo $cms->get_url(); ?>/product/<?php echo $val['url']; ?>"><?php printf ('%s %s', $val['title'], $val['version']); ?></a></h2>
					<p class="date" title="<?php echo date('r', $val['lastmod']); ?>"><?php echo date ('j F Y', $val['lastmod']); ?></p>
					<p><?php echo $val['description']; ?></p>
				</div>
<?php
		}

		// generate rss
		$rss_path = 'rss.xml';
		$rss_buffer = '';

		if (filemtime ($rss_path) < $lastmod_timestamp)
		{
			$rss_buffer = sprintf ('<?xml version="1.0" encoding="utf-8" ?>' . PHP_EOL . PHP_EOL . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:creativeCommons="http://backend.userland.com/creativeCommonsRssModule">' . PHP_EOL . "\t" . '<channel>' . PHP_EOL . "\t\t" . '<title>%s</title>'. PHP_EOL . "\t\t" . '<link>%s</link>' . PHP_EOL . "\t\t" . '<description>%s</description>' . PHP_EOL . "\t\t" . '<language>en-US</language>' . PHP_EOL . "\t\t" . '<lastBuildDate>%s</lastBuildDate>' . PHP_EOL . "\t\t" . '<atom:link href="%s/rss.xml" rel="self" type="application/rss+xml"></atom:link>
' . PHP_EOL, $cms->get_cfg ('author'), $cms->get_url (), $cms->get_cfg ('description'), date (DATE_RSS, $lastmod_timestamp), $cms->get_url ());

			$i = 0;

			foreach ($array_sorted as $val)
			{
				$rss_buffer .= sprintf ("\t\t" . '<item>' . PHP_EOL . "\t\t\t" . '<title>%s %s</title>' . PHP_EOL . "\t\t\t" . '<pubDate>%s</pubDate>' . PHP_EOL . "\t\t\t" . '<author>%s (%s)</author>' . PHP_EOL . "\t\t\t" . '<description>%s</description>' . PHP_EOL . "\t\t\t" . '<link>%s/product/%s</link>' . PHP_EOL . "\t\t\t" . '<guid isPermaLink="true">%s/product/%s</guid>' . PHP_EOL . "\t\t" . '</item>' . PHP_EOL, $val['title'], $val['version'], date (DATE_RSS, $val['lastmod']), $cms->get_cfg ('email'), $cms->get_cfg ('author'), $val['description'], $cms->get_url (), $val['url'], $cms->get_url (), $val['url']);

				if (++$i >= 10)
					break;
			}

			$rss_buffer .= "\t" . '</channel>' . PHP_EOL . '</rss>' . PHP_EOL;

			file_put_contents ($rss_path, $rss_buffer);

			touch ($rss_path, $lastmod_timestamp);
		}

		unset ($array_sorted, $val);

		require_once ('include/cms_footer.php');
	}
?>
