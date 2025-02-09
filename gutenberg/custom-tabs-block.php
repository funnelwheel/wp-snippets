<?php
/**
 * Plugin Name: Custom Tabs Gutenberg Block
 * Description: Adds a customizable tabbed interface block to the Gutenberg editor.
 * Version: 1.0.1
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
        if (window.wp && wp.blocks && wp.element && wp.blockEditor) {
            const { registerBlockType } = wp.blocks;
            const { createElement: el, Fragment } = wp.element;
            const { RichText, InspectorControls } = wp.blockEditor;
            const { Button, PanelBody, SelectControl } = wp.components;

            registerBlockType('custom/tabs', {
                title: 'Custom Tabs',
                icon: 'screenoptions',
                category: 'layout',
                attributes: {
                    tabs: {
                        type: 'array',
                        default: [{ title: 'Tab 1', content: 'Content 1' }]
                    },
                    layoutStyle: {
                        type: 'string',
                        default: 'top'
                    }
                },

                edit: function (props) {
                    const { attributes: { tabs, layoutStyle }, setAttributes } = props;

                    const updateTabContent = (index, key, value) => {
                        const updatedTabs = [...tabs];
                        updatedTabs[index][key] = value;
                        setAttributes({ tabs: updatedTabs });
                    };

                    const addTab = () => setAttributes({ tabs: [...tabs, { title: 'New Tab', content: 'New Content' }] });
                    const removeTab = (index) => setAttributes({ tabs: tabs.filter((_, i) => i !== index) });

                    return el(Fragment, {},
                        el(InspectorControls, {},
                            el(PanelBody, { title: 'Manage Tabs', initialOpen: true },
                                el(Button, { isPrimary: true, onClick: addTab }, 'Add Tab'),
                                el(SelectControl, {
                                    label: 'Tab Layout',
                                    value: layoutStyle,
                                    options: [
                                        { label: 'Tabs on Top', value: 'top' },
                                        { label: 'Tabs on Left', value: 'left' }
                                    ],
                                    onChange: (value) => setAttributes({ layoutStyle: value })
                                })
                            )
                        ),
                        el('div', { className: 'custom-tabs-editor' },
                            tabs.map((tab, index) =>
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
                    const { tabs, layoutStyle } = props.attributes;
                    return el('div', { className: `custom-tabs custom-tabs-${layoutStyle}` },
                        el('ul', { className: 'custom-tabs-nav' },
                            tabs.map((tab, index) =>
                                el('li', { className: 'custom-tab', 'data-index': index, key: index }, tab.title)
                            )
                        ),
                        el('div', { className: 'custom-tabs-content' },
                            tabs.map((tab, index) =>
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
        .custom-tabs-editor .custom-tab-editor { margin-bottom: 20px; }
        .custom-tabs-editor .custom-tab-title { font-weight: bold; }
        .custom-tabs-editor .custom-tab-content { color: #555; }
    </style>
    <?php
});

// Frontend styles and scripts
add_action('wp_enqueue_scripts', function() {
    ?>
    <style>
        .custom-tabs-top {
            background: #e2ffa4;
        }
        .custom-tabs {
            padding: 20px;
            border-radius: 10px;
        }
        .custom-tabs-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .custom-tab {
            cursor: pointer;
            background: #fff;
            border: 1px solid #ccc;
            padding: 10px 15px;
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
        }
        .custom-tabs-content > div.active {
            display: block;
        }

        .custom-tabs-top .custom-tabs-nav {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #ccc;
        }
        .custom-tabs-top .custom-tab {
            margin-right: 20px;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
        }
        .custom-tabs-top .custom-tabs-content > div {
            border-radius: 0 5px 5px 5px;
        }

        .custom-tabs-left {
            display: flex;
            gap: 20px;
            background: #e2ffa4;
        }
        .custom-tabs-left .custom-tabs-nav {
            background: #f0f0f0;
            border-right: 1px solid #ccc;
            width: max-content;
            min-width: 150px;
        }
        .custom-tabs-left .custom-tab {
            border-bottom: 1px solid #ccc;
            text-align: left;
            white-space: nowrap;
        }
        .custom-tabs-left .custom-tabs-content {
            flex-grow: 1;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.custom-tabs').forEach(container => {
                const tabs = container.querySelectorAll('.custom-tab');
                const contents = container.querySelectorAll('.custom-tabs-content > div');

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
        });
    </script>
    <?php
});
