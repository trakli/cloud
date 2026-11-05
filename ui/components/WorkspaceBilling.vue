<template>
  <div class="space-billing">
    <BillingNotice
      v-if="notice"
      v-bind="notice"
      :loading="loading"
      @refresh="refresh"
      @dismiss="dismiss"
    />

    <DsStatePanel
      v-if="loading && !status"
      state="loading"
      :title="t('Loading the space subscription')"
    />

    <DsStatePanel
      v-else-if="loadError && !status"
      state="error"
      :title="t('Could not load the space subscription')"
      :description="loadError"
      :action-label="t('Try again')"
      @action="load"
    />

    <DsCard v-else-if="status" padding="md" class="space-billing__card">
      <div class="space-billing__head">
        <h3>{{ t('Shared space plan') }}</h3>
        <DsChip size="sm" :variant="status.active ? 'success' : 'warning'">
          {{ status.active ? t('Active') : t('Not active') }}
        </DsChip>
      </div>
      <p class="space-billing__muted">
        {{
          status.active
            ? t('{count} of {capacity} places used, including the owner.', {
                count: status.member_count,
                capacity: status.capacity
              })
            : t(
                'Activate this space to budget together. Bank connections are included for everyone.'
              )
        }}
      </p>
      <p v-if="status.quote" class="space-billing__muted">
        {{
          t('Current plan: {price} per month.', {
            price: money(status.quote.amount_cents, status.quote.currency)
          })
        }}
      </p>

      <template v-if="status.can_manage_billing">
        <div class="space-billing__checkout">
          <DsInput
            v-model="members"
            :label="t('People, including you')"
            type="number"
            :disabled="!!busy"
          />
          <div class="space-billing__price" aria-live="polite">
            <strong v-if="quote">
              {{
                t('{price} per month', {
                  price: money(quote.amount_cents, quote.currency)
                })
              }}
            </strong>
            <span v-if="quote" class="space-billing__muted">
              {{
                t('{base} covers {included} people, then {extra} per extra person.', {
                  base: money(quote.base_amount_cents, quote.currency),
                  included: quote.included_members,
                  extra: money(quote.additional_member_amount_cents, quote.currency)
                })
              }}
            </span>
          </div>
        </div>
        <p class="space-billing__muted">
          {{ t('Invitations are free. People join once there is a place for them.') }}
        </p>
        <div class="space-billing__actions">
          <DsButton :disabled="!canReview" @click="confirming = true">
            {{ status.has_subscription ? t('Review change') : t('Continue to checkout') }}
          </DsButton>
          <DsButton
            v-if="status.pending_checkout"
            variant="secondary"
            :loading="busy === 'cancel'"
            :disabled="!!busy"
            @click="cancelCheckout"
          >
            {{ t('Cancel pending checkout') }}
          </DsButton>
          <DsButton
            v-if="status.has_billing_account"
            variant="secondary"
            :loading="busy === 'portal'"
            :disabled="!!busy"
            @click="portal"
          >
            <template #icon><IconExternal /></template>
            {{ t('Manage billing') }}
          </DsButton>
        </div>
      </template>
      <p v-else class="space-billing__muted">
        {{ t('Only the owner can change the plan for this space.') }}
      </p>
    </DsCard>

    <DsModal v-model="confirming" :title="t('Confirm the space plan')">
      <template v-if="quote">
        <p>
          {{
            t('{price} per month for {count} people, including you.', {
              price: money(quote.amount_cents, quote.currency),
              count: quote.members
            })
          }}
        </p>
        <p class="space-billing__muted">
          {{
            status?.has_subscription
              ? t(
                  'The change applies once payment is confirmed. Any prorated charge or credit applies straight away.'
                )
              : t('The space becomes active once checkout is confirmed.')
          }}
        </p>
        <div class="space-billing__actions">
          <DsButton variant="ghost" :disabled="!!busy" @click="confirming = false">
            {{ t('Cancel') }}
          </DsButton>
          <DsButton :loading="busy === 'checkout'" @click="checkout">
            {{ status?.has_subscription ? t('Confirm change') : t('Continue to checkout') }}
          </DsButton>
        </div>
      </template>
    </DsModal>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { DsButton, DsCard, DsChip, DsInput, DsModal, DsStatePanel } from '@whilesmart/design';
