<?php
/**
 * End-to-end test fixture, run with the PHP CLI inside the web server container.
 *
 * Creates and removes the users, posts, files and records the E2E tests use,
 * and restores the plugin settings and options it changed. Every command
 * prints one JSON document.
 *
 * Usage: php fixture.php <command> [args...]
 *
 * @package LeadMagnetLocker
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

$_SERVER['HTTP_HOST']   = getenv( 'LML_E2E_HOST' ) ? getenv( 'LML_E2E_HOST' ) : 'localhost:85';
$_SERVER['REQUEST_URI'] = '/';

// Keep fixture runs out of the error tracker: pre-register the filter before WordPress loads.
$GLOBALS['wp_filter']['wp_sentry_dsn'][10][] = array(
	'function'      => static fn() => '',
	'accepted_args' => 1,
);

require dirname( __DIR__, 5 ) . '/wp-load.php';

use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerStorage;

const LML_E2E_PREFIX     = 'lml-e2e-';
const LML_E2E_EMAIL_HOST = '@lml-e2e.test';
const LML_E2E_ADMIN      = 'lml_e2e_admin';
const LML_E2E_SUBSCRIBER = 'lml_e2e_subscriber';
const LML_E2E_MU_PLUGIN  = 'lml-e2e-silence-sentry.php';

/**
 * Where the pre-test state is kept between "setup" and "teardown".
 *
 * @return string
 */
function lml_e2e_state_file(): string {
	return sys_get_temp_dir() . '/lml-e2e-state.json';
}

/**
 * Print a JSON result and stop.
 *
 * @param mixed $data Result.
 * @return never
 */
function lml_e2e_out( $data ) {
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
	exit( 0 );
}

/**
 * Create (or recreate) a user with a random password.
 *
 * @param string $login User login.
 * @param string $role  Role.
 * @return array{login: string, password: string, id: int}
 */
function lml_e2e_user( string $login, string $role ): array {
	$existing = get_user_by( 'login', $login );
	if ( $existing ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $existing->ID );
	}

	$password = wp_generate_password( 24, false );
	$id       = wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => $password,
			'user_email' => $login . LML_E2E_EMAIL_HOST,
			'role'       => $role,
		)
	);

	return array(
		'login'    => $login,
		'password' => $password,
		'id'       => (int) $id,
	);
}

/**
 * Create a published page with the given content.
 *
 * @param string $slug    Slug suffix.
 * @param string $content Post content.
 * @return array{id: int, url: string}
 */
function lml_e2e_page( string $slug, string $content ): array {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'LML E2E ' . $slug,
			'post_name'    => LML_E2E_PREFIX . $slug,
			'post_content' => $content,
		)
	);

	return array(
		'id'  => (int) $id,
		'url' => (string) get_permalink( $id ),
	);
}

/**
 * Remove everything the tests created.
 *
 * @return void
 */
function lml_e2e_cleanup(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array( LML_E2E_ADMIN, LML_E2E_SUBSCRIBER ) as $login ) {
		$user = get_user_by( 'login', $login );
		if ( $user ) {
			wp_delete_user( $user->ID );
		}
	}

	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			's'              => 'LML E2E',
		)
	);
	foreach ( $pages as $page_id ) {
		if ( str_starts_with( (string) get_post_field( 'post_name', $page_id ), LML_E2E_PREFIX ) ) {
			wp_delete_post( $page_id, true );
		}
	}

	$table = $wpdb->prefix . 'lead_magnet_downloads';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE email LIKE %s", '%' . $wpdb->esc_like( LML_E2E_EMAIL_HOST ) ) );

	$storage = new LeadMagnetLockerStorage();
	foreach ( array( $storage->base_dir(), $storage->private_dir() ) as $dir ) {
		foreach ( (array) glob( $dir . '/' . LML_E2E_PREFIX . '*' ) as $file ) {
			if ( is_file( (string) $file ) ) {
				unlink( (string) $file );
			}
		}
	}

	// Rate-limit counters and other transients the tests may have created.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%leadmalo\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_%leadmalo\\_rl\\_%'" );

	$mu_plugin = WP_CONTENT_DIR . '/mu-plugins/' . LML_E2E_MU_PLUGIN;
	if ( is_file( $mu_plugin ) ) {
		unlink( $mu_plugin );
	}
}

/**
 * Plugin options (wp_options rows) the tests may change.
 *
 * @return array<string, array{option_name: string, option_value: string, autoload: string}>
 */
