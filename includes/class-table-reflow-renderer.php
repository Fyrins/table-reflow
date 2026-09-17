<?php
/**
 * Server-side transformation of the table block.
 *
 * @package TableReflow
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites the markup of opted-in table blocks at render time.
 *
 * The transformation is a pure attribute rewrite performed with the HTML API:
 * classes, ARIA roles and `data-label` attributes are added, and nothing else.
 * No node is created, moved or removed, so the reading order of the document
 * always matches the visual order (WCAG 2.2, 1.3.2 Meaningful Sequence).
 *
 * `WP_HTML_Tag_Processor` can only read and write attributes; it cannot insert
 * a node. The column header is therefore restored visually through
 * `data-label` and a `content: attr(data-label)` pseudo-element. Generated
 * content is not announced reliably by every screen reader, so the header
 * information is *also* kept in the accessibility tree through the visually
 * hidden `thead` and the explicit roles. The pseudo-element is a visual
 * convenience and never the only source of the information.
 *
 * @since 1.0.0
 */
final class Table_Reflow_Renderer {

	/**
	 * Class marking a table that stacks on small screens.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const STACKED_CLASS = 'table-reflow';

	/**
	 * Class prefix carrying the chosen breakpoint.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const BREAKPOINT_CLASS_PREFIX = 'table-reflow--bp-';

	/**
	 * Class marking a container that keeps horizontal scrolling.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const SCROLL_CLASS = 'table-reflow-scroll';

	/**
	 * Class marking the visually hidden header section.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const HEAD_CLASS = 'table-reflow__head';

	/**
	 * Handle of the front-end stylesheet to enqueue when a table is transformed.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $style_handle;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $style_handle Handle of the registered front-end stylesheet.
	 */
	public function __construct( $style_handle ) {
		$this->style_handle = (string) $style_handle;
	}

	/**
	 * Filters the rendered markup of a block.
	 *
	 * Hooked on `render_block`. Returns the content untouched for every block
	 * that is not an opted-in table, so the cost on other blocks is one string
	 * comparison.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content Rendered block markup.
	 * @param array  $block         Parsed block, including its attributes.
	 * @return string Rendered block markup, transformed when applicable.
	 */
	public function render_block( $block_content, $block ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		if ( ! isset( $block['blockName'] ) || 'core/table' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->is_enabled( $block ) ) {
			return $block_content;
		}

		if ( ! class_exists( 'WP_HTML_Processor' ) ) {
			return $block_content;
		}

		$structure = $this->analyze( $block_content );

		if ( null === $structure ) {
			return $block_content;
		}

		if ( $structure['stackable'] ) {
			$transformed = $this->apply_stacked_markup(
				$block_content,
				$structure['labels'],
				$this->get_breakpoint( $block )
			);
		} else {
			$transformed = $this->apply_scroll_fallback( $block_content, $structure['caption'] );
		}

		if ( null === $transformed ) {
			return $block_content;
		}

		$this->enqueue_style();

		return $transformed;
	}

	/**
	 * Tells whether the author opted this table in.
	 *
	 * The attribute is read from the parsed block rather than from a class in
	 * the markup: the plugin writes nothing into the saved content, so existing
	 * tables keep validating whether the plugin is active or not.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block Parsed block.
	 * @return bool True when the stacked display is requested.
	 */
	private function is_enabled( $block ) {
		if ( ! isset( $block['attrs']['tableReflow'] ) ) {
			return false;
		}

		return wp_validate_boolean( $block['attrs']['tableReflow'] );
	}

	/**
	 * Returns the sanitized breakpoint requested by a block.
	 *
	 * Any value outside the accepted set falls back to the default, so a
	 * hand-edited block attribute cannot inject an arbitrary class name.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block Parsed block.
	 * @return string Breakpoint value in pixels.
	 */
	private function get_breakpoint( $block ) {
		$requested = isset( $block['attrs']['tableReflowBreakpoint'] )
			? (string) $block['attrs']['tableReflowBreakpoint']
			: '';

		return in_array( $requested, Table_Reflow_Config::get_breakpoint_values(), true )
			? $requested
			: Table_Reflow_Config::get_default_breakpoint();
	}

