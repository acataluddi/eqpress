<?php
/**
 * easyPress Console Proxy
 * Version: 1.0
 *
 * Use transients to store web stats for 15 min
 *
 */	

 
if ( !isset( $_POST['do'] ) )
	process_errors( "easypress-console-proxy accessed with no do POST argument\n", true );
if ( !isset( $_POST['domain'] ) )
	process_errors( "easypress-console-proxy accessed with no domain POST argument\n", true );
if ( !isset( $_POST['api_key'] ) )
	process_errors( "easypress-console-proxy accessed with no api_key POST argument\n", true );
if ( !valid_domain( $_POST['domain'] ) )
	process_errors( "easypress-console-proxy: The domain supplied is invalid", true );

$do = $_POST['do'];
$all_errors = array();
$domain = $_POST['domain'];
$api_key = $_POST['api_key'];

// check for ssl_state POST argument
if ( 'wpadmin_ssl' == $do ) {
	if ( isset( $_POST['ssl_state'] ) )
		$ssl_state = $_POST['ssl_state'];
	else
		process_errors( "easypress-console-proxy: wpadmin_ssl error. No ssl_state argument provided. Domain is $domain.", true );
}
// check for edits POST argument
if ( 'editor' == $do ) {
	if ( isset( $_POST['edits'] ) )
		$edits = $_POST['edits'];
	else
		process_errors( "easypress-console-proxy: admin_editor error. No edits argument provided. Domain is $domain.", true );
}

// Print error message and exit if the API key is not valid.
if ( !validate_api_key( $api_key, $domain ) ) {
	$message = 'API key is not valid';
	echo "<h2>$message</h2>";
	process_errors( $message, true );
}

log_console_usage( $domain, $_POST );

switch($do) {
	case 'perms':
		do_perms( $domain );
		break;
	case 'logs':
		do_logs( $domain );
		break;
	case 'cache':
		do_cache( $domain );
		break;
	case 'stats':
		do_stats( $domain );
		break;
	case 'stats_month':
		do_stats_month( $domain );
		break;
	case 'lockdown':
		do_lockdown( $domain );
		break;
	case 'undo_lockdown':
		do_undo_lockdown( $domain );
		break;
	case 'reset_passwd':
		do_password_reset( $domain );
		break;
	case 'wpadmin_ssl':
		do_wpadmin_ssl( $domain, $ssl_state );
		break;
	case 'editor':
		do_editor( $domain, $edits );
		break;
}


/**
 * Reset permissions and ownership to www-data. Set the flag, monit does the rest.
 * Using monit because root access required.
 *
 * @param string $domain is the customer's domain name.
 *
 */
function do_perms( $domain ) {
	$flag = dirname( __FILE__ ) . '/../perms/' . $domain;
	if( file_exists( $flag ) ) {
		echo "<br /><b>File permissions and ownership modifications are in progress. Patience is power.</b>";
	} else {
		$last_line_out = exec( "touch $flag 2>&1", $out, $ret_val );
		echo "<br /><b>The file permissions and ownerships under your document root will reset within 60 seconds.</b>";
	}
}

/**
 * easyPress Console website and PHP logs
 *
 */
