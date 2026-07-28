window.dispatchEvent(
    new CustomEvent('lineweb:extension-ready', {
        detail: { extension: 'example-polls' },
    }),
);
