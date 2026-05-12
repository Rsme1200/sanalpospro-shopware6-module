import ExamplePlugin from './example-plugin/example-plugin.plugin';
import SanalPosProIframePlugin from './sanalpospro-iframe/sanalpospro-iframe.plugin';

const PluginManager = window.PluginManager;

PluginManager.register('ExamplePlugin', ExamplePlugin, '[data-example-plugin]');
PluginManager.register('SanalPosProIframe', SanalPosProIframePlugin, '[data-sanalpospro-iframe]');