	/**
	 * Enqueues the front-end stylesheet.
	 *
	 * Called only once a table has actually been transformed, which keeps the
	 * stylesheet off every page that contains no such table. When the block is
	 * rendered after `wp_head`, WordPress prints the stylesheet in the footer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function enqueue_style() {
		if ( wp_style_is( $this->style_handle, 'enqueued' ) ) {
			return;
		}

		if ( ! wp_style_is( $this->style_handle, 'registered' ) ) {
			return;
		}

		wp_enqueue_style( $this->style_handle );
	}

	/**
	 * Inspects the markup and decides whether it can be stacked.
	 *
	 * The walk relies on `WP_HTML_Processor::get_breadcrumbs()`, which reports
	 * the real position of a node in the parsed tree. That is what separates a
	 * `th` belonging to `thead` from a `th` used as a row header in the body,
	 * a distinction a flat tag scan cannot make.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Rendered block markup.
	 * @return array|null {
	 *     Structure report, or null when the markup could not be parsed.
	 *
	 *     @type bool     $stackable Whether the table can be stacked.
	 *     @type string[] $labels    Column header labels, in column order.
	 *     @type string   $caption   Caption text, empty when there is none.
	 * }
	 */
	private function analyze( $html ) {
		$processor = WP_HTML_Processor::create_fragment( $html );

		if ( null === $processor ) {
			return null;
		}

		$labels       = array();
		$caption      = '';
		$tables       = 0;
		$head_rows    = 0;
		$body_rows    = array();
		$row_index    = -1;
		$has_tbody    = false;
		$head_index   = -1;
		$is_stackable = true;

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();

			if ( '#text' === $token_type ) {
				$crumbs = $processor->get_breadcrumbs();

				if ( in_array( 'FIGCAPTION', $crumbs, true ) ) {
					$caption .= $processor->get_modifiable_text();
				} elseif ( $head_index >= 0 && in_array( 'THEAD', $crumbs, true ) && in_array( 'TH', $crumbs, true ) ) {
					$labels[ $head_index ] .= $processor->get_modifiable_text();
				}

				continue;
			}

			if ( '#tag' !== $token_type || $processor->is_tag_closer() ) {
				continue;
			}

			$tag    = $processor->get_tag();
			$crumbs = $processor->get_breadcrumbs();

			switch ( $tag ) {
				case 'TABLE':
					++$tables;

					// A table nested inside a cell has no single column mapping.
					if ( $tables > 1 ) {
						$is_stackable = false;
						break 2;
					}
					break;

				case 'TBODY':
					$has_tbody = true;
					break;

				case 'TR':
					if ( in_array( 'THEAD', $crumbs, true ) ) {
						++$head_rows;

						// Two header rows mean one cell maps to several labels.
						if ( $head_rows > 1 ) {
							$is_stackable = false;
							break 2;
						}
					} else {
						$body_rows[] = 0;
						++$row_index;
					}
					break;

				case 'TH':
				case 'TD':
					// Merged cells break the one cell / one column assumption.
					if ( null !== $processor->get_attribute( 'colspan' ) || null !== $processor->get_attribute( 'rowspan' ) ) {
						$is_stackable = false;
						break 2;
					}

					if ( in_array( 'THEAD', $crumbs, true ) ) {
						// A td in the header row carries no usable label.
						if ( 'TH' !== $tag ) {
							$is_stackable = false;
							break 2;
						}

						++$head_index;
						$labels[ $head_index ] = '';
					} else {
						// A th in the body means headers sit in the first column.
						if ( 'TH' === $tag ) {
							$is_stackable = false;
							break 2;
						}

						if ( $row_index < 0 ) {
							$is_stackable = false;
							break 2;
						}

						++$body_rows[ $row_index ];
					}
					break;
			}
		}

		$caption = trim( $caption );

		if ( ! $is_stackable ) {
			return array(
				'stackable' => false,
				'labels'    => array(),
				'caption'   => $caption,
			);
		}

		$is_stackable = $this->is_structure_consistent( $tables, $has_tbody, $labels, $body_rows );

