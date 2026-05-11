import './page/sanalpospro-connect-index';

Shopware.Module.register('sanalpospro-connect', {
    type: 'plugin',
    name: 'sanalpospro-connect',
    title: 'SanalPos Pro',
    description: 'PayThor React CDN Application',
    color: '#1abc9c',
    icon: 'regular-credit-card',

    routes: {
        index: {
            component: 'sanalpospro-connect-index',
            path: 'index',
        },
    },

    navigation: [{
        id: 'sanalpospro-connect',
        label: 'SanalPos Pro',
        color: '#1abc9c',
        icon: 'regular-credit-card',
        parent: 'sw-extension',
        position: 10,
    }, {
        id: 'sanalpospro-connect-index',
        label: 'Account & Management',
        color: '#1abc9c',
        path: 'sanalpospro.connect.index',
        icon: 'regular-credit-card',
        parent: 'sanalpospro-connect', // Assign to the folder above
        position: 10,
    }],
});
