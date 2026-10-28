<template>
  <div class="billing">
    <section
      v-if="notice"
      class="billing__notice"
      :class="`billing__notice--${notice.tone}`"
      :role="notice.tone === 'error' ? 'alert' : 'status'"
    >
      <component :is="toneIcons[notice.tone]" class="billing__notice-icon" aria-hidden="true" />
      <p>{{ notice.message }}</p>
      <div class="billing__notice-actions">
        <DsButton
          v-if="notice.refresh"
          size="sm"
          variant="secondary"
          :loading="loading"
          @click="load"
        >
          <template #icon><IconRefresh /></template>
          {{ t('Refresh status') }}
        </DsButton>
        <DsIconButton
          size="sm"
          variant="ghost"
          :title="t('Dismiss')"
          :aria-label="t('Dismiss')"
          @click="dismiss"
        >
          <IconClose />
        </DsIconButton>
      </div>
    </section>

    <DsStatePanel v-if="loading && !status" state="loading" :title="t('Loading your plan')" />

    <DsStatePanel
      v-else-if="loadError && !status"
      state="error"
      :title="t('Could not load your plan')"
      :description="loadError"
      :action-label="t('Try again')"
      @action="load"
    />

    <template v-else-if="status">
      <DsCard padding="md" class="billing__plan">
        <div class="billing__plan-head">
          <div class="billing__plan-title">
            <h3>{{ status.paid_access ? t('Paid plan') : t('Free plan') }}</h3>
            <DsChip size="sm" :variant="plan.tone">{{ t(plan.label) }}</DsChip>
          </div>
          <p v-if="status.paid_through" class="billing__muted">
            {{
              t('Paid through {date}', {
                date: formatDate(status.paid_through)
              })
            }}
          </p>
          <div
            v-if="status.paid_access || status.has_billing_account"
            class="billing__plan-actions"
          >
            <DsButton
              v-if="hasBanks && status.paid_access"
              variant="ghost"
              @click="navigateTo('/banks')"
            >
              {{ t('Manage banks') }}
            </DsButton>
            <DsButton
              v-if="status.has_billing_account"
              variant="secondary"
              :loading="busy === 'portal'"
              @click="portal"
            >
              <template #icon><IconExternal /></template>
              {{ t('Manage billing') }}
            </DsButton>
          </div>
        </div>

        <dl class="billing__limits">
          <div v-for="item in limits" :key="item.label" class="billing__limit">
            <dt>{{ t(item.label) }}</dt>
            <dd>
              {{ item.value === null ? t('Unlimited') : item.value.toLocaleString(lang) }}
            </dd>
          </div>
        </dl>

        <div v-if="status.ai_usage.allowance > 0" class="billing__usage">
          <div class="billing__usage-head">
            <strong>{{ t('Monthly model usage') }}</strong>
            <span>
              {{ status.ai_usage.used.toLocaleString(lang) }} /
              {{ status.ai_usage.allowance.toLocaleString(lang) }}
              {{ t('tokens') }}
            </span>
          </div>
          <div
            class="billing__meter"
            role="meter"
            :aria-label="t('Monthly model usage')"
            :aria-valuenow="usage"
            aria-valuemin="0"
            aria-valuemax="100"
          >
            <span :style="{ width: `${usage}%` }" />
          </div>
          <p class="billing__muted">
            {{
              t('Resets {date} (UTC)', {
                date: formatDate(status.ai_usage.period_end)
              })
            }}
          </p>
        </div>
      </DsCard>

      <DsCard v-if="!status.paid_access" padding="md" class="billing__upgrade">
        <div class="billing__upgrade-copy">
          <span class="billing__upgrade-tile" :style="areaStyle('amber')" aria-hidden="true">
            <IconPremium />
          </span>
          <div>
            <h3>{{ t('Upgrade for more room') }}</h3>
            <p class="billing__muted">
              {{
                t(
                  'Upgrade for unlimited wallets, categories and bank connections, plus 100,000 model tokens each month.'
                )
              }}
            </p>
          </div>
        </div>

        <div v-if="regions.length" class="billing__checkout">
          <DsDropdown
            id="billing-region"
            v-model="region"
            :label="t('Pricing region')"
            :options="regions"
            :disabled="busy !== null"
          />
          <DsDropdown
            id="billing-period"
            v-model="period"
            :label="t('Billing period')"
            :options="periods"
            :disabled="busy !== null"
          />
          <div class="billing__price">
            <strong>{{ selectedPrice ?? '—' }}</strong>
            <span>{{ period === 'monthly' ? t('per month') : t('per year') }}</span>
          </div>
          <DsButton
            class="billing__buy"
            :disabled="!selectedPrice"
            :loading="busy === 'checkout'"
            @click="checkout"
          >
            {{ t('Upgrade') }}
          </DsButton>
        </div>
        <p v-else class="billing__muted">
          {{ t('Plans are not available in your region yet.') }}
        </p>

        <p v-if="pricing?.trial_days" class="billing__muted">
          {{
            t('A {days}-day trial is available once per account.', {
              days: pricing.trial_days
            })
          }}
        </p>
      </DsCard>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import {
  DsButton,
  DsCard,
  DsChip,
  DsDropdown,
  DsIconButton,
  DsStatePanel,
  areaStyle
} from '@whilesmart/design';
import IconClose from '~icons/solar/close-circle-linear';
import IconDanger from '~icons/solar/danger-circle-linear';
import IconExternal from '~icons/solar/arrow-right-up-linear';
import IconInfo from '~icons/solar/info-circle-linear';
import IconPremium from '~icons/solar/crown-star-bold-duotone';
import IconRefresh from '~icons/solar/refresh-linear';
import IconSuccess from '~icons/solar/check-circle-linear';
import { useApi } from '~/composables/useApi';
import { extractApiErrors } from '~/utils/apiErrors';
import type { BillingStatus, CloudPlans, PremiumResponse } from '../types/api';
import {
  checkoutNotice,
  defaultRegion,
  planStatus,
  regionOptions,
  usageShare
} from '../utils/billing';
import { useBillingRedirect } from '../composables/useBillingRedirect';

