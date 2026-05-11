import './page/sanalpospro-installment-list';
import './page/sanalpospro-installment-detail';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('sanalpospro-installment', {
    type: 'plugin',
    name: 'sanalpospro-installment',
    title: 'sanalpospro-installment.general.title',
    description: 'sanalpospro-installment.general.description',
    color: '#1abc9c',
    icon: 'regular-credit-card',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'sanalpospro-installment-list',
            path: 'list',
        },
        detail: {
            component: 'sanalpospro-installment-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sanalpospro.installment.list',
            },
        },
        create: {
            component: 'sanalpospro-installment-detail',
            path: 'create',
            meta: {
                parentPath: 'sanalpospro.installment.list',
            },
        },
    },

    navigation: [{
        id: 'sanalpospro-installment',
        label: 'sanalpospro-installment.general.title',
        color: '#1abc9c',
        path: 'sanalpospro.installment.list',
        icon: 'regular-credit-card',
        parent: 'sanalpospro-connect',
        position: 10,
    }],
});
