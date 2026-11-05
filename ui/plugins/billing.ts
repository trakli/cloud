import BillingSettings from '../components/BillingSettings.vue';
import WorkspaceBilling from '../components/WorkspaceBilling.vue';
import { useExtensionSlots } from '~/composables/useExtensionSlots';

export default defineNuxtPlugin(() => {
  useExtensionSlots().registerComponent('cloud.settings', BillingSettings);
  useExtensionSlots().registerComponent('cloud.workspace.billing', WorkspaceBilling);
});
