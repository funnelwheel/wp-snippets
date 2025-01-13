<?php
/**
 * Plugin Name: Sidebar Design Block
 * Description: Adds a Gutenberg block with editable content for sidebar design.
 * Version: 1.2
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

    wp_add_inline_script('sidebar-design-block', "(function (blocks, editor, element, components) {
        const { registerBlockType } = blocks;
        const { RichText, MediaUpload, InspectorControls, BlockControls, AlignmentToolbar } = editor;
        const { createElement: el } = element;
        const { PanelBody, SelectControl, Button } = components;

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
                headingFontFamily: {
                    type: 'string',
                    default: 'Arial, sans-serif'
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
                contentFontFamily: {
                    type: 'string',
                    default: 'Arial, sans-serif'
                },
                buttonText: {
                    type: 'string',
                    default: 'Book a call'
                },
                buttonUrl: {
                    type: 'string',
                    default: '#'
                },
                imageUrl: {
                    type: 'string',
                    default: 'https://dummyimage.com/300x150/cccccc/000000&text=Sidebar+Image'
                }
            },
            edit: (props) => {
                const { attributes, setAttributes } = props;
                const { heading, headingAlignment, headingFontSize, headingFontFamily, content, contentAlignment, contentFontSize, contentFontFamily, buttonText, buttonUrl, imageUrl } = attributes;

                return el(
                    'div',
                    { className: 'sidebar-design-block' },
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
                            el(SelectControl, {
                                label: 'Font Family',
                                value: headingFontFamily,
                                options: [
                                    { label: 'Arial', value: 'Arial, sans-serif' },
                                    { label: 'Georgia', value: 'Georgia, serif' },
                                    { label: 'Tahoma', value: 'Tahoma, sans-serif' },
                                    { label: 'Verdana', value: 'Verdana, sans-serif' }
                                ],
                                onChange: (value) => setAttributes({ headingFontFamily: value })
                            }),
                            el('input', {
                                type: 'number',
                                value: headingFontSize,
                                onChange: (event) => setAttributes({ headingFontSize: parseInt(event.target.value, 10) || 24 }),
                                placeholder: 'Font Size'
                            })
                        ),
                        el(
                            PanelBody,
                            { title: 'Content Settings', initialOpen: false },
                            el(SelectControl, {
                                label: 'Font Family',
                                value: contentFontFamily,
                                options: [
                                    { label: 'Arial', value: 'Arial, sans-serif' },
                                    { label: 'Georgia', value: 'Georgia, serif' },
                                    { label: 'Tahoma', value: 'Tahoma, sans-serif' },
                                    { label: 'Verdana', value: 'Verdana, sans-serif' }
                                ],
                                onChange: (value) => setAttributes({ contentFontFamily: value })
                            }),
                            el('input', {
                                type: 'number',
                                value: contentFontSize,
                                onChange: (event) => setAttributes({ contentFontSize: parseInt(event.target.value, 10) || 16 }),
                                placeholder: 'Font Size'
                            })
                        )
                    ),
                    el('img', {
                        src: imageUrl,
                        alt: 'Sidebar Image',
                        style: { width: '100%', borderRadius: '8px', marginBottom: '15px' }
                    }),
                    el(
                        RichText,
                        {
                            tagName: 'h2',
                            value: heading,
                            onChange: (value) => setAttributes({ heading: value }),
                            placeholder: 'Enter heading...',
                            style: { fontWeight: 'bold', textAlign: headingAlignment, fontSize: headingFontSize + 'px', fontFamily: headingFontFamily }
                        }
                    ),
                    el(
                        RichText,
                        {
                            tagName: 'p',
                            value: content,
                            onChange: (value) => setAttributes({ content: value }),
                            placeholder: 'Enter content...',
                            style: { textAlign: contentAlignment, fontSize: contentFontSize + 'px', fontFamily: contentFontFamily }
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
                            }
                        ),
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
                const { heading, headingAlignment, headingFontSize, headingFontFamily, content, contentAlignment, contentFontSize, contentFontFamily, buttonText, buttonUrl, imageUrl } = attributes;

                return el(
                    'div',
                    { className: 'sidebar-widget', style: {
                        backgroundColor: '#121212', color: '#fff', padding: '25px',
                        borderRadius: '12px', textAlign: 'center', fontFamily: 'Arial, sans-serif', maxWidth: '300px', margin: '0 auto'
                    } },
                    el('img', {
                        src: imageUrl,
                        alt: 'Sidebar Image',
                        style: { width: '100%', borderRadius: '8px', marginBottom: '15px' }
                    }),
                    el(RichText.Content, {
                        tagName: 'h2',
                        value: heading,
                        style: { fontSize: headingFontSize + 'px', marginBottom: '15px', fontWeight: 'bold', color: '#BAFF96', textAlign: headingAlignment, fontFamily: headingFontFamily }
                    }),
                    el(RichText.Content, {
                        tagName: 'p',
                        value: content,
                        style: { marginBottom: '20px', fontSize: contentFontSize + 'px', lineHeight: '1.5', color: '#ddd', textAlign: contentAlignment, fontFamily: contentFontFamily }
                    }),
                    el('a', {
                        href: buttonUrl,
                        style: {
                            display: 'inline-block', backgroundColor: '#BAFF96', color: '#121212', padding: '12px 25px',
                            borderRadius: '6px', textDecoration: 'none', fontWeight: 'bold', fontSize: '14px', transition: 'background-color 0.3s ease'
                        }
                    }, buttonText)
                );
            }
        });
    })(window.wp.blocks, window.wp.editor, window.wp.element, window.wp.components);");

    register_block_type('custom/sidebar-design-block', [
        'editor_script' => 'sidebar-design-block'
    ]);
}
add_action('init', 'sidebar_design_block_enqueue_assets');
