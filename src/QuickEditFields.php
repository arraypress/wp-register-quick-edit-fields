<?php
/**
 * Quick Edit Class
 *
 * A lightweight class for registering custom quick edit fields on WordPress
 * post list tables. Provides a simple API for common field types with
 * automatic saving, sanitization, and JavaScript population.
 *
 * @package     ArrayPress\RegisterQuickEditFields
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterQuickEditFields;

use Exception;

/**
 * Class QuickEdit
 *
 * Manages custom quick edit field registration for post list tables.
 *
 * @package ArrayPress\RegisterQuickEdit
 */
class QuickEditFields {

    /**
     * The post type this instance is registered for.
     *
     * @var string
     */
    protected string $post_type;

    /**
     * Unique identifier for this field group.
     *
     * @var string
     */
    protected string $group_id;

    /**
     * Registered quick edit fields storage.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $fields = [];

    /**
     * Track which post types have had scripts output.
     *
     * @var array<string, bool>
     */
    protected static array $scripts_output = [];

    /**
     * Supported field types.
     *
     * @var array<string>
     */
    protected static array $field_types = [
            'text',
            'textarea',
            'number',
            'select',
            'checkbox',
            'url',
            'email',
    ];

    /**
     * QuickEdit constructor.
     *
     * Initializes the quick edit field registration.
     *
     * @param array  $fields    Array of field configurations keyed by field key.
     * @param string $post_type The post type to register fields for.
     *
     * @throws Exception If a field key is invalid or field type is unsupported.
     */
    public function __construct( array $fields, string $post_type ) {
        $this->post_type = $post_type;
        $this->group_id  = 'quick_edit_' . $post_type;

        $this->add_fields( $fields );

        // Load hooks immediately if already in admin, otherwise wait
        if ( did_action( 'admin_init' ) ) {
            $this->load_hooks();
        } else {
            add_action( 'admin_init', [ $this, 'load_hooks' ] );
        }
    }

    /**
     * Add fields to the configuration.
     *
     * Parses and validates field configurations, merging with defaults.
     *
     * @param array $fields Array of field configurations keyed by field key.
     *
     * @return void
     * @throws Exception If a field key is invalid or field type is unsupported.
     */
    protected function add_fields( array $fields ): void {
        $defaults = [
                'label'             => '',
                'type'              => 'text',
                'description'       => '',
                'options'           => [],
                'column'            => '',
                'meta_key'          => '',
                'min'               => null,
                'max'               => null,
                'step'              => null,
                'sanitize_callback' => null,
                'capability'        => 'edit_posts',
                'attrs'             => [],
        ];

        foreach ( $fields as $key => $field ) {
            if ( ! is_string( $key ) || empty( $key ) ) {
                throw new Exception( 'Invalid field key provided. It must be a non-empty string.' );
            }

            // Validate field type
            $type = $field['type'] ?? 'text';
            if ( ! in_array( $type, self::$field_types, true ) ) {
                throw new Exception( sprintf( 'Invalid field type "%s" for field "%s".', $type, $key ) );
            }

            // Auto-set meta_key if not provided
            if ( empty( $field['meta_key'] ) ) {
                $field['meta_key'] = $key;
            }

            // Auto-set column if not provided
            if ( empty( $field['column'] ) ) {
                $field['column'] = $key;
            }

            self::$fields[ $this->group_id ][ $key ] = wp_parse_args( $field, $defaults );
        }
    }

    /**
     * Get all registered fields for this group.
     *
     * @return array Array of field configurations.
     */
    public function get_fields(): array {
        return self::$fields[ $this->group_id ] ?? [];
    }

    /**
     * Get all registered fields across all groups.
     *
     * @return array Array of all field configurations.
     */
    public static function get_all_fields(): array {
        return self::$fields;
    }

    /**
     * Get a specific field configuration by key.
     *
     * @param string $key The field key.
     *
     * @return array|null The field configuration or null if not found.
     */
    public function get_field( string $key ): ?array {
        return self::$fields[ $this->group_id ][ $key ] ?? null;
    }

