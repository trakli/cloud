<template>
  <section
    class="billing-notice"
    :class="`billing-notice--${tone}`"
    :role="tone === 'error' ? 'alert' : 'status'"
  >
    <component :is="toneIcons[tone]" class="billing-notice__icon" aria-hidden="true" />
    <p>{{ message }}</p>
    <div class="billing-notice__actions">
      <DsButton
        v-if="refresh"
        size="sm"
        variant="secondary"
        :loading="loading"
        @click="$emit('refresh')"
      >
        <template #icon><IconRefresh /></template>
        {{ t('Refresh status') }}
      </DsButton>
      <DsIconButton
        size="sm"
        variant="ghost"
        :title="t('Dismiss')"
        :aria-label="t('Dismiss')"
        @click="$emit('dismiss')"
      >
        <IconClose />
      </DsIconButton>
    </div>
  </section>
</template>

<script setup lang="ts">
import { DsButton, DsIconButton } from '@whilesmart/design';
import IconClose from '~icons/solar/close-circle-linear';
import IconDanger from '~icons/solar/danger-circle-linear';
import IconInfo from '~icons/solar/info-circle-linear';
import IconRefresh from '~icons/solar/refresh-linear';
import IconSuccess from '~icons/solar/check-circle-linear';

defineProps<{
  tone: 'info' | 'success' | 'error';
  message: string;
  refresh?: boolean;
  loading?: boolean;
}>();
defineEmits<{ refresh: []; dismiss: [] }>();

const { t } = useI18n();
const toneIcons = { info: IconInfo, success: IconSuccess, error: IconDanger };
</script>

<style scoped>
.billing-notice {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--ds-space-3);
  padding: var(--ds-space-3) var(--ds-space-4);
  border-radius: var(--ds-radius-lg);
}

.billing-notice p {
  margin: 0;
  font-size: var(--ds-text-sm);
}

.billing-notice--info {
  background: var(--ds-status-info-bg);
  color: var(--ds-status-info-text);
}

.billing-notice--success {
  background: var(--ds-status-success-bg);
  color: var(--ds-status-success-text);
}

.billing-notice--error {
  background: var(--ds-status-error-bg);
  color: var(--ds-status-error-text);
}

.billing-notice__icon {
  width: 1.375rem;
  height: 1.375rem;
}

.billing-notice__actions {
  display: flex;
  align-items: center;
  gap: var(--ds-space-2);
}

@media (max-width: 640px) {
  .billing-notice {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .billing-notice__actions {
    grid-column: 1 / -1;
    justify-content: flex-end;
  }
}
</style>
