<?php 

class Carbon_DataStore_TermMeta extends Carbon_DataStore_Base {
	protected $term_id;

	static function create_table() {
		global $wpdb;

		$tables = $wpdb->get_results('SHOW TABLES LIKE "' . $wpdb->prefix . 'termmeta"');

		if ( !empty($tables) ) {
			return;
		}

		$charset_collate = '';	
		if ( ! empty($wpdb->charset) ) {
			$charset_collate = "DEFAULT CHARACTER SET " . $wpdb->charset;
		}
			
		if ( ! empty($wpdb->collate) ) {
			$charset_collate .= " COLLATE " . $wpdb->collate;
		}

		$wpdb->query('CREATE TABLE ' . $wpdb->prefix . 'termmeta (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			term_id bigint(20) unsigned NOT NULL default "0",
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY	(meta_id),
			KEY term_id (term_id),
			KEY meta_key (meta_key)
		) ' . $charset_collate . ';');
	}

	function init() {
		global $wpdb;

		// Setup termmeta table and hooks only once
		if ( !empty($wpdb->termmeta) ) {
			return;
		}

		$wpdb->termmeta = $wpdb->prefix . 'termmeta';

		self::create_table();

		// Delete all meta associated with the deleted term
		add_action('delete_term', array(__CLASS__, 'on_delete_term'), 10, 3);
	}

	function save(Carbon_Field $field) {
		if ( !add_metadata('term', $this->term_id, $field->get_name(), $field->get_value(), true) ) {
			update_metadata('term', $this->term_id, $field->get_name(), $field->get_value());
		}
	}

	function load(Carbon_Field $field) {
		global $wpdb;

		$value = $wpdb->get_col('
			SELECT `meta_value`
			FROM ' . $wpdb->termmeta . '
			WHERE `term_id`=' . intval($this->term_id) . '
			AND `meta_key`="' . $field->get_name() . '"
			LIMIT 1
		');

		if ( !is_array($value) || count($value) < 1 ) {
			$field->set_value(false);
			return;
		}

		$field->set_value($value[0]);
	}

	function delete(Carbon_Field $field) {
		delete_metadata('term', $this->term_id, $field->get_name(), $field->get_value());
	}

	function load_values($field) {
		global $wpdb;

		if ( is_object($field) && is_subclass_of($field, 'Carbon_Field') ) {
			$meta_key = $field->get_name();
		} else {
			$meta_key = $field;
		}

		return $wpdb->get_results('
			SELECT meta_key AS field_key, meta_value AS field_value FROM ' . $wpdb->termmeta . '
			WHERE `meta_key` LIKE "' . addslashes($meta_key) . '_%" AND term_id="' . intval($this->term_id) . '"
		', ARRAY_A);
	}

	function delete_values(Carbon_Field $field) {
		global $wpdb;

		$group_names = $field->get_group_names();
		$field_name = $field->get_name();

		$meta_key_constraint = '`meta_key` LIKE "' . $field_name . implode('-%" OR `meta_key` LIKE "' . $field_name, $group_names) . '-%"';

		return $wpdb->query('
			DELETE FROM ' . $wpdb->termmeta . '
			WHERE (' . $meta_key_constraint . ') AND term_id="' . intval($this->term_id) . '"
		');
	}

	function set_id($term_id) {
		$this->term_id = $term_id;
	}

	static function on_delete_term($term_id, $tt_id, $taxonomy) {
		global $wpdb;

		return $wpdb->query('
			DELETE FROM ' . $wpdb->termmeta . '
			WHERE `term_id` = "' . intval($term_id) . '"
		');
	}
}