function lml_e2e_plugin_options(): array {
	global $wpdb;

	$rows = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'leadmalo\\_%' AND option_name NOT LIKE 'leadmalo\\_rl\\_%'", ARRAY_A );

	return array_column( (array) $rows, null, 'option_name' );
}

$command = $argv[1] ?? '';
$args    = array_slice( $argv, 2 );
$storage = new LeadMagnetLockerStorage();
$table   = $wpdb->prefix . 'lead_magnet_downloads';
$s_table = $wpdb->prefix . 'lead_magnet_settings';

switch ( $command ) {
	case 'setup':
		lml_e2e_cleanup();

		// Snapshot what teardown must restore (kept from the first setup if a run crashed).
		if ( ! is_file( lml_e2e_state_file() ) ) {
			file_put_contents(
				lml_e2e_state_file(),
				wp_json_encode(
					array(
						'settings' => $wpdb->get_results( "SELECT id, setting_key, setting_value FROM {$s_table} ORDER BY id", ARRAY_A ),
						'options'  => lml_e2e_plugin_options(),
					)
				)
			);
		}

		// Silence the error tracker for requests made by the tests.
		wp_mkdir_p( WP_CONTENT_DIR . '/mu-plugins' );
		file_put_contents(
			WP_CONTENT_DIR . '/mu-plugins/' . LML_E2E_MU_PLUGIN,
			"<?php\n// Lead Magnet Locker E2E tests: removed by the fixture teardown.\nif ( isset( \$_SERVER['HTTP_X_LML_E2E'] ) ) {\n\tadd_filter( 'wp_sentry_dsn', '__return_empty_string', 999 );\n}\n"
		);

		// Known schedule: forms always visible.
		foreach ( array( 'enable_date_trigger' => '0', 'start_date' => '', 'end_date' => '', 'start_time' => '00:00', 'end_time' => '23:59', 'timezone' => 'UTC' ) as $key => $value ) {
			$wpdb->replace( $s_table, array( 'setting_key' => $key, 'setting_value' => $value ) );
		}

		$storage->ensure_directories();
		$files = array(
			LML_E2E_PREFIX . 'a.pdf'    => "%PDF-1.4\n% lml e2e file A\n",
			LML_E2E_PREFIX . 'b.txt'    => "lml e2e file B\n",
			LML_E2E_PREFIX . 'used.txt' => "lml e2e file in use\n",
			LML_E2E_PREFIX . 'free.txt' => "lml e2e file not in use\n",
		);
		foreach ( $files as $name => $content ) {
			file_put_contents( $storage->upload_dir() . '/' . $name, $content );
		}

		$wpdb->insert(
			$table,
			array(
				'email'        => 'used' . LML_E2E_EMAIL_HOST,
				'file_name'    => LML_E2E_PREFIX . 'used.txt',
				'download_key' => wp_generate_password( 32, false ),
			)
		);

		lml_e2e_out(
			array(
				'admin'      => lml_e2e_user( LML_E2E_ADMIN, 'administrator' ),
				'subscriber' => lml_e2e_user( LML_E2E_SUBSCRIBER, 'subscriber' ),
				'pages'      => array(
					'single'  => lml_e2e_page( 'single', '[lead_magnet file="' . LML_E2E_PREFIX . 'a.pdf"]' ),
					'double'  => lml_e2e_page( 'double', '[lead_magnet file="' . LML_E2E_PREFIX . 'a.pdf"] [lead_magnet file="' . LML_E2E_PREFIX . 'b.txt" title="Second box"]' ),
					'custom'  => lml_e2e_page( 'custom', '[lead_magnet file="' . LML_E2E_PREFIX . 'b.txt" title="Title <b>&amp; &quot;quotes&quot;</b>" description="Desc & more" button_text="Send it"]' ),
					'missing' => lml_e2e_page( 'missing', '[lead_magnet file="' . LML_E2E_PREFIX . 'does-not-exist.pdf"]' ),
					'empty'   => lml_e2e_page( 'empty', '[lead_magnet]' ),
					'escape'  => lml_e2e_page( 'escape', '[lead_magnet file="../../../wp-config.php"]' ),
				),
				'tokens'     => array(
					'a' => $storage->token( LML_E2E_PREFIX . 'a.pdf' ),
					'b' => $storage->token( LML_E2E_PREFIX . 'b.txt' ),
				),
				'files'      => $files,
				'private'    => substr( $storage->private_dir(), strlen( ABSPATH ) - 1 ),
				'legacy'     => substr( $storage->base_dir(), strlen( ABSPATH ) - 1 ),
			)
		);
		// No break: lml_e2e_out() exits.

	case 'teardown':
		lml_e2e_cleanup();

		$state = is_file( lml_e2e_state_file() ) ? json_decode( (string) file_get_contents( lml_e2e_state_file() ), true ) : null;
		if ( is_array( $state ) ) {
			$wpdb->query( "DELETE FROM {$s_table}" );
			foreach ( $state['settings'] as $row ) {
				$wpdb->insert( $s_table, $row );
			}

			foreach ( array_keys( lml_e2e_plugin_options() ) as $name ) {
				if ( ! array_key_exists( $name, $state['options'] ) ) {
					delete_option( $name );
				}
			}
			foreach ( $state['options'] as $row ) {
				delete_option( $row['option_name'] );
				$wpdb->insert( $wpdb->options, $row );
			}
			wp_cache_flush();
			unlink( lml_e2e_state_file() );
		}
		lml_e2e_out( array( 'ok' => true ) );

	case 'setting':
		$wpdb->replace( $s_table, array( 'setting_key' => $args[0], 'setting_value' => $args[1] ?? '' ) );
		lml_e2e_out( array( 'ok' => true ) );

	case 'settings':
		lml_e2e_out( $wpdb->get_results( "SELECT setting_key, setting_value FROM {$s_table}", OBJECT_K ) );

	case 'record':
		lml_e2e_out( array( 'record' => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE download_key = %s", $args[0] ), ARRAY_A ) ) );

	case 'records':
		lml_e2e_out( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id", $args[0] ), ARRAY_A ) );

	case 'insert-record':
		$key = wp_generate_password( 32, false );
		$wpdb->insert( $table, array( 'email' => $args[0], 'file_name' => $args[1], 'download_key' => $key ) );
		lml_e2e_out(
			array(
				'id'    => (int) $wpdb->insert_id,
				'key'   => $key,
				'nonce' => wp_create_nonce( 'lead_magnet_download_' . $key ),
			)
		);

	case 'download-nonce':
		// Nonce for a logged-out visitor, as the AJAX handler creates it.
		wp_set_current_user( 0 );
		lml_e2e_out( array( 'nonce' => wp_create_nonce( 'lead_magnet_download_' . $args[0] ) ) );

	case 'put-file':
		$dir = 'legacy' === $args[0] ? $storage->base_dir() : $storage->upload_dir();
		file_put_contents( $dir . '/' . $args[1], $args[2] ?? 'content' );
		lml_e2e_out( array( 'ok' => true ) );

	case 'where':
		$name = $args[0];
		if ( is_file( $storage->private_dir() . '/' . $name ) ) {
			lml_e2e_out( array( 'where' => 'private' ) );
		}
		lml_e2e_out( array( 'where' => is_file( $storage->base_dir() . '/' . $name ) ? 'legacy' : 'none' ) );

	case 'file-exists':
		lml_e2e_out( array( 'exists' => lead_magnet_locker_file_exists( $args[0] ) ) );

	case 'js-page':
		// A page that fills and submits each form on load, for the headless browser test.
		// The second form gets a broken nonce to exercise the error path.
		kses_remove_filters();
		lml_e2e_out(
			lml_e2e_page(
				'js',
				'[lead_magnet file="' . LML_E2E_PREFIX . 'a.pdf"] [lead_magnet file="' . LML_E2E_PREFIX . 'b.txt"]'
				. '<script>window.addEventListener("load",function(){var forms=jQuery(".lead-magnet-email-form");'
				. 'forms.eq(0).find("input[name=email]").val("js' . LML_E2E_EMAIL_HOST . '");forms.eq(0).trigger("submit");'
				. 'setTimeout(function(){lead_magnet_ajax.nonce="deadbeef00";forms.eq(1).find("input[name=email]").val("js2' . LML_E2E_EMAIL_HOST . '");forms.eq(1).trigger("submit");},1500);});</script>'
			)
		);

	case 'option':
		if ( isset( $args[1] ) ) {
			'__delete__' === $args[1] ? delete_option( $args[0] ) : update_option( $args[0], $args[1] );
		}
		lml_e2e_out( array( 'value' => get_option( $args[0], null ) ) );

	default:
		fwrite( STDERR, "Unknown command: {$command}\n" );
		exit( 1 );
}
