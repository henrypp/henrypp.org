<?php
/*
	started @ Nov 2012
*/

// check php version
if(version_compare(PHP_MAJOR_VERSION .'.'. PHP_MINOR_VERSION, '5.5', '<'))
{
	trigger_error('PHP 5.5 and more required, not '. PHP_MAJOR_VERSION .'.'. PHP_MINOR_VERSION, E_USER_ERROR);
}

// check extensions
foreach(['exif', 'gd', 'json', 'mysqli'] as $val)
{
	if(!extension_loaded($val))
	{
		trigger_error('Required extension "'. $val .'" does not loaded!', E_USER_ERROR);
	}
}

unset($cms, $val);

$cms = new CMS();

class CMS
{
	// Constants
	const PROJECT_NAME = 'My Admin';
	const PROJECT_VERSION = '1.3 \b\u\i\l\d yz';
	const PROJECT_BUILD = 1453116023; // unix-timestamp value, generate "$this->version" variable
	const PROJECT_AUTHOR = 'Henry++';
	const PROJECT_WEBPAGE = 'https://www.henrypp.org';

	const PROFILE_COOKIE = 'hashley1'; // auth session cookie name
	const PROFILE_ALGORITHM = PASSWORD_BCRYPT; // password hashing algorithm
	const PROFILE_EXPIRATION = 86400; // auth session expiration (in seconds)

	const MEDIA_CHMOD = 0777;
	const MEDIA_DIRECTORY = 'uploads';
	const MEDIA_QUALITY_JPEG = 85;
	const MEDIA_QUALITY_PNG = 9;
	const MEDIA_MAX_WIDTH = 1920; // max width for images

	const MYSQLI_PREFIX = 'cms_'; //

	// Variables
	private $lang = 'ru';
	private $timezone = 'utc';

	private $media_extension = [
		'archive' => ['7z', 'rar', 'zip'],
		'audio' => ['mp3', 'm4a', 'ogg', 'wav'],
		'document' => ['doc', 'docx', 'pdf', 'ppt', 'pptx', 'pps', 'ppsx', 'rtf', 'txt', 'xls', 'xlsx'],
		'image' => ['gif', 'jpg', 'pdf', 'jpeg', 'png'],
		'video' => ['m4v', 'mp4', 'ogv', 'webm'],
	];

	private $media_thumbnail = [
		'preview' => [200, 200], // list($width, $height);
	];

	// Private variables
	private $mysqli = NULL, $mysqli_status = FALSE, $i18n;

	// Column configuration
	private $table_hidden = ['media'];
	private $table_internal = ['comment', 'config', 'media', 'page', 'profile'];
	private $column_internal = ['id' => 'id', 'parent_id' => 'parent_id', 'timestamp' => 'timestamp', 'lastmod' => 'lastmod'];
	private $column_required = ['title', 'url', 'email'];
	private $column_title = ['title', 'name', 'email', 'id'];

	// Predefine
	private $predefine = ['profile' => ['type' => ['type_admin', 'type_editor', 'type_user']]];

	function __construct()
	{
		require 'cms_config.php';

		// Set timezone
		date_default_timezone_set($this->timezone);

		// Generate variables
		$this->version = date(self::PROJECT_VERSION, self::PROJECT_BUILD);
		$this->root_dir = !empty($this->root_dir) ? ('/' . trim($this->root_dir, '/')) : NULL;
		$this->url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') .'://'. $_SERVER['HTTP_HOST'] . $this->root_dir;
	}

	function __destruct()
	{
		if($this->mysqli_status)
		{
			$this->mysqli->close();
		}
	}

	// Get website url with request uri (optional)
	function get_url($bWithRequestURI = FALSE)
	{
		return $this->url . ($bWithRequestURI ? $_SERVER['REQUEST_URI'] : NULL);
	}

	// Get full path to root directory
	private function get_root_dir()
	{
		return $_SERVER['DOCUMENT_ROOT'] . $this->root_dir;
	}

	// Get full path to root directory
	function is_page()
	{
		return $_SERVER['REQUEST_URI'] !== '/';
	}

	// Get localized string by key
	function i18n($key, $default_val = NULL)
	{
		if(!isset($this->i18n[$this->lang][$key]))
		{
			$path = sprintf('%s/include/i18n/%s.php', $this->get_root_dir(), $this->lang);

			if(!file_exists($path))
			{
				return $default_val ? $default_val : $key;
			}

			require_once($path);
		}

		return isset($this->i18n[$this->lang][$key]) ? $this->i18n[$this->lang][$key] : ($default_val ? $default_val : $key);
	}

	/*
		Print array result as JSON

		$status		:	status code
		$text		:	i18n value
		$data		:	other data for ajax result
	*/