function do_logs( $domain ) {
	$php_log = '/var/www/' . $domain . '/wordpress/php-errors.log';
	$web_alog = '/var/log/nginx/' . $domain . '.access.log';
	$web_elog = '/var/log/nginx/' . $domain . '.error.log';
	
	if ( isset($_POST['log']) && $_POST['log'] == 'pe' ) {
		echo '<pre class="super-big">PHP Error Log</pre>';
		$shellout = exec( '/usr/bin/tail -50 ' . $php_log . ' 2>&1', $out, $status );
		if ( $status != 0 ) {
			$message = var_export( $out, true );
			$message .= $shellout;
			process_errors( $message );
		} else {
			foreach ( $out as $line )
				echo '<pre>' . htmlentities( $line, ENT_COMPAT, 'UTF-8' )  . '</pre>';
		}
	}	
	if ( isset($_POST['log']) && $_POST['log'] == 'wa' ) {
		echo '<pre class="super-big">Web Access Log</pre>';
		$shellout = exec( '/usr/bin/tail -50 ' . $web_alog . ' 2>&1', $out, $status );
		if ( $status != 0 ) {
			$message = var_export( $out, true );
			$message .= $shellout;
			process_errors( $message );
		} else {
			foreach ( $out as $line )
				echo '<pre>' . htmlentities( $line, ENT_COMPAT, 'UTF-8' ) . '</pre>';
		}
	}
	if ( isset($_POST['log']) && $_POST['log'] == 'we' ) {
		echo '<pre class="super-big">Web Error Log</pre>';
		$shellout = exec( '/usr/bin/tail -50 ' . $web_elog, $out, $status );
		if ( $status != 0 ) {
			$message = var_export( $out, true );
			$message .= $shellout;
			process_errors( $message );
		} else {
			foreach ( $out as $line )
				echo '<pre>' . htmlentities( $line, ENT_COMPAT, 'UTF-8' )  . '</pre>';
		}
	}
}

/**
 * easyPress Console cache management page
 *
 */
function do_cache( $domain ) {
	$cache = "/var/cache/nginx/$domain/";
	$shellout = '';
	if ( isset($_POST['purge']) && $_POST['purge'] == 'yes' ) {
		if ( is_dir( $cache ) && ( count( glob( "$cache/*" ) ) != 0 ) ) {
			$shellout = exec( '/bin/rm -r ' . $cache . '/* 2>&1', $out, $status );
			if ( $status != 0 ) {
				echo '<h2>Return code is not 0.</h2>';
				$message = var_export( $out, true );
				$message .= $shellout;
				process_errors( $message );
			} else {
				echo '<h2>Cache has been deleted.</h2>';
			}
		} elseif ( !is_dir( $cache ) ) {
            $message = "The directory $cache does not exist.";
            echo "<h2>" . $message . "</h2>";
            process_errors( $message, true );
        } else {
			echo '<h2>Cache is empty.</h2>';
			exit;
		}
	}
	/*
	if ( is_dir( $cache ) && ( count( glob( "$cache/*" ) ) != 0 ) ) {
		$shellout = exec( '/usr/bin/du -sh ' . $cache . ' 2>&1', $out, $status );
		if ( $status != 0 ) {
			$message = var_export( $out, true );
			process_errors( $message );
			process_errors( $shellout );
		} else {
			echo '<pre class="super-big">Size of cache: ' . preg_replace('/\/.*$/', '', $shellout ) . '</pre>';
		}
	} else {
		echo '<h2>Cache is empty.</h2>';
		exit;
	}
	*/
}

/**
 * easyPress Console Website Stats page
 *
 */
function do_stats( $domain ) {
	require_once( dirname( __FILE__) . '/../plugin/easypress-console/inc/class-http-log-parser.php' );
	$hlp = new HTTP_Log_Parser();
	$hlp->set_log( $domain );
	$stats = $hlp->visitors();
	echo '<p class="super-big">Today\'s Stats</p>';
	echo '<pre class="super-big">';
	echo 'Hits:            ' . $stats['hits'] . '<br />';
	echo 'Visits:          ' . $stats['visits'] . '<br />';
	echo 'Unique Visitors: ' . $stats['visitors'] . '<br />';
	echo 'Data Transfered: ' . $stats['transfered'] . '</pre>';
}

/**
 * easyPress Console monthly website stats
 *
 * Connect to swp_webstats database to extract daily metrics and output a table,
 * CSV, JSON, YAML
 *
 */
