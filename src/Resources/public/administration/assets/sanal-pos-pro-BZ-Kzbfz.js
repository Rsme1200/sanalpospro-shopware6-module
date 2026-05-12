const{Criteria:i}=Shopware.Data;Shopware.Component.register("sanalpospro-installment-list",{template:`
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
    `,inject:["repositoryFactory"],data(){return{items:null,isLoading:!1}},computed:{repository(){return this.repositoryFactory.create("sanalpospro_installment")},columns(){return[{property:"bankName",label:this.$tc("sanalpospro-installment.list.columnBankName"),allowResize:!0,primary:!0},{property:"cardType",label:this.$tc("sanalpospro-installment.list.columnCardType"),allowResize:!0},{property:"installmentCount",label:this.$tc("sanalpospro-installment.list.columnInstallmentCount"),allowResize:!0,align:"right"},{property:"interestRate",label:this.$tc("sanalpospro-installment.list.columnInterestRate"),allowResize:!0,align:"right"},{property:"isActive",label:this.$tc("sanalpospro-installment.list.columnIsActive"),allowResize:!0,align:"center"}]}},created(){this.loadItems()},methods:{loadItems(){this.isLoading=!0;const e=new i;e.setPage(1),e.setLimit(25),this.repository.search(e,Shopware.Context.api).then(t=>{this.items=t}).finally(()=>{this.isLoading=!1})},onDeleteItem(e){this.repository.delete(e.id,Shopware.Context.api).then(()=>{this.loadItems()})}}});Shopware.Component.register("sanalpospro-installment-detail",{template:`
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
    `,inject:["repositoryFactory"],data(){return{item:null,isLoading:!1,isSaving:!1}},computed:{repository(){return this.repositoryFactory.create("sanalpospro_installment")}},created(){this.loadItem()},methods:{loadItem(){this.isLoading=!0,this.$route.params.id?this.repository.get(this.$route.params.id,Shopware.Context.api).then(e=>{this.item=e}).finally(()=>{this.isLoading=!1}):(this.item=this.repository.create(Shopware.Context.api),this.item.isActive=!0,this.item.interestRate=0,this.item.installmentCount=1,this.isLoading=!1)},onSave(){this.isSaving=!0,this.repository.save(this.item,Shopware.Context.api).then(()=>{this.isSaving=!1,Shopware.State.dispatch("notification/createNotification",{title:this.$tc("sanalpospro-installment.detail.title"),message:this.$tc("sanalpospro-installment.detail.messageSaveSuccess"),variant:"success"}),this.$router.push({name:"sanalpospro.installment.list"})}).catch(()=>{this.isSaving=!1,Shopware.State.dispatch("notification/createNotification",{title:this.$tc("sanalpospro-installment.detail.title"),message:this.$tc("sanalpospro-installment.detail.messageSaveError"),variant:"error"})})}}});const r={"sanalpospro-installment":{general:{title:"Ratenpläne",description:"Bank-Ratenpläne und Zinssätze verwalten"},list:{title:"Ratenpläne",columnBankName:"Bankname",columnCardType:"Kartentyp",columnInstallmentCount:"Ratenanzahl",columnInterestRate:"Zinssatz (%)",columnIsActive:"Aktiv",buttonCreate:"Ratenplan erstellen",deleteConfirmTitle:"Ratenplan löschen",deleteConfirmText:'Möchten Sie den Ratenplan für "{bankName}" wirklich löschen?'},detail:{title:"Ratenplan-Detail",titleNew:"Neuer Ratenplan",labelBankName:"Bankname",labelCardType:"Kartentyp",labelInstallmentCount:"Ratenanzahl",labelInterestRate:"Zinssatz (%)",labelIsActive:"Aktiv",placeholderBankName:"z.B. Garanti BBVA",placeholderCardType:"z.B. Visa, Mastercard",messageSaveSuccess:"Ratenplan erfolgreich gespeichert.",messageSaveError:"Ratenplan konnte nicht gespeichert werden."}}},p={"sanalpospro-installment":{general:{title:"Installment Plans",description:"Manage bank installment plans and interest rates"},list:{title:"Installment Plans",columnBankName:"Bank Name",columnCardType:"Card Type",columnInstallmentCount:"Installment Count",columnInterestRate:"Interest Rate (%)",columnIsActive:"Active",buttonCreate:"Create Installment Plan",deleteConfirmTitle:"Delete Installment Plan",deleteConfirmText:'Are you sure you want to delete the installment plan for "{bankName}"?'},detail:{title:"Installment Plan Detail",titleNew:"New Installment Plan",labelBankName:"Bank Name",labelCardType:"Card Type",labelInstallmentCount:"Installment Count",labelInterestRate:"Interest Rate (%)",labelIsActive:"Active",placeholderBankName:"e.g. Garanti BBVA",placeholderCardType:"e.g. Visa, Mastercard",messageSaveSuccess:"Installment plan saved successfully.",messageSaveError:"Could not save installment plan."}}};Shopware.Module.register("sanalpospro-installment",{type:"plugin",name:"sanalpospro-installment",title:"sanalpospro-installment.general.title",description:"sanalpospro-installment.general.description",color:"#1abc9c",icon:"regular-credit-card",snippets:{"de-DE":r,"en-GB":p},routes:{list:{component:"sanalpospro-installment-list",path:"list"},detail:{component:"sanalpospro-installment-detail",path:"detail/:id",meta:{parentPath:"sanalpospro.installment.list"}},create:{component:"sanalpospro-installment-detail",path:"create",meta:{parentPath:"sanalpospro.installment.list"}}},navigation:[{id:"sanalpospro-installment",label:"sanalpospro-installment.general.title",color:"#1abc9c",path:"sanalpospro.installment.list",icon:"regular-credit-card",parent:"sanalpospro-connect",position:10}]});const{Criteria:o}=Shopware.Data;Shopware.Component.register("sanalpospro-webhook-log-list",{template:`
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
    `,inject:["repositoryFactory"],data(){return{items:null,isLoading:!1}},computed:{repository(){return this.repositoryFactory.create("sanalpospro_webhook_log")},columns(){return[{property:"createdAt",label:this.$tc("sanalpospro-webhook-log.list.columnCreatedAt"),allowResize:!0,primary:!0,sortable:!0},{property:"orderTxId",label:this.$tc("sanalpospro-webhook-log.list.columnOrderTxId"),allowResize:!0},{property:"paythorTxId",label:this.$tc("sanalpospro-webhook-log.list.columnPaythorTxId"),allowResize:!0},{property:"action",label:this.$tc("sanalpospro-webhook-log.list.columnAction"),allowResize:!0},{property:"status",label:this.$tc("sanalpospro-webhook-log.list.columnStatus"),allowResize:!0},{property:"amount",label:this.$tc("sanalpospro-webhook-log.list.columnAmount"),allowResize:!0,align:"right"},{property:"currency",label:this.$tc("sanalpospro-webhook-log.list.columnCurrency"),allowResize:!0}]}},created(){this.loadItems()},methods:{loadItems(){this.isLoading=!0;const e=new o;e.setPage(1),e.setLimit(25),e.addSorting(o.sort("createdAt","DESC")),this.repository.search(e,Shopware.Context.api).then(t=>{this.items=t}).finally(()=>{this.isLoading=!1})},getStatusVariant(e){return{approved:"success",success:"success",failed:"danger",pending:"warning",refunded:"info"}[e]||"neutral"}}});const c={"sanalpospro-webhook-log":{general:{title:"Webhook-Protokolle",description:"SanalPosPro Webhook-Transaktionsprotokolle anzeigen"},list:{title:"Webhook-Protokolle",columnCreatedAt:"Datum",columnOrderTxId:"Bestelltransaktions-ID",columnPaythorTxId:"PayThor-Transaktions-ID",columnAction:"Aktion",columnStatus:"Status",columnAmount:"Betrag",columnCurrency:"Währung"}}},m={"sanalpospro-webhook-log":{general:{title:"Webhook Logs",description:"View SanalPosPro webhook transaction logs"},list:{title:"Webhook Logs",columnCreatedAt:"Date",columnOrderTxId:"Order Transaction ID",columnPaythorTxId:"PayThor Transaction ID",columnAction:"Action",columnStatus:"Status",columnAmount:"Amount",columnCurrency:"Currency"}}};Shopware.Module.register("sanalpospro-webhook-log",{type:"plugin",name:"sanalpospro-webhook-log",title:"sanalpospro-webhook-log.general.title",description:"sanalpospro-webhook-log.general.description",color:"#e74c3c",icon:"regular-list",snippets:{"de-DE":c,"en-GB":m},routes:{list:{component:"sanalpospro-webhook-log-list",path:"list"}},navigation:[{id:"sanalpospro-webhook-log",label:"sanalpospro-webhook-log.general.title",color:"#e74c3c",path:"sanalpospro.webhook.log.list",icon:"regular-list",parent:"sanalpospro-connect",position:20}]});Shopware.Component.register("sanalpospro-connect-index",{template:`
        <sw-page class="sanalpospro-connect-index">
            <template #smart-bar-header>
                <h2>SanalPos Pro Management</h2>
            </template>
            <template #content>
                <sw-card-view>
                    <!-- PayThor React App Container -->
                    <div id="root" data-platform="shopware" data-app-id="106" data-program-id="1" style="min-height: 800px; width: 100%;"></div>
                </sw-card-view>
            </template>
        </sw-page>
    `,mounted(){this.loadPayThorApp()},beforeDestroy(){this.cleanupPayThorApp()},methods:{loadPayThorApp(){this.cleanupPayThorApp(),window.xfvv="shopware-dummy-xfvv-token",window.target_url=window.location.origin.replace("/admin","")+"/sanalpospro/iapi/index",window.store_url=window.location.origin,window.app_id=106,window.platform="shopware",window.program_id=1,window.style_url="https://cdn.paythor.com/1/106/10.0.4/index.css",window.generalSettings={order_status:{default_value:"process",options:{process:"Processing"}},currency_convert:{default_value:"no",options:{yes:"Yes",no:"No"}},showInstallmentsTabs:{default_value:"no",options:{yes:"Yes",no:"No"}},paymentPageTheme:{default_value:"modern",options:{classic:"Classic",modern:"Modern"}}};try{const a="106",l="paythor-connect-app-id",n=["etc-token","etc-user-level","etc-is-impersonating","etc-original-admin-token","etc-impersonate-token"];localStorage.getItem(l)!==a&&(n.forEach(s=>localStorage.removeItem(s)),sessionStorage.clear(),localStorage.setItem(l,a))}catch{console.warn("LocalStorage access denied for Paythor")}const e=document.createElement("link");e.id="paythor-style",e.rel="stylesheet",e.href=window.style_url,document.head.appendChild(e);const t=document.createElement("script");t.id="paythor-script",t.type="module",t.src="https://cdn.paythor.com/1/106/10.0.4/index.js?v="+Date.now(),document.body.appendChild(t)},cleanupPayThorApp(){const e=document.getElementById("paythor-script");e&&e.remove();const t=document.getElementById("paythor-style");t&&t.remove();const a=document.getElementById("root");a&&(a.innerHTML="")}}});Shopware.Module.register("sanalpospro-connect",{type:"plugin",name:"sanalpospro-connect",title:"SanalPos Pro",description:"PayThor React CDN Application",color:"#1abc9c",icon:"regular-credit-card",routes:{index:{component:"sanalpospro-connect-index",path:"index"}},navigation:[{id:"sanalpospro-connect",label:"SanalPos Pro",color:"#1abc9c",icon:"regular-credit-card",parent:"sw-extension",position:10},{id:"sanalpospro-connect-index",label:"Account & Management",color:"#1abc9c",path:"sanalpospro.connect.index",icon:"regular-credit-card",parent:"sanalpospro-connect",position:10}]});
//# sourceMappingURL=sanal-pos-pro-BZ-Kzbfz.js.map
