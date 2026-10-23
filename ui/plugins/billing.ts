import BillingSettings from '../components/BillingSettings.vue';
import { useExtensionSlots } from '~/composables/useExtensionSlots';

export default defineNuxtPlugin(() => {
  useExtensionSlots().registerComponent('cloud.settings', BillingSettings);
});
