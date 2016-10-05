<?php
/**
 * Handles paginating of query results
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Pager\Web;

use \Skeleton\Pager\Config;
use \Skeleton\Pager\Sql\Condition;
use \Skeleton\Pager\Sql\Join;

class Pager {
	/**
	 * Classname
	 *
	 * @access private
	 * @var string $classname
	 */
	private $classname;

	/**
	 * Options
	 *
	 * @access private
	 * @var array $options
	 */
	private $options = [
		'conditions' => [],
		'sort' => null,
		'direction' => 'asc',
		'page' => 1,
		'jump_to' => true,
		'joins' => [],
		'sort_permissions' => [],
	];

	/**
	 * Items
	 *
	 * @access public
	 * @var array $items
	 */
	public $items = [];

	/**
	 * Item count
	 *
	 * @access public
	 * @var int $item_count
	 */
	public $item_count = 0;

	/**
	 * Interval
	 *
	 * @access private
	 * @var int $interval
	 */
	private $interval = 5;

	/**
	 * Link: a html string with pager links
	 *
	 * @access public
	 * @var string $link
	 */
	public $links;

	/**
	 * Constructor
	 *
	 * @access private
	 * @param $code
	 */
	public function __construct($classname = null) {
		if ($classname === null) {
			throw new \Exception('You must provide a classname');
		}

		$this->classname = $classname;
	}

	/**
	 * Set sort field
	 *
	 * @access public
	 * @param string $sort
	 */
	public function set_sort($sort) {
		$object = new \ReflectionClass($this->classname);
		if (is_callable($sort) === false AND $object->hasMethod($sort) === false) {
			$sort = $this->expand_field_name($sort);
		}
		$this->options['sort'] = $sort;
	}

	/**
	 * Set direction
	 *
	 * @access public
	 * @param string $direction
	 */
	public function set_direction($direction = 'asc') {
		$this->options['direction'] = $direction;
	}

	/**
	 * Add a sort permission
	 *
	 * @access public
	 * @param $column
	 * @param $database_field
	 */
	public function add_sort_permission($database_field) {
		$object = new \ReflectionClass($this->classname);
		if (is_callable($database_field) === false AND $object->hasMethod($database_field) === false) {
			$database_field = $this->expand_field_name($database_field);
		}
		$this->options['sort_permissions'][] = $database_field;
	}

	/**
	 * Set page
	 *
	 * @access public
	 * @param int $page
	 */
	public function set_page($page) {
		$this->options['page'] = $page;
	}

	/**
	 * Add condition
	 *
	 * @access public
	 * @param string $field
	 * @param string $comparison (optional)
	 * @param string $value
	 */
	public function add_condition() {
		$params = func_get_args();
		$conditions = $this->options['conditions'];

		if (is_a($params, '\Skeleton\Pager\Sql\Condition')) {
			$condition[$field][] = $params;
			return;
		}

		$field = array_shift($params);
		$field = $this->expand_field_name($field);

		if (count($params) == 1) {
			$condition = new Condition($field, '=', array_shift($params));
		} else {
			$condition = new Condition($field, array_shift($params), $params);
		}
		$conditions[$field][] = $condition;

		$this->options['conditions'] = $conditions;
	}

	/**
	 * Has condition
	 *
	 * @access public
	 * @param string $field
	 * @param string $comparison (optional)
	 * @param string $value
	 */
	public function has_condition() {
		$params = func_get_args();

		if (is_a($params, '\Skeleton\Pager\Sql\Condition')) {
			$condition = $params;
		} else {
			$field = array_shift($params);
			$field = $this->expand_field_name($field);

			if (count($params) == 1) {
				$condition = new Condition($field, '=', array_shift($params));
			} else {
				$condition = new Condition($field, array_shift($params), $params);
			}
		}




		foreach ($this->options['conditions'] as $cond_field => $stored_conditions) {
			if ($field != $cond_field) {
				continue;
			}

			foreach ($stored_conditions as $stored_condition) {
				if ($condition->equals($stored_condition)) {
					return true;
				}

			}
		}
		return false;
	}

	/**
	 * Add join
	 *
	 * @access public
	 * @param string $remote_table
	 * @param string $remote_id
	 * @param string $local_field
	 * @param array $extra_join_conditions
	 */
	public function add_join($remote_table, $remote_id, $local_field, $extra_conditions = []) {
		$local_field = $this->expand_field_name($local_field);
		/*
			$extra_join = [
				$remote_table,
				$remote_id,
				$local_field
			]
		*/

		$join = new Join($remote_table, $remote_id, $local_field);

		if (is_a($extra_conditions, '\Skeleton\Pager\Sql\Condition')) {
			$join->add_condition($extra_conditions);
		} else {
			foreach ($extra_conditions as $extra_condition) {
				$join->add_condition($extra_condition);
			}
		}

		$this->options['joins'][] = $join;
	}

	/**
	 * Activate 'Jump to page'
	 *
	 * @access public
	 * @param bool $jump_to
	 */
	public function set_jump_to($jump_to) {
		$this->options['jump_to'] = $jump_to;
	}

	/**
	 * Set a search
	 *
	 * @access public
	 * @param string $search
	 */
	public function set_search($search, $search_fields = []) {
		foreach ($search_fields as $key => $search_field) {
			$search_fields[$key] = $this->expand_field_name($search_field);
		}
		$this->options['conditions']['%search%'] = [ $search, $search_fields ];
	}

	/**
	 * Get search
	 *
	 * @access public
	 * @return string $search
	 */
	public function get_search() {
		if (isset($this->options['conditions']['%search%'])) {
			return $this->options['conditions']['%search%'][0];
		} else {
			return '';
		}
	}

	/**
	 * Get sum
	 *
	 * @access public
	 * @param string $field
	 */
	public function get_sum($field) {
		return call_user_func_array([$this->classname, 'sum'], [$field, $this->options['conditions'], $this->options['joins']]);
	}

	/**
	 * Clear conditions
	 *
	 * @access public
	 */
	public function clear_conditions() {
		unset($this->options['conditions']);
		$this->options['conditions'] = [];

		if (Config::$sticky_pager) {
			$pager_uri_key = $this->get_pager_uri_key();
			unset($_SESSION['pager'][$pager_uri_key]);
		}
	}

	/**
	 * Clear condition
	 *
	 * @access public
	 * @param string $key
	 */
	public function clear_condition($key) {
		unset($this->options['conditions'][$key]);
	}

	/**
	 * Get conditions
	 *
	 * @return array $conditions
	 */
	public function get_conditions() {
		return $this->options['conditions'];
	}

	/**
	 * Get classname
	 *
	 * @access public
	 * @return string $classname
	 */
	public function get_classname() {
		return $this->classname;
	}

	/**
	 * Create the header cells of the paged table
	 *
	 * @param string $header Name of the header
	 * @param string $field_name Name of the database field that is represented here
	 * @return string $output
	 * @access public
	 */
	public function create_header($header, $field_name) {
		$object = new \ReflectionClass($this->classname);
		if (is_callable($field_name) === false AND $object->hasMethod($field_name) === false) {
			$field_name = $this->expand_field_name($field_name);
		}

		if ($this->options['sort'] == $field_name) {
			if ($this->options['direction'] == 'asc') {
				$direction = 'desc';
			} else {
				$direction = 'asc';
			}
		} else {
			$direction = 'asc';
		}

		$hash = $this->create_options_hash($this->options['conditions'], $this->options['page'], $field_name, $direction, $this->options['joins']);

		parse_str($_SERVER['QUERY_STRING'], $qry_str_parts);
		$qry_str_parts['q'] = $hash;

		$url = self::find_page_uri() . '?' . http_build_query($qry_str_parts);

		$output = $header . ' ';

		if ($this->options['sort'] == $field_name) {
			if ($direction == 'desc') {
				$output .= '<span class="glyphicon glyphicon-chevron-up"></span>';
			} else {
				$output .= '<span class="glyphicon glyphicon-chevron-down"></span>';
			}
		}

		// Only allow sorting on fields actually in the permission list
		if (isset($this->options['sort_permissions']) and in_array($field_name, $this->options['sort_permissions'])) {
			$output = '<a href="' . $url . '">' . $output . '</a>';
		}

		return $output;
	}

	/**
	 * Paginate the results
	 *
	 * @access private
	 */
	public function page($all = false) {
		$pager_uri_key = $this->get_pager_uri_key();

		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			if (!isset($_GET['q']) AND isset($_SESSION['pager'][$pager_uri_key]) AND Config::$sticky_pager) {
				$this->options = array_replace_recursive($this->options, $this->get_options_from_hash($_SESSION['pager'][$pager_uri_key]));
			} elseif (isset($_GET['q'])) {
				unset($this->options['conditions']);
				$this->options = array_replace_recursive($this->options, $this->get_options_from_hash($_GET['q']));
			}
		}

		if (isset($_GET['p'])) {
			$this->set_page($_GET['p']);
		}

		if ($this->options['sort'] === null AND isset($this->options['sort_permissions']) AND count($this->options['sort_permissions']) > 0) {
			reset($this->options['sort_permissions']);
			$this->options['sort'] = current($this->options['sort_permissions']);
		}

		// Check if we are allowed to sort at all
		if ($this->options['sort'] != null AND !is_callable($this->options['sort']) AND !in_array($this->options['sort'], $this->options['sort_permissions'])) {
			throw new \Exception('Sorting not allowed for field ' . $this->options['sort']);
		}

		$sort = $this->options['sort'];

		$this->options['all'] = $all;

		$params = [
			$sort,
			$this->options['direction'],
			$this->options['page'],
			$this->options['conditions'],
			$this->options['all'],
			$this->options['joins']
		];

		$this->items = call_user_func_array([$this->classname, 'get_paged'], $params);

		$this->item_count = call_user_func_array([$this->classname, 'count'], [$this->options['conditions'], $this->options['joins']]);
		$this->generate_links();

		$hash = $this->create_options_hash($this->options['conditions'], $this->options['page'], $this->options['sort'], $this->options['direction'], $this->options['joins']);

		if (Config::$sticky_pager) {
			$_SESSION['pager'][$pager_uri_key] = $hash;
		}
	}

	/**
	 * Export the result to CSV
	 *
	 * @access public
	 * @param array $fields
	 */
	public function export($fields) {
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . $this->classname . '.csv');

		if ($fields === array_values($fields)) {
			$new_fields = [];
			foreach ($fields as $field) {
				$new_fields[$field] = $field;
			}
			$fields = $new_fields;
		}

		foreach ($fields as $field) {
			$this->add_sort_permission($field);
		}

		$result = $this->page(true);
		foreach ($fields as $field) {
			echo $field . ';';
		}
		echo "\n";
		foreach ($this->items as $item) {
			foreach ($fields as $field) {
				echo \Skeleton\Pager\Util::object_get_attribute($item, $field) . ';';
			}
			echo "\n";
		}
		exit;
	}

	/**
	 * Get the pager options from a hash
	 *
	 * @access private
	 * @param string $hash
	 * @return array $options
	 */
	private function get_options_from_hash($hash) {
		return unserialize(base64_decode(urldecode($hash)));
	}

	/**
	 * Create options hash
	 *
	 * @access private
	 * @param array $conditions
	 * @param int $page
	 * @param int $sort
	 * @param string $direction
	 */
	public function create_options_hash($conditions = null, $page = null, $sort = null, $direction = null) {
		if ($conditions === null) {
			$conditions = $this->options['conditions'];
		}

		if ($page === null) {
			$page = $this->options['page'];
		}

		if ($sort === null) {
			$sort = $this->options['sort'];
		}

		if ($direction === null) {
			$direction = $this->options['direction'];
		}

		$options = array(
			'classname' => $this->classname,
			'conditions' => $conditions,
			'page' => $page,
			'sort' => $sort,
			'direction' => $direction,
			'joins' => $this->options['joins'],
		);

		$hash = urlencode(base64_encode(serialize($options)));
		return $hash;
	}

	/**
	 * Create page link
	 *
	 * @access private
	 * @param int $page
	 * @param string $url
	 * @param bool $active
	 */
	private function create_page_link($page, $url, $active = false) {
		$link = '<li';
		$class = [];
		if ($active) {
			$class[] = 'active';
		}
		if (is_numeric($page) AND $page < 10) {
			$class[] = 'single';
		}
		if (count($class) > 0) {
			$link.= ' class="' . implode(' ', $class) . '"';
		}
		$link .= '>';
		$link .= '<a href="' . $url . '">' . $page . '</a>';
		return $link;
	}

	/**
	 * Generate the necessary links to navigate the paged result
	 *
	 * @access private
	 */
	private function generate_links() {
		$items_per_page = Config::$items_per_page;
		if ($items_per_page == 0) {
			$pages = 0;
		} else {
			$pages = ceil($this->item_count / $items_per_page);
		}

		// Don't make links if there is only one page
		if ($pages == 1) {
			$this->links = '';
			return;
		}

		$str_links = '';
		$links = [];
		if ($this->options['page'] > 1) {
			$links[] = '-1';
		}

		for ($i = 1; $i <= $pages; $i++) {
			$print = false;

			// Display the first two pages
			if ($i < 2) {
				$print = true;
			}

			// Display the two pages before and after the current one
			if ($i >= $this->options['page']-2 AND $i <= $this->options['page']+2) {
				$print = true;
			}

			// Make sure at least 9 pages are printed all the time
			if (($this->options['page'] < 5 AND $i <= 7) OR ($this->options['page'] > $pages-5 AND $i >= $pages-6)) {
				$print = true;
			}

			// Display the last two pages
			if ($i > $pages-1) {
				$print = true;
			}

			if ($print === true) {
				if (end($links) > 0 AND end($links)+1 != $i) {
					$links[] = '...';
				}

				$links[] = $i;
				$previous_print = $i;
			}
		}

		if ($this->options['page'] < $pages) {
			$links[] = '+1';
		}

		foreach ($links as $key => $link) {
			if ($link === '-1') {
				$number = $this->options['page']-1;
				$text = '&laquo;';
				$active = false;
			} elseif ($link === '+1') {
				$number = $this->options['page']+1;
				$text = '&raquo;';
				$active = false;
			} elseif ($link == $this->options['page']) {
				$number = $link;
				$text = $link;
				$active = true;
			} elseif ($link == '...') {
				continue;
			} elseif (is_numeric($link)) {
				$number = $link;
				$text = $link;
				$active = false;
			}

			if ($text == '&raquo;' AND isset($this->options['jump_to']) and $this->options['jump_to']) {
				$str_links .= '<li><span class="jump-to-page" style="padding: 4px"><input type="text" size="4" style="border: solid 1px #eee; text-align: center;" placeholder="#" id="jump-to-page-' . str_replace('_', '-', strtolower($this->classname)) . '"></span></li>';
			}

			$hash = $this->create_options_hash($this->options['conditions'], $number, $this->options['sort'], $this->options['direction'], $this->options['joins']);

			$qry_str = '';
			if (isset($_SERVER['QUERY_STRING'])) {
				$qry_str = $_SERVER['QUERY_STRING'];
			}

			parse_str($qry_str, $qry_str_parts);

			$qry_str_parts['q'] = $hash;
			if (isset($qry_str_parts['p'])) {
				unset($qry_str_parts['p']);
			}

			$url = self::find_page_uri() . '?' . http_build_query($qry_str_parts);
			$str_links .= $this->create_page_link($text, $url, $active);
			if ($key+1 == count($links) AND $text != '&raquo;' AND $this->options['jump_to']) {
				$str_links .= '<li><span class="jump-to-page" style="padding: 4px"><input type="text" size="4" style="border: solid 1px #eee; text-align: center;"  placeholder="#" id="jump-to-page-' . str_replace('_', '-', strtolower($this->classname)) . '"></span></li>';
			}
		}

		$content = '<ul class="pagination pagination-centered" id="pager-' . str_replace('_', '-', strtolower($this->classname)) . '">' . $str_links . '</ul>';
		$this->links = $content;
	}

	/**
	 * Find out how we should refer to the current page
	 *
	 * @access private
	 * @return string $uri
	 */
	private static function find_page_uri() {
		// We need to remove the base_uri from the link, because it will get
		// rewritten afterwards. If we leave it, it will be prepended again,
		// which makes the link invalid.
		if (class_exists('\Skeleton\Core\Application')) {
			$application = \Skeleton\Core\Application::get();
		}

		$request_uri = str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);

		if (isset($application->config->base_uri) AND strpos($request_uri, $application->config->base_uri) === 0) {
			$url = substr($request_uri, strlen($application->config->base_uri) -1);
		} else {
			$url = $request_uri;
		}

		return $url;
	}

	/**
	 * expand field name
	 * If a fieldname without a '.' is given, it will be prepended with the table name
	 *
	 * @access private
	 * @param $string $field_name
	 * @return string $expanded_field_name
	 */
	private function expand_field_name($field_name) {
		if (strpos($field_name, '.') !== false) {
			return $field_name;
		}

		$classname = $this->classname;
		$object = new \ReflectionClass($classname);
		if (is_callable($field_name) === true OR $object->hasMethod($field_name) === true) {
			return $field_name;
		}

		return $classname::trait_get_database_table() . '.' . $field_name;
	}

	/**
	 * Get key for storing the options hash in session
	 *
	 * @access private
	 * @return string $pager_uri_key
	 */
	private function get_pager_uri_key() {
		$request_uri = str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
		$qry_str = $_SERVER['QUERY_STRING'];


		parse_str($qry_str, $qry_str_parts);
		unset($qry_str_parts['p']);
		unset($qry_str_parts['q']);
		$pager_uri_key = base64_encode(strtolower($this->classname) . '/' . str_replace('/index', '', $request_uri) . '?' . implode('&', $qry_str_parts));

		return $pager_uri_key;
	}

	/**
	 * Get from options_hash
	 *
	 * @access public
	 * @param string $options_hash
	 * @return Web_Pager $pager
	 */
	public static function get_by_options_hash($options_hash) {
		$options = unserialize(base64_decode(urldecode($options_hash)));
		$pager = new self($options['classname']);
		$pager->options = $options;
		return $pager;
	}
}
