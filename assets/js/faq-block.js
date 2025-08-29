/**
 * FAQ Block for Gutenberg Editor
 */

(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, TextControl, TextareaControl, Button } = wp.components;
    const { Fragment, useState } = wp.element;

    registerBlockType('fp-dms/faq', {
        title: 'FAQ (FP Digital Marketing)',
        description: 'Add FAQ sections with structured data support',
        category: 'widgets',
        icon: 'editor-help',
        attributes: {
            faqs: {
                type: 'array',
                default: [
                    {
                        question: '',
                        answer: ''
                    }
                ]
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { faqs } = attributes;

            const updateFAQ = (index, field, value) => {
                const newFaqs = [...faqs];
                newFaqs[index][field] = value;
                setAttributes({ faqs: newFaqs });
            };

            const addFAQ = () => {
                setAttributes({
                    faqs: [...faqs, { question: '', answer: '' }]
                });
            };

            const removeFAQ = (index) => {
                const newFaqs = faqs.filter((_, i) => i !== index);
                setAttributes({ faqs: newFaqs });
            };

            return (
                Fragment(
                    null,
                    wp.element.createElement(
                        'div',
                        { className: 'fp-dms-faq-block-editor' },
                        wp.element.createElement('h3', null, 'FAQ Block'),
                        faqs.map((faq, index) =>
                            wp.element.createElement(
                                'div',
                                { key: index, className: 'faq-item-editor' },
                                wp.element.createElement(TextControl, {
                                    label: `Question ${index + 1}`,
                                    value: faq.question,
                                    onChange: (value) => updateFAQ(index, 'question', value),
                                    placeholder: 'Enter your question...'
                                }),
                                wp.element.createElement(TextareaControl, {
                                    label: `Answer ${index + 1}`,
                                    value: faq.answer,
                                    onChange: (value) => updateFAQ(index, 'answer', value),
                                    placeholder: 'Enter your answer...',
                                    rows: 3
                                }),
                                faqs.length > 1 && wp.element.createElement(Button, {
                                    isSecondary: true,
                                    isDestructive: true,
                                    onClick: () => removeFAQ(index),
                                    style: { marginTop: '10px' }
                                }, 'Remove FAQ')
                            )
                        ),
                        wp.element.createElement(Button, {
                            isPrimary: true,
                            onClick: addFAQ,
                            style: { marginTop: '15px' }
                        }, 'Add Another FAQ')
                    )
                )
            );
        },

        save: function(props) {
            const { attributes } = props;
            const { faqs } = attributes;

            if (!faqs || faqs.length === 0) {
                return null;
            }

            return wp.element.createElement(
                'div',
                { className: 'fp-dms-faq-block' },
                faqs.map((faq, index) => {
                    if (!faq.question || !faq.answer) {
                        return null;
                    }
                    return wp.element.createElement(
                        'details',
                        { key: index, className: 'faq-item' },
                        wp.element.createElement(
                            'summary',
                            { className: 'faq-question' },
                            faq.question
                        ),
                        wp.element.createElement(
                            'div',
                            { className: 'faq-answer' },
                            faq.answer
                        )
                    );
                })
            );
        }
    });
})();