function do_stats_month( $domain ) {
	$last_few_days = 0;
	$busiest_day = array( 0, 0, 0, 0, 0 );
	$db_query = "
		SELECT date, hits, visits, unique_visitors, bytes_transfered
		FROM stats WHERE domain_id
		IN (
			SELECT ID
			FROM domains
			WHERE domain LIKE '$domain')
		ORDER BY date DESC";
	$dbh = new mysqli( 'localhost', 'swp_webstats', 'c3hXyH2jhrW7YL7w26qRU', 'swp_webstats' );
	if ( $dbh->connect_error ) {
		process_errors( "Connect failed to swp_webstats for domain $domain: " . $dbh->connect_errno . ' : ' . $dbh->connect_error, true );
	}
	if ( $db_results = $dbh->query( $db_query ) ) {
		$all_stats = $db_results->fetch_all();
		if ( !empty( $all_stats ) ) {
			echo "<h3>Monthly Stats</h3>";
			echo '<div class="CSSTableGenerator">';
			echo "<table>\n";
			echo "<tr><td>Month</td><td>Hits</td><td>Visits</td><td>Unique Visitors</td><td>Transfered</td></tr>\n";
			$total_bytes = $month_hits = $month_visits = $month_uniques = $month_bytes = 0;
			$this_month = preg_replace( '/-[0-9][0-9]$/', '', $all_stats[0][0] );
			foreach ( $all_stats as $stats ) {
				if ( $busiest_day[3] < $stats[3] )
					$busiest_day = $stats;
				if ( $last_few_days < 31 ) {
					$daily_stats[] = $stats;
					$last_few_days++;
				}
				$total_bytes += $stats[4];
				$same_month = strpos( $stats[0], $this_month );
				if ( $same_month === false ) {
					//echo "Total Bandwith Utilized in $this_month: " . bytes_to_size( $month_bytes ) . '<br />';
					echo "<tr><td>$this_month</td><td>$month_hits</td><td>$month_visits</td><td>$month_uniques</td><td>";
					echo bytes_to_size( $month_bytes ) . "</td></tr>\n";
					$month_hits = $month_visits = $month_uniques = $month_bytes = 0;
					$month_bytes += $stats[4];
					$this_month = preg_replace( '/-[0-9][0-9]$/', '', $stats[0] );
				} else {
					$month_hits += $stats[1];
					$month_visits += $stats[2];
					$month_uniques += $stats[3];
					$month_bytes += $stats[4];
				}
			}
			echo "<tr><td>$this_month</td><td>$month_hits</td><td>$month_visits</td><td>$month_uniques</td><td>";
			echo bytes_to_size( $month_bytes ) . "</td></tr></table>\n</div>\n";
			//echo "Total Bandwith Utilized in $this_month: " . bytes_to_size( $month_bytes ) . '<br />';
			echo "Total Bandwith Utilized: " . bytes_to_size( $total_bytes ) . '<br />';
			echo "<h3>Busiest Day</h3>";
			echo '<div class="CSSTableGenerator">';
			echo "<table>\n";
			echo "<tr><td>Date</td><td>Hits</td><td>Visits</td><td>Unique Visitors</td><td>Transfered</td></tr>\n";
			echo "<tr><td>$busiest_day[0]</td><td>$busiest_day[1]</td><td>$busiest_day[2]</td><td>$busiest_day[3]</td><td>";
			echo bytes_to_size( $busiest_day[4] ) . "</td></tr>\n";
			echo "</table>\n</div>\n";
			echo "<h3>Recent Daily Stats</h3>";
			echo '<div class="CSSTableGenerator">';
			echo "<table>\n";
			echo "<tr><td>Date</td><td>Hits</td><td>Visits</td><td>Unique Visitors</td><td>Transfered</td></tr>\n";
			foreach ( $daily_stats as $ds ) {
				echo "<tr><td>$ds[0]</td><td>$ds[1]</td><td>$ds[2]</td><td>$ds[3]</td><td>";
				echo bytes_to_size( $ds[4] ) . "</td></tr>\n";
			}
			echo "</table>\n</div>";
		} else {
			echo "No stats yet.";
		}
	} else {
		process_errors( 'Results for query not found', true );
	}
	$dbh->close();
}
	
/**
 * Lockdown the document root using stricter permissions and ownerships.
 * Using monit because root access required.
 *
 * @param string $domain is the customer's domain name.
 *
 */
