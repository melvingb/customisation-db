<?php
/**
*
* @package Titania
* @version $Id$
* @copyright (c) 2008 phpBB Customisation Database Team
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_TITANIA'))
{
	exit;
}

/**
 * titania class and functions for use within titania pages and apps.
 */
class titania
{
	/**
	 * Current viewing page location
	 *
	 * @var string
	 */
	public static $page;

	/**
	 * Titania configuration
	 *
	 * @var titania_config
	 */
	public static $config;
	
	/*
	 * Instance of titania_cache class
	 *
	 * $var titania_cache
	 */
	public static $cache;

	/*
	 * Initialise titania:
	 *	Session management, Cache, Language ...
	 *
	 * @return void
	 */
	public static function initialise()
	{
		global $user, $auth, $template;

		// Start session management
		$user->session_begin();
		$auth->acl($user->data);
		$user->setup();

		self::$page = $user->page['script_path'] . $user->page['page_name'];

		// Instantiate cache
		if (!class_exists('titania_cache'))
		{
			include(TITANIA_ROOT . 'includes/titania_cache.' . PHP_EXT);
		}
		self::$cache = new titania_cache();

		// Set template path and template name
		$template->set_custom_template(titania::$config->template_path, 'titania');

		// Add common titania language file
		self::add_lang('common');
	}

	/**
	 * Reads a configuration file with an assoc. config array
	 *
	 * @param string $file	Path to configuration file
	 */
	public static function read_config_file($file)
	{
		if (!file_exists($file) || !is_readable($file))
		{
			echo '<p>';
			echo '	The titania configuration file could not be found or is inaccessible. Check your configuration.';
			echo '	<br />';
			echo '	To install titania you have to rename config.example.php to config.php and adjust it to your needs.';
			echo '</p>';

			exit;
		}

		require($file);

		if (!isset(self::$config))
		{
			if (!class_exists('titania_config'))
			{
				require(TITANIA_ROOT . 'includes/titania_config.' . PHP_EXT);
			}

			self::$config = new titania_config();
		}

		if (!is_array($config))
		{
			$config = array();
		}

		self::$config->read_array($config);
	}

	/**
	 * Add a phpBB language file
	 *
	 * @param mixed $lang_set
	 * @param bool $use_db
	 * @param bool $use_help
	 */
	public static function add_lang($lang_set, $use_db = false, $use_help = false)
	{
		global $user;

		$old_path = $user->lang_path;

		$user->set_custom_lang_path(self::$config->language_path);
		$user->add_lang($lang_set, $use_db, $use_help);

		$user->set_custom_lang_path($old_path);
	}

	/**
	 * Titania page_header
	 *
	 * @param string $page_title
	 * @param bool $display_online_list
	 */
	public static function page_header($page_title = '', $display_online_list = false)
	{
		global $template, $user;

		// Check if page_title is a language string
		if (isset($user->lang[$page_title]))
		{
			$page_title = $user->lang[$page_title];
		}

		// Call the phpBB page_header() function, but we perform our own actions here as well.
		page_header($page_title, $display_online_list);

		if ($user->data['user_id'] == ANONYMOUS)
		{
			$u_login_logout = $template->_rootref['U_LOGIN_LOGOUT'] . '&amp;redirect=' . self::$page;
		}
		else
		{
			$u_login_logout = append_sid(TITANIA_ROOT . 'index.' . PHP_EXT, 'mode=logout', true, $user->session_id);
		}

		$template->assign_vars(array(
			// rewrite the login URL to redirect to the currently viewed page.
			'U_LOGIN_LOGOUT'		=> $u_login_logout,
			'LOGIN_REDIRECT'		=> $user->page['page'],
			'S_LOGIN_ACTION'		=> append_sid(PHPBB_ROOT_PATH . 'ucp.' . PHP_EXT, 'mode=login'),
			'T_TITANIA_THEME_PATH'	=> titania::$config->theme_path,
			'T_TITANIA_STYLESHEET'	=> titania::$config->theme_path . 'stylesheet.css',
		));
	}

