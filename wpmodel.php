<?php


class WPModel {

	// Start static varaibles & methods

	// Global WPModel settings
	public static $storage_key_prefix = 'wpcollection_';

	// Child class specific settings & varaibles
	private static $storage_key, $key, $model_class, $models, $is_initialized;
	
	private static function init() {
		if ( is_null(self::$is_initialized) ) {
			// Set storage_key varaible to lowercase string of called class if storage_key option isn't specified
			if ( isset(static::$storage_key) ) {
				self::$storage_key = static::$storage_key;
			} else {
				self::$storage_key = strtolower(get_called_class());
			}

			self::$model_class = get_called_class();

			self::load_storage();

			self::$is_initialized = true;
		}
	}

	private static function load_storage() {
		self::$key = self::$storage_key_prefix . self::$storage_key;
		
		// Ensure there is a default value in option
		add_option(self::$key, '[]');

		// Only pull in rows if this collection hasn't been loaded yet, or it's a different collection than the previous
		$rows = (array)json_decode(get_option(self::$key));

		self::$models = array();

		foreach ( $rows as $n => $row ) self::$models[] = new self::$model_class($row, $n);
	}

	private static function commit_storage() {
		$rows = array();

		// Extract data objects
		foreach ( self::$models as $n => $row ) {
			if ( !is_null($row->should_persist) ) $rows[] = $row->raw();
		}

		// Serialize data
		$json_data = json_encode($rows);

		// Update DB
		update_option(self::$key, $json_data);
	}

	public static function find($finder = null) {
		self::init();

		if ( is_null($finder) ) {
			return self::all();
		}

		$matches = array();

		foreach ( self::$models as $n => $row ) {
			if ( $finder($row) == true ) $matches[] = $row;
		}
		
		return $matches;
	}

	public static function last() {
		self::init();

		$last = end(self::$models);
		reset(self::$models);

		return $last;
	}

	public static function all() {
		self::init();

		return self::$models;
	}

	public static function find_one($finder = null) {
		self::init();

		if ( !is_null($finder) ) {
			foreach ( self::$models as $n => $row ) {
				if ( $finder($row) == true ) return $row;
			}
		} else {
			if ( isset(self::$models[0]) ) return self::$rows[0];
		}
		
		return null;
	}

	private static function delete_by_index($index) {
		self::init();

		// Remove element from array (reindex)
		array_splice(self::$models, $index, 1);

		// Update internal indexes
		foreach ( self::$models as $n => $row ) $row->internal_index = $n;

		self::commit_storage();
	}

	public static function insert($obj) {
		self::init();

		// Get unique ID
		do {
			$obj['_id'] = self::generate_id();
		} while ( self::exists(function($row) {
			$row->get('_id') == $obj['_id'];
		}) );

		if ( count(self::$models) > 0 ) {
			$internal_index = self::last()->internal_index + 1;
		} else {
			$internal_index = 0;
		}
		
		self::$models[] = new self::$model_class((object)$obj, $internal_index);

		self::commit_storage();
	}

	// public static function get($id) {
	// 	self::init();

	// 	if ( !is_null($id) ) {
	// 		foreach ( self::$rows as $n => $row ) {
	// 			if ( $id == $row->_id ) return new self::$model_class($row, $n);
	// 		}
	// 	}

	// 	return null;
	// }

	public static function exists($finder) {
		self::init();

		if ( !is_null($finder) ) {
			foreach ( self::$models as $n => $row ) {
				if ( $finder($row) == true ) return true;
			}
		}
		
		return false;
	}

	public static function drop() {
		self::init();
		self::$models = array();
		self::commit_storage();
	}

	private static function generate_id($length = 20) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		
		$randomString = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$randomString .= $characters[rand(0, strlen($characters) - 1)];
		}

		return $randomString;
	}


	// Start instance methods
	private $data;
	protected $internal_index, $should_persist;

	public function __construct($data, $internal_index = null) {
		
		// Is this a new record?
		if ( is_null($internal_index) ) {

			// Get unique ID
			do {
				$data['_id'] = self::generate_id();
			} while ( self::exists(function($row) {
				$row->get('_id') == $data['_id'];
			}) );

			if ( count(self::$models) > 0 ) {
				$internal_index = self::last()->internal_index + 1;
			} else {
				$internal_index = 0;
			}
			
			self::$models[] = $this;
		} else {
			$this->should_persist = true;
		}

		$this->data = (object)$data;
		$this->internal_index = $internal_index;
	}

	public function raw() {
		return $this->data;
	}

	public function get($key) {
		return $this->data->{$key};
	}

	public function set($key, $value) {
		$this->data->{$key} = $value;
	}

	public function save() {
		$this->should_persist = true;

		self::commit_storage();
	}

	public function delete() {
		unset($this->data);

		self::delete_by_index($this->internal_index);
	}

}

