import Plugin from 'src/plugin-system/plugin.class';

/**
 * SanalPosPro Installment Tabs Plugin
 *
 * Handles card-family tab switching on the product detail page installment table.
 * Mirrors the JavaScript from the Magento (installment-modern.js) and
 * OpenCart (modern.twig inline script) modules.
 */
export default class SanalPosProInstallmentPlugin extends Plugin {
    init() {
        this._tabItems = this.el.querySelectorAll('[data-sppro-card-target]');
        this._tabPanes = this.el.querySelectorAll('[data-sppro-card-content]');

        if (this._tabItems.length === 0) {
            return;
        }

        this._registerEvents();
    }

    _registerEvents() {
        this._tabItems.forEach((item) => {
            item.addEventListener('click', this._onTabClick.bind(this, item));
        });
    }

    _onTabClick(clickedItem) {
        const targetCard = clickedItem.getAttribute('data-sppro-card-target');

        // Deactivate all tabs
        this._tabItems.forEach((tab) => tab.classList.remove('active'));
        this._tabPanes.forEach((pane) => pane.classList.remove('active'));

        // Activate clicked tab
        clickedItem.classList.add('active');

        const targetPane = this.el.querySelector(
            `[data-sppro-card-content="${targetCard}"]`
        );
        if (targetPane) {
            targetPane.classList.add('active');
        }
    }
}
