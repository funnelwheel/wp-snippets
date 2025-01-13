<?php
/**
 * Plugin Name: Sidebar Design Block
 * Description: Adds a Gutenberg block with editable content for sidebar design.
 * Version: 1.9
 * Author: Kishores
 */

// Enqueue block editor assets
function sidebar_design_block_enqueue_assets() {
    wp_register_script(
        'sidebar-design-block',
        '',
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components'],
        null
    );

    // Localize script with theme customizer settings
    wp_localize_script('sidebar-design-block', 'sidebarDesignBlockSettings', [
        'buttonBackgroundColor' => get_theme_mod('button_background_color', '#BAFF96'),
        'buttonTextColor' => get_theme_mod('button_text_color', '#121212'),
    ]);

    wp_add_inline_script('sidebar-design-block', "(function (blocks, editor, element, components) {
        const { registerBlockType } = blocks;
        const { RichText, MediaUpload, InspectorControls } = editor;
        const { createElement: el } = element;
        const { PanelBody, SelectControl, Button, ColorPalette } = components;

        registerBlockType('custom/sidebar-design-block', {
            title: 'Sidebar Design Block',
            icon: 'layout',
            category: 'widgets',
            attributes: {
                heading: {
                    type: 'string',
                    source: 'html',
                    selector: 'h2',
                    default: 'Need Quality Design at Scale'
                },
                headingAlignment: {
                    type: 'string',
                    default: 'center'
                },
                headingFontSize: {
                    type: 'number',
                    default: 24
                },
                headingColor: {
                    type: 'string',
                    default: '' // Default to inherit theme color
                },
                content: {
                    type: 'string',
                    source: 'html',
                    selector: 'p',
                    default: 'We can help. Contact upnrunn!'
                },
                contentAlignment: {
                    type: 'string',
                    default: 'center'
                },
                contentFontSize: {
                    type: 'number',
                    default: 16
                },
                contentColor: {
                    type: 'string',
                    default: '' // Default to inherit theme color
                },
                buttonText: {
                    type: 'string',
                    default: 'Book a call'
                },
                buttonUrl: {
                    type: 'string',
                    default: '#'
                },
                buttonBackgroundColor: {
                    type: 'string',
                    default: '' // Default to inherit theme color
                },
                buttonTextColor: {
                    type: 'string',
                    default: '' // Default to inherit theme color
                },
                imageUrl: {
                    type: 'string',
                    default: 'https://dummyimage.com/300x150/cccccc/000000&text=Sidebar+Image'
                },
                paddingBottom: {
                    type: 'number',
                    default: 25 // default bottom padding
                },
                widgetBackgroundColor: {
                    type: 'string',
                    default: '' // Default to inherit background color
                }
            },
            edit: (props) => {
                const { attributes, setAttributes } = props;
                const { heading, headingAlignment, headingFontSize, headingColor, content, contentAlignment, contentFontSize, contentColor, buttonText, buttonUrl, buttonBackgroundColor, buttonTextColor, imageUrl, paddingBottom, widgetBackgroundColor } = attributes;

                // Use localized settings passed from PHP
                const themeButtonBgColor = window.sidebarDesignBlockSettings.buttonBackgroundColor;
                const themeButtonTextColor = window.sidebarDesignBlockSettings.buttonTextColor;

                // Set default styles for admin preview
                const adminPreviewStyles = {
                    backgroundColor: widgetBackgroundColor || 'inherit', // Default to theme background color
                    color: '#fff',
                    padding: '0',
                    paddingBottom: paddingBottom + 'px',
                    borderRadius: '12px',
                    textAlign: 'center',
                    fontFamily: 'Arial, sans-serif',
                    maxWidth: '300px',
                    margin: '0 auto'
                };

                return el(
                    'div',
                    { className: 'sidebar-design-block', style: adminPreviewStyles },
                    el(
                        InspectorControls,
                        {},
                        el(
                            PanelBody,
                            { title: 'Image Settings', initialOpen: true },
                            el(MediaUpload, {
                                onSelect: (media) => setAttributes({ imageUrl: media.url }),
                                allowedTypes: ['image'],
                                render: ({ open }) => el(Button, { onClick: open, isSecondary: true }, 'Select Image')
                            })
                        ),
                        el(
                            PanelBody,
                            { title: 'Heading Settings', initialOpen: false },
                            el('input', {
                                type: 'number',
                                value: headingFontSize,
                                onChange: (event) => setAttributes({ headingFontSize: parseInt(event.target.value, 10) || 24 }),
                                placeholder: 'Font Size'
                            }),
                            el('div', {},
                                el('label', {}, 'Heading Color'),
                                el(ColorPalette, {
                                    value: headingColor,
                                    onChange: (color) => setAttributes({ headingColor: color })
                                })
                            )
                        ),
                        el(
                            PanelBody,
                            { title: 'Content Settings', initialOpen: false },
                            el('input', {
                                type: 'number',
                                value: contentFontSize,
                                onChange: (event) => setAttributes({ contentFontSize: parseInt(event.target.value, 10) || 16 }),
                                placeholder: 'Font Size'
                            }),
                            el('div', {},
                                el('label', {}, 'Content Color'),
                                el(ColorPalette, {
                                    value: contentColor,
                                    onChange: (color) => setAttributes({ contentColor: color })
                                })
                            )
                        ),
                        el(
                            PanelBody,
                            { title: 'Padding Bottom Settings', initialOpen: false },
                            el('input', {
                                type: 'number',
                                value: paddingBottom,
                                onChange: (event) => setAttributes({ paddingBottom: parseInt(event.target.value, 10) || 25 }),
                                placeholder: 'Padding Bottom (px)'
                            })
                        ),
                        el(
                            PanelBody,
                            { title: 'Background Color Settings', initialOpen: false },
                            el('div', {},
                                el('label', {}, 'Widget Background Color'),
                                el(ColorPalette, {
                                    value: widgetBackgroundColor,
                                    onChange: (color) => setAttributes({ widgetBackgroundColor: color })
                                })
                            )
                        ),
                        el(
                            PanelBody,
                            { title: 'Button Settings', initialOpen: false },
                            el('div', {},
                                el('label', {}, 'Button Background Color'),
                                el(ColorPalette, {
                                    value: buttonBackgroundColor || themeButtonBgColor, // Fallback to theme color
                                    onChange: (color) => setAttributes({ buttonBackgroundColor: color })
                                })
                            ),
                            el('div', {},
                                el('label', {}, 'Button Text Color'),
                                el(ColorPalette, {
                                    value: buttonTextColor || themeButtonTextColor, // Fallback to theme color
                                    onChange: (color) => setAttributes({ buttonTextColor: color })
                                })
                            )
                        )
                    ),
                    el(
                        'img',
                        {
                            src: imageUrl,
                            alt: 'Sidebar Image',
                            style: { width: '100%', borderRadius: '8px', marginBottom: '15px' }
                        }
                    ),
                    el(
                        RichText,
                        {
                            tagName: 'h2',
                            value: heading,
                            onChange: (value) => setAttributes({ heading: value }),
                            placeholder: 'Enter heading...',
                            style: {
                                textAlign: headingAlignment,
                                fontSize: headingFontSize + 'px',
                                color: headingColor || 'inherit' // Inherit theme color by default
                            }
                        }
                    ),
                    el(
                        RichText,
                        {
                            tagName: 'p',
                            value: content,
                            onChange: (value) => setAttributes({ content: value }),
                            placeholder: 'Enter content...',
                            style: {
                                textAlign: contentAlignment,
                                fontSize: contentFontSize + 'px',
                                color: contentColor || 'inherit' // Inherit theme color by default
                            }
                        }
                    ),
                    el(
                        'div',
                        {},
                        el(
                            'input',
                            {
                                type: 'url',
                                value: buttonUrl,
                                onChange: (event) => setAttributes({ buttonUrl: event.target.value }),
                                placeholder: 'Enter button URL...',
                                style: { marginRight: '10px', padding: '5px' }
                            }),
                        el(
                            'input',
                            {
                                type: 'text',
                                value: buttonText,
                                onChange: (event) => setAttributes({ buttonText: event.target.value }),
                                placeholder: 'Enter button text...',
                                style: { padding: '5px' }
                            }
                        )
                    )
                );
            },
            save: (props) => {
                const { attributes } = props;
                const { heading, headingAlignment, headingFontSize, headingColor, content, contentAlignment, contentFontSize, contentColor, buttonText, buttonUrl, buttonBackgroundColor, buttonTextColor, imageUrl, paddingBottom, widgetBackgroundColor } = attributes;

                // Use localized settings passed from PHP
                const themeButtonBgColor = window.sidebarDesignBlockSettings.buttonBackgroundColor;
                const themeButtonTextColor = window.sidebarDesignBlockSettings.buttonTextColor;

                // Default to theme styles for text and font properties
                const headingTextColor = headingColor || 'inherit';
                const contentTextColor = contentColor || 'inherit';
                const widgetBgColor = widgetBackgroundColor || 'inherit'; // Fallback to theme background color
                const buttonBgColor = buttonBackgroundColor || themeButtonBgColor;
                const buttonTxtColor = buttonTextColor || themeButtonTextColor;

                return el(
                    'div',
                    { className: 'sidebar-widget', style: {
                        backgroundColor: widgetBgColor, color: '#fff', padding: '0', paddingBottom: paddingBottom + 'px',
                        borderRadius: '12px', textAlign: 'center', fontFamily: 'Arial, sans-serif', maxWidth: '300px', margin: '0 auto'
                    }},
                    el('img', {
                        src: imageUrl,
                        alt: 'Sidebar Image',
                        style: { width: '100%', borderRadius: '8px', marginBottom: '15px' }
                    }),
                    el(RichText.Content, {
                        tagName: 'h2',
                        value: heading,
                        style: { fontSize: headingFontSize + 'px', marginBottom: '15px', fontWeight: 'bold', color: headingTextColor, textAlign: headingAlignment }
                    }),
                    el(RichText.Content, {
                        tagName: 'p',
                        value: content,
                        style: { marginBottom: '20px', fontSize: contentFontSize + 'px', lineHeight: '1.5', color: contentTextColor, textAlign: contentAlignment }
                    }),
                    el('a', {
                        href: buttonUrl,
                        style: {
                            display: 'inline-block', backgroundColor: buttonBgColor, color: buttonTxtColor, padding: '12px 25px',
                            borderRadius: '6px', textDecoration: 'none', fontWeight: 'bold', fontSize: '14px', transition: 'background-color 0.3s ease', width: 'auto'
                        }
                    }, buttonText)
                );
            }
        });
    })(window.wp.blocks, window.wp.editor, window.wp.element, window.wp.components);");

    register_block_type('custom/sidebar-design-block', [
        'editor_script' => 'sidebar-design-block',
        'render_callback' => 'render_sidebar_design_block',
    ]);
}
add_action('init', 'sidebar_design_block_enqueue_assets');

