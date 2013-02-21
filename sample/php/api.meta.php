<?php

/**
* Meta API
*
* Get, update/create and delete meta data
*
* @package		OpenTasks
* @copyright	Copyright (c) 2013 João Sardinha (http://johnsardine.com/)
* @license		https://github.com/johnsardine/open-tasks/blob/master/license.txt MIT License
* @version		1.0
* @link			https://github.com/johnsardine/open-tasks
* @since		1.0
*/

class Meta
{

	private $pdo = '';

	private $config = array();

	public $table = 'meta';

	public function __construct($params = array())
	{

		if (count($params) > 0) {
			foreach ($params as $key => $val) {
				if (isset($this->$key)) {
					$this->$key = $val;
				}
			}
		}

		if (empty($this->pdo) && empty($this->pdo)) {
			throw new Exception('No PDO connection available');
		}

		if (empty($this->table)) {
			throw new Exception('No main table defined');
		}

		// If pdo is not a PDO connection
		if (!($this->pdo instanceof PDO) && !empty($this->connection)) {

			$this->pdo = new PDO('mysql:host='.$this->config['host'].';dbname='.$this->config['name'].';charset=utf8', $this->config['user'], $this->config['pass'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));

		}

		// Create meta table if not present
		$table_meta_exists = $this->pdo->query('SHOW TABLES LIKE "'.$this->table.'"')->rowCount() > 0;
		if (!$table_meta_exists) {
			$this->pdo->exec("CREATE TABLE `".$this->table."` (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`item_id` bigint(20) unsigned NOT NULL,
					`key` varchar(255) NOT NULL DEFAULT '',
					`value` longtext NOT NULL,
					PRIMARY KEY (`id`),
					FULLTEXT KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
		}

	}


	public function get($request = array())
	{

		$output = array(
			'id' => null,
			'item_id' => null,
			'key' => null,
			'value' => null
		);

		$get_meta = $this->pdo->prepare('SELECT * FROM `'.$this->table.'` WHERE `item_id` = :item_id AND `key` = :key');
		$get_meta->execute($request);

		$row_count = $get_meta->rowCount();

		// If no results
		if (empty($row_count))
			return $output;

		$get_meta->setFetchMode(PDO::FETCH_ASSOC);

		return $get_meta->fetch();

	}


	public function update($request = array())
	{

		// If is an array
		if (is_array(current($request)))
			return array_map(__METHOD__, $request);

		// Get current option
		$current = $this->get(array(
				'item_id' => $request['item_id'],
				'key' => $request['key'],
			));

		// If meta exists, update
		if ($current['id']) {
			$data = array(
				'id' => $current['id'],
				'value' => $request['value']
			);
			$do_meta = $this->pdo->prepare('UPDATE `'.$this->table.'` SET `value` = :value WHERE `id` = :id');
		}
		// Create meta
		else {
			$data = array(
				'item_id' => $request['item_id'],
				'key' => $request['key'],
				'value' => $request['value']
			);
			$do_meta = $this->pdo->prepare('INSERT INTO `'.$this->table.'` (`item_id`, `key`, `value`) VALUE (:item_id, :key, :value)');
		}

		// Return query status
		return $do_meta->execute($data);

	}


	public function delete()
	{

	}


}