function do_lockdown( $domain ) {
	$flag = dirname( __FILE__ ) . '/../lockdown/lock/' . $domain;
	if( file_exists( $flag ) ) {
		echo "<br /><b>Lockdown is in progress. Patience is power.</b>";
	} else {
		$last_line_out = exec( "touch $flag 2>&1", $out, $ret_val );
		echo "<br />Lockdown will be set within ";
	}
}

/**
 * Undo lockdown.
 * Using monit because root access required.
 *
 * @param string $domain is the customer's domain name.
 *
 */
function do_undo_lockdown( $domain ) {
	$flag = dirname( __FILE__ ) . '/../lockdown/unlock/' . $domain;
	if( file_exists( $flag ) ) {
		echo "<br /><b>Undoing the lockdown is in progress. Patience is power.</b>";
	} else {
		$last_line_out = exec( "touch $flag 2>&1", $out, $ret_val );
		echo "<br />Lockdown will be undone within ";
	}
}

/**
 * Reset SFTP password.
 *
 * 1. Generate a random password
 * 2. Write the password to a file for monit to find (monit will use chpasswd)
 * 3. Send the password to PWPush
 * 4. echo the URL to the cosole screen
 * 5. email it to the site administrator.
 *
 * @param string $domain is the customer's domain name.
 *
 */
function do_password_reset( $domain ) {
	$passwd_length = 23;
	$flag = dirname( __FILE__ ) . '/../password/' . $domain;
	if( file_exists( $flag ) ) {
		echo "<br /><b>Password reset is in progress. Patience is power.</b>";
	} else {
		$new_passwd = random( $passwd_length );
		$http_response = surf( "cred=$new_passwd&time=10&units=days&views=3&url_only=yes" );
		$both_passwds = $new_passwd . "'" . $http_response['body'];
		write_to_file( $flag, $both_passwds, $domain );
		echo '<p class="console-password">Your new password is: <b>' . $new_passwd . "</b></p>";
		echo 'The system will apply the new password within 60 seconds.';
	}
}

/**
 * Administration over SSL
 * 
 * Choose between SSL for logins only, SSL for all wp-admin screens including
 * login or no SSL.
 *
 * @param string $domain is the customer's domain name.
 * 
 */
function do_wpadmin_ssl( $domain, $ssl_state ) {
	$wp_dir = "/var/www/" . $domain . "/wordpress";
	$backup_wp_config = $wp_dir . '/.wpc_backup';
	if ( is_file( $wp_dir . '/wp-config.php' ) )
		$wp_config_file = $wp_dir . '/wp-config.php';
	else if ( is_file( $wp_dir . '../wp-config.php' ) )
		$wp_config_file = $wp_dir . '../wp-config.php';
	else
		process_errors( "easypress-console-proxy: wpadmin_ssl error. " .  $wp_dir . "/wp-config.php not found. Domain is $domain.", true );
	
	if ( 'off' == $ssl_state ) {
		$new_state_admin = "'FORCE_SSL_ADMIN', false";
		$new_state_login = "'FORCE_SSL_LOGIN', false";
	} else if ( 'admin' == $ssl_state ) {
		$new_state_admin = "'FORCE_SSL_ADMIN', true";
		$new_state_login = "'FORCE_SSL_LOGIN', true";
	} else if ( 'login' == $ssl_state ) {
		$new_state_admin = "'FORCE_SSL_ADMIN', false";
		$new_state_login = "'FORCE_SSL_LOGIN', true";
	} else
		process_errors( "easypress-console-proxy: wpadmin_ssl error. Unexpeced ssl_state argument: $ssl_state. Domain is $domain.", true );
	
	$old_wp_config = file_get_contents( $wp_config_file );
	file_put_contents( $backup_wp_config, $old_wp_config );
	$new_wp_config = preg_replace( '/\'FORCE_SSL_ADMIN\', *(true|false)/', $new_state_admin, $old_wp_config );
	$old_wp_config = $new_wp_config;
	$new_wp_config = preg_replace( '/\'FORCE_SSL_LOGIN\', *(true|false)/', $new_state_login, $old_wp_config );
	file_put_contents( $wp_config_file, $new_wp_config );
	echo "<p>Well done!</p>";
}