		return array(
			'stackable' => $is_stackable,
			'labels'    => $is_stackable ? array_map( array( $this, 'normalize_label' ), $labels ) : array(),
			'caption'   => $caption,
		);
	}

	/**
	 * Checks the counts collected during the walk.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $tables    Number of table elements found.
	 * @param bool     $has_tbody Whether an explicit tbody tag is present.
	 * @param string[] $labels    Column header labels collected from thead.
	 * @param int[]    $body_rows Cell count of each body row.
	 * @return bool True when the structure can be stacked.
	 */
	private function is_structure_consistent( $tables, $has_tbody, $labels, $body_rows ) {
		if ( 1 !== $tables ) {
			return false;
		}

		// No header row: there is no label to restore next to each value.
		if ( empty( $labels ) ) {
			return false;
		}

		// An implicit tbody has no tag to carry the rowgroup role.
		if ( ! $has_tbody ) {
			return false;
		}

		if ( empty( $body_rows ) ) {
			return false;
		}

		$expected = count( $labels );

		foreach ( $body_rows as $count ) {
			if ( $count !== $expected ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Collapses the whitespace of a collected label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label Raw label text.
	 * @return string Normalized label text.
	 */
	private function normalize_label( $label ) {
		return normalize_whitespace( (string) $label );
	}

	/**
	 * Adds the stacking classes, the ARIA roles and the cell labels.
	 *
	 * Every write is checked. When a role cannot be written, the table would be
	 * displayed as blocks without its semantics, which is worse than scrolling,
	 * so the method gives up and lets the caller fall back.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $html       Rendered block markup.
	 * @param string[] $labels     Column header labels, in column order.
	 * @param string   $breakpoint Sanitized breakpoint value in pixels.
	 * @return string|null Transformed markup, or null when it could not be written.
	 */
	private function apply_stacked_markup( $html, $labels, $breakpoint ) {
		$processor = WP_HTML_Processor::create_fragment( $html );

		if ( null === $processor ) {
			return null;
		}

		$column = 0;

		while ( $processor->next_token() ) {
			if ( '#tag' !== $processor->get_token_type() || $processor->is_tag_closer() ) {
				continue;
			}

			$tag    = $processor->get_tag();
			$crumbs = $processor->get_breadcrumbs();

			switch ( $tag ) {
				case 'TABLE':
					if ( ! $processor->add_class( self::STACKED_CLASS ) ) {
						return null;
					}

					if ( ! $processor->add_class( self::BREAKPOINT_CLASS_PREFIX . $breakpoint ) ) {
						return null;
					}

					if ( ! $processor->set_attribute( 'role', 'table' ) ) {
						return null;
					}
					break;

				case 'THEAD':
					if ( ! $processor->set_attribute( 'role', 'rowgroup' ) ) {
						return null;
					}

					if ( ! $processor->add_class( self::HEAD_CLASS ) ) {
						return null;
					}
					break;

				case 'TBODY':
				case 'TFOOT':
					if ( ! $processor->set_attribute( 'role', 'rowgroup' ) ) {
						return null;
					}
					break;

				case 'TR':
					if ( ! $processor->set_attribute( 'role', 'row' ) ) {
						return null;
					}

					$column = 0;
					break;

				case 'TH':
					if ( ! in_array( 'THEAD', $crumbs, true ) ) {
						break;
					}

					if ( ! $processor->set_attribute( 'role', 'columnheader' ) ) {
						return null;
					}

					// Keep an author-defined scope, add the implied one otherwise.
					if ( null === $processor->get_attribute( 'scope' ) && ! $processor->set_attribute( 'scope', 'col' ) ) {
						return null;
					}

					++$column;
					break;

				case 'TD':
					if ( in_array( 'THEAD', $crumbs, true ) ) {
						break;
					}

					if ( ! $processor->set_attribute( 'role', 'cell' ) ) {
						return null;
					}

					if ( isset( $labels[ $column ] ) && '' !== $labels[ $column ] ) {
						if ( ! $processor->set_attribute( 'data-label', $labels[ $column ] ) ) {
							return null;
						}
					}

					++$column;
					break;
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Makes the scrollable container reachable and named.
	 *
	 * The block already wraps the table in a `figure` that scrolls sideways.
	 * A scrollable region must be reachable with a keyboard and must have an
	 * accessible name, otherwise its content is unreachable without a pointer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html    Rendered block markup.
	 * @param string $caption Caption text, used as the accessible name when present.
	 * @return string|null Transformed markup, or null when there is no container.
	 */
	private function apply_scroll_fallback( $html, $caption ) {
		$processor = WP_HTML_Processor::create_fragment( $html );

		if ( null === $processor ) {
			return null;
		}

		while ( $processor->next_token() ) {
			if ( '#tag' !== $processor->get_token_type() || $processor->is_tag_closer() ) {
				continue;
			}

			if ( 'FIGURE' !== $processor->get_tag() ) {
				continue;
			}

			if ( ! $processor->add_class( self::SCROLL_CLASS ) ) {
				return null;
			}

			// Never override an attribute the author or the theme already set.
			if ( null === $processor->get_attribute( 'tabindex' ) ) {
				$processor->set_attribute( 'tabindex', '0' );
			}

			if ( null === $processor->get_attribute( 'role' ) ) {
				$processor->set_attribute( 'role', 'region' );
			}

			if ( null === $processor->get_attribute( 'aria-label' ) && null === $processor->get_attribute( 'aria-labelledby' ) ) {
				$label = '' !== $caption ? $caption : Table_Reflow_Config::get_scrollable_label();
				$processor->set_attribute( 'aria-label', $label );
			}

			return $processor->get_updated_html();
		}

		return null;
	}
}