const { t, locale } = useI18n();
const lang = computed(() => locale.value);
const api = useApi();
const route = useRoute();
const hasBanks = useRouter().hasRoute('banks');

const status = ref<BillingStatus | null>(null);
const pricing = ref<CloudPlans | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
const dismissed = ref(false);
const { busy, error: actionError, go } = useBillingRedirect<'checkout' | 'portal'>();
const region = ref('us');
const period = ref('yearly');

const toneIcons = { info: IconInfo, success: IconSuccess, error: IconDanger };

const periods = computed(() => [
  { value: 'yearly', label: t('Yearly') },
  { value: 'monthly', label: t('Monthly') }
]);
const regions = computed(() => regionOptions(pricing.value));
const selectedPrice = computed(
  () => pricing.value?.regions[region.value]?.prices[period.value]?.price_formatted
);
const plan = computed(() => planStatus(status.value?.status ?? 'free'));
const usage = computed(() =>
  status.value ? usageShare(status.value.ai_usage.used, status.value.ai_usage.allowance) : 0
);
const limits = computed(() => [
  { label: 'Wallets', value: status.value?.limits.wallets ?? null },
  {
    label: 'Custom categories',
    value: status.value?.limits.categories ?? null
  },
  {
    label: 'Bank connections',
    value: status.value?.limits.bank_connections ?? null
  }
]);

const notice = computed(() => {
  if (actionError.value) return { tone: 'error' as const, message: actionError.value };
  if (dismissed.value || !status.value) return null;
  const returned = checkoutNotice(route.query.checkout, status.value.paid_access);
  return returned ? { ...returned, message: t(returned.message) } : null;
});

const formatDate = (value: string) => new Date(value).toLocaleDateString(lang.value);

async function load() {
  loading.value = true;
  loadError.value = null;
  try {
    const [current, plans] = await Promise.all([
      api<PremiumResponse<BillingStatus>>('/cloud/billing/status'),
      api<PremiumResponse<CloudPlans>>('/cloud/plans')
    ]);
    status.value = current.data;
    pricing.value = plans.data;
    region.value = defaultRegion(plans.data, region.value);
  } catch (e) {
    loadError.value = extractApiErrors(e);
  } finally {
    loading.value = false;
  }
}

const checkout = () =>
  go('checkout', '/cloud/billing/checkout', {
    region: region.value,
    plan: period.value
  });
const portal = () => go('portal', '/cloud/billing/portal');

async function dismiss() {
  if (actionError.value) {
    actionError.value = null;
    return;
  }
  dismissed.value = true;
  const { checkout: _checkout, ...query } = route.query;
  await navigateTo({ path: route.path, query }, { replace: true });
}

