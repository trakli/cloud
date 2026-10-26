<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { DsButton, DsCard, DsChip, DsInput, DsModal, DsStatePanel } from '@whilesmart/design';
import type { Workspace } from '~/types/workspace';
import { extractApiErrors } from '~/utils/apiErrors';

interface Quote {
  quote_id: string;
  currency: string;
  interval: string;
  members: number;
  included_members: number;
  max_members: number;
  base_amount_cents: number;
  additional_member_amount_cents: number;
  amount_cents: number;
}
interface Status {
  active: boolean;
  status: string;
  member_count: number;
  capacity: number;
  quote: Quote | null;
  can_manage_billing: boolean;
  has_billing_account: boolean;
  has_subscription: boolean;
  pending_checkout: boolean;
}
const props = defineProps<{ workspace: Workspace }>();
const emit = defineEmits<{ changed: [] }>();
const api = useApi();
const route = useRoute();
const status = ref<Status | null>(null);
const quote = ref<Quote | null>(null);
const members = ref('');
const error = ref('');
const notice = ref(
  route.query.checkout === 'success'
    ? 'Checkout completed. Refresh the subscription if payment is still being confirmed.'
    : ''
);
const loading = ref(true);
const quoting = ref(false);
const busy = ref(false);
const confirming = ref(false);
const base = computed(() => `/cloud/workspaces/${props.workspace.slug}/billing`);
const canSubmit = computed(
  () => !!quote.value && !quoting.value && Number(members.value) === quote.value.members
);
let quoteRequest = 0;
function money(amount: number, currency: string) {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency
  }).format(amount / 100);
}
async function load() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api<{ data: Status }>(base.value);
    status.value = response.data;
    const initial = (await api<{ data: Quote }>('/cloud/workspace-pricing')).data;
    members.value = String(
      Math.max(initial.included_members, response.data.capacity, response.data.member_count)
    );
    await getQuote();
  } catch (cause) {
    error.value = extractApiErrors(cause);
  } finally {
    loading.value = false;
  }
}
async function getQuote() {
  const request = ++quoteRequest;
  quote.value = null;
  confirming.value = false;
  quoting.value = true;
  try {
    const response = await api<{ data: Quote }>('/cloud/workspace-pricing', {
      query: { members: members.value }
    });
    if (request === quoteRequest) {
      quote.value = response.data;
      error.value = '';
    }
  } catch (cause) {
    if (request === quoteRequest) error.value = extractApiErrors(cause);
  } finally {
    if (request === quoteRequest) quoting.value = false;
  }
}
async function refresh() {
  await load();
  emit('changed');
}
async function checkout() {
  if (!canSubmit.value || !quote.value) return;
  busy.value = true;
  error.value = '';
  try {
    const response = await api<{ data: { url?: string; pending?: boolean } }>(
      `${base.value}/checkout`,
      {
        method: 'POST',
        body: {
          members: quote.value.members,
          quote_id: quote.value.quote_id,
          confirmed: true
        }
      }
    );
    confirming.value = false;
    if (response.data.url) {
      await navigateTo(response.data.url, { external: true });
    } else {
      notice.value = 'Your payment is being confirmed. Refresh to check your member allowance.';
      await refresh();
    }
  } catch (cause) {
    error.value = extractApiErrors(cause);
    confirming.value = false;
  } finally {
    busy.value = false;
  }
}
async function cancelCheckout() {
  busy.value = true;
  error.value = '';
  try {
    await api(`${base.value}/cancel-checkout`, { method: 'POST' });
    await load();
  } catch (cause) {
    error.value = extractApiErrors(cause);
  } finally {
    busy.value = false;
  }
}
async function portal() {
  busy.value = true;
  error.value = '';
  try {
    const response = await api<{ data: { url: string } }>(`${base.value}/portal`, {
      method: 'POST'
    });
    await navigateTo(response.data.url, { external: true });
  } catch (cause) {
    error.value = extractApiErrors(cause);
  } finally {
    busy.value = false;
  }
}
watch(members, getQuote);
onMounted(load);
</script>