/**
 * Plugin and Theme Editor
 * 
 * Enable or diable the editor.
 *
 * @param string $domain is the customer's domain name.
 * 
 */
function do_editor( $domain, $edits ) {
	$wp_dir = "/var/www/" . $domain . "/wordpress";
	$backup_wp_config = $wp_dir . '/.wpc_backup';
	if ( is_file( $wp_dir . '/wp-config.php' ) )
		$wp_config_file = $wp_dir . '/wp-config.php';
	else if ( is_file( $wp_dir . '../wp-config.php' ) )
		$wp_config_file = $wp_dir . '../wp-config.php';
	else
		process_errors( "easypress-console-proxy: admin-editor error. " .  $wp_dir . "/wp-config.php not found. Domain is $domain.", true );
	
	if ( 'off' == $edits ) {
		$new_state = "'DISALLOW_FILE_EDIT', true";
	} else if ( 'on' == $edits ) {
		$new_state = "'DISALLOW_FILE_EDIT', false";
	} else
		process_errors( "easypress-console-proxy: admin-editor error. Unexpected edits argument: $edits. Domain is $domain.", true );
	
	$old_wp_config = file_get_contents( $wp_config_file );
	file_put_contents( $backup_wp_config, $old_wp_config );
	$new_wp_config = preg_replace( '/\'DISALLOW_FILE_EDIT\', *(true|false)/', $new_state, $old_wp_config );
	file_put_contents( $wp_config_file, $new_wp_config );
	echo "<p>Well done!</p>";
}

/**
 * Validate the API key sent by console plugin.
 *
 * @param string $api_key is the API key defined in wp-config.php.
 * @param string $domain is the domain of the calling console.
 *
 * @return boolean whether the key is valid or not.
 *
 */
function validate_api_key ( $api_key, $domain ) {
	$ep_secret_key = $domain . '6kwiyk768g7gy2PVhhzFEUu';
	$ep_api_key = md5( $ep_secret_key );
	if ( $ep_api_key === $api_key )
		return true;
	else
		return false;
}

/**
 * Make sure the domain name entered is valid.
 *
 * @param string $domain is the domain name of the site to install.
 * @return boolean
 *
 */
function valid_domain($domain) {
	$subdomain = "";
	$pieces = explode(".", $domain);
	if ( ( $num_pieces = sizeof($pieces) ) < 2)
		return false;
    foreach($pieces as $piece) {
        if (!preg_match('/^[a-z\d][a-z\d-]{0,62}$/i', $piece) || preg_match('/-$/', $piece) )
            return false;
		else
			return true;
	}
}

/**
 * Process errors
 *
 * @param string $message is the error message to write to the log and/or screen.
 * @param boolean $exit_now will determine whether or not to terminate the process.
 * 
 */
function process_errors( $message, $exit_now = false ) {
	global $all_errors;
	$all_errors[] = $message;
	error_log( $message );
	if ( $exit_now ) {
		var_dump( $all_errors );
		error_log( var_export( $all_errors, true ) );
		exit;
	}
}

/**
 * Log usage
 *
 * @param string $domain
 * @param string $query is a dump of $_POST
 *
 */
function log_console_usage( $domain, $query ) {
	$log_dir = dirname( __FILE__ ) . '/../log/';
	if ( is_dir( $log_dir ) ) {
		$console_log = $log_dir . $domain;
		$now = date('r');
		$unow = time();
		unset( $query['domain'] );
		unset( $query['api_key'] );
		$full_query = http_build_query( $query );
		$fh = fopen( $console_log, 'a' ) or process_errors( "Failed to open $console_log for: $domain", true );
		fwrite( $fh, "$now $unow " ) or process_errors( "Failed to write to $console_log for: $domain", true );
		fwrite( $fh, "$full_query\n" ) or process_errors( "Failed to write to $console_log for: $domain", true );
		fclose( $fh ) or process_errors( "Failed to close $console_log for: $domain", true );
	} else {
		process_errors( "easypress-console-proxy: log_console_usage() failed. Log directory not found. Domain is $domain" );
	}
}

