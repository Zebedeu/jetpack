<?php

class Jetpack_Import_Stats {
	static $known_importers = array(
		'Blogger_Importer' => 'blogger',
		'LJ_API_Import' => 'livejournal',
		'MT_Import' => 'mt',
		'RSS_Import' => 'rss',
		'WP_Import' => 'wordpress',
	);

	static $action_event_name_map = array(
		'import_start' => 'jetpack_import_start',
		'import_done'  => 'jetpack_import_done',
		'import_end'   => 'jetpack_import_done',
	);

	public static function init() {
		// Only handle import actions for sites that have agreed to TOS 
		if ( Jetpack::jetpack_tos_agreed() ) {
			add_action( 'import_start', array( 'Jetpack_Import_Stats', 'log_import_progress' ) );
			add_action( 'import_done',  array( 'Jetpack_Import_Stats', 'log_import_progress' ) );
			add_action( 'import_end',   array( 'Jetpack_Import_Stats', 'log_import_progress' ) );
		}
	}

	private static function get_calling_class() {
		$action = current_filter();
		$backtrace = wp_debug_backtrace_summary( null, 0, false );

		$do_action_pos = -1;
		for ( $i = 0; $i < count( $backtrace ); $i++ ) {
			// Find the location in the stack of the calling action
			if ( preg_match( "/^do_action\\(\'([^\']+)/", $backtrace[ $i ], $matches ) ) {
				if ( $matches[1] === $action ) {
					$do_action_pos = $i;
					break;
				}
			}
		}

		// if the action wasn't called, the calling class is unknown
		if ( -1 === $do_action_pos ) {
			return 'unknown';
		}

		// The calling class is next in the stack, after $do_action_pos
		list( $className ) = explode( '->', $backtrace[ $do_action_pos + 1 ] );
		return $className;
	}

	public static function log_import_progress( $importer ) {
		// prefer self-reported importer-names
		if ( ! $importer ) {
			// fall back to inferring by calling class name
			$importer = self::get_calling_class();
		}
		
		// Give known importers a "friendly" name
		if ( isset( self::$known_importers[ $importer ] ) ) {
			$importer = self::$known_importers[ $importer ];
		}
		$action = current_filter();
		// map action to event name
		$event_name = self::$action_event_name_map[ $action ];
		
		$current_user = wp_get_current_user();

		// Record event to Tracks
		jetpack_tracks_record_event( $current_user, $event_name, array(
			'importer' => $importer,
		) );
	}
}

add_action( 'init', array( 'Jetpack_Import_Stats', 'init' ) );
