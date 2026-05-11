Shopware.Component.register('sanalpospro-installment-detail', {
    template: `
<sw-page class="sanalpospro-installment-detail">
    <template #smart-bar-header>
        <h2 v-if="item && item.id">{{ $tc('sanalpospro-installment.detail.title') }}</h2>
        <h2 v-else>{{ $tc('sanalpospro-installment.detail.titleNew') }}</h2>
    </template>

    <template #smart-bar-actions>
        <sw-button
            variant="primary"
            :isLoading="isSaving"
            @click="onSave"
        >
            {{ $tc('global.default.save') }}
        </sw-button>
    </template>

    <template #content>
        <sw-card-view v-if="item">
            <sw-card :isLoading="isLoading">
                <sw-text-field
                    v-model="item.bankName"
                    :label="$tc('sanalpospro-installment.detail.labelBankName')"
                    :placeholder="$tc('sanalpospro-installment.detail.placeholderBankName')"
                    required
                />

                <sw-text-field
                    v-model="item.cardType"
                    :label="$tc('sanalpospro-installment.detail.labelCardType')"
                    :placeholder="$tc('sanalpospro-installment.detail.placeholderCardType')"
                />

                <sw-number-field
                    v-model="item.installmentCount"
                    :label="$tc('sanalpospro-installment.detail.labelInstallmentCount')"
                    :min="1"
                    :step="1"
                    required
                    numberType="int"
                />

                <sw-number-field
                    v-model="item.interestRate"
                    :label="$tc('sanalpospro-installment.detail.labelInterestRate')"
                    :min="0"
                    :step="0.01"
                    :digits="2"
                    numberType="float"
                />

                <sw-switch-field
                    v-model="item.isActive"
                    :label="$tc('sanalpospro-installment.detail.labelIsActive')"
                />
            </sw-card>
        </sw-card-view>
    </template>
</sw-page>
    `,

    inject: ['repositoryFactory'],

    data() {
        return {
            item: null,
            isLoading: false,
            isSaving: false,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('sanalpospro_installment');
        },
    },

    created() {
        this.loadItem();
    },

    methods: {
        loadItem() {
            this.isLoading = true;

            if (this.$route.params.id) {
                this.repository.get(this.$route.params.id, Shopware.Context.api)
                    .then((entity) => {
                        this.item = entity;
                    })
                    .finally(() => {
                        this.isLoading = false;
                    });
            } else {
                this.item = this.repository.create(Shopware.Context.api);
                this.item.isActive = true;
                this.item.interestRate = 0;
                this.item.installmentCount = 1;
                this.isLoading = false;
            }
        },

        onSave() {
            this.isSaving = true;

            this.repository.save(this.item, Shopware.Context.api)
                .then(() => {
                    this.isSaving = false;

                    Shopware.State.dispatch('notification/createNotification', {
                        title: this.$tc('sanalpospro-installment.detail.title'),
                        message: this.$tc('sanalpospro-installment.detail.messageSaveSuccess'),
                        variant: 'success',
                    });

                    this.$router.push({ name: 'sanalpospro.installment.list' });
                })
                .catch(() => {
                    this.isSaving = false;

                    Shopware.State.dispatch('notification/createNotification', {
                        title: this.$tc('sanalpospro-installment.detail.title'),
                        message: this.$tc('sanalpospro-installment.detail.messageSaveError'),
                        variant: 'error',
                    });
                });
        },
    },
});
