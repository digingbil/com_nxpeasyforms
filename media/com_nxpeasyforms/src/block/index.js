(function () {
    if (!window.wp || !window.wp.blocks) {
        return;
    }

    const { registerBlockType } = window.wp.blocks;
    const { __ } = window.wp.i18n || ((text) => text);
    const components = window.wp.components || {};
    const element = window.wp.element || {};
    const apiFetch = window.wp.apiFetch;
    const blockEditor = window.wp.blockEditor || window.wp.editor || {};
    const useBlockProps = blockEditor.useBlockProps;
    const SelectControl = components.SelectControl;
    const Notice = components.Notice;
    const Spinner = components.Spinner;
    const Fragment = element.Fragment || 'div';
    const createElement = element.createElement || window.React?.createElement;
    const useState = element.useState;
    const useEffect = element.useEffect;

    if (
        !registerBlockType ||
        !apiFetch ||
        !SelectControl ||
        !Notice ||
        !createElement ||
        !useState ||
        !useEffect ||
        !useBlockProps
    ) {
        return;
    }

    const BLOCK_NAME = 'nxp/easy-form';

    registerBlockType(BLOCK_NAME, {
        title: __('NXP Easy Form', 'nxp-easy-forms'),
        description: __(
            'Embed a form created with NXP Easy Forms.',
            'nxp-easy-forms'
        ),
        icon: 'feedback',
        category: 'widgets',
        keywords: [
            __('form', 'nxp-easy-forms'),
            __('contact', 'nxp-easy-forms'),
            __('easy', 'nxp-easy-forms'),
        ],
        supports: {
            align: ['wide', 'full'],
        },
        attributes: {
            formId: {
                type: 'number',
                default: 0,
            },
        },
        edit({ attributes, setAttributes }) {
            const { formId } = attributes;
            const [forms, setForms] = useState([]);
            const [loading, setLoading] = useState(true);
            const [error, setError] = useState(null);

            useEffect(() => {
                setLoading(true);
                setError(null);

                apiFetch({ path: '/nxp-easy-forms/v1/forms' })
                    .then((response) => {
                        if (response && Array.isArray(response.forms)) {
                            const formOptions = response.forms.map((form) => ({
                                value: form.id,
                                label:
                                    form.title ||
                                    __('Untitled form', 'nxp-easy-forms'),
                            }));
                            setForms(formOptions);
                        } else {
                            setForms([]);
                        }
                        setLoading(false);
                    })
                    .catch((err) => {
                        // Non-fatal: log a warning for developers
                        console.warn('Failed to fetch forms:', err);
                        setError(
                            err.message ||
                                __('Failed to load forms', 'nxp-easy-forms')
                        );
                        setLoading(false);
                    });
            }, []);

            const options = [
                {
                    value: 0,
                    label: __('Select a form…', 'nxp-easy-forms'),
                },
                ...forms,
            ];

            const blockProps = useBlockProps({
                className: 'nxp-easy-block-editor',
            });

            return createElement(
                Fragment,
                null,
                createElement(
                    'div',
                    blockProps,
                    loading &&
                        createElement(
                            'div',
                            { style: { padding: '20px', textAlign: 'center' } },
                            createElement(Spinner),
                            createElement(
                                'p',
                                null,
                                __('Loading forms...', 'nxp-easy-forms')
                            )
                        ),
                    !loading &&
                        error &&
                        createElement(
                            Notice,
                            { status: 'error', isDismissible: false },
                            error
                        ),
                    !loading &&
                        !error &&
                        createElement(SelectControl, {
                            label: __('Form', 'nxp-easy-forms'),
                            value: formId || 0,
                            options,
                            onChange: (value) =>
                                setAttributes({ formId: Number(value) }),
                        }),
                    !loading &&
                        !error &&
                        !forms.length &&
                        createElement(
                            Notice,
                            { status: 'info', isDismissible: false },
                            __(
                                'Create a form with NXP Easy Forms to embed it here.',
                                'nxp-easy-forms'
                            )
                        )
                )
            );
        },
        save() {
            return null;
        },
    });
})();
