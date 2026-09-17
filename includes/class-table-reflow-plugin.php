<?php
/**
 * Plugin bootstrap: hook registration and asset loading.
 *
 * @package TableReflow
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 *
 * Every hook registered here fires on `init` or later. No translation function
 * is called before `init`, which WordPress 6.7 and later reports through
 * `_doing_it_wrong()`.
 *
 * @since 1.0.0
 */
final class Table_Reflow_Plugin {

	/**
	 * Handle of the front-end stylesheet.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const STYLE_HANDLE = 'table-reflow';

	/**
	 * Handle of the editor script.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const EDITOR_SCRIPT_HANDLE = 'table-reflow-editor';

	/**
	 * Block the plugin extends.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const TARGET_BLOCK = 'core/table';

	/**
	 * Markup transformer.
	 *
	 * @since 1.0.0
	 * @var   Table_Reflow_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->renderer = new Table_Reflow_Renderer( self::STYLE_HANDLE );
	}

	/**
	 * Registers every hook the plugin needs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'render_block', array( $this->renderer, 'render_block' ), 10, 2 );
	}

	/**
	 * Registers the assets without enqueueing them.
	 *
	 * The stylesheet is only enqueued once a table has actually been
	 * transformed, so a page with no such table loads nothing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			self::STYLE_HANDLE,
			TABLE_REFLOW_URL . 'assets/css/table-reflow.css',
			array(),
			TABLE_REFLOW_VERSION
		);

		wp_register_script(
			self::EDITOR_SCRIPT_HANDLE,
			TABLE_REFLOW_URL . 'assets/js/table-reflow-editor.js',
			array(
				'wp-block-editor',
				'wp-blocks',
				'wp-components',
				'wp-compose',
				'wp-element',
				'wp-hooks',
				'wp-i18n',
			),
			TABLE_REFLOW_VERSION,
			true
		);

		wp_set_script_translations(
			self::EDITOR_SCRIPT_HANDLE,
			'table-reflow',
			TABLE_REFLOW_PATH . 'languages'
		);
	}

	/**
	 * Enqueues the editor script and hands it its configuration.
	 *
	 * The breakpoint labels are translated on the server rather than in the
	 * script, so they follow the site locale without a second string catalogue.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		if ( ! $this->is_target_block_available() ) {
			return;
		}

		wp_enqueue_script( self::EDITOR_SCRIPT_HANDLE );

		$settings = array(
			'breakpoints'       => Table_Reflow_Config::get_breakpoint_choices(),
			'defaultBreakpoint' => Table_Reflow_Config::get_default_breakpoint(),
		);

		wp_add_inline_script(
			self::EDITOR_SCRIPT_HANDLE,
			'window.tableReflowEditor = ' . wp_json_encode( $settings ) . ';',
			'before'
		);
	}

	/**
	 * Tells whether the block the plugin extends is registered.
	 *
	 * The table block can be unregistered by a site, in which case the editor
	 * script has nothing to extend.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the target block is registered.
	 */
	private function is_target_block_available() {
		$registry = WP_Block_Type_Registry::get_instance();

		return $registry->is_registered( self::TARGET_BLOCK );
	}
}
