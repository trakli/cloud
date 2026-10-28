import type { CloudPlans } from '../types/api';

export type PlanTone = 'success' | 'warning' | 'primary' | 'default';

const statuses: Record<string, { tone: PlanTone; label: string }> = {
  active: { tone: 'success', label: 'Active' },
  group: { tone: 'primary', label: 'Group subscription' },
  comped: { tone: 'success', label: 'Complimentary' },
  trialing: { tone: 'primary', label: 'Trial' },
  past_due: { tone: 'warning', label: 'Payment due' },
  canceled: { tone: 'default', label: 'Cancelled' }
};

export function planStatus(status: string): { tone: PlanTone; label: string } {
  return statuses[status] ?? { tone: 'default', label: 'Free' };
}

export function regionOptions(plans: CloudPlans | null): Array<{ value: string; label: string }> {
  return Object.entries(plans?.regions ?? {}).map(([value, region]) => ({
    value,
    label: `${region.name} (${region.currency})`
  }));
}

export function defaultRegion(plans: CloudPlans | null, preferred = 'us'): string {
  const keys = Object.keys(plans?.regions ?? {});
  return keys.includes(preferred) ? preferred : (keys[0] ?? preferred);
}

export function usageShare(used: number, allowance: number): number {
  if (allowance <= 0) return 0;
  return Math.min(100, Math.round((used / allowance) * 100));
}

export interface CheckoutNotice {
  tone: 'success' | 'info';
  message: string;
  refresh?: boolean;
}

export function checkoutNotice(returned: unknown, active: boolean): CheckoutNotice | null {
  if (returned === 'success') {
    return active
      ? { tone: 'success', message: 'Payment confirmed. Paid access is active.' }
      : {
          tone: 'info',
          refresh: true,
          message: 'Payment confirmation is pending. Refresh in a moment to see it.'
        };
  }
  if (returned === 'cancelled') {
    return { tone: 'info', message: 'Checkout cancelled. Nothing has changed.' };
  }
  return null;
}

export function formatCents(amount: number, currency: string, locale?: string): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(amount / 100);
}