	function show_ajax_result($status, $text, $data = NULL)
	{
		if(is_bool($status))
		{
			$status = $status ? 'success' : 'error';
		}

		if($text)
		{
			$text = $this->i18n($text === TRUE ? 'ajax_'. $status : $text);
		}

		return print(json_encode(['status' => $status, 'text'=> $text, 'data' => $data ? $data : NULL], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
	}

	// Initialize database connection
	private function db_connect()
	{
		if(!$this->mysqli_status)
		{
			$this->mysqli = @new mysqli(empty($this->db_host) ? 'localhost' : $this->db_host, $this->db_user, $this->db_pass, $this->db_name);

			if(($this->mysqli_status = ($this->mysqli instanceof mysqli) && !$this->mysqli->connect_errno))
			{
				$this->mysqli->set_charset('utf8');
			}
		}

		return $this->mysqli_status;
	}

	// Add prefix to table name
	function db_table_prefix($table)
	{
		return (self::MYSQLI_PREFIX . $table);
	}

	// Get LAST_INSERT_ID() of last query
	function db_insert_id()
	{
		return $this->mysqli->insert_id;
	}

	// Routine for "$this->db_query()", "$this->db_query_result()", "$this->db_query_free()" thread
	function db_select($table, $column = NULL, $where = NULL, $opt = [])
	{
		$result = [];

		if($this->db_connect())
		{
			$query = $this->db_query($table, $column, $where, $opt);

			$result = $this->db_query_result($query, TRUE);

			$this->db_query_free($query);
		}

		return $result;
	}

	/*
		Generate and execute insert/update query

		$table		:	table name (without prefix)

		$data		:	['column' => 'value', ...]

		$where		:	"array"		-	['column' => 'value'
						"int"		-	by row id
						"string"	-	option like WHERE

		$time		:	custom lastmod timestamp

		onResult	:	bool (check "$this->mysqli->error" for result)
	*/

	function db_insert($table, $data, $where = NULL, $time = NULL)
	{
		$result = FALSE;

		if($this->db_connect() && !empty($data))
		{
			$query = ($where ? 'UPDATE' : 'INSERT INTO') .' `'. $this->db_table_prefix($table) .'` SET ';

			if(!$time)
			{
				$time = time();
			}

			$column_all = $this->db_table_schema($table);

			// on add
			if(!$where)
			{
				$data['timestamp'] = $time;
				$data['profile_id'] = $this->profile_id();

				// set to last pos
				if(isset($column_all['pos']) && !isset($data['pos']))
				{
					if(($pos = $this->mysqli->query('SELECT MAX(pos) AS `pos` FROM `'. $this->db_table_prefix($table) .'`'. (isset($column_all['parent_id']) ? ' WHERE `parent_id` = "'. (isset($data['parent_id']) ? $data['parent_id'] : 0) .'"' : NULL))) && ($pos = $pos->fetch_assoc()))
					{
						$data['pos'] = $pos['pos'] + 1;
					}
				}
			}

			// on edit
			$data['lastmod'] = $time;

			// query
			foreach($data as $key => $val)
			{
				if(isset($column_all[$key]))
				{
					if($key === 'id' || ($key === 'password' && empty($val)))
					{
						continue;
					}
					else if($key === 'media')
					{
						if(empty($val) || (isset($val[0]) && empty($val[0])))
						{
							continue;
						}
					}
					else if(strpos($key, 'date') !== FALSE)
					{
						if($val)
						{
							$val = strtotime($val); // html5 input date format (Y-m-d)
							$val = ($val !== FALSE ? $val + 86400 : 0);
						}
						else
						{
							$val = 0;
						}
					}
					else if($key === 'password')
					{
						$val = password_hash($val, self::PROFILE_ALGORITHM);
					}
					else if($key === 'parent_id')
					{
						if(!$val)
						{
							$val = NULL;
						}
					}

					if(is_array($val))
					{
						$val = array_unique($val);
						$val = array_filter($val);

//							sort($val, SORT_NUMERIC); // normalize key order

						$val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
					}

					$query .= '`'. $key .'` = '. ($val === NULL ? 'NULL' : '"'. $this->mysqli->real_escape_string($val) .'"') .',';
				}
			}

			unset($key, $val);

			$query = rtrim($query, ',');

			// where
			if($where)
			{
				switch(gettype($where))
				{
					case 'integer';
					{
						$query .= ' WHERE `id` = "'. $where .'"';
						break;
					}

					case 'array';
					{
						$query .= ' WHERE ';

						foreach($where as $key => $val)
						{
							if(isset($column_all[$key]))
							{
								$val = ($val === NULL ? 'is NULL' : '= "'. $val .'"');

								$query .= '`'. $key .'` '. $val .' AND ';
							}
						}

						unset($key, $val);

						$query = rtrim($query, 'AND ');

						break;
					}

					default:
					{
						$query .= ' '. $where;
						break;
					}
				}
			}

			$result = $this->mysqli->query($query) ? TRUE : FALSE;
		}

		return $result;
	}

	/*
		Get column value from table

		$table		:	table name (without prefix)
		$column		:	column name
		$where		:	see "$this->db_query()" reference
	*/

	function db_column_value($table, $column, $where)
	{
		$result = NULL;

		if(($query = $this->db_select($table, $column, $where)) && isset($query[0][$column]))
		{
			$result = $query[0][$column];
		}

		return $result;
	}

	/*
		Delete row by "id" from table

		$table		:	table name (without prefix)
		$id			:	row id
		$is_force	:	if FALSE and not logged in, return negative result

		onResult	:	BOOL
	*/

	function db_delete($table, $id, $is_force = FALSE)
	{
		if(!$this->db_connect() || (!$is_force && !$this->profile_is_authorized(1)))
		{
			return FALSE;
		}

		if($table == 'media')
		{
			if(($query = $this->db_select($table, 'md5', $id)))
			{
				foreach(glob($this->media_location($query[0]['md5'], '*')['path']) as $val)
				{
					unlink($val);
				}

				unset($val);
			}
		}

		return ($this->mysqli->query('DELETE FROM `'. $this->db_table_prefix($table) .'` WHERE `id` = '. $id) && $this->mysqli->affected_rows >= 1) ? TRUE : FALSE;
	}

	/*
		Generate selector and execute mysql query routine

		$table		:	table name (without prefix)

		$column		:	"array"		-	selected columns
						"string"	- 	single column

		$where		:	"array"		-	['column1' => value1, 'column2' => value2...]
						"int"		-	row id
						"string"	-	options like WHERE

		$opt[]		:	"array"		-	['order' => column name OR bool for random order, 'limit' => 1..9k]
						"string"	-	custom options

		onResult	:	NULL or "mysqli_result" class
	*/

	function db_query($table, $column = NULL, $where = NULL, $opt = [])
	{
		$result = NULL;

		if($this->db_connect())
		{
			$query = NULL;

			if(!$table && $where)
			{
				$query = $where;
			}
			else
			{
				$query = 'SELECT ';
				$column_all = $this->db_table_schema($table);

				// column
				if(!$column)
				{
					$query .= '*'; // select all
				}
				else
				{
					if($column && is_string($column))
					{
						$column = array($column);
					}

					if(is_array($column))
					{
						$column[] = 'id'; // select "id" always
						$column[] = 'parent_id'; // select "parent_id" always

						$column = array_unique($column); // clear duplicate columns

						foreach($column as $val)
						{
							if(isset($column_all[$val]))
							{
								$query .= '`'. $val .'`,';
							}
						}

						unset($val);

						$query = rtrim($query, ',');
					}
				}

				// from
				$query .= ' FROM `'. $this->db_table_prefix($table) .'`';

				// where
				if($where)
				{
					switch(gettype($where))
					{
						case 'integer';
						{
							$query .= ' WHERE `id` = "'. $where .'" AND ';
							break;
						}

						case 'array';
						{
							$query .= ' WHERE ';

							foreach($where as $key => $val)
							{
								if(isset($column_all[$key]))
								{
									$val = ($val === NULL ? 'is NULL' : '= "'. $val .'"');

									$query .= '`'. $key .'` '. $val .' AND ';
								}
							}

							unset($key, $val);

							break;
						}

						default:
						{
							$query .= ' '. $where .' AND';
							break;
						}
					}

					// opt
					if(empty($opt['with_hidden']))
					{
						$time = time();

						if(isset($column_all['show']))
						{
							$query .= ' `show` = 1 AND ';
						}

						if(isset($column_all['date_start']))
						{
							$query .= ' (`date_start` <= '. $time .' OR `date_start` = 0) AND ';
						}

						if(isset($column_all['date_stop']))
						{
							$query .= ' (`date_stop` >= '. $time .' OR `date_stop` = 0) AND ';
						}
					}

					$query = rtrim($query, 'AND ');
				}

				// order
				if(!isset($opt['order']))
				{
					if(isset($column_all['pos']))
					{
						$opt['order'] = 'pos';
					}
					else if(isset($column_all['timestamp']))
					{
						$opt['order'] = 'timestamp';
						$opt['order_desc'] = TRUE;
					}
				}

				if(isset($opt['order']) && strpos($query, 'ORDER BY') === FALSE)
				{
					$query .= ' ORDER BY '. ($opt['order'] === TRUE ? 'RAND()' : '`'. $opt['order'] .'`' . (!empty($opt['order_desc']) ? ' DESC' : NULL));
				}

				// limit
				if(isset($opt['limit']) && strpos($query, 'LIMIT') === FALSE)
				{
					$query .= ' LIMIT '. $opt['limit'];
				}
			}

			$result = $this->mysqli->query($query);
		}

		return $result;
	}

	/*
		Get result like "fetch_assoc()" function

		$query		:	"mysqli_result" class
		$is_full	:	fetch all or on-by-one

		onResult	:	array()
	*/

	function db_query_result($query, $is_full = FALSE)
	{
		$result = [];

		if($query instanceof mysqli_result)
		{
			// return all or one-by-one
			if($is_full)
			{
				while($row = $query->fetch_assoc())
				{
					$result[] = $row;
				}
			}
			else
			{
				$result = $query->fetch_assoc();
			}

			$this->db_normalize_types($result); // normalize types
		}

		return $result;
	}

	/*
		Get table row's count by selector

		$table		:	table name (without prefix) or "mysqli_result" result of "$this->db_query()"
		$where		:	option like WHERE

		onResult	:	NULL or number of rows
	*/

	function db_query_count($table, $where = NULL)
	{
		$result = NULL;

		if($table instanceof mysqli_result)
		{
			$result = $table->num_rows;
		}
		else
		{
			if($this->db_connect())
			{
				$row = $this->mysqli->query('SELECT COUNT(1) FROM `'. $this->db_table_prefix($table) .'` '. $where)->fetch_row();

				$result = empty($row[0]) ? NULL : (int)$row[0];
			}
		}

		return $result;
	}

	// Free memory for mysqli result
	function db_query_free($mysqli_result)
	{
		if($mysqli_result instanceof mysqli_result)
		{
			$mysqli_result->free();
		}
	}

	// INTERNAL USE ONLY

	private function db_normalize_types(&$result)
	{
		if($result)
		{
			foreach($result as $key => &$val)
			{
				if(is_array($val))
				{
					$this->db_normalize_types($val);
				}
				else if($val === '')
				{
					$val = NULL;
				}
				else if(ctype_digit($val))
				{
					$val = (int)$val;
				}
				else if($val && is_string($val))
				{
					if(($val[0] === '[' || $val[0] === '{') && ($j = @json_decode($val)))
					{
						$val = (array)$j;
					}
				}
			}

			unset($key, $val);
		}
	}

	// Get table column name defined as title
	function db_title_key($table)
	{
		foreach($this->column_title as $val)
		{
			if($this->db_row_exists($table, $val))
			{
				return $val;
			}
		}

		unset($val);

		return NULL;
	}

	/*
		Check column/row existing

		$table		:	table name (without prefix)
		$column		:	column value
		$value		:	row value (if NULL then checking only column exist)

		onResult	:	BOOL
	*/

	function db_row_exists($table, $column, $value = NULL)
	{
		$result = FALSE;

		if($this->db_connect())
		{
			$table = $this->db_table_prefix($table);
			$selector = $value ? ('SELECT 1 FROM `'. $table .'` WHERE `'. $column .'` = "'. $value .'" LIMIT 1') : ('SHOW COLUMNS FROM `'. $table .'` LIKE "'. $column .'"');

			$result = @$this->mysqli->query($selector)->num_rows ? TRUE : FALSE;
		}

		return $result;
	}

	// Get table's list from database
	function db_table_list($bShowAll = TRUE)
	{
		$result = [];

		if($this->db_connect())
		{
			if($query = $this->db_query(NULL, NULL, 'SHOW TABLES FROM '. $this->db_name))
			{
				while(($row = $this->db_query_result($query)))
				{
					$str = reset($row);

					if(defined('self::MYSQLI_PREFIX') && is_string($str))
					{
						$str = substr($str, strlen(self::MYSQLI_PREFIX));
					}

					if(!$bShowAll && in_array($str, $this->table_hidden))
					{
						continue;
					}

					$result[] = $str;
				}

				$this->db_query_free($query);
			}
		}

		return $result;
	}

	// Get column's list from table and extended information (optional)
	function db_table_schema($table)
	{
		$result = [];

		if($this->db_connect())
		{
			if($query = $this->db_query(NULL, NULL, 'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = "'. $this->db_table_prefix($table) .'"'))
			{
				while(($row = $this->db_query_result($query)))
				{
					if(!empty($row['CHARACTER_MAXIMUM_LENGTH']))
					{
						$row['CHARACTER_MAXIMUM_LENGTH'] = (int)$row['CHARACTER_MAXIMUM_LENGTH'];
					}

					$result[$row['COLUMN_NAME']] = $row;
				}

				$this->db_query_free($query);
			}
		}

		return $result;
	}

	// Encode cookie value for login session
	private function profile_cookie_encode($id, $password_hash, $expiration)
	{
		return $id .'@'. $expiration .'@'. hash_hmac('md5', $id . $expiration . $password_hash, $expiration);
	}

	// Decode cookie value for login session
	private function profile_cookie_decode()
	{
		if(!empty($_COOKIE[self::PROFILE_COOKIE]) && ($array = explode('@', $_COOKIE[self::PROFILE_COOKIE])) && count($array) >= 3)
		{
			return [
				'id' => (int)$array[0],
				'expiration' => (int)$array[1],
				'hash' => $array[2],
			];
		}

		return NULL;
	}

	/*
		Get logged in profile access level:

		"0"		:	user
		"1"		:	editor
		"777"	:	administrator
	*/

	private function profile_access_level($id)
	{
		if(($type = $this->profile_get('type', $id)))
		{
			if($type === 'type_admin')
			{
				return 777;
			}
			else if($type === 'type_editor')
			{
				return 1;
			}
		}

		return 0;
	}

	// Get logged in profile ID
	function profile_id()
	{
		$cookie = NULL;

		if($this->profile_is_authorized(0, $cookie))
		{
			return $cookie['id'];
		}

		return NULL;
	}

	/*
		Create login session

		$email		:	client email
		$password	:	client password
		$min_level	:	minimum request privilege level (see "$this->profile_access_level")

		onResult	:	BOOL
	*/

	function profile_login($email, $password, $min_level = 0)
	{
		$real_id = $this->profile_get('id', $email);
		$real_password = $this->profile_get('password', $email);

		if($this->profile_access_level($real_id) >= $min_level && password_verify($password, $real_password))
		{
			$expiration = time() + self::PROFILE_EXPIRATION;

			@setcookie(self::PROFILE_COOKIE, $this->profile_cookie_encode($real_id, $real_password, $expiration), $expiration, '/', NULL, FALSE, TRUE);

			return TRUE;
		}

		return FALSE;
	}

	// Get da f__k out from server (logout)
	function profile_logout()
	{
		@setcookie(self::PROFILE_COOKIE, 0, time() + 1, '/', NULL, FALSE, TRUE);

		unset($_COOKIE[self::PROFILE_COOKIE]);
	}

	// Check profile is logged in
	function profile_is_authorized($min_level, &$cookie = NULL)
	{
		if(($cookie = $this->profile_cookie_decode()) && $this->profile_access_level($cookie['id']) >= $min_level)
		{
			$password_hash = $this->profile_get('password', $cookie['id']);

			// check expiration and hash
			if($cookie['expiration'] > time() && strcmp($_COOKIE[self::PROFILE_COOKIE], $this->profile_cookie_encode($cookie['id'], $password_hash, $cookie['expiration'])) === 0)
			{
				$expiration = time() + self::PROFILE_EXPIRATION;

				@setcookie(self::PROFILE_COOKIE, $this->profile_cookie_encode($cookie['id'], $password_hash, $expiration), $expiration, '/', NULL, FALSE, TRUE);

				return TRUE;
			}

			$this->profile_logout();
		}

		$cookie = NULL; // authorized, but no rights

		return FALSE;
	}

	// Create new profile
	function profile_add($data = [])
	{
		if($this->db_row_exists('profile', 'email', $data['email']))
		{
			return FALSE;
		}

		if(empty($data['type']))
		{
			$data['type'] = (!$this->db_row_exists('profile', 'type', 'type_admin')) ? 'type_admin' : 'type_user';
		}

		return ($this->db_insert('profile', $data, NULL)) ? TRUE : FALSE;
	}

	/*
		Get profile information

		$key	:	profile "name", "email" and other information
		$id		:	profile "id" OR "email"
	*/

	function profile_get($key, $id = NULL)
	{
		if(!$id)
		{
			$id = $this->profile_id(); // by id
		}
		else if(is_string($id))
		{
			$id = $this->db_column_value('profile', 'id', ['email' => $id]); // by email
		}

		return $id ? $this->db_column_value('profile', $key, $id) : NULL;
	}

	/*
		Get media location (directory, path, url)

		$md5		:	md5 of media
		$extension	:	extension of media
		$thumbnail	:	option for thumbnail (optional)

		onResult	:	['dir' => media directory, 'path' => media path, 'url' => media url]
	*/

	private function media_location($md5, $extension, $thumbnail = NULL)
	{
		$subdir = substr($md5, 0, 3) .'/'. substr($md5, 3, 3);

		if($extension)
		{
			if($extension[0] !== '*')
			{
				$extension = '.' . $extension;
			}

			$filename = substr($md5, 6) . ($thumbnail ? '_'. $thumbnail : NULL) . $extension;
		}

		return [
			'dir'	=> $this->get_root_dir() .'/'. self::MEDIA_DIRECTORY .'/'. $subdir,
			'path'	=> ($extension ? $this->get_root_dir() .'/'. self::MEDIA_DIRECTORY .'/'. $subdir .'/'. $filename : NULL),
			'url'	=> ($extension ? $this->get_url() .'/'. self::MEDIA_DIRECTORY .'/'. $subdir .'/'. $filename : NULL),
		];
	}

	/*
		Check media file is allowed for uploading by extension, use it before uploading

		$extension	:	file extension

		onResult	:	NULL or type of media
	*/

	private function media_is_allowed($extension)
	{
		$result = NULL;

		foreach($this->media_extension as $key => $val)
		{
			$val = array_flip($val);

			if(isset($val[$extension]))
			{
				$result = $key;
				break;
			}
		}

		unset($key, $val);

		return $result;
	}

	/*
		Check media is exists in database by MD5

		$md5		:	md5 of checking file

		onResult	:	NULL or existing ID
	*/

	private function media_is_exists($md5)
	{
		$result = NULL;

		if($this->db_connect())
		{
			if(($query = $this->db_select('media', ['md5', 'extension'], ['md5' => $md5])))
			{
				if(file_exists($this->media_location($query[0]['md5'], $query[0]['extension'])['path']))
				{
					$result = (int)$query[0]['id'];
				}
				else
				{
					$this->db_delete('media', $query[0]['id'], TRUE);
				}
			}
		}

		return $result;
	}

	/*
		Get media URL from database table by ID

		$data		:	"array"		-	multiple media array by id
						"int"		-	single media by id
						"string"	-	multiple media by type

		$type		:	thumbnail type

		onResult	:	array()
	*/

	function media_get($data, $type = NULL)
	{
		$result = [];

		if(!$type)
		{
			$type = key($this->media_thumbnail);
		}

		if(!empty($data) && $this->db_connect())
		{
			$where = NULL;

			if(is_numeric($data))
			{
				$where = $data;
			}
			else if(is_string($data))
			{
				$where = ['type' => $data];
			}
			else if(is_array($data))
			{
				$data = array_filter($data);
				$data = implode(',', $data);

				$where = 'WHERE `id` IN ('. $data .') ORDER BY FIND_IN_SET(`id`, "'. $data .'")'; // sort order fix
			}

			foreach($this->db_select('media', NULL, $where) as $val)
			{
				$location = $this->media_location($val['md5'], $val['extension']);

				if(file_exists($location['path']))
				{
					if($val['type'] === 'image')
					{
						$location_t = $this->media_location($val['md5'], $val['extension'], $type);

						if(!file_exists($location_t['path']))
						{
							$this->media_image_process($val['md5'], $val['extension']);
						}
					}
					else
					{
						$location_t = ['url' => 'http://placehold.it/200x200/'. substr(dechex(crc32($val['type'])), 0, 6) .'/ffffff&text='. $val['type']];
					}

					$result[] = [
						'id' => $val['id'],
						'timestamp' => $val['timestamp'],
						'title' => $val['title'],
						'url' => $location['url'],
						'thumbnail' => $location_t['url'],
					];
				}
			}

			unset($val);
		}

		return $result;
	}

	/*
		Upload a single file

		$src		:	"$_FILES['tmp_name']" value
		$filename	:	"$_FILES['name']" value

		onResult	:	NULL or inserted ID
	*/

	private function media_upload_single($src, $filename)
	{
		$result = NULL;

		if(strstr($filename, '..') === FALSE && is_uploaded_file($src) && $this->db_connect())
		{
			if(($extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION))) && ($type = $this->media_is_allowed($extension)))
			{
				// check duplicate by md5
				$md5 = md5_file($src);

				if(($id = $this->media_is_exists($md5)) !== NULL)
				{
					return $id; // return existing media ID
				}

				// check target directory existing
				$location = $this->media_location($md5, $extension);

				if(!file_exists($location['dir']))
				{
					mkdir($location['dir'], self::MEDIA_CHMOD, TRUE);
				}

				// move media file
				if(move_uploaded_file($src, $location['path']))
				{
					// optimization
					if($type === 'image')
					{
						$this->media_image_process($md5, $extension);
					}

					$this->db_insert('media', ['title' => pathinfo($filename, PATHINFO_FILENAME), 'type' => $type, 'md5' => $md5, 'extension' => $extension]);

					$result = $this->db_insert_id();
				}
			}
		}

		return $result;
	}

