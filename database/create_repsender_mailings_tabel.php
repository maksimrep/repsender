<?php
add_action("after_switch_theme", "create_repsender_mailings_tabel");

function create_repsender_mailings_tabel() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name_newsletter_emails = $wpdb->get_blog_prefix() . 'repsender_emails';
	$table_name_subscribers = $wpdb->get_blog_prefix() . 'repsender_subscribers';

	$table_name = $wpdb->get_blog_prefix() . 'repsender_mailings';
	$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";

	$sql = "CREATE TABLE {$table_name} (
		email_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		status_send boolean default false,
		open boolean default false,
		time_send int(10) unsigned NOT NULL default 0,
		error varchar(100) NOT NULL default '',
		ip varchar(50) NOT NULL default '',
		PRIMARY KEY (email_id, user_id),
		FOREIGN KEY (email_id) REFERENCES $table_name_newsletter_emails(id),
		FOREIGN KEY (user_id) REFERENCES $table_name_subscribers(id)
	)
	{$charset_collate};";

	if( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ){
		dbDelta($sql);
	}
}
//create_repsender_mailings_tabel();