/**
 * bytes_to_size() - convert to readable format
 *
 * @param integer $bytes is the value to convert from bytes to something more readable
 * @param integer $precision is the number of decimal digits to calculate
 *
 */
function bytes_to_size($bytes, $precision = 2) {
	$kilobyte = 1024;
	$megabyte = $kilobyte * 1024;
	$gigabyte = $megabyte * 1024;
	$terabyte = $gigabyte * 1024;

	if (($bytes >= 0) && ($bytes < $kilobyte))
	{
		return $bytes . ' B';
	}
	elseif (($bytes >= $kilobyte) && ($bytes < $megabyte))
	{
		return round($bytes / $kilobyte, $precision) . ' KB';
	}
	elseif (($bytes >= $megabyte) && ($bytes < $gigabyte))
	{
		return round($bytes / $megabyte, $precision) . ' MB';
	}
	elseif (($bytes >= $gigabyte) && ($bytes < $terabyte))
	{
		return round($bytes / $gigabyte, $precision) . ' GB';
	}
	elseif ($bytes >= $terabyte)
	{
		return round($bytes / $terabyte, $precision) . ' TB';
	}
	else
	{
		return $bytes . ' B';
	}
}

/**
 * Connect to the proxy.
 *
 * @param string $url is the full URL containing the hostname and request.
 * @param string $host is the hostname of the new site used in the Host: HTTP header.
 * @param string $method is the HTTP method (GET | POST).
 * @param string $post_params are the POST arguments in key=value&key=value format.
 *
 * @return array containing HTTP response code, the body of the response, elapsed time.
 */
function surf( $post_params ) {
	require_once ( dirname(__FILE__) . '/../plugin/easypress-console/inc/class-curl-request.php' );
	try {
		$mych = new CurlRequest;
		$params = array( //'url' => 'https://easypress.ca/pwpush/pwpusher_public/pw.php',
                        'url' => 'https://www.getpendeo.com/pwpush/pwpusher_public/pw.php',
						'host' => 'www.getpendeo.com',
						'header' => '',
						'method' => 'POST',
						'referer' => '',
						'cookie' => '',
						'post_fields' => $post_params,
						'timeout' => 90,
						'verbose' => 0 );

		$mych->init( $params );
		$result = $mych->exec();
		if ( $result['curl_error'] ) throw new Exception( $result['curl_error'] );
		if ( $result['http_code'] != '200' ) throw new Exception( "HTTP Code = " . $result['http_code'] . "\nBody: " . $result['body'] );
		if ( NULL === $result['body'] ) throw new Exception( "Body of file is empty" );
		//echo $result['header'];
		//echo 'HTTP return code: ' . $result['http_code'];
	}
	catch ( Exception $e ) {
		error_log( "easypress-console-proxy: surf() error. " . $e->getMessage() );
	}
	return array( 'code' => $result['http_code'], 'body' => $result['body'], 'etime' => $result['etime'] );
}

/**
 * Return a random alphanumeric string
 *
 * @param int $len is the length of the random string to generate.
 * @return string containing the random string.
 *
 */
function random($len) {
	$chars = '/[0-9A-Za-z]/';
	$rs = "";
	$f = fopen('/dev/urandom', 'r');
	if ( $f == FALSE )
		process_errors( 'Could not open /dev/urandom', true );
	for($x = 0; $x < $len; $x++) {
		$c = fgetc($f);
		if (preg_match($chars, $c))
			$rs .= $c;
		else
			$x--;
	}
	fclose($f);
	return $rs;
}

/**
 * Write to a file
 *
 * @param string $the_file is the absolute path to the file to create.
 * @param string $the_text is the content being written to the file.
 *
 */
function write_to_file( $the_file, $the_text, $domain ) {
	$fh = fopen( $the_file, 'wb' ) or process_errors( "Failed to open $the_file for: " . $domain, true );
	fwrite( $fh, $the_text ) or process_errors( "Failed to write to $the_file for: " . $domain, true );
	fclose( $fh ) or process_errors( "Failed to close $the_file for: " . $domain, true );
}