onMounted(load);
</script>

<style scoped>
.billing {
  display: grid;
  gap: var(--ds-space-4);
  max-width: 64rem;
}

.billing h3 {
  margin: 0;
  color: var(--ds-text-primary);
  font-size: var(--ds-text-lg);
  font-weight: var(--ds-font-weight-semibold);
}

.billing__muted {
  margin: 0;
  color: var(--ds-text-secondary);
  font-size: var(--ds-text-sm);
}

.billing__notice {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--ds-space-3);
  padding: var(--ds-space-3) var(--ds-space-4);
  border-radius: var(--ds-radius-lg);
}

.billing__notice p {
  margin: 0;
  font-size: var(--ds-text-sm);
}

.billing__notice--info {
  background: var(--ds-status-info-bg);
  color: var(--ds-status-info-text);
}

.billing__notice--success {
  background: var(--ds-status-success-bg);
  color: var(--ds-status-success-text);
}

.billing__notice--error {
  background: var(--ds-status-error-bg);
  color: var(--ds-status-error-text);
}

.billing__notice-icon {
  width: 1.375rem;
  height: 1.375rem;
}

.billing__notice-actions {
  display: flex;
  align-items: center;
  gap: var(--ds-space-2);
}

.billing__plan,
.billing__upgrade {
  display: grid;
  gap: var(--ds-space-4);
}

.billing__plan-head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--ds-space-1) var(--ds-space-4);
}

.billing__plan-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--ds-space-2);
}

.billing__plan-head .billing__muted {
  grid-column: 1;
}

.billing__plan-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--ds-space-2);
  grid-column: 2;
  grid-row: 1 / span 2;
}

.billing__limits {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--ds-space-3);
  margin: 0;
}

.billing__limit {
  display: grid;
  gap: var(--ds-space-1);
  padding: var(--ds-space-3);
  border-radius: var(--ds-radius-lg);
  background: var(--ds-bg-subtle);
}

.billing__limit dt {
  color: var(--ds-text-secondary);
  font-size: var(--ds-text-xs);
}

.billing__limit dd {
  margin: 0;
  color: var(--ds-text-primary);
  font-size: var(--ds-text-xl);
  font-weight: var(--ds-font-weight-semibold);
}

.billing__usage {
  display: grid;
  gap: var(--ds-space-2);
}

.billing__usage-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--ds-space-2);
  color: var(--ds-text-primary);
  font-size: var(--ds-text-sm);
}

.billing__meter {
  height: 0.5rem;
  border-radius: var(--ds-radius-full);
  background: var(--ds-bg-muted);
  overflow: hidden;
}

.billing__meter span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--ds-interactive-primary);
}

.billing__upgrade-copy {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: var(--ds-space-4);
}

.billing__upgrade-copy > div {
  display: grid;
  gap: var(--ds-space-1);
}

.billing__upgrade-tile {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: var(--ds-radius-lg);
  background: var(--ds-area-fill);
  color: var(--ds-area-ink);
  font-size: 1.5rem;
}

.billing__checkout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto auto;
  align-items: end;
  gap: var(--ds-space-3);
}

.billing__price {
  display: grid;
  justify-items: end;
  color: var(--ds-text-primary);
}

.billing__price strong {
  font-size: var(--ds-text-2xl);
  font-weight: var(--ds-font-weight-bold);
  line-height: var(--ds-leading-tight);
}

.billing__price span {
  color: var(--ds-text-secondary);
  font-size: var(--ds-text-xs);
}

@media (max-width: 720px) {
  .billing__checkout {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }

  .billing__price {
    justify-items: start;
  }

  .billing__buy {
    grid-column: 1 / -1;
    width: 100%;
  }
}

@media (max-width: 640px) {
  .billing__plan-head {
    grid-template-columns: 1fr;
  }

  .billing__plan-actions {
    grid-column: 1;
    grid-row: auto;
  }

  .billing__plan-actions > * {
    flex: 1 1 0;
  }

  .billing__limits {
    grid-template-columns: 1fr;
  }

  .billing__limit {
    grid-template-columns: 1fr auto;
    align-items: center;
  }

  .billing__checkout {
    grid-template-columns: 1fr;
  }

  .billing__notice {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .billing__notice-actions {
    grid-column: 1 / -1;
    justify-content: flex-end;
  }
}
</style>
