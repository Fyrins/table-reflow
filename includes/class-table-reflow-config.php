<?php
/**
 * Plugin defaults and public extension points.
 *
 * @package TableReflow
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds every value a site owner may want to change, and exposes it as a filter.
 *
 * Nothing in this class is stored in the database. The plugin creates no option,
 * so uninstalling it leaves no trace beyond the block attributes already saved
 * in post content.
 *
 * @since 1.0.0
 */
final class Table_Reflow_Config {

	/**
	 * Breakpoint applied when a table does not specify one.
	 *
	 * Matches the small-screen breakpoint used by the block editor itself.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DEFAULT_BREAKPOINT = '600';

	/**
	 * Returns the breakpoint values the plugin accepts, as strings of pixels.
	 *
	 * Every value returned here must have a matching media query in
	 * assets/css/table-reflow.css. A media query condition cannot read a CSS
	 * custom property, so the set of breakpoints is deliberately closed: adding
	 * a value without adding its stylesheet rules would silently do nothing.
	 *
	 * This method never calls a translation function, so it is safe to call it
	 * from the rendering path at any point of the request.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] List of accepted breakpoint values.
	 */
	public static function get_breakpoint_values() {
		$values = array( '480', '600', '782', '960' );

		/**
		 * Filters the breakpoint values the plugin accepts.
		 *
		 * Each value must have matching rules in the plugin stylesheet, or in a
		 * stylesheet the theme enqueues itself. A value with no rules produces a
		 * table that never stacks.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $values List of accepted breakpoint values, in pixels.
		 */
		$values = (array) apply_filters( 'table_reflow_breakpoint_values', $values );

		$values = array_values( array_unique( array_map( 'strval', $values ) ) );

		return empty( $values ) ? array( self::DEFAULT_BREAKPOINT ) : $values;
	}

	/**
	 * Returns the breakpoint choices shown in the block inspector.
	 *
	 * Must not be called before the `init` hook, because it translates strings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> List of `{ value, label }` pairs.
	 */
	public static function get_breakpoint_choices() {
		$labels = array(
			'480' => __( 'Small phones', 'table-reflow' ),
			'600' => __( 'Phones', 'table-reflow' ),
			'782' => __( 'Small tablets', 'table-reflow' ),
			'960' => __( 'Tablets', 'table-reflow' ),
		);

		$choices = array();

		foreach ( self::get_breakpoint_values() as $value ) {
			if ( isset( $labels[ $value ] ) ) {
				/* translators: 1: human readable device name, for example "Phones". 2: viewport width in pixels, for example "600". */
				$label = sprintf( __( '%1$s – under %2$spx', 'table-reflow' ), $labels[ $value ], $value );
			} else {
				/* translators: %s: viewport width in pixels, for example "600". */
				$label = sprintf( __( 'Under %spx', 'table-reflow' ), $value );
			}

			$choices[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return $choices;
	}

	/**
	 * Returns the default breakpoint, validated against the accepted values.
	 *
	 * @since 1.0.0
	 *
	 * @return string Breakpoint value in pixels.
	 */
	public static function get_default_breakpoint() {
		$values = self::get_breakpoint_values();

		/**
		 * Filters the breakpoint used by tables that do not specify one.
		 *
		 * @since 1.0.0
		 *
		 * @param string $default Breakpoint value in pixels.
		 */
		$default = (string) apply_filters( 'table_reflow_default_breakpoint', self::DEFAULT_BREAKPOINT );

		return in_array( $default, $values, true ) ? $default : reset( $values );
	}

	/**
	 * Returns the accessible name given to a table that falls back to scrolling.
	 *
	 * The scrollable container is keyboard focusable, so it needs a name. When
	 * the block has a caption, the caption is used instead of this generic name.
	 *
	 * Must not be called before the `init` hook, because it translates a string.
	 *
	 * @since 1.0.0
	 *
	 * @return string Accessible name for the scrollable container.
	 */
	public static function get_scrollable_label() {
		$label = __( 'Table, scrollable sideways', 'table-reflow' );

		/**
		 * Filters the accessible name of a horizontally scrollable table.
		 *
		 * Applies only to tables whose structure could not be stacked, and only
		 * when the block has no caption to name it.
		 *
		 * @since 1.0.0
		 *
		 * @param string $label Accessible name for the scrollable container.
		 */
		return (string) apply_filters( 'table_reflow_scrollable_label', $label );
	}
}
