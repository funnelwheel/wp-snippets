<?php
/**
 * Plugin Name: Custom Tabs Gutenberg Block
 * Description: Adds a customizable tabbed interface block to the Gutenberg editor.
 * Version: 1.0
 * Author: Kishores
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

// Register block assets
function custom_tabs_block_enqueue_assets() {
    register_block_type('custom/tabs', array(
        'editor_script' => 'custom-tabs-block',
        'editor_style'  => 'custom-tabs-block-editor',
        'style'         => 'custom-tabs-block-frontend',
    ));
}
add_action('init', 'custom_tabs_block_enqueue_assets');

// JavaScript for the Gutenberg Block
add_action('enqueue_block_editor_assets', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.wp && wp.blocks && wp.element && wp.editor) {
            const { registerBlockType } = wp.blocks;
            const { createElement: el, Fragment } = wp.element;
            const { RichText, InspectorControls } = wp.editor;
            const { Button, PanelBody } = wp.components;

            registerBlockType('custom/tabs', {
                title: 'Custom Tabs',
                icon: 'screenoptions',
                category: 'layout',
                attributes: {
                    tabs: {
                        type: 'array',
                        default: [
                            { title: 'Tab 1', content: 'Content 1' }
                        ]
                    }
                },

                edit: function (props) {
                    function updateTabContent(index, key, value) {
                        const tabs = [...props.attributes.tabs];
                        tabs[index][key] = value;
                        props.setAttributes({ tabs });
                    }

                    function addTab() {
                        const tabs = [...props.attributes.tabs, { title: 'New Tab', content: 'New Content' }];
                        props.setAttributes({ tabs });
                    }

                    function removeTab(index) {
                        const tabs = props.attributes.tabs.filter((_, i) => i !== index);
                        props.setAttributes({ tabs });
                    }

                    return el(Fragment, {},
                        el(InspectorControls, {},
                            el(PanelBody, { title: 'Manage Tabs', initialOpen: true },
                                el(Button, { isPrimary: true, onClick: addTab }, 'Add Tab')
                            )
                        ),
                        el('div', { className: 'custom-tabs-editor' },
                            props.attributes.tabs.map((tab, index) =>
                                el('div', { className: 'custom-tab-editor', key: index },
                                    el(RichText, {
                                        tagName: 'h4',
                                        className: 'custom-tab-title',
                                        value: tab.title,
                                        onChange: (value) => updateTabContent(index, 'title', value),
                                        placeholder: 'Tab Title'
                                    }),
                                    el(RichText, {
                                        tagName: 'p',
                                        className: 'custom-tab-content',
                                        value: tab.content,
                                        onChange: (value) => updateTabContent(index, 'content', value),
                                        placeholder: 'Tab Content'
                                    }),
                                    el(Button, { isDestructive: true, onClick: () => removeTab(index) }, 'Remove Tab')
                                )
                            )
                        )
                    );
                },

                save: function (props) {
                    return el('div', { className: 'custom-tabs' },
                        el('ul', { className: 'custom-tabs-nav' },
                            props.attributes.tabs.map((tab, index) =>
                                el('li', { className: 'custom-tab', 'data-index': index, key: index }, tab.title)
                            )
                        ),
                        el('div', { className: 'custom-tabs-content' },
                            props.attributes.tabs.map((tab, index) =>
                                el('div', { className: 'custom-tab-content', 'data-index': index, key: index }, tab.content)
                            )
                        )
                    );
                }
            });
        }
    });
    </script>
    <style>
        .custom-tabs-editor .custom-tab-editor {
            margin-bottom: 20px;
        }
        .custom-tabs-editor .custom-tab-title {
            font-weight: bold;
        }
        .custom-tabs-editor .custom-tab-content {
            color: #555;
        }
    </style>
    <?php
});

// Frontend styles and scripts
add_action('wp_enqueue_scripts', function() {
    ?>
    <style>
        .custom-tabs {
            background: #e2ffa4;
            padding: 20px;
            border-radius: 10px;
        }
        .custom-tabs-nav {
            display: flex;
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #ccc;
        }
        .custom-tab {
            margin-right: 20px;
            padding: 10px 15px;
            cursor: pointer;
            background: #fff;
            border-radius: 5px 5px 0 0;
            border: 1px solid #ccc;
            border-bottom: none;
        }
        .custom-tab.active {
            background: #000;
            color: #fff;
        }
        .custom-tabs-content > div {
            display: none;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 0 5px 5px 5px;
        }
        .custom-tabs-content > div.active {
            display: block;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.custom-tab');
            const contents = document.querySelectorAll('.custom-tabs-content > div');

            if (tabs.length > 0) {
                tabs[0].classList.add('active');
                contents[0].classList.add('active');

                tabs.forEach((tab, index) => {
                    tab.addEventListener('click', () => {
                        tabs.forEach(t => t.classList.remove('active'));
                        contents.forEach(c => c.classList.remove('active'));
                        tab.classList.add('active');
                        contents[index].classList.add('active');
                    });
                });
            }
        });
    </script>
    <?php
});
