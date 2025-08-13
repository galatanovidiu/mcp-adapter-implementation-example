<?php declare( strict_types = 1 );
/**
 * Abilities API
 *
 * Defines WP_Ability class.
 *
 * @package WordPress
 * @subpackage Abilities API
 * @since 0.1.0
 */

/**
 * Encapsulates the properties and methods related to a specific ability in the registry.
 *
 * @since 0.1.0
 * @access private
 *
 * @see WP_Abilities_Registry
 */
class WP_Ability {

	/**
	 * The name of the ability, with its namespace.
	 * Example: `my-plugin/my-ability`.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected string $name;

	/**
	 * The human-readable ability label.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected string $label;

	/**
	 * The detailed ability description.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected string $description;

	/**
	 * The optional ability input schema.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected array $input_schema = [];

	/**
	 * The optional ability output schema.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected array $output_schema = [];

	/**
	 * The ability execute callback.
	 *
	 * @since 0.1.0
	 * @var callable
	 */
	protected $execute_callback;

	/**
	 * The optional ability permission callback.
	 *
	 * @since 0.1.0
	 * @var ?callable
	 */
	protected $permission_callback = null;

	/**
	 * The optional ability metadata.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected array $meta = [];

	/**
	 * Constructor.
	 *
	 * Do not use this constructor directly. Instead, use the `wp_register_ability()` function.
	 *
	 * @see wp_register_ability()
	 *
	 * @since 0.1.0
	 *
	 * @param string $name       The name of the ability, with its namespace.
	 * @param array  $properties An associative array of properties for the ability. This should
	 *                           include `label`, `description`, `input_schema`, `output_schema`,
	 *                           `execute_callback`, `permission_callback`, and `meta`.
	 */
	public function __construct( string $name, array $properties ) {
		$this->name = $name;
		foreach ( $properties as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}
	}

	/**
	 * Retrieves the name of the ability, with its namespace.
	 * Example: `my-plugin/my-ability`.
	 *
	 * @since 0.1.0
	 *
	 * @return string The ability name, with its namespace.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Retrieves the human-readable label for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The human-readable ability label.
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Retrieves the detailed description for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The detailed description for the ability.
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Retrieves the input schema for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array The input schema for the ability.
	 */
	public function get_input_schema(): array {
		return $this->input_schema;
	}

	/**
	 * Retrieves the output schema for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array The output schema for the ability.
	 */
	public function get_output_schema(): array {
		return $this->output_schema;
	}

	/**
	 * Retrieves the metadata for the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array The metadata for the ability.
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	/**
	 * Validates input data against the input schema.
	 *
	 * @since 0.1.0
	 *
	 * @param array $input Optional. The input data to validate.
	 * @return bool Returns true if valid, false if validation fails.
	 */
	protected function validate_input( array $input = array() ): bool {
		$input_schema = $this->get_input_schema();
		if ( empty( $input_schema ) ) {
			return true;
		}

		$valid_input = rest_validate_value_from_schema( $input, $input_schema );
		if ( is_wp_error( $valid_input ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(
					sprintf(
						/* translators: %1$s ability name, %2$s error message. */
						__( 'Invalid input provided for ability "%1$s": %2$s.' ),
						$this->name,
						$valid_input->get_error_message()
					),
				),
				'0.1.0'
			);
			return false;
		}

		return true;
	}

	/**
	 * Checks whether the ability has the necessary permissions.
	 * If the permission callback is not set, the default behavior is to allow access
	 * when the input provided passes validation.
	 *
	 * @since 0.1.0
	 *
	 * @param array $input Optional. The input data for permission checking.
	 * @return bool Whether the ability has the necessary permission.
	 */
	public function has_permission( array $input = array() ): bool {
		if ( ! $this->validate_input( $input ) ) {
			return false;
		}

		if ( ! is_callable( $this->permission_callback ) ) {
			return true;
		}

		return call_user_func( $this->permission_callback, $input );
	}

	/**
	 * Executes the ability callback.
	 *
	 * @since 0.1.0
	 *
	 * @param array $input The input data for the ability.
	 * @return mixed|WP_Error The result of the ability execution, or WP_Error on failure.
	 */
	protected function do_execute( array $input ) {
		if ( ! is_callable( $this->execute_callback ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(
					/* translators: %s ability name. */
					sprintf( __( 'Ability "%s" does not have a valid execute callback.' ), $this->name ),
				),
				'0.1.0'
			);
			return null;
		}
		return call_user_func( $this->execute_callback, $input );
	}

	/**
	 * Validates output data against the output schema.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $output The output data to validate.
	 * @return bool Returns true if valid, false if validation fails.
	 */
	protected function validate_output( $output ): bool {
		$output_schema = $this->get_output_schema();
		if ( empty( $output_schema ) ) {
			return true;
		}

		$valid_output = rest_validate_value_from_schema( $output, $output_schema );
		if ( is_wp_error( $valid_output ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(
					sprintf(
						/* translators: %1$s ability name, %2$s error message. */
						__( 'Invalid output provided for ability "%1$s": %2$s.' ),
						$this->name,
						$valid_output->get_error_message()
					),
				),
				'0.1.0'
			);
			return false;
		}

		return true;
	}

	/**
	 * Executes the ability after input validation and running a permission check.
	 * Before returning the return value, it also validates the output.
	 *
	 * @since 0.1.0
	 *
	 * @param array $input Optional. The input data for the ability.
	 * @return mixed|WP_Error The result of the ability execution, or WP_Error on failure.
	 */
	public function execute( array $input = array() ) {
		if ( ! $this->has_permission( $input ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(
					/* translators: %s ability name. */
					sprintf( __( 'Ability "%s" does not have necessary permission.' ), $this->name )
				),
				'0.1.0'
			);
			return null;
		}

		$result = $this->do_execute( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $this->validate_output( $result ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Wakeup magic method.
	 *
	 * @since 0.1.0
	 */
	public function __wakeup(): void {
		throw new \LogicException( __CLASS__ . ' should never be unserialized.' );
	}
}