	/*
		Upload media files

		$table		:	table name (without prefix)
		$files		:	$_FILES array
		$id			:	id
	*/

	function media_upload($files, $table, $id)
	{
		$result = [];

		if($this->db_connect())
		{
			foreach($files as $key => $val)
			{
				if((is_array($val['name']) && ($count = count($val['name']))))
				{
					// Multiple
					for($i = 0; $i < $count; $i++)
					{
						if(($media_id = $this->media_upload_single($val['tmp_name'][$i], $val['name'][$i])) !== NULL)
						{
							$result[] = $media_id;
						}
					}

					if($id && !empty($result))
					{
						if(($media_prev = $this->db_column_value($table, $key, $id)) && is_array($media_prev))
						{
							$result = array_merge($media_prev, $result);
						}

						$this->db_insert($table, [$key => $result], $id);
					}
				}
				else if(!empty($val['name']))
				{
					// Single
					if(($result[0] = $this->media_upload_single($val['tmp_name'], $val['name'])) !== NULL && $id)
					{
						$this->db_insert($table, [$key => $result[0]], $id);
					}
				}
			}

			unset($val, $key);
		}

		return $result;
	}

	/*
		Optimize image and create thumbnail
		https://gist.github.com/jasdeepkhalsa/4339969

		$md5		:	see "$this->media_location()" reference
		$extension	:	see "$this->media_location()" reference

		onResult	:	BOOL
	*/

