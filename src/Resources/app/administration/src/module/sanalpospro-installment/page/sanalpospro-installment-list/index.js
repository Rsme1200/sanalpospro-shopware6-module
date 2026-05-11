const { Criteria } = Shopware.Data;

Shopware.Component.register('sanalpospro-installment-list', {
    template: `
<sw-page class="sanalpospro-installment-list">
    <template #smart-bar-header>
        <h2>{{ $tc('sanalpospro-installment.list.title') }}</h2>
    </template>

    <template #smart-bar-actions>
        <sw-button
            variant="primary"
            :routerLink="{ name: 'sanalpospro.installment.create' }"
        >
            {{ $tc('sanalpospro-installment.list.buttonCreate') }}
        </sw-button>
    </template>

    <template #content>
        <sw-entity-listing
            v-if="items"
            :items="items"
            :repository="repository"
            :columns="columns"
            :isLoading="isLoading"
            :showSelection="true"
            :allowDelete="true"
            :allowEdit="true"
            detailRoute="sanalpospro.installment.detail"
            @delete-item="onDeleteItem"
        >
            <template #column-isActive="{ item }">
                <sw-icon
                    v-if="item.isActive"
                    name="regular-checkmark-xs"
                    small
                    color="#37d046"
                />
                <sw-icon
                    v-else
                    name="regular-times-xs"
                    small
                    color="#de294c"
                />
            </template>
        </sw-entity-listing>

        <sw-empty-state
            v-if="!isLoading && (!items || items.length === 0)"
            :title="$tc('sanalpospro-installment.list.title')"
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
            return this.repositoryFactory.create('sanalpospro_installment');
        },

        columns() {
            return [
                {
                    property: 'bankName',
                    label: this.$tc('sanalpospro-installment.list.columnBankName'),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'cardType',
                    label: this.$tc('sanalpospro-installment.list.columnCardType'),
                    allowResize: true,
                },
                {
                    property: 'installmentCount',
                    label: this.$tc('sanalpospro-installment.list.columnInstallmentCount'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'interestRate',
                    label: this.$tc('sanalpospro-installment.list.columnInterestRate'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'isActive',
                    label: this.$tc('sanalpospro-installment.list.columnIsActive'),
                    allowResize: true,
                    align: 'center',
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

            this.repository.search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.items = result;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onDeleteItem(item) {
            this.repository.delete(item.id, Shopware.Context.api)
                .then(() => {
                    this.loadItems();
                });
        },
    },
});
