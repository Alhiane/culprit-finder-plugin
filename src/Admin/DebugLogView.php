<?php
/**
 * Debug log tab.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Debug\LogLocation;
use CulpritFinder\Debug\LogReader;
use CulpritFinder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The end of the PHP error log, read-only: no download, no clearing, no wp-config.php edits (ADR-0031).
 */
final class DebugLogView {

	const RANGE_ALL     = 'all';
	const RANGE_SESSION = 'session';
	const MAX_LINE      = 2000;

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Render the tab.
	 */
	public function render() {
		$status = LogLocation::status();
		echo '<div class="cf-log">';
		$this->render_settings( $status );

		switch ( $status['state'] ) {
			case LogLocation::STATE_OFF:
				$this->render_how_to_enable();
				break;
			case LogLocation::STATE_SYSLOG:
				$this->render_empty( __( 'The log goes to the system log', 'culprit-finder' ), __( 'PHP on this server writes errors to the system log (syslog), which WordPress can’t read. Ask your host where to find it.', 'culprit-finder' ) );
				break;
			case LogLocation::STATE_UNREADABLE:
				$this->render_empty( __( 'The log can’t be read here', 'culprit-finder' ), __( 'The log file exists, but PHP isn’t allowed to read it from WordPress. Your host’s control panel or file manager can show it.', 'culprit-finder' ) );
				break;
			case LogLocation::STATE_EMPTY:
				$this->render_empty( __( 'No errors logged yet', 'culprit-finder' ), __( 'Logging is on, and nothing has been written to the log so far.', 'culprit-finder' ) );
				break;
			default:
				$this->render_entries( $status );
		}
		echo '</div>';
	}

	/**
	 * The debug constants as they are now.
	 *
	 * @param array $status From LogLocation::status().
	 */
	private function render_settings( array $status ) {
		$on  = __( 'On', 'culprit-finder' );
		$off = __( 'Off', 'culprit-finder' );
		echo '<section class="cf-card cf-log__settings">';
		echo '<dl>';
		echo '<dt>WP_DEBUG</dt><dd>' . esc_html( $status['debug'] ? $on : $off ) . '</dd>';
		echo '<dt>WP_DEBUG_LOG</dt><dd>' . esc_html( $status['log'] ? $on : $off ) . '</dd>';
		echo '<dt>WP_DEBUG_DISPLAY</dt><dd>' . esc_html( $status['display'] ? $on : $off ) . '</dd>';
		if ( '' !== $status['path'] ) {
			echo '<dt>' . esc_html__( 'Log file', 'culprit-finder' ) . '</dt><dd><code>' . esc_html( LogReader::scrub( $status['path'] ) ) . '</code></dd>';
		}
		echo '</dl>';
		if ( $status['display'] ) {
			echo '<p class="cf-log__warning">' . esc_html__( 'Errors are shown on your pages, so visitors can see them too. On a live site, set WP_DEBUG_DISPLAY to false and read them here instead.', 'culprit-finder' ) . '</p>';
		}
		echo '<p class="cf-help">' . esc_html__( 'Read-only. Culprit Finder never changes these settings or the log file.', 'culprit-finder' ) . '</p>';
		echo '</section>';
	}

	/**
	 * Logging is off and no old log exists: how to turn it on.
	 */
	private function render_how_to_enable() {
		echo '<div class="cf-empty">';
		echo '<h2>' . esc_html__( 'Debug logging is off', 'culprit-finder' ) . '</h2>';
		echo '<p>' . esc_html__( 'To see PHP errors here, add these lines to wp-config.php, above the line “That’s all, stop editing!”. Errors are then written to a log instead of your pages, so visitors don’t see them.', 'culprit-finder' ) . '</p>';
		echo '<pre class="cf-log__snippet">' . esc_html( "define( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );" ) . '</pre>';
		echo '<p class="cf-help">' . esc_html__( 'Set WP_DEBUG back to false when you’re done.', 'culprit-finder' ) . '</p>';
		echo '</div>';
	}

