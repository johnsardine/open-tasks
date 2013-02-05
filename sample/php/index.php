<?php

header_remove('x-powered-by');

global $config;

$config = new stdClass();
$config->base_url = 'http://localhost/open-tasks/sample/php/';
$config->db = new stdClass();
$config->db->host = 'localhost';
$config->db->user = 'root';
$config->db->pass = 'root';
$config->db->name = 'opentasks';
$config->db->charset = 'UTF-8';

function segment($segment = null, $default = null)
{
	$path = null;

	if (!empty($_SERVER['PATH_INFO'])) {
		$path = $_SERVER['PATH_INFO'];
	}
	else if (!empty($_SERVER['ORIG_PATH_INFO'])) {
		$path = $_SERVER['ORIG_PATH_INFO'];
	}

	$path = explode('/', $path);

	$last = array_keys($path);
	$last = end($last);

	if (empty($path[$last])) array_pop($path);

	if (!$segment && !$default) {
		unset($path[0]);
		return $path;
	}

	return (!empty($path[$segment])) ? $path[$segment] : $default;
}

function get($key = null, $default = null)
{
	if (!$key && !empty($_GET)) {
		return $_GET;
	} elseif (!$key) {
		return false;
	}

	return (isset($_GET[$key])) ? $_GET[$key] : $default ;
}

function post($key = null)
{
	if (!$key && !empty($_POST)) {
		return $_POST;
	} elseif (!$key) {
		return false;
	}

	$args = func_get_args();

	$str = '$_POST';
	foreach ($args as $index => $key) {
		$str .= '["'.$key.'"]';
	}
	$str .= '';

	return eval('return (isset('.$str.')) ? '.$str.' : null ;');
}

function url($path = null) {
	global $config;

	return $config->base_url.'index.php/'.$path;
}

$controller = 'tasks';
$method = 'index';

$controller = segment(1, $controller);
$method = segment(2, $method);

$file = $controller.'.php';

if (!file_exists($file)) exit('what are you doing?');

include_once $file;

$load = new $controller($config);

// Remove controller and method from sent args
$args = array();
$args = segment();
$args = array_slice($args, 2);

exit(call_user_func_array(array($load, $method), $args));
