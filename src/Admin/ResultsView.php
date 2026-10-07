<?php
/**
 * Results tab.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Hooks;
use CulpritFinder\Plugin;
use CulpritFinder\Report\Builder;
use CulpritFinder\Report\Report;
use CulpritFinder\Session\Store;
use CulpritFinder\Session\Token;
use CulpritFinder\Session\View;

defined( 'ABSPATH' ) || exit;

/**
 * History of the last results and one result as a readable report (ADR-0019).
 */
final class ResultsView {

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
	 * Render the list, or one result when `result` is in the request.
	 *
	 * @param array|null $session Current session.
	 * @param bool       $owned   Whether this browser owns it.
	 */
	public function render( $session, $owned ) {
		$id = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		if ( '' !== $id ) {
			$record = $this->plugin->store()->result( $id );
			if ( null !== $record ) {
				$this->render_detail( $record, $session, $owned );
				return;
			}
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'That result no longer exists.', 'culprit-finder' ) . '</p></div>';
		}
		$this->render_list();
	}

	/**
	 * The history table.
	 */
	private function render_list() {
		$results = $this->plugin->store()->results();
		if ( ! $results ) {
			echo '<div class="cf-empty"><h2>' . esc_html__( 'No results yet', 'culprit-finder' ) . '</h2>';
			echo '<p>' . esc_html__( 'When a search finishes, its result and report appear here.', 'culprit-finder' ) . '</p>';
			echo '<p><a class="button cf-button cf-button--primary" href="' . esc_url( Links::tools() ) . '">' . esc_html__( 'Start troubleshooting', 'culprit-finder' ) . '</a></p></div>';
			return;
		}

		echo '<div class="cf-results-head"><p class="cf-muted">' . esc_html(
			sprintf(
				/* translators: %d: maximum number of results kept */
				__( 'Your last %d results are kept on this site only. Nothing is sent anywhere.', 'culprit-finder' ),
				Store::MAX_RESULTS
			)
		) . '</p>';
		echo '<a class="button cf-button--danger" href="' . esc_url( Links::action( 'clear_results' ) ) . '">' . esc_html__( 'Clear all', 'culprit-finder' ) . '</a></div>';

		echo '<table class="widefat striped cf-results"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Date', 'culprit-finder' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Result', 'culprit-finder' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Plugins', 'culprit-finder' ) . '</th>';
		echo '<th scope="col" class="cf-num">' . esc_html__( 'Answers', 'culprit-finder' ) . '</th>';
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'culprit-finder' ) . '</span></th>';
		echo '</tr></thead><tbody>';
		foreach ( $results as $record ) {
			$type = $record['result']['type'];
			echo '<tr>';
			echo '<td class="cf-nowrap">' . esc_html( self::date( $record ) ) . '</td>';
			echo '<td><span class="cf-badge cf-badge--' . esc_attr( Page::is_found( $type ) ? 'found' : strtolower( $type ) ) . '">' . esc_html( Page::verdict_badge( $type ) ) . '</span></td>';
			echo '<td><a class="cf-strong" href="' . esc_url( Links::result( $record['id'] ) ) . '">' . esc_html( self::culprit_summary( $record ) ) . '</a></td>';
			echo '<td class="cf-num">' . esc_html( number_format_i18n( (int) $record['answers'] ) ) . '</td>';
			echo '<td class="cf-row-actions"><a href="' . esc_url(
				Links::action(
					'download',
					array(
						'result' => $record['id'],
						'format' => 'md',
					)
				)
			) . '">' . esc_html__( 'Download', 'culprit-finder' ) . '</a> · ';
			echo '<a class="cf-delete" href="' . esc_url( Links::action( 'delete_result', array( 'result' => $record['id'] ) ) ) . '">' . esc_html__( 'Delete', 'culprit-finder' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * One result as a readable report.
	 *
	 * @param array      $record  Result record.
	 * @param array|null $session Current session.
	 * @param bool       $owned   Whether this browser owns it.
	 */
	private function render_detail( array $record, $session, $owned ) {
		$result  = $record['result'];
		$plugins = isset( $record['plugins'] ) ? $record['plugins'] : array();
		$env     = $record['env'];
		$current = $owned && $this->is_current_session_result( $record, $session );
		$report  = Report::text( $record );

		echo '<p><a class="cf-back" href="' . esc_url( Links::tools( array( 'tab' => Page::TAB_RESULTS ) ) ) . '">' . esc_html__( '← All results', 'culprit-finder' ) . '</a></p>';

		if ( $current ) {
			$this->render_session_bar( $session );
		}

		echo '<section class="cf-card cf-question culprit-finder-result">';
		echo '<p class="cf-eyebrow' . ( Page::is_found( $result['type'] ) ? ' cf-eyebrow--success' : '' ) . '">' . esc_html(
			sprintf(
				/* translators: 1: "Found it" or "Result", 2: number of answers, 3: date */
				_n( '%1$s · %2$d answer · %3$s', '%1$s · %2$d answers · %3$s', (int) $record['answers'], 'culprit-finder' ),
				Page::is_found( $result['type'] ) ? __( 'Found it', 'culprit-finder' ) : __( 'Result', 'culprit-finder' ),
				(int) $record['answers'],
				self::date( $record )
			)
		) . '</p>';
		echo '<h2>' . esc_html( Page::verdict_heading( $result['type'] ) ) . '</h2>';
		echo '<p>' . esc_html( Page::verdict_text( $result['type'] ) ) . '</p>';

		foreach ( $result['culprits'] as $culprit ) {
			$this->culprit_card( $culprit, $plugins, Page::is_found( $result['type'] ) );
		}

		echo '<div class="cf-env"><h3>' . esc_html__( 'Environment', 'culprit-finder' ) . '</h3><dl>';
		$addons = Builder::addons( $record );
		$kept   = array();
		foreach ( $result['kept_on'] as $basename ) {
			if ( ! in_array( $basename, $addons, true ) ) {
				$kept[] = Builder::label( $basename, $plugins );
			}
		}
		$rows = array(
			__( 'WordPress', 'culprit-finder' )      => $env['wp'],
			__( 'PHP', 'culprit-finder' )            => $env['php'],
			__( 'Theme', 'culprit-finder' )          => trim( $env['theme'] . ' ' . $env['theme_version'] ),
			__( 'Plugins tested', 'culprit-finder' ) => number_format_i18n( (int) $record['tested'] ),
			__( 'Kept on', 'culprit-finder' )        => $kept ? implode( ', ', $kept ) : __( 'None', 'culprit-finder' ),
		);
		if ( $addons ) {
			$labels = array();
			foreach ( $addons as $basename ) {
				$labels[] = Builder::label( $basename, $plugins );
			}
			$rows[ __( 'Add-ons kept on', 'culprit-finder' ) ] = implode( ', ', $labels );
		}
		$rows[ __( 'Not tested', 'culprit-finder' ) ] = __( 'Must-use plugins, drop-ins, theme', 'culprit-finder' );
		$rows[ __( 'Multisite', 'culprit-finder' ) ]  = ! empty( $env['multisite'] ) ? __( 'Yes', 'culprit-finder' ) : __( 'No', 'culprit-finder' );
		foreach ( $rows as $label => $value ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		echo '</dl></div>';

		echo '<div class="cf-secondary cf-report-actions">';
		echo '<button type="button" class="button cf-button cf-button--primary culprit-finder-copy" data-target="culprit-finder-report" data-done="' . esc_attr__( 'Copied!', 'culprit-finder' ) . '" hidden>' . esc_html__( 'Copy for forum', 'culprit-finder' ) . '</button>';
		echo '<a class="button" href="' . esc_url(
			Links::action(
				'download',
				array(
					'result' => $record['id'],
					'format' => 'md',
				)
			)
		) . '">' . esc_html__( 'Download .md', 'culprit-finder' ) . '</a>';
		echo '<a class="button" href="' . esc_url(
			Links::action(
				'download',
				array(
					'result' => $record['id'],
					'format' => 'txt',
				)
			)
		) . '">' . esc_html__( 'Download .txt', 'culprit-finder' ) . '</a>';
		Hooks::action( 'culprit_finder_result_actions', Report::public_record( $record ), $current ? View::of( $session ) : null );
		echo '<a class="button cf-button--danger cf-push-right" href="' . esc_url( Links::action( 'delete_result', array( 'result' => $record['id'] ) ) ) . '">' . esc_html__( 'Delete', 'culprit-finder' ) . '</a>';
		echo '</div>';
		echo '<p class="cf-help">' . esc_html__( 'Safe to share publicly: no site address, user names or emails.', 'culprit-finder' ) . '</p>';
		echo '<details class="cf-plain"><summary>' . esc_html__( 'Show plain text', 'culprit-finder' ) . '</summary>';
		echo '<pre id="culprit-finder-report" class="cf-report__text">' . esc_html( $report ) . '</pre></details>';
		echo '</section>';
	}

	/**
	 * Controls for the session that produced this result: Done, Undo, Run again.
	 *
	 * @param array $session Session.
	 */
	private function render_session_bar( array $session ) {
		$key = Token::generate();
		echo '<div class="cf-card cf-session-bar">';
		echo '<p><strong>' . esc_html__( 'This is the result of your current session.', 'culprit-finder' ) . '</strong> ' . esc_html__( 'Your site is back to normal for you while you read it.', 'culprit-finder' ) . '</p>';
		echo '<div class="cf-secondary">';
		echo '<a class="button cf-button cf-button--primary" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'Done, exit', 'culprit-finder' ) . '</a>';
		echo '<a class="button" href="' . esc_url( Links::action( 'undo', array(), Links::control_panel() ) ) . '">' . esc_html__( 'Undo last answer', 'culprit-finder' ) . '</a>';
		echo '<form class="cf-inline-form" method="post" action="' . esc_url( Links::form_action() ) . '">';
		wp_nonce_field( 'culprit_finder_start' );
		echo '<input type="hidden" name="action" value="culprit_finder_start">';
		echo '<input type="hidden" name="culprit_finder_saved" value="1">';
		echo '<input type="hidden" name="culprit_finder_recovery" value="' . esc_attr( $key ) . '">';
		if ( ! empty( $session['problem_url'] ) ) {
			echo '<input type="hidden" name="culprit_finder_problem_url" value="' . esc_attr( $session['problem_url'] ) . '">';
		}
		foreach ( $session['pinned'] as $basename ) {
			echo '<input type="hidden" name="culprit_finder_pin[]" value="' . esc_attr( $basename ) . '">';
		}
		echo '<button type="submit" class="button">' . esc_html__( 'Run again', 'culprit-finder' ) . '</button></form>';
		echo '</div>';
		echo '<p class="cf-help">' . esc_html__( 'Run again starts a new session with a new emergency exit link. Bookmark it first:', 'culprit-finder' ) . ' <code class="culprit-finder-exit-url">' . esc_html( Links::recovery( $key ) ) . '</code></p>';
		echo '</div>';
	}

	/**
	 * Whether a record is the result of the running (done) session.
	 *
	 * @param array      $record  Result record.
	 * @param array|null $session Session.
	 * @return bool
	 */
	private function is_current_session_result( array $record, $session ) {
		if ( null === $session ) {
			return false;
		}
		$last = $this->plugin->store()->last_result();
		return is_array( $last ) && isset( $last['id'] ) && $last['id'] === $record['id'] && $this->plugin->manager()->step( $session )->is_done();
	}

	/**
	 * Card for one culprit with next steps.
	 *
	 * @param string $basename Basename.
	 * @param array  $plugins  Plugin facts from the record.
	 * @param bool   $found    Whether the verdict is confident.
	 */
	private function culprit_card( $basename, array $plugins, $found ) {
		$info = isset( $plugins[ $basename ] ) ? $plugins[ $basename ] : array(
			'name'     => $basename,
			'version'  => '',
			'author'   => '',
			'uri'      => '',
			'requires' => array(),
		);
		echo '<div class="cf-culprit"><div class="cf-culprit__who">';
		echo '<h3>' . esc_html( $info['name'] ) . ' <span class="cf-muted">' . esc_html( $info['version'] ) . '</span></h3>';
		$meta = array();
		if ( '' !== $info['author'] ) {
			/* translators: %s: plugin author */
			$meta[] = esc_html( sprintf( __( 'By %s', 'culprit-finder' ), $info['author'] ) );
		}
		if ( '' !== $info['uri'] ) {
			$meta[] = '<a href="' . esc_url( $info['uri'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Plugin website', 'culprit-finder' ) . '</a>';
		}
		if ( $meta ) {
			echo '<p class="cf-muted">' . wp_kses_post( implode( ' · ', $meta ) ) . '</p>';
		}
		if ( ! empty( $info['requires'] ) ) {
			$labels = array();
			foreach ( $info['requires'] as $dep ) {
				$labels[] = Builder::label( $dep, $plugins );
			}
			/* translators: %s: list of plugin names */
			echo '<p class="cf-muted">' . esc_html( sprintf( __( 'Tested together with its required plugins: %s', 'culprit-finder' ), implode( ', ', $labels ) ) ) . '</p>';
		}
		echo '</div>';
		if ( $found ) {
			echo '<div class="cf-culprit__next"><strong>' . esc_html__( 'What you can do next', 'culprit-finder' ) . '</strong><ul>';
			/* translators: %s: Plugins screen URL */
			echo '<li>' . wp_kses_post( sprintf( __( 'Check for an update on the <a href="%s">Plugins screen</a> (after you exit).', 'culprit-finder' ), esc_url( admin_url( 'plugins.php' ) ) ) ) . '</li>';
			echo '<li>' . esc_html__( 'Send this report to the plugin’s support forum.', 'culprit-finder' ) . '</li>';
			echo '<li>' . esc_html__( 'If you can live without it, deactivate it for everyone from the Plugins screen.', 'culprit-finder' ) . '</li>';
			echo '</ul></div>';
		}
		echo '</div>';
	}

	/**
	 * Local date and time a result finished.
	 *
	 * @param array $record Result record.
	 * @return string
	 */
	public static function date( array $record ) {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $record['finished_at'] );
	}

	/**
	 * Culprit names for the table.
	 *
	 * @param array $record Result record.
	 * @return string
	 */
	public static function culprit_summary( array $record ) {
		$labels = array();
		foreach ( $record['result']['culprits'] as $culprit ) {
			$labels[] = Builder::label( $culprit, isset( $record['plugins'] ) ? $record['plugins'] : array() );
		}
		return $labels ? implode( ' + ', $labels ) : __( 'None of the plugins tested', 'culprit-finder' );
	}
}
