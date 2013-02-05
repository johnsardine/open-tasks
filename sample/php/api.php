<?php

// Include RESTfull helpers
include_once 'Rest.inc.php';

class Api extends Rest {

	private $pdo;

	public $request_method;

	private $table_main = 'items';

	private $table_meta = 'meta';

	function __construct($config)
	{

		// Inherit Rest proprieties
		parent::__construct();

		// Create PDO MySQL connection
		$this->pdo = new PDO('mysql:host='.$config->db->host.';dbname='.$config->db->name.';charset=utf8', $config->db->user, $config->db->pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));

		$this->request_method = $this->get_request_method();

		// Create main table if not present
		$table_main_exists = $this->pdo->query('SHOW TABLES LIKE "'.$this->table_main.'"')->rowCount() > 0;
		if (!$table_main_exists) {
			$this->pdo->exec("CREATE TABLE `".$this->table_main."` (
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

		// Create meta table if not present
		$table_meta_exists = $this->pdo->query('SHOW TABLES LIKE "'.$this->table_meta.'"')->rowCount() > 0;
		if (!$table_meta_exists) {
			$this->pdo->exec("CREATE TABLE `".$this->table_meta."` (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`item_id` bigint(20) unsigned NOT NULL,
					`key` varchar(255) NOT NULL DEFAULT '',
					`value` longtext NOT NULL,
					PRIMARY KEY (`id`),
					FULLTEXT KEY `value` (`value`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
		}

	}


	function index()
	{

		$output = array(
			'message' => 'Nothing here'
		);
		$output = json_encode($output);
		$this->response($output, 404);

	}


	/**
	 * tasks function.
	 *
	 * @access public
	 * @return void
	 */
	public function tasks()
	{

		// Get resource id (if exists)
		// checks for id=:id or /tasks/:id
		// Acceps multiple ids, comma separated

		switch ($this->request_method) {

			// Preform a get request for tasks
		case 'GET' :

			$request = $this->_request;

			// Accept id via segment
			$id_segment = segment(3);
			if ($id_segment) {
				$request['id'] = $id_segment;
			}

			// Explode all parameters into arrays
			foreach ($request as $key => $value) {
				$request[$key] = explode(',', $value);
			}

			// Fetch only tasks through this method
			$request['type'] = 'task';

			// Init output
			$output = array();

			// Send current request anc capture the output
			$output = $this->_get_item($request);

			// Throw 404 error if nothing found
			if (empty($output)) {
				$output = array(
					'message' => 'Nothing found'
				);
				$output = json_encode($output);
				$this->response($output, 404);
			}

			// If specific id request and output is only one
			if (isset($request['id']) && count($request['id']) === 1) {
				$output = current($output);
			}

			$output = json_encode($output);
			$this->response($output, 200);

			break;

			// Preform task addition/modification
		case 'POST' :

			$request = $this->_request;

			// Check if is a batch action
			$is_batch = is_array(current($request)) === true;

			// Prepare output
			$output = array();

			if ($is_batch) {

				foreach ($request as $single) {
					// Predefined parameters
					$single['date'] = gmdate('Y-m-d H:i:s');
					$single['type'] = 'task';
					$output[] = $this->_do_item($single);
				}

			} else {

				// If is single item and no id is in parameters, check if is in URI segment
				if (empty($request['id']) && is_numeric(segment(3)))
					$request['id'] = segment(3);

				// Predefined parameters
				$request['date'] = gmdate('Y-m-d H:i:s');
				$request['type'] = 'task';
				$output = $this->_do_item($request);
			}

			$output = json_encode($output);
			$this->response($output, 201);

			break;

			// Preform task deletion
		case 'DELETE' :

			$request = $this->_request;

			$id = (!empty($request['id'])) ? $request['id'] : segment(3);

			if (empty($id)) {
				$output = array(
					'message' => 'No id provided for deletion'
				);
				$output = json_encode($output);
				$this->response($output, 406);
			}

			// Delete requested id
			$do_request = $this->pdo->exec('DELETE FROM `'.$this->table_main.'` WHERE `type`="task" AND `id`="'.$id.'"');

			if ($do_request === 0) {
				// Prepare last insert row data for output
				$output = array(
					'message' => 'No task exists with id '.$id
				);
				$output = json_encode($output);
				$this->response($output, 406);
			}

			$output = array(
				'message' => 'Deleted',
				'id' => $id
			);
			$output = json_encode($output);
			$this->response($output, 200);

			break;

		default:

			// Prepare data for output
			$output = array(
				'message' => 'Accepted methods: GET, POST, DELETE',
			);
			$output = json_encode($output);
			$this->response($output, 406);

			break;

		}

	}


	/**
	 * groups function.
	 *
	 * @access public
	 * @return void
	 */
	public function groups()
	{

	}


	/**
	 * Get item
	 *
	 * Recieves an array of parameters and conditions and returns an array with items + meta
	 *
	 * @access private
	 * @param array $request (default: array())
	 * @return void
	 */
	private function _get_item($request = array())
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
			'parent'
		);

		$operators_main = array(
			'_not' => 'AND `:field` NOT IN (:value)',
			'_less_or' => 'AND `:field` <= :value',
			'_less' => 'AND `:field` < :value',
			'_more_or' => 'AND `:field` >= :value',
			'_more' => 'AND `:field` > :value',
			'_or' => 'OR `:field` IN (:value)',
			'_in' => 'AND `:field` IN (:value)'
		);

		$operators_meta = array(
			'_not' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` NOT IN (:value))',
			'_less_or' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` <= (:value))',
			'_less' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` < (:value))',
			'_more_or' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` >= (:value))',
			'_more' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` > (:value))',
			'_or' => 'OR `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` IN (:value))',
			'_in' => 'AND `item_id` IN (SELECT `item_id` FROM `'.$this->table_meta.'` WHERE `key` IN (":field") AND `value` IN (:value))',
		);

		$operators = array_keys($operators_main);

		$parameters = array();

		// Iterate through each field and build an array of operations
		foreach ($request as $key => $value) {

			// Prepare values
			if (is_array($value)) {
				$value = array_map(array($this->pdo, 'quote'), $value);
			} else {
				$value = $this->pdo->quote($value);
			}

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

			$parameters[] = array(
				'field' => $key,
				'operator' => $operator,
				'value' => (is_array($value)) ? implode(',', $value) : $value
			);
		}

		$search_for = array(
			':field',
			':value'
		);

		$parameters_main = array();
		$parameters_meta = array();

		// Process meta parameters first and fetch matched ids
		foreach ($parameters as $row) {

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
			$get_meta = $this->pdo->prepare('SELECT DISTINCT `item_id` FROM `'.$this->table_meta.'` WHERE'.$request_string);
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

		// Get matched items
		if (empty($parameters_main) && empty($parameters_meta)) {
			$get_item = $this->pdo->prepare('SELECT * FROM `'.$this->table_main.'`');
			$get_item->execute();
			$get_item->setFetchMode(PDO::FETCH_ASSOC);
			$items = $get_item->fetchAll();
		}
		else {
			//var_dump($request_string);
			$request_string = implode(' ', $parameters_main);
			$request_string = substr($request_string, 3);
			$get_item = $this->pdo->prepare('SELECT * FROM `'.$this->table_main.'` WHERE '.$request_string);
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
		$get_items_meta = $this->pdo->prepare('SELECT `item_id`, `key`, `value` FROM `'.$this->table_meta.'` WHERE `item_id` IN ('.implode(',', $output_id).')');
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


	/**
	 * Insert/Update item
	 *
	 * Recieves an array with parameters
	 *
	 * If an ID is present, will try to update an existing item, if not, it will create it
	 *
	 * If a parameter is not a predefined field, will be added/updated in meta
	 *
	 * @access private
	 * @param array $request (default: array())
	 * @return void
	 */
	private function _do_item($request = array())
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
			$request_action = 'UPDATE `'.$this->table_main.'` SET '.implode(',', $request_string).' WHERE `id`=:id';

		} else {

			// Else, create
			$request_action = 'INSERT INTO `'.$this->table_main.'` (`'.implode('`,`', $request_keys).'`) VALUES (:'.implode(',:', $request_keys).')';

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
		$this->_do_meta($data_meta);

		// Get last inserted row
		$get_request = $this->pdo->query('SELECT * FROM `'.$this->table_main.'` WHERE `id`="'.$resource_id.'"');

		$get_request->setFetchMode(PDO::FETCH_OBJ);

		// Return last insert row data for output
		return $get_request->fetchObject();

	}



	/**
	 * Get meta
	 *
	 * Recieves an array with item_id and key
	 *
	 * Returns corresponding meta if exists
	 *
	 * TO-DO: If value is json array, will be decoded
	 *
	 * @access private
	 * @param array $request (default: array())
	 * @return void
	 */
	private function _get_meta($request = array())
	{

		$output = array(
			'id' => null,
			'item_id' => null,
			'key' => null,
			'value' => null
		);

		$get_meta = $this->pdo->prepare('SELECT * FROM `'.$this->table_meta.'` WHERE `item_id` = :item_id AND `key` = :key');
		$get_meta->execute($request);

		$row_count = $get_meta->rowCount();

		// If no results
		if (empty($row_count))
			return $output;

		$get_meta->setFetchMode(PDO::FETCH_ASSOC);

		return $get_meta->fetch();

	}


	/**
	 * Insert/Update meta
	 *
	 * @access private
	 * @param array $request (default: array())
	 * @return void
	 */
	private function _do_meta($request = array())
	{

		// If is an array
		if (is_array(current($request)))
			return array_map(__METHOD__, $request);

		// Get current option
		$current = $this->_get_meta(array(
				'item_id' => $request['item_id'],
				'key' => $request['key'],
			));

		// If meta exists, update
		if ($current['id']) {
			$data = array(
				'id' => $current['id'],
				'value' => $request['value']
			);
			$do_meta = $this->pdo->prepare('UPDATE `'.$this->table_meta.'` SET `value` = :value WHERE `id` = :id');
		}
		// Create meta
		else {
			$data = array(
				'item_id' => $request['item_id'],
				'key' => $request['key'],
				'value' => $request['value']
			);
			$do_meta = $this->pdo->prepare('INSERT INTO `'.$this->table_meta.'` (`item_id`, `key`, `value`) VALUE (:item_id, :key, :value)');
		}

		// Return query status
		return $do_meta->execute($data);

	}


	public function user()
	{

		// GET /user - lists users
		// GET /user/12 - gets user 12 and all associated data
		// GET /user/12/name (email, name, type, infinite number of data, coming from user meta, queries, if exists, returns, if not, returns false)

		// POST /user - Inserts new user, recieves, at least, email and password, passowrd should be bctypt'ed

		// POST /user/login - recieves user (email or username) and password, preforms auth, investigate best RESTfull method (should not store session in server side, should be on client side)

	}


	/**
	 * Get mustache template
	 *
	 * Returns the requested mustache template
	 *
	 * Still needs work, is not well integrated into the api
	 *
	 * @access public
	 * @return void
	 */
	function template()
	{
		$file = get('template', segment(3, null));

		if (!$file) exit;

		$file = 'template/'.$file.'.mustache';

		if (!file_exists($file)) exit;

		$file = file_get_contents($file);
		echo $file;
	}


	private function tests()
	{

		echo "INSERT INTO `items` (`date`, `due_date`, `user`, `title`, `status`, `priority`, `type`, `parent`)
VALUES\n";
		for ($i=0; $i <= 9999; $i++) {
			echo "('0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, 'Title', '', 0, 'task', 0),\n";
		}
		echo "('0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, 'Title', '', 0, 'task', 0);";

	}


}