	function media_image_resize($hImg, $iWidth, $iHeight, $iNewWidth, $iNewHeight)
	{
		if($hImg)
		{
			$iRatio = $iWidth / $iHeight;

			if(!$iNewWidth && $iNewHeight)
			{
				$iNewWidth = round($iNewHeight * $iRatio);
			}
			else if(!$iNewHeight && $iNewWidth)
			{
				$iNewHeight = round($iNewWidth / $iRatio);
			}

			if($iRatio >= ($iNewWidth / $iNewHeight))
			{
				// image is wider than thumbnail (in aspect ratio sense)
				$iResultHeight = $iNewHeight;
				$iResultWidth = $iWidth / ($iHeight / $iNewHeight);
			}
			else
			{
				// thumbnail is wider than the image
				$iResultWidth = $iNewWidth;
				$iResultHeight = $iHeight / ($iWidth / $iNewWidth);
			}

			// resize and crop
			if(($hImgResized = imagecreatetruecolor($iNewWidth, $iNewHeight)) && imagealphablending($hImgResized, FALSE) && imagesavealpha($hImgResized, TRUE) && imagecopyresampled($hImgResized, $hImg, 0 - ($iResultWidth - $iNewWidth) / 2, 0 - ($iResultHeight - $iNewHeight) / 2, 0, 0, $iResultWidth, $iResultHeight, $iWidth, $iHeight))
			{
				return $hImgResized;
			}
		}

		return NULL;
	}