import IconExternal from '~icons/solar/arrow-right-up-linear';
import type { Workspace } from '~/types/workspace';
import { extractApiErrors } from '~/utils/apiErrors';
import { checkoutNotice, formatCents } from '../utils/billing';
import { useBillingRedirect } from '../composables/useBillingRedirect';
import BillingNotice from './BillingNotice.vue';

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
const { t, locale } = useI18n();
const api = useApi();
const route = useRoute();
const { busy, error: actionError, go } = useBillingRedirect<'checkout' | 'cancel' | 'portal'>();
const status = ref<Status | null>(null);
const quote = ref<Quote | null>(null);
const members = ref('');
const loadError = ref<string | null>(null);
const quoteError = ref<string | null>(null);
const pending = ref(false);
const dismissed = ref(false);
const loading = ref(true);
const quoting = ref(false);
const confirming = ref(false);
const base = computed(() => `/cloud/workspaces/${props.workspace.slug}/billing`);
const canReview = computed(
  () =>
    !!quote.value &&
    !quoting.value &&
    !busy.value &&
    Number(members.value) === quote.value.members &&
    !(status.value?.active && status.value.capacity === quote.value.members)
);
let quoteRequest = 0;

const money = (amount: number, currency: string) => formatCents(amount, currency, locale.value);

const notice = computed(() => {
  const failure = actionError.value ?? quoteError.value;
  if (failure) return { tone: 'error' as const, message: failure };
  if (pending.value) {
    return {
      tone: 'info' as const,
      refresh: true,
      message: t('Payment confirmation is pending. Refresh in a moment to see it.')
    };
  }
  if (dismissed.value || !status.value) return null;
  const returned = checkoutNotice(route.query.checkout, status.value.active);
  return returned ? { ...returned, message: t(returned.message) } : null;
});

async function load() {
  loading.value = true;
  loadError.value = null;
  try {
    const [current, pricing] = await Promise.all([
      api<{ data: Status }>(base.value),
      api<{ data: Quote }>('/cloud/workspace-pricing')
    ]);
    status.value = current.data;
    if (current.data.active) pending.value = false;
    members.value = String(
      Math.max(pricing.data.included_members, current.data.capacity, current.data.member_count)
    );
    await getQuote();
  } catch (cause) {
    loadError.value = extractApiErrors(cause);
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
      quoteError.value = null;
    }
  } catch (cause) {
    if (request === quoteRequest) quoteError.value = extractApiErrors(cause);
  } finally {
    if (request === quoteRequest) quoting.value = false;
  }
}

async function refresh() {
  await load();
  emit('changed');
}

async function checkout() {
  if (!canReview.value || !quote.value) return;
  const result = await go<{ url?: string; pending?: boolean }>(
    'checkout',
    `${base.value}/checkout`,
    {
      members: quote.value.members,
      quote_id: quote.value.quote_id,
      confirmed: true
    }
  );
  confirming.value = false;
  if (result && !result.url) {
    pending.value = true;
    await refresh();
  }
}

async function cancelCheckout() {
  if (await go('cancel', `${base.value}/cancel-checkout`)) await load();
}

const portal = () => go('portal', `${base.value}/portal`);

async function dismiss() {
  if (actionError.value || quoteError.value) {
    actionError.value = null;
    quoteError.value = null;
    return;
  }
  pending.value = false;
  dismissed.value = true;
  const { checkout: _checkout, ...query } = route.query;
  await navigateTo({ path: route.path, query }, { replace: true });
}

watch(members, getQuote);
onMounted(load);
</script>

<style scoped>
.space-billing {
  display: grid;
  gap: var(--ds-space-4);
  max-width: 64rem;
}

.space-billing__card {
  display: grid;
  gap: var(--ds-space-4);
}

.space-billing h3 {
  margin: 0;
  color: var(--ds-text-primary);
  font-size: var(--ds-text-lg);
  font-weight: var(--ds-font-weight-semibold);
}

.space-billing p {
  margin: 0;
}

.space-billing__muted {
  color: var(--ds-text-secondary);
  font-size: var(--ds-text-sm);
}

.space-billing__head,
.space-billing__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--ds-space-3);
}

.space-billing__checkout {
  display: grid;
  grid-template-columns: minmax(0, 14rem) minmax(0, 1fr);
  align-items: end;
  gap: var(--ds-space-4);
}

.space-billing__price {
  display: grid;
  gap: var(--ds-space-1);
}

@media (max-width: 640px) {
  .space-billing__checkout {
    grid-template-columns: 1fr;
  }

  .space-billing__actions {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
