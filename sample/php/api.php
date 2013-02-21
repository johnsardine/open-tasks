<?php

// Include RESTfull helpers
include_once 'Rest.inc.php';

class Api extends Rest {

	private $pdo;

	public $request_method;

	private $table_main = 'items';

	private $table_meta = 'meta';

	public $meta;

	public $items;

	function __construct($config)
	{

		// Inherit Rest proprieties
		parent::__construct();

		// Create PDO MySQL connection
		$this->pdo = new PDO('mysql:host='.$config->db->host.';dbname='.$config->db->name.';charset=utf8', $config->db->user, $config->db->pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));

		// Include meta
		require_once 'api.meta.php';
		$this->meta = new Meta(array(
				'table' => $this->table_meta,
				'pdo' => $this->pdo
			));

		// Include items
		require_once 'api.items.php';
		$this->items = new Items(array(
				'table' => $this->table_main,
				'table_meta' => $this->table_meta,
				'pdo' => $this->pdo,
				'meta' => $this->meta
			));

		$this->request_method = $this->get_request_method();

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

		$this->item('task');

	}


	/**
	 * groups function.
	 *
	 * @access public
	 * @return void
	 */
	public function groups()
	{

		$this->item('group');

	}


	private function item($type = 'task')
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
			$request['type'] = $type;

			// Init output
			$output = array();

			// Send current request anc capture the output
			$output = $this->items->get($request);

			// Throw 404 error if nothing found
			if (empty($output) && isset($request['id'])) {
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
					$single['type'] = $type;
					$output[] = $this->items->update($single);
				}

			} else {

				// If is single item and no id is in parameters, check if is in URI segment
				if (empty($request['id']) && is_numeric(segment(3)))
					$request['id'] = segment(3);

				// Predefined parameters
				$request['date'] = gmdate('Y-m-d H:i:s');
				$request['type'] = $type;
				$output = $this->items->update($request);
			}

			$output = json_encode($output);
			$this->response($output, 201);

			break;

			// Preform task deletion
		case 'DELETE' :

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
			$request['type'] = $type;

			// Delete requested id
			$delete_items = $this->items->delete($request);

			if (empty($delete_items)) {
				// Prepare last insert row data for output
				$output = array(
					'message' => 'No '.$type.' exists with id '.$id
				);
				$output = json_encode($output);
				$this->response($output, 406);
			}

			$output = $delete_items;
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
	 * Get item
	 *
	 * Recieves an array of parameters and conditions and returns an array with items + meta
	 *
	 * @access private
	 * @param array $request (default: array())
	 * @return void
	 */
	public function _get_item($request = array())
	{
		return $this->items->get($request);
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
		$this->items->update($request);
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
	public function _get_meta($request = array())
	{
		$this->meta->get($request);
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
		$this->meta->update($request);
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
