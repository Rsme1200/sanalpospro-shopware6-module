import Plugin from 'src/plugin-system/plugin.class';

export default class SanalPosProIframePlugin extends Plugin {
    init() {
        this.returnUrl = this.el.dataset.returnUrl;
        this.transactionId = this.el.dataset.transactionId;
        this._onMessage = this._onMessage.bind(this);
        window.addEventListener('message', this._onMessage);
    }

    _onMessage(event) {
        if (event.origin !== window.location.origin) {
            return;
        }

        if (!event.data || event.data.source !== 'paythor_sanalpospro') {
            return;
        }

        const { status } = event.data;

        if (status === 'success') {
            window.location.replace(this.returnUrl);
        } else {
            window.location.replace('/checkout/cart');
        }
    }
}