	/**
	 * Titania Logout method to redirect the user to the Titania root instead of the phpBB Root
	 *
	 * @param bool $return if we are within a method, we can use the error_box instead of a trigger_error on the redirect.
	 */
	public static function logout($return = false)
	{
		global $user;

		if ($user->data['user_id'] != ANONYMOUS && isset($_GET['sid']) && !is_array($_GET['sid']) && $_GET['sid'] === $user->session_id)
		{
			$user->session_kill();
			$user->session_begin();
			$message = $user->lang['LOGOUT_REDIRECT'];
		}
		else
		{
			$message = ($user->data['user_id'] == ANONYMOUS) ? $user->lang['LOGOUT_REDIRECT'] : $user->lang['LOGOUT_FAILED'];
		}

		if ($return)
		{
			return $message;
		}

		meta_refresh(3, append_sid(TITANIA_ROOT . 'index.' . PHP_EXT));

		$message = $message . '<br /><br />' . sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid(TITANIA_ROOT . 'index.' . PHP_EXT) . '">', '</a> ');
		trigger_error($message);
	}

	/**
	 * Titania page_footer
	 *
	 * @param cron $run_cron
	 */
	public static function page_footer($run_cron = true)
	{
		global $auth, $user, $template, $cache;

		// admin requested the cache to be purged, ensure they have permission and purge the cache.
		if (isset($_GET['cache']) && $_GET['cache'] == 'purge' && $auth->acl_get('a_'))
		{
			if (confirm_box(true))
			{
				$cache->purge();
				titania::error_box('SUCCESS', $user->lang['CACHE_PURGED'] . self::back_link('', '', array('cache')));
			}
			else
			{
				$s_hidden_fields = build_hidden_fields(array(
					'cache'		=> 'purge',
				));

				confirm_box(false, 'CONFIRM_PURGE_CACHE', $s_hidden_fields);
			}
		}

		$template->assign_vars(array(
			'U_PURGE_CACHE'		=> ($auth->acl_get('a_')) ? append_sid($user->page['script_path'] . $user->page['page_name'], 'cache=purge') : '',
		));

		page_footer($run_cron);
	}

	/**
	 * Generate HTML of to the previous or a specified page.
	 *
	 * @param string $redirect optional -- redirect URL absolute or relative path.
	 * @param string $l_redirect optional -- LANG string e.g.: 'RETURN_TO_MODS'
	 * @param array $exclude variables to exclude from params, if necessary. e.g.: array('search', 'sort');
	 * @param bool $return_url Return only the URL path, returns generated HTML by if set to false (default)
	 *
	 * @return HTML link string
	 */
	public static function back_link($redirect = '', $l_redirect = '', $exclude = array(), $return_url = false)
	{
		global $user, $config;

		$params = $query = array();
		$exclude = array_combine($exclude, $exclude);

		if (!$redirect)
		{
			// we must process our own redirect
			// full site URL based on config.
			$site_url = $config['server_protocol'] . $config['server_name'] . '/';

			// if HTTP_REFERER is set, and begins with the site URL, we allow it to be our redirect...
			if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] && (strpos($_SERVER['HTTP_REFERER'], $site_url) === 0))
			{
				$url_scheme = parse_url($_SERVER['HTTP_REFERER']);

				$redirect = $url_scheme['path'];
				$query_ary = (isset($url_scheme['query'])) ? explode('&', $url_scheme['query']) : $query;

				foreach ($query_ary as $param)
				{
					list($key, $value) = explode('=', $param);
					$query[$key] = $value;
				}
			}
			else
			{
				$redirect = $user->page['script_path'] . $user->page['page_name'];
			}
		}
		else
		{
			$url_scheme = parse_url($redirect);
			$redirect = $url_scheme['path'];
			$query_ary = (isset($url_scheme['query'])) ? explode('&', $url_scheme['query']) : $query;

			foreach ($query_ary as $param)
			{
				list($key, $value) = explode('=', $param);
				$query[$key] = $value;
			}
		}

		if ($exclude || !$redirect)
		{
			// collect the list of $_GET params to be used in the redirect string if query string not filled.
			$query = ($query) ? $query : $_GET;

			foreach ($query as $key => $value)
			{
				if (!isset($exclude[$key]))
				{
					$params[] = $key . '=' . $value;
				}
			}

			$redirect .= ($params) ? '?' . implode('&amp;', $params) : '';
		}

		// set the redirect string (Return to previous page)
		$l_redirect = ($l_redirect) ? $l_redirect : 'RETURN_LAST_PAGE';

		return (!$return_url) ? sprintf('<br /><br /><a href="%1$s">%2$s</a>', $redirect, $user->lang[$l_redirect]) : $redirect;
	}

	/**
	 * Wrapper for phpBB function trigger_error (with added http response code)
	 *
	 * @param string	$error_msg		error message or language string
	 * @param int		$error_type		error type e.g. E_USER_NOTICE
	 * @param int		$status_code	http response code
	 *
	 * @return void
	 */
	public static function trigger_error($error_msg, $error_type = false, $status_code = false)
	{
		if ($status_code)
		{
			titania::set_header_status($status_code);
		}

		if ($error_type)
		{
			trigger_error($error_msg, $error_type);
		}
		else
		{
			trigger_error($error_msg);
		}
	}

	/**
	 * Show the errorbox or successbox
	 *
	 * @param string $l_title message title - custom or user->lang defined
	 * @param string $l_message message string
	 * @param int $error_type ERROR_SUCCESS or ERROR_ERROR constant
	 * @param int $status_code an HTTP status code
	 */
	public static function error_box($l_title, $l_message, $error_type = ERROR_SUCCESS, $status_code = NULL)
	{
		global $template, $user;

		if ($status_code)
		{
			titania::set_header_status($status_code);
		}

		$template->assign_block_vars('errorbox', array(
			'TITLE'		=> (isset($user->lang[$l_title])) ? $user->lang[$l_title] : $l_title,
			'MESSAGE'	=> (isset($user->lang[$l_message])) ? $user->lang[$l_message] : $l_message,
			'S_ERROR'	=> ($error_type == ERROR_ERROR) ? true : false,
			'S_SUCCESS'	=> ($error_type == ERROR_SUCCESS) ? true : false,
		));
	}

	public static function set_header_status($status_code = NULL)
	{
		// Send the appropriate HTTP status header
		static $status = array(
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			204 => 'No Content',
			205 => 'Reset Content',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found', // Moved Temporarily
			303 => 'See Other',
			304 => 'Not Modified',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			406 => 'Not Acceptable',
			409 => 'Conflict',
			410 => 'Gone',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
		);

		if ($status_code && isset($status[$status_code]))
		{
			$header = $status_code . ' ' . $status[$status_code];
			header('HTTP/1.1 ' . $header, false, $status_code);
			header('Status: ' . $header, false, $status_code);
		}
	}
}
