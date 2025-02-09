/*
Plugin Name: Taxonomy Terms with Images - URL Only (All Taxonomies)
Description: Add a text field to your taxonomy term edit screens to enter an image URL, and display terms with images in a Gutenberg block. This snippet registers the field for all taxonomies.
Version: 1.1
Author: Your Name
*/

/*--------------------------------------------------------------------------
  1. ADMIN: Add a Text Field for the Term Image URL
--------------------------------------------------------------------------*/

/**
 * Outputs the term image URL field.
 * Uses different markup for the edit form (object provided) and the add form (no object).
 *
 * @param mixed $term A term object (edit form) or a string/empty (add form).
 */
function add_term_image_url_field($term) {
    if ( is_object($term) && isset($term->term_id) ) {
        // Edit form: a term object is provided.
        $image_url = get_term_meta($term->term_id, 'term_image_url', true);
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="term_image_url">Term Image URL</label>
            </th>
            <td>
                <input type="text" name="term_image_url" id="term_image_url" value="<?php echo esc_attr($image_url); ?>" style="width:100%;" placeholder="Enter image URL here" />
                <?php if ($image_url) { ?>
                    <br /><img src="<?php echo esc_url($image_url); ?>" style="max-width:100px; margin-top:10px;" />
                <?php } ?>
            </td>
        </tr>
        <?php
    } else {
        // Add form: no term object is provided.
        ?>
        <div class="form-field term-image-wrap">
            <label for="term_image_url">Term Image URL</label>
            <input type="text" name="term_image_url" id="term_image_url" value="" style="width:100%;" placeholder="Enter image URL here" />
        </div>
        <?php
    }
}

// Register the field for all taxonomies.
function register_term_image_field_for_all_taxonomies() {
    $taxonomies = get_taxonomies([], 'names');
    foreach ( $taxonomies as $taxonomy ) {
        add_action("{$taxonomy}_edit_form_fields", 'add_term_image_url_field');
        add_action("{$taxonomy}_add_form_fields", 'add_term_image_url_field');
        add_action("edited_{$taxonomy}", 'save_term_image_url');
        add_action("created_{$taxonomy}", 'save_term_image_url');
    }
}
add_action('init', 'register_term_image_field_for_all_taxonomies');

// Save the image URL when the term is saved.
function save_term_image_url($term_id) {
    if ( isset($_POST['term_image_url']) ) {
        update_term_meta($term_id, 'term_image_url', sanitize_text_field($_POST['term_image_url']));
    }
}

/*--------------------------------------------------------------------------
  2. GUTENBERG BLOCK: Register a Dynamic Block to Display Terms with Images
--------------------------------------------------------------------------*/

function custom_terms_list_with_images_block_assets() {
    // Register an (empty) script handle and then add our inline JavaScript.
    wp_register_script(
        'custom-terms-list-block-editor',
        '',
        array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-data'),
        false,
        true
    );
    wp_add_inline_script('custom-terms-list-block-editor', "
    (function( wp ) {
      const { registerBlockType } = wp.blocks;
      const { InspectorControls } = wp.blockEditor;
      const { PanelBody, SelectControl } = wp.components;
      registerBlockType('custom/terms-list-with-images', {
        title: 'Terms List with Images',
        icon: 'format-gallery',
        category: 'widgets',
        attributes: {
          taxonomy: {
            type: 'string',
            default: 'collections',
          },
        },
        edit: function(props) {
          const { attributes, setAttributes } = props;
          const { taxonomy } = attributes;
          return (
            wp.element.createElement('div', null,
              wp.element.createElement(InspectorControls, null,
                wp.element.createElement(PanelBody, { title: 'Settings', initialOpen: true },
                  wp.element.createElement(SelectControl, {
                    label: 'Select Taxonomy',
                    value: taxonomy,
                    options: [
                      { label: 'Category', value: 'category' },
                      { label: 'Collections', value: 'collections' }
                    ],
                    onChange: (newTaxonomy) => setAttributes({ taxonomy: newTaxonomy })
                  })
                )
              ),
              wp.element.createElement('p', null, 'Terms List will be rendered on the front end.')
            )
          );
        },
        save: function() {
          return null;
        },
      });
    })( window.wp );
    ");

    // Register the block as a dynamic block (rendered by PHP).
    register_block_type('custom/terms-list-with-images', array(
        'editor_script'   => 'custom-terms-list-block-editor',
        'render_callback' => 'render_terms_list_with_images_block',
        'attributes'      => array(
            'taxonomy' => array(
                'type'    => 'string',
                'default' => 'collections',
            ),
        ),
    ));
}
add_action('init', 'custom_terms_list_with_images_block_assets');

// Render the block output on the front end.
function render_terms_list_with_images_block($attributes) {
    $taxonomy = isset($attributes['taxonomy']) ? $attributes['taxonomy'] : 'collections';
    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ));

    if ( empty($terms) || is_wp_error($terms) ) {
        return '<p>No terms found.</p>';
    }

    $output = '<ul class="wp-block-terms-list">';
    foreach ($terms as $term) {
        $image_url = get_term_meta($term->term_id, 'term_image_url', true);
        $image_url = $image_url ? $image_url : 'https://placehold.co/150';
        $active_class = (get_queried_object_id() == $term->term_id) ? 'active' : '';
        $output .= '<li class="' . esc_attr($active_class) . '">';
        $output .= '<a href="' . esc_url(get_term_link($term)) . '">';
        $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($term->name) . '">';
        $output .= '<span>' . esc_html($term->name) . '</span>';
        $output .= '</a>';
        $output .= '</li>';
    }
    $output .= '</ul>';

    $output .= '<style>
        .wp-block-terms-list {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 0;
            list-style: none;
        }
        .wp-block-terms-list li {
            text-align: center;
        }
        .wp-block-terms-list li img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }
        .wp-block-terms-list li.active img, 
        .wp-block-terms-list li:hover img {
            border-color: #000;
        }
        .wp-block-terms-list li span {
            display: block;
            margin-top: 8px;
            font-size: 14px;
            color: #333;
        }
        .wp-block-terms-list li.active span {
            font-weight: bold;
        }
    </style>';

    return $output;
}