function render_sidebar_design_block($attributes) {
    // Extract attributes
    $attributes = wp_parse_args($attributes, [
        'heading' => 'Need Quality Design at Scale',
        'headingAlignment' => 'center',
        'headingFontSize' => 24,
        'headingColor' => '',
        'content' => 'We can help. Contact upnrunn!',
        'contentAlignment' => 'center',
        'contentFontSize' => 16,
        'contentColor' => '',
        'buttonText' => 'Book a call',
        'buttonUrl' => '#',
        'buttonBackgroundColor' => get_theme_mod('button_background_color', '#BAFF96'),
        'buttonTextColor' => get_theme_mod('button_text_color', '#121212'),
        'imageUrl' => 'https://dummyimage.com/300x150/cccccc/000000&text=Sidebar+Image',
        'paddingBottom' => 25,
        'widgetBackgroundColor' => '',
    ]);

    // Generate the HTML
    $html = '<div class="sidebar-widget" style="'
        . 'background-color: ' . esc_attr($attributes['widgetBackgroundColor'] ?: 'inherit') . '; '
        . 'padding-bottom: ' . esc_attr($attributes['paddingBottom']) . 'px; '
        . 'text-align: center; font-family: Arial, sans-serif; max-width: 300px; margin: 0 auto; border-radius:8px;">';

    $html .= '<img src="' . esc_url($attributes['imageUrl']) . '" alt="Sidebar Image" style="width:100%; border-radius:8px; margin-bottom:15px;" />';

    $html .= '<h2 style="font-size: ' . esc_attr($attributes['headingFontSize']) . 'px; '
        . 'text-align: ' . esc_attr($attributes['headingAlignment']) . '; '
        . 'color: ' . esc_attr($attributes['headingColor'] ?: 'inherit') . '; '
        . 'margin-bottom: 15px; font-weight: bold;">'
        . esc_html($attributes['heading']) . '</h2>';

    $html .= '<p style="font-size: ' . esc_attr($attributes['contentFontSize']) . 'px; '
        . 'text-align: ' . esc_attr($attributes['contentAlignment']) . '; '
        . 'color: ' . esc_attr($attributes['contentColor'] ?: 'inherit') . '; '
        . 'margin-bottom: 20px;">'
        . esc_html($attributes['content']) . '</p>';

    $html .= '<a href="' . esc_url($attributes['buttonUrl']) . '" style="'
        . 'display: inline-block; background-color: ' . esc_attr($attributes['buttonBackgroundColor']) . '; '
        . 'color: ' . esc_attr($attributes['buttonTextColor']) . '; '
        . 'padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; '
        . 'transition: background-color 0.3s ease;">'
        . esc_html($attributes['buttonText']) . '</a>';

    $html .= '</div>';

    return $html;
}