    /**
     * Load WordPress hooks.
     *
     * Registers actions for rendering fields, saving data, and outputting scripts.
     *
     * @return void
     */
    public function load_hooks(): void {
        add_action( 'quick_edit_custom_box', [ $this, 'render_fields' ], 10, 2 );
        add_action( 'save_post_' . $this->post_type, [ $this, 'save_fields' ] );
        add_action( 'admin_footer', [ $this, 'output_scripts' ] );
    }

    /**
     * Render fields in the quick edit form.
     *
     * @param string $column_name The column name being rendered.
     * @param string $post_type   The post type.
     *
     * @return void
     */
    public function render_fields( string $column_name, string $post_type ): void {
        if ( $post_type !== $this->post_type ) {
            return;
        }

        $fields = $this->get_fields();

        foreach ( $fields as $key => $field ) {
            if ( $column_name !== $key ) {
                continue;
            }

            if ( ! $this->check_permission( $field ) ) {
                continue;
            }

            $this->render_field( $key, $field );
        }
    }

    /**
     * Render a single field in the quick edit form.
     *
     * @param string $key   The field key.
     * @param array  $field The field configuration.
     *
     * @return void
     */
    protected function render_field( string $key, array $field ): void {
        $field_name = esc_attr( $field['meta_key'] );
        $options    = $this->get_select_options( $field );
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php echo esc_html( $field['label'] ); ?></span>
                    <?php $this->render_input( $field_name, $key, $field, $options ); ?>
                </label>
                <?php if ( ! empty( $field['description'] ) ) : ?>
                    <p class="description"><?php echo esc_html( $field['description'] ); ?></p>
                <?php endif; ?>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Render the appropriate input element based on field type.
     *
     * @param string $name    The field name attribute.
     * @param string $key     The field key.
     * @param array  $field   The field configuration.
     * @param array  $options The select options if applicable.
     *
     * @return void
     */
    protected function render_input( string $name, string $key, array $field, array $options ): void {
        $attrs = $this->build_attrs( $field );

        switch ( $field['type'] ) {
            case 'select':
                $this->render_select( $name, $key, $field, $options );
                break;

            case 'checkbox':
                $this->render_checkbox( $name, $key, $field );
                break;

            case 'number':
                $this->render_number( $name, $key, $field, $attrs );
                break;

            case 'textarea':
                $this->render_textarea( $name, $key, $field, $attrs );
                break;

            case 'url':
            case 'email':
            case 'text':
            default:
                $this->render_text( $name, $key, $field, $attrs );
                break;
        }
    }

    /**
     * Build HTML attributes string from field configuration.
     *
     * @param array $field The field configuration.
     *
     * @return string The HTML attributes string.
     */
    protected function build_attrs( array $field ): string {
        $attrs_array = $field['attrs'] ?? [];

        // Add min/max/step for number fields
        if ( $field['type'] === 'number' ) {
            if ( isset( $field['min'] ) ) {
                $attrs_array['min'] = $field['min'];
            }
            if ( isset( $field['max'] ) ) {
                $attrs_array['max'] = $field['max'];
            }
            if ( isset( $field['step'] ) ) {
                $attrs_array['step'] = $field['step'];
            }
        }

        $attrs = '';
        foreach ( $attrs_array as $attr => $value ) {
            $attrs .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
        }

        return $attrs;
    }

    /**
     * Render a text input field.
     *
     * @param string $name  The field name attribute.
     * @param string $key   The field key.
     * @param array  $field The field configuration.
     * @param string $attrs Additional HTML attributes.
     *
     * @return void
     */
    protected function render_text( string $name, string $key, array $field, string $attrs ): void {
        $type = in_array( $field['type'], [ 'url', 'email' ], true ) ? $field['type'] : 'text';
        ?>
        <input type="<?php echo esc_attr( $type ); ?>"
               name="<?php echo esc_attr( $name ); ?>"
               data-quick-edit-field="<?php echo esc_attr( $key ); ?>"
               value=""
               class="regular-text"<?php echo $attrs; ?>>
        <?php
    }

    /**
     * Render a number input field.
     *
     * @param string $name  The field name attribute.
     * @param string $key   The field key.
     * @param array  $field The field configuration.
     * @param string $attrs Additional HTML attributes.
     *
     * @return void
     */
    protected function render_number( string $name, string $key, array $field, string $attrs ): void {
        ?>
        <input type="number"
               name="<?php echo esc_attr( $name ); ?>"
               data-quick-edit-field="<?php echo esc_attr( $key ); ?>"
               value=""
               class="small-text"<?php echo $attrs; ?>>
        <?php
    }

    /**
     * Render a textarea field.
     *
     * @param string $name  The field name attribute.
     * @param string $key   The field key.
     * @param array  $field The field configuration.
     * @param string $attrs Additional HTML attributes.
     *
     * @return void
     */
    protected function render_textarea( string $name, string $key, array $field, string $attrs ): void {
        ?>
        <textarea name="<?php echo esc_attr( $name ); ?>"
                  data-quick-edit-field="<?php echo esc_attr( $key ); ?>"
                  rows="3"
                  class="regular-text"<?php echo $attrs; ?>></textarea>
        <?php
    }

    /**
     * Render a select dropdown field.
     *
     * @param string $name    The field name attribute.
     * @param string $key     The field key.
     * @param array  $field   The field configuration.
     * @param array  $options The select options.
     *
     * @return void
     */
    protected function render_select( string $name, string $key, array $field, array $options ): void {
        ?>
        <select name="<?php echo esc_attr( $name ); ?>"
                data-quick-edit-field="<?php echo esc_attr( $key ); ?>">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>">
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render a checkbox field.
     *
     * @param string $name  The field name attribute.
     * @param string $key   The field key.
     * @param array  $field The field configuration.
     *
     * @return void
     */
    protected function render_checkbox( string $name, string $key, array $field ): void {
        ?>
        <input type="checkbox"
               name="<?php echo esc_attr( $name ); ?>"
               data-quick-edit-field="<?php echo esc_attr( $key ); ?>"
               value="1">
        <?php
    }

    /**
     * Get options for a select field.
     *
     * Handles both static arrays and callable options.
     *
     * @param array $field The field configuration.
     *
     * @return array Array of options as value => label pairs.
     */
    protected function get_select_options( array $field ): array {
        $options = $field['options'] ?? [];

        if ( is_callable( $options ) ) {
            $options = call_user_func( $options );
        }

        return is_array( $options ) ? $options : [];
    }

    /**
     * Save field values when posts are quick edited.
     *
     * @param int $post_id The post ID being saved.
     *
     * @return void
     */
    public function save_fields( int $post_id ): void {
        // Skip autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Verify the post type
        if ( get_post_type( $post_id ) !== $this->post_type ) {
            return;
        }

        // Check permission
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = $this->get_fields();

        foreach ( $fields as $key => $field ) {
            if ( ! $this->check_permission( $field ) ) {
                continue;
            }

            $meta_key = $field['meta_key'];

            // Handle checkbox separately (unchecked = not in POST)
            if ( 'checkbox' === $field['type'] ) {
                // Only process if this is actually a quick edit request with our fields
                if ( ! isset( $_REQUEST['_inline_edit'] ) ) {
                    continue;
                }
                $value = isset( $_REQUEST[ $meta_key ] ) ? 1 : 0;
                update_post_meta( $post_id, $meta_key, $value );
                continue;
            }

            if ( ! isset( $_REQUEST[ $meta_key ] ) ) {
                continue;
            }

            $value = $_REQUEST[ $meta_key ];

            // Sanitize the value
            $value = $this->sanitize_value( $value, $field );

            // Save or delete based on value
            if ( $value === '' || $value === null ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, $value );
            }
        }
    }

    /**
     * Sanitize a field value based on its type and configuration.
     *
     * @param mixed $value The raw value to sanitize.
     * @param array $field The field configuration.
     *
     * @return mixed The sanitized value.
     */
    protected function sanitize_value( $value, array $field ) {
        // Use custom sanitize callback if provided
        if ( is_callable( $field['sanitize_callback'] ) ) {
            return call_user_func( $field['sanitize_callback'], $value );
        }

        $type = $field['type'];

        switch ( $type ) {
            case 'checkbox':
                return $value ? 1 : 0;

            case 'number':
                // Use floatval if step allows decimals, otherwise intval
                $step = $field['step'] ?? 1;
                if ( is_numeric( $step ) && floor( (float) $step ) != (float) $step ) {
                    $value = floatval( $value );
                } else {
                    $value = intval( $value );
                }

                // Apply min/max constraints
                if ( isset( $field['min'] ) && $value < $field['min'] ) {
                    $value = $field['min'];
                }
                if ( isset( $field['max'] ) && $value > $field['max'] ) {
                    $value = $field['max'];
                }

                return $value;

            case 'select':
                // Validate against options
                $options = $this->get_select_options( $field );
                if ( ! array_key_exists( $value, $options ) ) {
                    return null;
                }

                return $value;

            case 'url':
                return esc_url_raw( $value );

            case 'email':
                return sanitize_email( $value );

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Check if the current user has permission to edit the field.
     *
     * @param array $field The field configuration.
     *
     * @return bool True if user has permission, false otherwise.
     */
    protected function check_permission( array $field ): bool {
        return current_user_can( $field['capability'] );
    }

    /**
     * Output JavaScript for populating quick edit fields.
     *
     * @return void
     */
    public function output_scripts(): void {
        $screen = get_current_screen();

        if ( ! $screen || $screen->id !== 'edit-' . $this->post_type ) {
            return;
        }

        // Only output once per post type
        if ( isset( self::$scripts_output[ $this->post_type ] ) ) {
            return;
        }
        self::$scripts_output[ $this->post_type ] = true;

        $fields = $this->get_fields();

        if ( empty( $fields ) ) {
            return;
        }

        // Build field configuration for JS
        $js_fields = [];
        foreach ( $fields as $key => $field ) {
            $js_fields[] = [
                    'key'    => $key,
                    'column' => $field['column'],
                    'type'   => $field['type'],
            ];
        }
        ?>
        <script>
            (function ($) {
                'use strict';

                const fields = <?php echo wp_json_encode( $js_fields ); ?>;

                /**
                 * Get field value from a table row column.
                 *
                 * @param {jQuery} $column The column element.
                 * @param {string} key     The field key.
                 * @returns {*} The field value or empty string.
                 */
                function getFieldValue($column, key) {
                    // Try data attribute on element with data-{key}
                    let $element = $column.find('[data-' + key + ']');
                    if ($element.length) {
                        return $element.data(key);
                    }

                    // Try data attribute directly on column
                    let value = $column.data(key);
                    if (value !== undefined) {
                        return value;
                    }

                    // Try span with data attribute
                    $element = $column.find('span[data-' + key + ']');
                    if ($element.length) {
                        return $element.data(key);
                    }

                    return '';
                }

                /**
                 * Populate a quick edit field with a value.
                 *
                 * @param {jQuery} $editRow The edit row element.
                 * @param {Object} field    The field configuration.
                 * @param {*}      value    The value to set.
                 */
                function populateField($editRow, field, value) {
                    const $field = $editRow.find('[data-quick-edit-field="' + field.key + '"]');

                    if (!$field.length) {
                        return;
                    }

                    if (field.type === 'checkbox') {
                        $field.prop('checked', value === 1 || value === true || value === '1');
                    } else {
                        $field.val(value);
                    }
                }

                // Initialize quick edit field population
                $(document).ready(function () {
                    $('#the-list').on('click', '.editinline', function () {
                        const $row = $(this).closest('tr');

                        // Use setTimeout to wait for WordPress to create the edit row
                        setTimeout(function () {
                            const $editRow = $('.inline-edit-row');

                            fields.forEach(function (field) {
                                const $column = $row.find('.column-' + field.column);
                                const value = getFieldValue($column, field.key);
                                populateField($editRow, field, value);
                            });
                        }, 50);
                    });
                });
            })(jQuery);
        </script>
        <?php
    }

}