	private function media_image_process($md5, $extension)
	{
		$aLocation = $this->media_location($md5, $extension);

		if(($iType = @exif_imagetype($aLocation['path'])))
		{
			// load image
			if($iType === IMAGETYPE_JPEG)
			{
				$hImg = imagecreatefromjpeg($aLocation['path']);
			}
			else if($iType === IMAGETYPE_PNG)
			{
				$hImg = imagecreatefrompng($aLocation['path']);
			}
			else if($iType === IMAGETYPE_GIF)
			{
				$hImg = imagecreatefromgif($aLocation['path']);
			}
			else
			{
				return FALSE;
			}

			// get image info
			$iWidth = imagesx($hImg);
			$iHeight = imagesy($hImg);

			// limit image width
			if($iWidth > self::MEDIA_MAX_WIDTH)
			{
				if($hNewImg = $this->media_image_resize($hImg, $iWidth, $iHeight, self::MEDIA_MAX_WIDTH, 0))
				{
					imagedestroy($hImg);

					$hImg = $hNewImg;

					$iWidth = imagesx($hImg);
					$iHeight = imagesy($hImg);
				}
			}

			// create thumbnail's
			foreach($this->media_thumbnail as $key => $val)
			{
				$aThumbLocation = $this->media_location($md5, $extension, $key);

				list($iThumbWidth, $iThumbHeight) = $val;

				if(($hThumb = $this->media_image_resize($hImg, $iWidth, $iHeight, $iThumbWidth, $iThumbHeight)))
				{
					if($iType === IMAGETYPE_JPEG)
					{
						imagejpeg($hThumb, $aThumbLocation['path'], self::MEDIA_QUALITY_JPEG);
					}
					else if($iType === IMAGETYPE_GIF)
					{
						imagegif($hThumb, $aThumbLocation['path']);
					}
					else if($iType === IMAGETYPE_PNG)
					{
						imagepng($hThumb, $aThumbLocation['path'], self::MEDIA_QUALITY_PNG, PNG_ALL_FILTERS);
					}

					imagedestroy($hThumb);
				}
			}

			unset($key, $val);

			// save image
			if($iType === IMAGETYPE_JPEG)
			{
				imagejpeg($hImg, $aLocation['path'], self::MEDIA_QUALITY_JPEG);
			}
			else if($iType === IMAGETYPE_GIF)
			{
				imagegif($hImg, $aLocation['path']);
			}
			else if($iType === IMAGETYPE_PNG)
			{
				imagealphablending($hImg, FALSE);
				imagesavealpha($hImg, TRUE);

				imagepng($hImg, $aLocation['path'], self::MEDIA_QUALITY_PNG, PNG_ALL_FILTERS);
			}

			imagedestroy($hImg);

			return TRUE;
		}

		return FALSE;
	}

