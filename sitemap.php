<?php
	require_once('include/cms_engine.php');

	// get last edit time
	$time = $cms->db_select('page', 'lastmod', '`lastmod` != 0 ORDER BY `lastmod` DESC LIMIT 1');
	$time = $time ? $time[0]['lastmod'] : time();

	// Send headers
	header('Content-type: application/xml');
	header('Last-Modified: '. gmdate('D, d M Y H:i:s', $time) .' GMT');

	print('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

	add_url(NULL, $time);

	$array = [];
	$cms->generate_tree('page', $array, ['with_info' => TRUE, 'with_url' => TRUE]);

	print_array($array, $time);

	function print_array($array, $time)
	{
		foreach($array as $val)
		{
			add_url($val['url'], $val['lastmod'] ? $val['lastmod'] : $time);

			if($val['data'])
			{
				print_array($val['data'], $time);
			}
		}

		unset($val);
	}

	print('</urlset>');

	// print url
	function add_url($url, $lastmod)
	{
		printf('<url><loc>%s</loc><lastmod>%s</lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>', ($url ? $url : $GLOBALS['cms']->get_url()), date('c', $lastmod));
	}
?>