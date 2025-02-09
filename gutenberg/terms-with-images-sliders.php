<?php
/*
Plugin Name: Taxonomy Terms with Images - Slider View
Description: Display taxonomy terms with images in an Instagram-style slider with circular looping and round arrow buttons. The container width adjusts based on the number of items, and a fixed gap between images is maintained.
Version: 1.2
Author: Your Name
*/

/*--------------------------------------------------------------------------
// ADMIN: Add a Text Field for the Term Image URL
--------------------------------------------------------------------------*/

function add_term_image_url_field($term) {
    if ( is_object($term) && isset($term->term_id) ) {
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
        ?>
        <div class="form-field term-image-wrap">
            <label for="term_image_url">Term Image URL</label>
            <input type="text" name="term_image_url" id="term_image_url" value="" style="width:100%;" placeholder="Enter image URL here" />
        </div>
        <?php
    }
}

function register_term_image_field_for_all_taxonomies() {
    $taxonomies = get_taxonomies([], 'names');
    foreach ($taxonomies as $taxonomy) {
        add_action("{$taxonomy}_edit_form_fields", 'add_term_image_url_field');
        add_action("{$taxonomy}_add_form_fields", 'add_term_image_url_field');
        add_action("edited_{$taxonomy}", 'save_term_image_url');
        add_action("created_{$taxonomy}", 'save_term_image_url');
    }
}
add_action('init', 'register_term_image_field_for_all_taxonomies');

function save_term_image_url($term_id) {
    if (isset($_POST['term_image_url'])) {
        update_term_meta($term_id, 'term_image_url', sanitize_text_field($_POST['term_image_url']));
    }
}

/*--------------------------------------------------------------------------
// GUTENBERG BLOCK: Register a Dynamic Block for Slider
--------------------------------------------------------------------------*/