	/*
		Get config value from database

		$key			:	config key (if not set return full config array)
		$default_val	:	if value doesn't exists, return this data

		onResult		:	string OR $default_val on fail
	*/

	function get_cfg($key, $default_val = NULL)
	{
		$result = $this->db_column_value('config', 'val', ['key' => $key]);

		return $result ? htmlspecialchars($result) : $default_val;
	}

	/*
		Save config value to database

		$key		:	config key
		$value		:	config value

		onResult	:	BOOL
	*/

	function set_cfg($key, $val)
	{
		$result = FALSE;

		if($this->db_connect())
		{
			$val = $this->mysqli->real_escape_string($val);

			$result = $this->mysqli->query('INSERT INTO `'. $this->db_table_prefix('config') .'` SET `key` = "'. $key .'", `val` = "'. $val .'" ON DUPLICATE KEY UPDATE `val` = "'. $val .'"') ? TRUE : FALSE;
		}

		return $result;
	}

	// INTERNAL USE ONLY

	function tourl($table, $id, $bFullUrl = TRUE)
	{
		$result = NULL;

		if($table !== 'page')
		{
			if(!($page_id = $this->db_column_value('page', 'id', ['children' => $table])))
			{
				return $result;
			}

			$result = $this->tourl('page', $page_id, FALSE);
		}

		if(($query = $this->db_select($table, ['parent_id', 'url'], $id)))
		{
			if(!empty($query[0]['parent_id']))
			{
				// append parent url
				$result = $this->tourl($table, $query[0]['parent_id'], FALSE) .'/'. $query[0]['url'];
			}
			else
			{
				// append http://domain.name
				$result = $result .'/'. (!empty($query[0]['url']) ? $query[0]['url'] : '?id='. $query[0]['id']);
			}
		}

		return $result ? (($bFullUrl ? $this->get_url() : NULL) . $result) : NULL;
	}

	// INTERNAL USE ONLY

