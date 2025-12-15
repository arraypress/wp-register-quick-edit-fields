# WP Register Quick Edit

A lightweight library for registering custom quick edit fields on WordPress post list tables.

## Features

- Simple API for registering quick edit fields
- Supports posts and custom post types
- Automatic sanitization based on field type
- Auto-generated JavaScript for populating field values
- Permission checking via capabilities
- Multiple field types: text, textarea, number, select, checkbox, url, email
- Callable options for dynamic select values
- No dependencies

## Installation

Install via Composer:

```bash
composer require arraypress/wp-register-quick-edit
```

## Usage

### Basic Example

```php
register_quick_edit_fields( 'download', [
    'tax_class' => [
        'label'    => __( 'Tax Class', 'my-plugin' ),
        'type'     => 'select',
        'column'   => 'tax_class',
        'options'  => [
            '0' => __( '— Use Default —', 'my-plugin' ),
            '1' => __( 'Reduced Rate', 'my-plugin' ),
            '2' => __( 'Zero Rate', 'my-plugin' ),
        ],
        'meta_key' => '_tax_class_id',
    ],
    'sale_price' => [
        'label'    => __( 'Sale Price', 'my-plugin' ),
        'type'     => 'number',
        'column'   => 'price',
        'meta_key' => '_sale_price',
        'step'     => '0.01',
    ],
]);
```

### Column Data Attribute Requirement

For quick edit to populate the current value, your column output must include a data attribute:

```php
// In your column callback:
add_filter( 'manage_download_posts_columns', function( $columns ) {
    $columns['tax_class'] = __( 'Tax Class', 'my-plugin' );
    return $columns;
});

add_action( 'manage_download_posts_custom_column', function( $column, $post_id ) {
    if ( $column === 'tax_class' ) {
        $value = get_post_meta( $post_id, '_tax_class_id', true );
        $label = get_tax_class_label( $value );
        
        // Include data attribute for quick edit JS to read
        printf(
            '<span data-tax_class="%s">%s</span>',
            esc_attr( $value ),
            esc_html( $label )
        );
    }
}, 10, 2 );
```

The data attribute name should match the field key: `data-{field_key}`.

### Field Configuration Options

| Option              | Type             | Default        | Description                           |
|---------------------|------------------|----------------|---------------------------------------|
| `label`             | string           | `''`           | Field label displayed in the UI       |
| `type`              | string           | `'text'`       | Field type (see below)                |
| `description`       | string           | `''`           | Help text displayed below the field   |
| `column`            | string           | Field key      | Column to read current value from     |
| `options`           | array\|callable  | `[]`           | Options for select fields             |
| `meta_key`          | string           | Field key      | The meta key to save to               |
| `min`               | int\|float\|null | `null`         | Minimum value for number fields       |
| `max`               | int\|float\|null | `null`         | Maximum value for number fields       |
| `step`              | int\|float\|null | `null`         | Step value for number fields          |
| `sanitize_callback` | callable\|null   | `null`         | Custom sanitization callback          |
| `capability`        | string           | `'edit_posts'` | Required capability to see/edit field |
| `attrs`             | array            | `[]`           | Additional HTML attributes            |

### Supported Field Types

| Type       | Description         | Auto-Sanitization                        |
|------------|---------------------|------------------------------------------|
| `text`     | Standard text input | `sanitize_text_field()`                  |
| `textarea` | Multi-line text     | `sanitize_textarea_field()`              |
| `number`   | Numeric input       | `intval()` or `floatval()` based on step |
| `select`   | Dropdown select     | Validates against options                |
| `checkbox` | Boolean toggle      | Cast to 0 or 1                           |
| `url`      | URL input           | `esc_url_raw()`                          |
| `email`    | Email input         | `sanitize_email()`                       |

### Dynamic Options

Use a callable to generate options dynamically:

```php
register_quick_edit_fields( 'product', [
    'category' => [
        'label'   => __( 'Category', 'my-plugin' ),
        'type'    => 'select',
        'column'  => 'product_cat',
        'options' => function() {
            $categories = get_terms( [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ] );
            
            $options = [ '' => __( '— Select —', 'my-plugin' ) ];
            foreach ( $categories as $cat ) {
                $options[ $cat->term_id ] = $cat->name;
            }
            
            return $options;
        },
        'meta_key' => '_product_category',
    ],
]);
```

### Multiple Post Types

```php
register_quick_edit_fields( [ 'post', 'page', 'product' ], [
    'featured' => [
        'label'    => __( 'Featured', 'my-plugin' ),
        'type'     => 'checkbox',
        'column'   => 'featured',
        'meta_key' => '_is_featured',
    ],
]);
```

### Custom Sanitization

```php
register_quick_edit_fields( 'product', [
    'price' => [
        'label'             => __( 'Price', 'my-plugin' ),
        'type'              => 'number',
        'column'            => 'price',
        'meta_key'          => '_price',
        'step'              => '0.01',
        'sanitize_callback' => function( $value ) {
            return round( floatval( $value ), 2 );
        },
    ],
]);
```

## How It Works

1. **Render**: Fields are rendered via the `quick_edit_custom_box` hook
2. **Populate**: Auto-generated JavaScript reads data attributes from the row's column and populates the fields
3. **Save**: Values are saved via `save_post_{post_type}` with automatic sanitization

## Requirements

- PHP 7.4 or later
- WordPress 5.0 or later

## License

GPL-2.0-or-later
