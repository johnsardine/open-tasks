<?php

/**
* Items API
*
* Get, update/create and delete items
*
* @package		OpenTasks
* @copyright	Copyright (c) 2013 João Sardinha (http://johnsardine.com/)
* @license		https://github.com/johnsardine/open-tasks/blob/master/license.txt MIT License
* @version		1.0
* @link			https://github.com/johnsardine/open-tasks
* @since		1.0
*/

class Items
{

	private $pdo = '';

	private $config = array();

	private $table = 'items';

	private $table_meta = 'meta';

	private $meta = ''; // Meta object

	public $predefined_fields = array();
	public $optimizations = array();
	public $operators_main = array();
	public $operators_meta = array();
	public $operators = array();

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
			throw new Exception('No PDO connection available. Expecting an instance of PDO.');
		}

		if (empty($this->table)) {
			throw new Exception('No main table defined');
		}

		if (empty($this->table_meta)) {
			throw new Exception('No meta table defined');
		}

		if (!($this->meta instanceof Meta)) {
			throw new Exception('No meta class found. Expecting an instance of Meta from api.meta.php');
		}

		// If pdo is not a PDO connection
		if (!($this->pdo instanceof PDO) && !empty($this->connection)) {

			$this->pdo = new PDO('mysql:host='.$this->config['host'].';dbname='.$this->config['name'].';charset=utf8', $this->config['user'], $this->config['pass'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));

		}

		// Create main table if not present
		$table_main_exists = $this->pdo->query('SHOW TABLES LIKE "'.$this->table.'"')->rowCount() > 0;
		if (!$table_main_exists) {
			$this->pdo->exec("CREATE TABLE `".$this->table."` (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					`due_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					`user` bigint(20) NOT NULL DEFAULT '0',
					`title` longtext NOT NULL,
					`status` varchar(30) NOT NULL DEFAULT '',
					`priority` bigint(20) NOT NULL DEFAULT '0',
					`type` varchar(30) NOT NULL DEFAULT '',
					`parent` bigint(20) NOT NULL,
					PRIMARY KEY (`id`),
					FULLTEXT KEY `title` (`title`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
		}

		$this->predefined_fields = array(
			'id',
			'date',
			'due_date',
			'user',
			'title',
			'status',
			'priority',
			'type',
			'parent'
		);

		$this->optimizations = array(
			'order_by' => 'id',
			'order' => 'DESC',
			'limit' => '18446744073709551610',
			'offset' => '0'
		);

		$this->operators_main = array(
			'_not' => 'AND `:field` NOT IN (:value)',
			'_less_or' => 'AND `:field` <= :value',
			'_less' => 'AND `:field` < :value',
			'_more_or' => 'AND `:field` >= :value',
			'_more' => 'AND `:field` > :value',
			'_or' => 'OR `:field` IN (:value)',
			'_in' => 'AND `:field` IN (:value)'
		);

		$this->operators_meta = array(
			'_not' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` NOT IN (:value))',
			'_less_or' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` <= (:value))',
			'_less' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` < (:value))',
			'_more_or' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` >= (:value))',
			'_more' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` > (:value))',
			'_or' => 'OR `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` IN (:value))',
			'_in' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` IN (:value))',
		);

		$this->operators = array_keys($this->operators_main);

	}


	public function get($request = array())
	{

		// Predefined fields
		$predefined_fields = $this->predefined_fields;

		$optimizations = $this->optimizations;

		$operators_main = $this->operators_main;
		$operators_meta = $this->operators_meta;

		$operators = $this->operators;

		// request parse rquest parameters
		$parameters = $this->parse_parameters($request);

		$search_for = array(
			':field',
			':value'
		);

		$parameters_main = array();
		$parameters_meta = array();

		// Process meta parameters first and fetch matched ids
		foreach ($parameters as $row) {

			// If the request key is a query optimization
			if (in_array($row['field'], array_keys($optimizations))) {
				$optimizations[$row['field']] = $row['value'];
				continue;
			}

			// If is predefined field, ignore
			if (in_array($row['field'], $predefined_fields)) continue;

			$operator_group = $operators_meta;

			$operator = $row['operator'];
			$operation_string = $operator_group[$operator];
			$field = $row['field'];
			$value = $row['value'];

			$replace_with = array(
				$field,
				$value
			);

			$parameters_meta[] = str_replace($search_for, $replace_with, $operation_string);

		}

		// If there are meta parameters, proceed
		if (!empty($parameters_meta)) {
			$request_string = implode(' ', $parameters_meta);
			$request_string = substr($request_string, 3);

			// Get meta items id that match the requested fields
			$get_meta = $this->pdo->prepare('SELECT DISTINCT `item_id` FROM `'.$this->meta->table.'` WHERE'.$request_string);
			$get_meta->execute();

			$matched_items = array();

			// If there are matched items, build a main parameter and continue
			if ($get_meta->rowCount()) {
				$get_meta->setFetchMode(PDO::FETCH_ASSOC);
				foreach ($get_meta->fetchAll() as $row) {
					$matched_items[$row['item_id']] = $row['item_id'];
				}

				$parameters[] = array(
					'field' => 'id',
					'operator' => '_in',
					'value' => (is_array($matched_items)) ? implode(',', array_map(array($this->pdo, 'quote'), $matched_items)) : $matched_items
				);

			} else {
				$matched_items = array('0');
				$parameters[] = array(
					'field' => 'id',
					'operator' => '_in',
					'value' => (is_array($matched_items)) ? implode(',', array_map(array($this->pdo, 'quote'), $matched_items)) : $matched_items
				);
			}
		}

		// Process main parameters last
		foreach ($parameters as $row) {

			// If the request key is a query optimization
			if (in_array($row['field'], array_keys($optimizations))) {
				$optimizations[$row['field']] = $row['value'];
				continue;
			}

			// If is meta field, ignore
			if (!in_array($row['field'], $predefined_fields)) continue;

			$operator_group = $operators_main;

			$operator = $row['operator'];
			$operation_string = $operator_group[$operator];
			$field = $row['field'];
			$value = $row['value'];

			$replace_with = array(
				$field,
				$value
			);

			$parameters_main[] = str_replace($search_for, $replace_with, $operation_string);

		}

		// Statement optimizations
		$optimizations_array = array();
		foreach ($optimizations as $key => $value) {
			if ($key === 'order') $key = '';
			if ($key === 'order_by') $value = '`'.strtoupper($value).'`';
			$key = strtoupper(str_replace('_', ' ', $key));
			$optimizations_array[$key] = trim($key.' '.$value);
		}
		$optimizations_string = implode(' ', $optimizations_array);

		// Store returned items
		$items = array();

		// Get matched items
		if (empty($parameters_main) && empty($parameters_meta)) {
			$get_item = $this->pdo->prepare('SELECT * FROM `'.$this->table.'` '.$optimizations_string);
			$get_item->execute();
			$get_item->setFetchMode(PDO::FETCH_ASSOC);
			$items = $get_item->fetchAll();
		}
		else {
			$request_string = implode(' ', $parameters_main);
			$request_string = trim(substr($request_string, 3));
			$get_item = $this->pdo->prepare('SELECT * FROM `'.$this->table.'` WHERE '.$request_string.' '.$optimizations_string);
			$get_item->execute();
			$get_item->setFetchMode(PDO::FETCH_ASSOC);
			$items = $get_item->fetchAll();
		}

		// Prepare output
		$output = array();
		$output_id = array();

		// Organize items in array with id as array key
		foreach ($items as $item) {
			$output[$item['id']] = $item;
			$output_id[$item['id']] = $item['id'];
		}

		// Fetch returned items meta
		$get_items_meta = $this->pdo->prepare('SELECT `item_id`, `key`, `value` FROM `'.$this->meta->table.'` WHERE `item_id` IN ('.implode(',', $output_id).')');
		$get_items_meta->execute();

		// Merge items meta with item
		if ($get_items_meta->rowCount()) {
			$get_items_meta->setFetchMode(PDO::FETCH_ASSOC);
			$items_meta = $get_items_meta->fetchAll();

			foreach ($items_meta as $row) {
				$json_meta = json_decode($row['value'], true);
				if (is_array($json_meta)) $row['value'] = $json_meta;
				$output[$row['item_id']][$row['key']] = $row['value'];
			}

		}

		$output = array_values($output);

		return $output;

	}


	public function update($request = array())
	{

		// Predefined fields
		$predefined_fields = array(
			'id',
			'date',
			'due_date',
			'user',
			'title',
			'status',
			'priority',
			'type',
			'parent',
		);

		// Meta data
		$data_meta = array();

		// Separate main fields from meta fields
		foreach ($request as $key => $value) {

			// If is not a predefined field, move to meta and remove from main data
			if (!in_array($key, $predefined_fields)) {
				$data_meta[$key] = array(
					'key' => $key,
					'value' => $value
				);
				unset($request[$key]);
			}
		}

		$request_data = array();
		$request_string = array();
		foreach ($request as $key => $value) {
			$request_data[$key] = $value;
			$request_string[$key] = '`'.$key.'`=:'.$key.'';
		}

		// Get request keys
		$request_keys = array_keys($request_data);

		$request_action = '';
		if (in_array('id', $request_keys)) {

			// Remove id from parameters
			unset($request_string['id']);

			// If has id, update
			$request_action = 'UPDATE `'.$this->table.'` SET '.implode(',', $request_string).' WHERE `id`=:id';

		} else {

			// Else, create
			$request_action = 'INSERT INTO `'.$this->table.'` (`'.implode('`,`', $request_keys).'`) VALUES (:'.implode(',:', $request_keys).')';

		}

		// Prepare task insertion
		$post_request = $this->pdo->prepare($request_action);

		// Prepare data for insertion
		$data = $request_data;

		// Execute
		$post_request->execute($data);

		// Get last operation id
		if (in_array('id', $request_keys)) {
			$resource_id = $request_data['id'];
		} else {
			$resource_id = $this->pdo->lastInsertId();
		}

		// Add current item id to meta array
		foreach ($data_meta as $key => $row) {
			$data_meta[$key]['item_id'] = $resource_id;
		}

		if (!empty($data_meta))
			$this->meta->update($data_meta);

		// Prepare output
		$output = array();

		// Get last inserted row
		$output = current($this->get(array( 'id' => $resource_id )));

		// Return last insert row data for output
		return $output;

	}


	public function delete($request = array())
	{

		// Predefined fields
		$predefined_fields = $this->predefined_fields;

		$optimizations = $this->optimizations;

		$operators_main = $this->operators_main;
		$operators_meta = $this->operators_meta;

		$operators = $this->operators;

		// request parse rquest parameters
		$parameters = $this->parse_parameters($request);

		$search_for = array(
			':field',
			':value'
		);

		$parameters_main = array();
		$parameters_meta = array();

		// Process meta parameters first and fetch matched ids
		foreach ($parameters as $row) {

			// If the request key is a query optimization
			if (in_array($row['field'], array_keys($optimizations))) {
				$optimizations[$row['field']] = $row['value'];
				continue;
			}

			// If is predefined field, ignore
			if (in_array($row['field'], $predefined_fields)) continue;

			$operator_group = $operators_meta;

			$operator = $row['operator'];
			$operation_string = $operator_group[$operator];
			$field = $row['field'];
			$value = $row['value'];

			$replace_with = array(
				$field,
				$value
			);

			$parameters_meta[] = str_replace($search_for, $replace_with, $operation_string);

		}

		// If there are meta parameters, proceed
		if (!empty($parameters_meta)) {
			$request_string = implode(' ', $parameters_meta);
			$request_string = substr($request_string, 3);

			// Get meta items id that match the requested fields
			$get_meta = $this->pdo->prepare('SELECT DISTINCT `item_id` FROM `'.$this->meta->table.'` WHERE'.$request_string);
			$get_meta->execute();

			$matched_items = array();

			// If there are matched items, build a main parameter and continue
			if ($get_meta->rowCount()) {
				$get_meta->setFetchMode(PDO::FETCH_ASSOC);
				foreach ($get_meta->fetchAll() as $row) {
					$matched_items[$row['item_id']] = $row['item_id'];
				}

				$parameters[] = array(
					'field' => 'id',
					'operator' => '_in',
					'value' => (is_array($matched_items)) ? implode(',', array_map(array($this->pdo, 'quote'), $matched_items)) : $matched_items
				);

			}
		}

		// Process main parameters last
		foreach ($parameters as $row) {

			// If the request key is a query optimization
			if (in_array($row['field'], array_keys($optimizations))) {
				$optimizations[$row['field']] = $row['value'];
				continue;
			}

			// If is meta field, ignore
			if (!in_array($row['field'], $predefined_fields)) continue;

			$operator_group = $operators_main;

			$operator = $row['operator'];
			$operation_string = $operator_group[$operator];
			$field = $row['field'];
			$value = $row['value'];

			$replace_with = array(
				$field,
				$value
			);

			$parameters_main[] = str_replace($search_for, $replace_with, $operation_string);

		}

		// Store returned items
		$items = array();

		// Select items for deletion
		if (!empty($parameters_main)) {
			$request_string = implode(' ', $parameters_main);
			$request_string = trim(substr($request_string, 3));
			$get_item = $this->pdo->prepare('SELECT * FROM `'.$this->table.'` WHERE '.$request_string);
			$get_item->execute();
			$get_item->setFetchMode(PDO::FETCH_ASSOC);
			$items = $get_item->fetchAll();
		}

		// Matching items id
		$output_id = array();
		foreach ($items as $item) {
			$output_id[$item['id']] = $item['id'];
		}

		// Delete items
		$delete_items = $this->pdo->exec('DELETE FROM `'.$this->table.'` WHERE `id` IN ('.implode(',', $output_id).')');
		// Delete associated meta
		$delete_items_meta = $this->pdo->exec('DELETE FROM `'.$this->meta->table.'` WHERE `item_id` IN ('.implode(',', $output_id).')');

		return array_values($output_id);

	}


	private function parse_parameters($request = array())
	{

		$operators = $this->operators;

		$parameters = array();

		// Iterate through each field and build an array of operations
		foreach ($request as $key => $value) {

			// Prepare array with key, value and operation
			foreach ($operators as $textual) {
				$operator = '';
				//if ($key == 'exclude') $key = 'id_not';

				// If current field contains an operation
				if ( strpos($key, $textual) !== false ) {
					$key = $replace_with['key'] = str_replace($textual, '', $key);
					$operator = $textual;

					// If operator exists and has been processed, exit this loop
					break;

				} else {
					$key = $replace_with['key'] = str_replace($textual, '', $key);
					$operator = $textual;
				}
			}

			// Prepare values
			if (is_array($value)) {
				$value = array_map(array($this->pdo, 'quote'), $value);
			} else {
				$value = $this->pdo->quote($value);
			}

			$parameters[] = array(
				'field' => $key,
				'operator' => $operator,
				'value' => (is_array($value)) ? implode(',', $value) : $value
			);
		}

		return $parameters;

	}


}