	function get_page($column = NULL, $url = NULL, &$breadcrumb = NULL)
	{
		$result = [];
		$children_id = NULL;

		if(!$url)
		{
			$url = $_SERVER['REQUEST_URI'];
		}

		if(($children_id = parse_url($url, PHP_URL_QUERY)))
		{
			parse_str($children_id, $children_id);

			if (array_key_exists ('id', $children_id))
			{
				$children_id = (int)($children_id['id']);
			}
		}

		if(($path = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'))))
		{
			$table = 'page';
			$title_key = $this->db_title_key($table);
			$pid = NULL;

			foreach($path as $key => $val)
			{
				if(($query = $this->db_select($table, ['url', 'children', $title_key], ['url' => $val, 'parent_id' => $pid], ['limit' => 1])))
				{
					// breadcrumb
					if(!is_null($breadcrumb))
					{
						$breadcrumb[] = ['title' => $query[0][$title_key], 'url' => $this->tourl($table, $query[0]['id'])];
					}

					if(empty($path[$key + 1]))
					{
						// id-fix
						if($children_id && !empty($query[0]['children']))
						{
							$table = $this->db_column_value($table, 'children', $query[0]['id']);
							$title_key = $this->db_title_key($table);

							$query[0]['id'] = $children_id;

							$breadcrumb[] = ['title' => $this->db_column_value($table, $title_key, $query[0]['id']), 'url' => $this->tourl($table, $query[0]['id'])]; // breadcrumb
						}

						$column = (array)$column;

						$column[] = $title_key;
						$column[] = 'title_html';
						$column[] = 'description';
						$column[] = 'text';

						$query2 = $this->db_select($table, $column, $query[0]['id']);

						$result['table'] = $table;

						$result['title'] = isset($query2[0][$title_key]) ? $query2[0][$title_key] : NULL;
						$result['title_html'] = !empty($query2[0]['title_html']) ? $query2[0]['title_html'] : $result['title'];

						$result['description'] = isset($query2[0]['description']) ? $query2[0]['description'] : NULL;
						$result['text'] = isset($query2[0]['text']) ? $query2[0]['text'] : NULL;

						foreach($column as $v)
						{
							if(!isset($result[$v]))
							{
								$result[$v] = isset($query2[0][$v]) ? $query2[0][$v] : NULL; // no-replace existing values
							}
						}

						unset($v);

						if(!empty($result['media']))
						{
							$result['media'] = $this->media_get($result['media']);
						}

						break;
					}

					$pid = $query[0]['id'];

					if(!empty($query[0]['children']))
					{
						$table = $query[0]['children'];
						$title_key = $this->db_title_key($table);
					}
				}
			}

			unset($key, $val);

			return $result;
		}

		return $path;
	}

	/*
		$table		:	table name (without prefix)
		&$result	:	result variable pointer

		$opt[
			$column,		// see: "$this->query()" reference
			$where,			// see: "$this->query()" reference

			$with_hidden	// retrieve hidden elements include
			$with_info,		// retrieve "id", "timestamp", "lastmod" in result
			$with_title_h,	// retrieve "title_html" in result
			$with_text,		// retrieve "description", "text" in result
			$with_url,		// retrieve full url
			$with_media,	// retrieve "media" array in result

			$no_child,		// doesn't retrieve children tables

			$depth,			// max tree depth

			$media_type,	// type for thumbnail (see: "$this->media_get()" reference)
		];

		$pid, $depth	:	INTERNAL USE ONLY
	*/

	function generate_tree($table, &$result, $opt = NULL, $pid = NULL, $depth = 0)
	{
		// check for errors
		if(isset($opt['depth']) && ($opt['depth'] < $depth))
		{
			return FALSE;
		}

		$title_key = $this->db_title_key($table);
		$has_pid = $this->db_row_exists($table, 'parent_id');
		$has_child = $this->db_row_exists($table, 'children');
/*
		// visible only
		if(!isset($opt['with_hidden']))
		{
			$opt['with_hidden'] = FALSE;
		}
*/
		// columns
		$column = [$title_key];

		if(!empty($opt['with_info']))		{$column[] = 'timestamp'; $column[] = 'lastmod';}
		if(!empty($opt['with_title_h']))	{$column[] = 'title_html';}
		if(!empty($opt['with_text']))		{$column[] = 'description'; $column[] = 'text';}
		if(!empty($opt['with_url']))		{$column[] = 'url';}
		if(!empty($opt['with_media']))		{$column[] = 'media';}
		if(empty($opt['no_child']))			{$column[] = 'children';}
		if(!isset($opt['media_type']))		{$opt['media_type'] = NULL;}

		if(!empty($opt['column']))
		{
			foreach((array)$opt['column'] as $val)
			{
				$column[] = $val;
			}

			unset($val);
		}

		// where
		$where = isset($opt['where']) ? $opt['where'] : ($has_pid ? ['parent_id' => $pid] : NULL);
		unset($opt['where']); // fix for children

		$query = $this->db_query($table, $column, $where, $opt);
		$i = 0;

		while(($row = $this->db_query_result($query)))
		{
			// title
			$result[$i]['title'] = $row[$title_key];

			// info
			if(!empty($opt['with_info']))
			{
				$result[$i]['id'] = $row['id'];
				$result[$i]['lastmod'] = isset($row['lastmod']) ? $row['lastmod'] : NULL;
				$result[$i]['timestamp'] = isset($row['timestamp']) ? $row['timestamp'] : NULL;
			}

			// title (html)
			if(!empty($opt['with_title_h']))
			{
				$result[$i]['title_html'] = !empty($row['title_html']) ? $row['title_html'] : $result[$i]['title'];
			}

			// text
			if(!empty($opt['with_text']))
			{
				$result[$i]['description'] = isset($row['description']) ? $row['description'] : NULL;
				$result[$i]['text'] = isset($row['text']) ? $row['text'] : NULL;
			}

			// url
			if(!empty($opt['with_url']))
			{
				$result[$i]['url'] = $this->tourl($table, $row['id']);
			}

			// media
			if(!empty($opt['with_media']))
			{
				$result[$i]['media'] = !empty($row['media']) ? $this->media_get($row['media'], $opt['media_type']) : NULL;
			}

			// another column
			if(!empty($opt['column']))
			{
				foreach((array)$opt['column'] as $val)
				{
					if(!isset($result[$i][$val]))
					{
						$result[$i][$val] = isset($row[$val]) ? $row[$val] : NULL; // no-replace existing values
					}
				}

				unset($val);
			}

			// child
			$result[$i]['data'] = NULL;

			if($has_pid || $has_child)
			{
				$child = empty($opt['no_child']) && !empty($row['children']);
				$this->generate_tree($child ? $row['children'] : $table, $result[$i]['data'], $opt, $child ? NULL : $row['id'], $depth + 1);
			}

			$i += 1;
		}

		$this->db_query_free($query);

		return TRUE;
	}

	// INTERNAL USE ONLY

	function print_ul($table, &$array = NULL)
	{
		if(!$array)
		{
			$this->generate_tree($table, $array, ['with_url' => TRUE]);
		}

		if($array)
		{
			print('<ul>');

			foreach($array as $val)
			{
				print('<li><a href="'. $val['url'] .'">'. $val['title'] .'</a>');

				if($val['data'])
				{
					$this->print_ul(NULL, $val['data']);
				}

				print('</li>');
			}

			unset($val);

			print('</ul>');
		}
	}

	// INTERNAL USE ONLY

	private function form_print_title($name, $is_required)
	{
		printf('<label%s>%s:</label>', $is_required ? ' class="required"' : NULL, $this->i18n($name));
	}

	// INTERNAL USE ONLY

	function form_print_select($table, $value = NULL, $bIsOptGroup = FALSE, $bIsMultiple = FALSE, $bPrintDefault = TRUE)
	{
		if(is_array($table))
		{
			if($bPrintDefault && !$bIsMultiple)
			{
				printf('<option value="0">%s</option>', is_bool($bPrintDefault) ? $this->i18n('default') : $bPrintDefault);
			}

			foreach($table as $val)
			{
				$bHasChild = !empty($val['data']);

				if($bIsOptGroup && $bHasChild)
				{
					printf('<optgroup label="%s">', $val['title']);
				}
				else
				{
					printf('<option value="%s"%s>%s</option>', $val['id'], $value == $val['id'] ? ' selected': NULL, $val['title']);
				}

				if($bHasChild)
				{
					$this->form_print_select($val['data'], $value, $bIsOptGroup, $bIsMultiple, FALSE);
				}

				if($bIsOptGroup && $bHasChild)
				{
					print('</optgroup>');
				}
			}

			unset($val);
		}
		else
		{
			$array = [];

			$this->generate_tree($table, $array, ['with_info' => TRUE]);

			$table = $array;

			unset($array);

			$this->form_print_select($table, $value, $bIsOptGroup, $bIsMultiple, $bPrintDefault);
		}
	}

	// INTERNAL USE ONLY

	/*
		by column name:
		- id, parent_id				(hidden)
		- media						(TEXT for multiple, INT for single MediaID)
		- table						(VARCHAR 16-32)
		- *date*					(INT for Unix Timestamp)
		- *_id						(INT ID of other table)

		by column type:
		- text, varchar >= 256		(textarea)
		- varchar >= 64 && < 256	(input)
		- varchar >= 16 && <= 32	(select)
		- int						(input numeric)
		- tinyint					(input checkbox)
	*/

	// UNUSED
	/*
	function form_generate($table, &$result = NULL)
	{
		printf('<form class="form ajax" method="post" action="ajax.php?action=data_save&table=%s" enctype="multipart/form-data" data-action="close" data-table="%s"><menu type="toolbar" class="menu flex"><li><button type="submit" class="btn"><i class="fa fa-save"></i> %s</button></li><li><button class="bg-red btn" type="button" data-action="close"><i class="fa fa-times"></i> %s</button></li></menu><hr />', $table, $table, $this->i18n('button_save'), $this->i18n('button_close'));

		// editable data
		foreach($this->db_table_schema($table) as $key => $val)
		{
			if(!isset($this->column_internal[$key]))
			{
				$is_required = in_array($key, $this->column_required);

				if($key === 'media')
				{
					// Media
					$is_multiple = $val['DATA_TYPE'] !== 'int' ? TRUE : FALSE;

					echo '<hr />';

					$this->form_print_title($key, $is_required);

					$accept = NULL;

					foreach($this->media_extension as $mt)
					{
						foreach($mt as $me)
						{
							$accept .= '.'. $me .',';
						}

						unset($me);
					}

					unset($mt);

					printf('<input name="%s%s" type="file" accept="%s"%s /><p>%s</p>', $key, $is_multiple ? '[]' : NULL, substr($accept, 0, -1), $is_multiple ? ' multiple' : NULL, sprintf($this->i18n('upload_max_filesize'), upload_max_size()));
				}
				else if($key === 'children')
				{
					$this->form_print_title($key, $is_required);

					printf('<select name="%s">', $key);

					printf('<option value="0">%s</option>', $this->i18n('default'));

					foreach($this->db_table_list() as $val)
					{
						if(in_array($val, $this->table_internal))
						{
							continue;
						}

						printf('<option value="%s">%s</option>', $val, $this->i18n($val));
					}

					unset($val);

					printf('</select>');
				}
				else if(strstr($key, '_id') !== FALSE)
				{
					// Id of other table
					$table_key = substr($key, 0, -3);
					$bIsMultiple = $val['DATA_TYPE'] !== 'int';

					$this->form_print_title($table_key, $is_required);

					printf('<select name="%s"%s>', $bIsMultiple ? $key .'[]' : $key, $bIsMultiple ? ' multiple' : NULL);

					$this->form_print_select($table_key, NULL, FALSE, $bIsMultiple);

					printf('</select>');
				}
				else if(strstr($key, 'date') !== FALSE)
				{
					// Date
					$this->form_print_title($key, $is_required);

					printf('<input name="%s" type="date" />', $key, $key);
				}
				else if($val['DATA_TYPE'] === 'text' || ($val['DATA_TYPE'] === 'varchar' && $val['CHARACTER_MAXIMUM_LENGTH'] >= 256))
				{
					// Textarea
					$this->form_print_title($key, $is_required);

					printf('<textarea name="%s"%s%s></textarea>', $key, $val['DATA_TYPE'] === 'varchar' ? NULL : ' class="redactor"', $val['CHARACTER_MAXIMUM_LENGTH'] ? ' maxlength="'. $val['CHARACTER_MAXIMUM_LENGTH'] .'"' : NULL);
				}
				else if($val['DATA_TYPE'] === 'varchar')
				{
					if($val['CHARACTER_MAXIMUM_LENGTH'] >= 16 && $val['CHARACTER_MAXIMUM_LENGTH'] <= 32)
					{
						// Select
						$this->form_print_title($key, $is_required);

						printf('<select name="%s">', $key);

						if(!empty($this->predefine[$table][$key]) && is_array($this->predefine[$table][$key]))
						{
							foreach($this->predefine[$table][$key] as $k => $v)
							{
								printf('<option value="%s">%s</option>', $v, $this->i18n($v) . (!is_int($k) ? ' ('. $k .')' : NULL));
							}
						}

						printf('</select>');
					}
					else if($val['CHARACTER_MAXIMUM_LENGTH'] >= 64 && $val['CHARACTER_MAXIMUM_LENGTH'] < 256)
					{
						// Input
						$this->form_print_title($key, $is_required);

						if($key === 'email')
						{
							$type = $key;
						}
						else if($key === 'password')
						{
							$type = $key;
						}
						else
						{
							$type = 'text';
						}

						$pattern = NULL;

						if($key === 'url')
						{
							$pattern = 'pattern="^[a-z0-9-_]+$"';
						}
						else if($key === 'url_link')
						{
							$pattern = 'pattern="^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$"';
						}

						printf('<input name="%s" type="%s" maxlength="%s" %s%s />', $key, $type, $val['CHARACTER_MAXIMUM_LENGTH'], $pattern, $is_required ? ' required' : NULL);
					}
				}
				else if($val['DATA_TYPE'] === 'tinyint')
				{
					// Checkbox
					$id = uniqid($key);

					// checkbox-fix
					printf('<input type="hidden" name="%s" value="0" /><input id="%s" type="checkbox" name="%s" value="1" %s /><label for="%s">%s</label>', $key, $id, $key, $val['COLUMN_DEFAULT'] ? ' checked' : NULL, $id, $this->i18n($key));
				}
				else if($val['DATA_TYPE'] === 'int')
				{
					// Number
					$this->form_print_title($key, $is_required);

					printf('<input name="%s" type="number" />', $key);
				}
			}
			else if(in_array($key, ['id', 'parent_id']))
			{
				// Hidden
				printf('<input name="%s" type="hidden" value="0" />', $key);
			}
		}

		unset($key, $val);

		print('<hr /><div class="infobar"><div><i class="fa fa-fw fa-info-circle"></i>: <span data-internal="id"></span></div><div><i class="fa fa-fw fa-calendar"></i>: <time data-internal="timestamp"></time></div><div><i class="fa fa-fw fa-pencil"></i>: <time data-internal="lastmod"></time></div></div>');

		print('</form>');
	}
	*/

	// Write debug information to file

	// UNUSED
	/*
	function dbg_print($obj)
	{
		if($f = fopen($_SERVER['DOCUMENT_ROOT'] . '/debug.log', 'a'))
		{
			fwrite($f, print_r($obj, TRUE));
			fwrite($f, PHP_EOL . PHP_EOL);
			fclose($f);
		}
	}
	*/
}
?>