<template>
  <DsCard class="workspace-billing">
    <DsStatePanel v-if="loading && !status" state="loading" title="Loading group subscription" />
    <template v-if="status">
      <div class="workspace-billing__heading">
        <h2>Group budgeting</h2>
        <DsChip :variant="status.active ? 'success' : 'warning'">{{
          status.active ? 'Active' : 'Subscription needed'
        }}</DsChip>
      </div>
      <p v-if="status.active">
        {{ status.member_count }} of {{ status.capacity }} members, including the owner.
      </p>
      <p v-else>
        Activate this space to budget together. Bank connections are included for every member.
      </p>
      <p v-if="status.quote">
        Current subscription:
        {{ money(status.quote.amount_cents, status.quote.currency) }} per month.
      </p>
      <template v-if="status.can_manage_billing">
        <p v-if="quote">
          {{ money(quote.base_amount_cents, quote.currency) }} per month includes
          {{ quote.included_members }} people. Each additional person is
          {{ money(quote.additional_member_amount_cents, quote.currency) }} per month.
        </p>
        <DsInput
          v-model="members"
          label="Member allowance, including you"
          type="number"
          :disabled="busy"
        />
        <strong v-if="quote" aria-live="polite"
          >{{ money(quote.amount_cents, quote.currency) }} per month for
          {{ quote.members }} people</strong
        >
        <p>Pending invitations are free. People can join once there is room in your allowance.</p>
        <div class="workspace-billing__actions">
          <DsButton
            :disabled="!canSubmit || busy || (status.active && status.capacity === quote?.members)"
            @click="confirming = true"
            >{{
              status.has_subscription ? 'Review subscription' : 'Continue to checkout'
            }}</DsButton
          >
          <DsButton
            v-if="status.pending_checkout"
            variant="secondary"
            :loading="busy"
            @click="cancelCheckout"
            >Cancel pending checkout</DsButton
          >
          <DsButton
            v-if="status.has_billing_account"
            variant="secondary"
            :loading="busy"
            @click="portal"
            >Manage payment and cancellation</DsButton
          >
        </div>
      </template>
      <DsButton variant="ghost" :loading="loading" @click="refresh">Refresh subscription</DsButton>
    </template>
    <p v-if="notice" role="status">{{ notice }}</p>
    <p v-if="error" role="alert">{{ error }}</p>
    <DsButton v-if="!status && !loading" variant="secondary" @click="load">Try again</DsButton>
    <DsModal v-model="confirming" title="Confirm group subscription">
      <template v-if="quote">
        <p>
          {{ money(quote.amount_cents, quote.currency) }} per month for {{ quote.members }} people,
          including you. Bank connections are included.
        </p>
        <p v-if="status?.has_subscription">
          Changes take effect after payment is confirmed. Any prorated charge or credit applies
          immediately.
        </p>
        <p v-else>Your shared space becomes active after checkout is confirmed.</p>
        <div class="workspace-billing__actions">
          <DsButton variant="ghost" :disabled="busy" @click="confirming = false">Cancel</DsButton>
          <DsButton :loading="busy" @click="checkout">{{
            status?.has_subscription ? 'Confirm subscription' : 'Continue to checkout'
          }}</DsButton>
        </div>
      </template>
    </DsModal>
  </DsCard>
</template>

<style scoped>
.workspace-billing {
  display: grid;
  gap: var(--ds-space-3);
}
.workspace-billing h2,
.workspace-billing p {
  margin: 0;
}
.workspace-billing p {
  color: var(--ds-text-secondary);
}
.workspace-billing__heading,
.workspace-billing__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--ds-space-3);
}
@media (max-width: 600px) {
  .workspace-billing__actions {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