	/**
	 * A state with nothing to list.
	 *
	 * @param string $title Title.
	 * @param string $text  Text.
	 */
	private function render_empty( $title, $text ) {
		echo '<div class="cf-empty"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * The newest entries, optionally only those logged since this browser's session started.
	 *
	 * @param array $status From LogLocation::status().
	 */
	private function render_entries( array $status ) {
		$tail = LogReader::tail( $status['path'] );
		if ( null === $tail ) {
			$this->render_empty( __( 'The log can’t be read here', 'culprit-finder' ), __( 'The log file exists, but PHP isn’t allowed to read it from WordPress. Your host’s control panel or file manager can show it.', 'culprit-finder' ) );
			return;
		}
		$entries = LogReader::entries( $tail['lines'] );
		$session = $this->plugin->owned_session();
		$range   = $this->range( null !== $session );
		if ( self::RANGE_SESSION === $range ) {
			$entries = LogReader::since( $entries, (int) $session['created_at'] );
		}
		$total   = count( $entries );
		$entries = array_reverse( array_slice( $entries, -LogReader::MAX_ENTRIES ) );

		if ( LogLocation::STATE_OLD === $status['state'] ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html(
				sprintf(
					/* translators: %s: date and time the file was last written */
					__( 'Logging is off. This is an older log, last written %s.', 'culprit-finder' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['modified'] )
				)
			) . '</p></div>';
		}

		echo '<div class="cf-results-head">';
		echo '<p>' . esc_html( self::summary( LogReader::counts( $entries ), $total, count( $entries ) ) ) . '</p>';
		if ( null !== $session ) {
			$this->render_range_links( $range );
		}
		echo '</div>';
		echo '<p class="cf-help">' . esc_html__( 'The log holds errors from every request to your site, from visitors as well as from you. Newest first.', 'culprit-finder' ) . ( $tail['partial'] ? ' ' . esc_html(
			sprintf(
				/* translators: %s: file size, e.g. "12 MB" */
				__( 'Only the end of the %s file is shown.', 'culprit-finder' ),
				size_format( $tail['size'] )
			)
		) : '' ) . '</p>';

		if ( ! $entries ) {
			$this->render_empty(
				self::RANGE_SESSION === $range ? __( 'Nothing logged since troubleshooting started', 'culprit-finder' ) : __( 'No errors logged yet', 'culprit-finder' ),
				__( 'If the problem shows a blank page or “critical error”, reload it once, then come back here.', 'culprit-finder' )
			);
			return;
		}

		echo '<ol class="cf-log__entries">';
		foreach ( $entries as $entry ) {
			echo '<li class="cf-log__entry cf-log__entry--' . esc_attr( $entry['level'] ) . '">';
			echo '<span class="cf-badge cf-log__level">' . esc_html( self::level_label( $entry['level'] ) ) . '</span>';
			echo '<pre>' . esc_html( implode( "\n", array_map( array( __CLASS__, 'clean' ), $entry['lines'] ) ) ) . '</pre>';
			echo '</li>';
		}
		echo '</ol>';
	}

	/**
	 * "Since troubleshooting started" / "All recent entries" links.
	 *
	 * @param string $range Current range.
	 */
	private function render_range_links( $range ) {
		$links = array(
			self::RANGE_SESSION => __( 'Since troubleshooting started', 'culprit-finder' ),
			self::RANGE_ALL     => __( 'All recent entries', 'culprit-finder' ),
		);
		echo '<p class="cf-log__range">';
		foreach ( $links as $id => $label ) {
			if ( $id === $range ) {
				echo '<strong>' . esc_html( $label ) . '</strong> ';
			} else {
				echo '<a href="' . esc_url(
					Links::tools(
						array(
							'tab'          => Page::TAB_DEBUG_LOG,
							'range'        => $id,
							'culprit_safe' => 1,
						)
					)
				) . '">' . esc_html( $label ) . '</a> ';
			}
		}
		echo '</p>';
	}

	/**
	 * The range from the request: entries since the session started by default while this browser
	 * troubleshoots, otherwise all.
	 *
	 * @param bool $running Whether this browser has a session.
	 * @return string
	 */
	private function range( $running ) {
		if ( ! $running ) {
			return self::RANGE_ALL;
		}
		$range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		return self::RANGE_ALL === $range ? self::RANGE_ALL : self::RANGE_SESSION;
	}

	/**
	 * One line, safe to escape: invalid UTF-8 removed, then cut on a character boundary.
	 *
	 * @param string $line Line.
	 * @return string
	 */
	public static function clean( $line ) {
		$line = LogReader::scrub( $line );
		if ( mb_strlen( $line ) > self::MAX_LINE ) {
			$line = mb_substr( $line, 0, self::MAX_LINE ) . ' …';
		}
		return $line;
	}

	/**
	 * Plain words for a level.
	 *
	 * @param string $level LogReader::LEVEL_*.
	 * @return string
	 */
	private static function level_label( $level ) {
		switch ( $level ) {
			case LogReader::LEVEL_FATAL:
				return __( 'Fatal error', 'culprit-finder' );
			case LogReader::LEVEL_WARN:
				return __( 'Warning', 'culprit-finder' );
			case LogReader::LEVEL_NOTICE:
				return __( 'Notice', 'culprit-finder' );
			case LogReader::LEVEL_DB:
				return __( 'Database', 'culprit-finder' );
			default:
				return __( 'Other', 'culprit-finder' );
		}
	}

	/**
	 * "3 fatal errors, 12 warnings and 40 notices in the 100 newest of 250 entries."
	 *
	 * @param array<string, int> $counts Counts of the shown entries.
	 * @param int                $total  Entries in range.
	 * @param int                $shown  Entries shown.
	 * @return string
	 */
	private static function summary( array $counts, $total, $shown ) {
		$parts = array(
			/* translators: %s: number of fatal errors */
			sprintf( _n( '%s fatal error', '%s fatal errors', $counts[ LogReader::LEVEL_FATAL ], 'culprit-finder' ), number_format_i18n( $counts[ LogReader::LEVEL_FATAL ] ) ),
			/* translators: %s: number of warnings */
			sprintf( _n( '%s warning', '%s warnings', $counts[ LogReader::LEVEL_WARN ], 'culprit-finder' ), number_format_i18n( $counts[ LogReader::LEVEL_WARN ] ) ),
			/* translators: %s: number of notices */
			sprintf( _n( '%s notice', '%s notices', $counts[ LogReader::LEVEL_NOTICE ], 'culprit-finder' ), number_format_i18n( $counts[ LogReader::LEVEL_NOTICE ] ) ),
		);
		$text  = implode( ', ', $parts ) . '.';
		if ( $total > $shown ) {
			$text .= ' ' . sprintf(
				/* translators: 1: entries shown, 2: entries in total */
				__( 'Showing the %1$s newest of %2$s entries.', 'culprit-finder' ),
				number_format_i18n( $shown ),
				number_format_i18n( $total )
			);
		}
		return $text;
	}
}