function custom_terms_slider_block_assets() {
    wp_register_script(
        'custom-terms-slider-block-editor',
        '',
        array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-data'),
        false,
        true
    );
    
    wp_add_inline_script('custom-terms-slider-block-editor', "
    (function(wp) {
        const { registerBlockType } = wp.blocks;
        const { InspectorControls } = wp.blockEditor;
        const { PanelBody, SelectControl, RangeControl, ToggleControl } = wp.components;
        registerBlockType('custom/terms-slider', {
            title: 'Terms Slider with Images',
            icon: 'images-alt2',
            category: 'widgets',
            attributes: {
                taxonomy: { type: 'string', default: 'collections' },
                slidesPerView: { type: 'number', default: 3 },
                showTermDescriptions: { type: 'boolean', default: false },
                autoplay: { type: 'boolean', default: false },
                autoplaySpeed: { type: 'number', default: 3000 }
            },
            edit: function(props) {
                const { attributes, setAttributes } = props;
                // Fallback options if dynamic taxonomies are unavailable
                const taxonomies = wp.data.select('core').getTaxonomies() || [
                    { name: 'Category', slug: 'category' },
                    { name: 'Collections', slug: 'collections' },
                    { name: 'Tags', slug: 'post_tag' }
                ];
                const options = taxonomies.map(tax => ({ label: tax.name, value: tax.slug }));
                return (
                    wp.element.createElement('div', null,
                        wp.element.createElement(InspectorControls, null,
                            wp.element.createElement(PanelBody, { title: 'Settings', initialOpen: true },
                                wp.element.createElement(SelectControl, {
                                    label: 'Select Taxonomy',
                                    value: attributes.taxonomy,
                                    options: options,
                                    onChange: (taxonomy) => setAttributes({ taxonomy })
                                }),
                                wp.element.createElement(RangeControl, {
                                    label: 'Slides Per View',
                                    value: attributes.slidesPerView,
                                    onChange: (value) => setAttributes({ slidesPerView: value }),
                                    min: 1,
                                    max: 10
                                }),
                                wp.element.createElement(ToggleControl, {
                                    label: 'Show Term Descriptions',
                                    checked: attributes.showTermDescriptions,
                                    onChange: (value) => setAttributes({ showTermDescriptions: value })
                                }),
                                wp.element.createElement(ToggleControl, {
                                    label: 'Enable Autoplay',
                                    checked: attributes.autoplay,
                                    onChange: (value) => setAttributes({ autoplay: value })
                                }),
                                wp.element.createElement(RangeControl, {
                                    label: 'Autoplay Speed (ms)',
                                    value: attributes.autoplaySpeed,
                                    onChange: (value) => setAttributes({ autoplaySpeed: value }),
                                    min: 1000,
                                    max: 10000,
                                    step: 500
                                })
                            )
                        ),
                        wp.element.createElement('p', null, 'Terms Slider will be rendered on the front end.')
                    )
                );
            },
            save: function() { return null; }
        });
    })(window.wp);
    ");
    
    register_block_type('custom/terms-slider', array(
        'editor_script'   => 'custom-terms-slider-block-editor',
        'render_callback' => 'render_terms_slider_block',
        'attributes'      => array(
            'taxonomy' => array('type' => 'string', 'default' => 'collections'),
            'slidesPerView' => array('type' => 'number', 'default' => 3),
            'showTermDescriptions' => array('type' => 'boolean', 'default' => false),
            'autoplay' => array('type' => 'boolean', 'default' => false),
            'autoplaySpeed' => array('type' => 'number', 'default' => 3000)
        ),
    ));
}
add_action('init', 'custom_terms_slider_block_assets');

function render_terms_slider_block($attributes) {
    $taxonomy = $attributes['taxonomy'] ?? 'collections';
    $slidesPerView = $attributes['slidesPerView'] ?? 3;
    $showDescriptions = $attributes['showTermDescriptions'] ?? false;
    $autoplay = $attributes['autoplay'] ?? false;
    $autoplaySpeed = $attributes['autoplaySpeed'] ?? 3000;
    $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
    
    if (empty($terms) || is_wp_error($terms)) {
        return '<p>No terms found.</p>';
    }
    
    // Calculate container width based on the lesser of number of terms and slidesPerView
    $terms_count = count($terms);
    $item_width = 100; // fixed width for each term item in px
    $gap = 10; // constant gap between items in px
    if ($terms_count < $slidesPerView) {
        $container_width = $terms_count * $item_width + ($terms_count - 1) * $gap;
    } else {
        $container_width = $slidesPerView * $item_width + ($slidesPerView - 1) * $gap;
    }
    
    // Set scroll amount to one item width plus gap
    $scroll_amount = $item_width + $gap;
    
    ob_start();
    ?>
    <div class="terms-slider-container" style="width: <?php echo esc_attr($container_width); ?>px; margin: 0 auto;" data-autoplay="<?php echo esc_attr($autoplay ? 'true' : 'false'); ?>" data-speed="<?php echo esc_attr($autoplaySpeed); ?>">
        <button class="slider-arrow left" aria-label="Previous Terms" tabindex="0" style="background: white; color: black; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&lt;</button>
        <div class="terms-slider" role="list">
            <?php foreach ($terms as $term) :
                $image_url = get_term_meta($term->term_id, 'term_image_url', true) ?: 'https://placehold.co/80'; ?>
                <div class="term-item" role="listitem" tabindex="0">
                    <a href="<?php echo esc_url(get_term_link($term)); ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($term->name); ?>">
                        <span><?php echo esc_html($term->name); ?></span>
                        <?php if ($showDescriptions && !empty($term->description)) : ?>
                            <p class="term-description"><?php echo esc_html($term->description); ?></p>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="slider-arrow right" aria-label="Next Terms" tabindex="0" style="background: white; color: black; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&gt;</button>
    </div>
    <style>
        .terms-slider-container {
            position: relative;
            overflow: hidden;
            display: block;
        }
        .terms-slider {
            display: inline-flex;
            gap: <?php echo $gap; ?>px;
            overflow-x: auto;
            scroll-behavior: smooth;
        }
        .terms-slider::-webkit-scrollbar { display: none; }
        .term-item {
            text-align: center;
            flex: 0 0 <?php echo $item_width; ?>px;
            transition: transform 0.3s ease;
        }
        .term-item img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid #ddd;
            transition: transform 0.3s ease;
        }
        .term-item:hover img {
            transform: scale(1.1);
        }
        .term-item span { display: block; margin-top: 5px; font-size: 14px; }
        .term-description { font-size: 12px; color: #666; margin-top: 4px; }
        .slider-arrow {
            background: white;
            color: black;
            border: none;
            padding: 0;
            cursor: pointer;
            position: absolute;
            top: 25%; /* arrow positioned at 25% from the top */
			transform: translateY(-50%);
            z-index: 2;
            font-size: 14px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .slider-arrow.left { left: 0; }
        .slider-arrow.right { right: 0; }
        .slider-arrow:focus, .term-item:focus {
            outline: 3px solid #0073aa;
        }
    </style>
    <script>
        window.addEventListener('load', function() {
            const slider = document.querySelector('.terms-slider');
            const leftArrow = document.querySelector('.slider-arrow.left');
            const rightArrow = document.querySelector('.slider-arrow.right');
            const container = document.querySelector('.terms-slider-container');
            const autoplay = container.dataset.autoplay === 'true';
            const speed = parseInt(container.dataset.speed);
            const scrollAmount = <?php echo esc_js($scroll_amount); ?>;
        
            function getMaxScroll() {
                return slider.scrollWidth - container.offsetWidth;
            }
        
            function scrollRight() {
                const maxScroll = getMaxScroll();
                let newScroll = slider.scrollLeft + scrollAmount;
                // If near or beyond the end, wrap around
                if (newScroll >= maxScroll - 1) {
                    newScroll = 0;
                }
                slider.scrollTo({ left: newScroll, behavior: 'smooth' });
            }
        
            function scrollLeft() {
                const maxScroll = getMaxScroll();
                let newScroll = slider.scrollLeft - scrollAmount;
                // If near or before the beginning, wrap around to the end
                if (newScroll <= 1) {
                    newScroll = maxScroll;
                }
                slider.scrollTo({ left: newScroll, behavior: 'smooth' });
            }
        
            rightArrow.addEventListener('click', scrollRight);
            leftArrow.addEventListener('click', scrollLeft);
        
            // Keyboard navigation support
            document.addEventListener('keydown', function(event) {
                if (event.key === 'ArrowLeft') {
                    scrollLeft();
                } else if (event.key === 'ArrowRight') {
                    scrollRight();
                }
            });
        
            // Autoplay functionality
            if (autoplay) {
                setInterval(() => {
                    scrollRight();
                }, speed);
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
?>
