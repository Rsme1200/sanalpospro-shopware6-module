const { Criteria } = Shopware.Data;

Shopware.Component.register('sanalpospro-webhook-log-list', {
    template: `
<sw-page class="sanalpospro-webhook-log-list">
    <template #smart-bar-header>
        <h2>{{ $tc('sanalpospro-webhook-log.list.title') }}</h2>
    </template>

    <template #content>
        <sw-entity-listing
            v-if="items"
            :items="items"
            :repository="repository"
            :columns="columns"
            :isLoading="isLoading"
            :showSelection="false"
            :allowDelete="false"
            :allowEdit="false"
            :allowInlineEdit="false"
        >
            <template #column-status="{ item }">
                <sw-label
                    :variant="getStatusVariant(item.status)"
                    appearance="pill"
                    size="small"
                >
                    {{ item.status }}
                </sw-label>
            </template>
        </sw-entity-listing>

        <sw-empty-state
            v-if="!isLoading && (!items || items.length === 0)"
            :title="$tc('sanalpospro-webhook-log.list.title')"
        />
    </template>
</sw-page>
    `,

    inject: ['repositoryFactory'],

    data() {
        return {
            items: null,
            isLoading: false,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('sanalpospro_webhook_log');
        },

        columns() {
            return [
                {
                    property: 'createdAt',
                    label: this.$tc('sanalpospro-webhook-log.list.columnCreatedAt'),
                    allowResize: true,
                    primary: true,
                    sortable: true,
                },
                {
                    property: 'orderTxId',
                    label: this.$tc('sanalpospro-webhook-log.list.columnOrderTxId'),
                    allowResize: true,
                },
                {
                    property: 'paythorTxId',
                    label: this.$tc('sanalpospro-webhook-log.list.columnPaythorTxId'),
                    allowResize: true,
                },
                {
                    property: 'action',
                    label: this.$tc('sanalpospro-webhook-log.list.columnAction'),
                    allowResize: true,
                },
                {
                    property: 'status',
                    label: this.$tc('sanalpospro-webhook-log.list.columnStatus'),
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: this.$tc('sanalpospro-webhook-log.list.columnAmount'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'currency',
                    label: this.$tc('sanalpospro-webhook-log.list.columnCurrency'),
                    allowResize: true,
                },
            ];
        },
    },

    created() {
        this.loadItems();
    },

    methods: {
        loadItems() {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.setPage(1);
            criteria.setLimit(25);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            this.repository.search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.items = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getStatusVariant(status) {
            const map = {
                'approved': 'success',
                'success': 'success',
                'failed': 'danger',
                'pending': 'warning',
                'refunded': 'info',
            };

            return map[status] || 'neutral';
        },
    },
});
