import './page/sanalpospro-webhook-log-list';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('sanalpospro-webhook-log', {
    type: 'plugin',
    name: 'sanalpospro-webhook-log',
    title: 'sanalpospro-webhook-log.general.title',
    description: 'sanalpospro-webhook-log.general.description',
    color: '#e74c3c',
    icon: 'regular-list',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'sanalpospro-webhook-log-list',
            path: 'list',
        },
    },
});
