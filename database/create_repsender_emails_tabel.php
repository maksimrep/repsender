<?php
add_action("after_switch_theme", "create_repsender_emails_tabel");

function create_repsender_emails_tabel() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name = $wpdb->get_blog_prefix() . 'repsender_emails';
	$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";

	$sql = "CREATE TABLE {$table_name} (
	id bigint(20) unsigned NOT NULL auto_increment,
    language varchar(10) NOT NULL default '',
	type enum('text','html') NOT NULL default 'text',
	from_email varchar(100) NOT NULL default '',
    subject varchar(250) NOT NULL default '',
    description varchar(250) NOT NULL default '',
    message longtext default '',
    created timestamp NOT NULL default CURRENT_TIMESTAMP,
	post_date datetime NOT NULL default '0000-00-00 00:00:00',
    open_count int(10) unsigned NOT NULL default 0,
	status enum('new','sending','sent','paused') NOT NULL default 'new',
    total int(11) NOT NULL default 0,
    last_id int(11) NOT NULL default 0,
    sent int(11) NOT NULL default 0,
	PRIMARY KEY (id)
	)
	{$charset_collate};";

	if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
		dbDelta($sql);
	}	
}
//create_repsender_emails_tabel();
