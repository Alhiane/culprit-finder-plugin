<?php
/**
 * Dependency graph helpers: closure and deterministic topological order.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin dependency graph restricted to the snapshot (ADR-0005). Pure PHP.
 */
final class Graph {

	/**
	 * Basename => basename[] of required plugins, only edges inside the snapshot.
	 *
	 * @var array<string, string[]>
	 */
	private $deps = array();

	/**
	 * Snapshot members as a lookup set.
	 *
	 * @var array<string, true>
	 */
	private $members = array();

	/**
	 * Constructor.
	 *
	 * @param string[]                $snapshot Plugin basenames.
	 * @param array<string, string[]> $deps     Dependencies; edges leaving the snapshot are ignored.
	 */
	public function __construct( array $snapshot, array $deps ) {
		foreach ( $snapshot as $plugin ) {
			$this->members[ $plugin ] = true;
		}
		foreach ( $deps as $plugin => $required ) {
			$plugin = (string) $plugin;
			if ( ! isset( $this->members[ $plugin ] ) || ! is_array( $required ) ) {
				continue;
			}
			$edges = array();
			foreach ( $required as $dep ) {
				if ( is_string( $dep ) && isset( $this->members[ $dep ] ) && $dep !== $plugin ) {
					$edges[ $dep ] = true;
				}
			}
			$edges = array_keys( $edges );
			sort( $edges, SORT_STRING );
			$this->deps[ $plugin ] = $edges;
		}
	}

	/**
	 * Direct dependencies of a plugin (inside the snapshot).
	 *
	 * @param string $plugin Basename.
	 * @return string[]
	 */
	public function deps_of( $plugin ) {
		return isset( $this->deps[ $plugin ] ) ? $this->deps[ $plugin ] : array();
	}

	/**
	 * The set plus all transitive dependencies, intersected with the snapshot, sorted.
	 *
	 * @param string[] $set Basenames.
	 * @return string[]
	 */
	public function closure( array $set ) {
		$seen  = array();
		$queue = array();
		foreach ( $set as $plugin ) {
			if ( isset( $this->members[ $plugin ] ) && ! isset( $seen[ $plugin ] ) ) {
				$seen[ $plugin ] = true;
				$queue[]         = $plugin;
			}
		}
		while ( $queue ) {
			$plugin = array_pop( $queue );
			foreach ( $this->deps_of( $plugin ) as $dep ) {
				if ( ! isset( $seen[ $dep ] ) ) {
					$seen[ $dep ] = true;
					$queue[]      = $dep;
				}
			}
		}
		$result = array_keys( $seen );
		sort( $result, SORT_STRING );
		return $result;
	}

	/**
	 * Topological order of a subset: dependencies first, ties by strcmp.
	 *
	 * Cycle members are kept adjacent in strcmp order (ADR-0005): strongly connected
	 * components are ordered as single nodes keyed by their smallest member.
	 *
	 * @param string[] $subset Basenames to order (edges to plugins outside the subset are ignored).
	 * @return string[]
	 */
	public function topo_order( array $subset ) {
		$in_subset = array();
		foreach ( $subset as $plugin ) {
			$in_subset[ $plugin ] = true;
		}
		$nodes = array_keys( $in_subset );
		sort( $nodes, SORT_STRING );

		$components = $this->components( $nodes, $in_subset );
		$comp_of    = array();
		foreach ( $components as $index => $component ) {
			foreach ( $component as $plugin ) {
				$comp_of[ $plugin ] = $index;
			}
		}

		// Component DAG: edge dep-component -> dependent-component.
		$pending    = array_fill( 0, count( $components ), 0 );
		$dependents = array_fill( 0, count( $components ), array() );
		foreach ( $nodes as $plugin ) {
			foreach ( $this->deps_of( $plugin ) as $dep ) {
				if ( ! isset( $in_subset[ $dep ] ) ) {
					continue;
				}
				$from = $comp_of[ $dep ];
				$to   = $comp_of[ $plugin ];
				if ( $from !== $to && ! isset( $dependents[ $from ][ $to ] ) ) {
					$dependents[ $from ][ $to ] = true;
					++$pending[ $to ];
				}
			}
		}

		// Kahn's algorithm, always taking the ready component with the smallest key.
		$ready = array();
		foreach ( $components as $index => $component ) {
			if ( 0 === $pending[ $index ] ) {
				$ready[ $component[0] ] = $index;
			}
		}
		$order = array();
		while ( $ready ) {
			ksort( $ready, SORT_STRING );
			$index = reset( $ready );
			unset( $ready[ key( $ready ) ] );
			foreach ( $components[ $index ] as $plugin ) {
				$order[] = $plugin;
			}
			foreach ( array_keys( $dependents[ $index ] ) as $next ) {
				--$pending[ $next ];
				if ( 0 === $pending[ $next ] ) {
					$ready[ $components[ $next ][0] ] = $next;
				}
			}
		}
		return $order;
	}

	/**
	 * Strongly connected components (Tarjan), each sorted by strcmp.
	 *
	 * @param string[]            $nodes     Sorted nodes.
	 * @param array<string, true> $in_subset Lookup of nodes.
	 * @return string[][]
	 */
	private function components( array $nodes, array $in_subset ) {
		$index    = 0;
		$indices  = array();
		$lowlink  = array();
		$on_stack = array();
		$stack    = array();
		$result   = array();

		$visit = function ( $node ) use ( &$visit, &$index, &$indices, &$lowlink, &$on_stack, &$stack, &$result, $in_subset ) {
			$indices[ $node ] = $index;
			$lowlink[ $node ] = $index;
			++$index;
			$stack[]           = $node;
			$on_stack[ $node ] = true;

			foreach ( $this->deps_of( $node ) as $dep ) {
				if ( ! isset( $in_subset[ $dep ] ) ) {
					continue;
				}
				if ( ! isset( $indices[ $dep ] ) ) {
					$visit( $dep );
					$lowlink[ $node ] = min( $lowlink[ $node ], $lowlink[ $dep ] );
				} elseif ( isset( $on_stack[ $dep ] ) ) {
					$lowlink[ $node ] = min( $lowlink[ $node ], $indices[ $dep ] );
				}
			}

			if ( $lowlink[ $node ] === $indices[ $node ] ) {
				$component = array();
				do {
					$member = array_pop( $stack );
					unset( $on_stack[ $member ] );
					$component[] = $member;
				} while ( $member !== $node );
				sort( $component, SORT_STRING );
				$result[] = $component;
			}
		};

		foreach ( $nodes as $node ) {
			if ( ! isset( $indices[ $node ] ) ) {
				$visit( $node );
			}
		}
		return $result;
	}
}
