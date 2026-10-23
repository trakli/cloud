import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import BillingSettings from '../components/BillingSettings.vue';

const request = vi.hoisted(() => vi.fn());
const navigate = vi.hoisted(() => vi.fn());
vi.mock('~/composables/useApi', () => ({ useApi: () => request }));
vi.stubGlobal('navigateTo', navigate);

const current = {
  paid_access: false,
  has_billing_account: false,
  status: 'free',
  paid_through: null,
  limits: { wallets: 3, categories: 5, bank_connections: 0 },
  ai_usage: {
    used: 0,
    allowance: 0,
    remaining: 0,
    period_start: '2026-10-01T00:00:00Z',
    period_end: '2026-11-01T00:00:00Z'
  }
};

const plans = {
  trial_days: 3,
  regions: {
    us: {
      name: 'United States',
      currency: 'USD',
      prices: {
        monthly: { price: 8.99, price_formatted: '$8.99' },
        yearly: { price: 86.28, price_formatted: '$86.28' }
      }
    }
  }
};

function respond(status: object, pricing: object = plans) {
  request.mockImplementation((path: string, options?: { method?: string }) =>
    Promise.resolve({
      success: true,
      message: 'Success',
      data: options?.method
        ? { url: 'https://example.invalid/pay' }
        : path === '/cloud/plans'
          ? pricing
          : status
    })
  );
}

function render() {
  return mount(BillingSettings, { attachTo: document.body });
}

const text = () => document.body.textContent ?? '';
const button = (label: string) =>
  ([...document.body.querySelectorAll('button')] as HTMLElement[]).find(
    (el) => el.textContent?.trim() === label
  );

beforeEach(() => {
  request.mockReset();
  navigate.mockReset();
  document.body.innerHTML = '';
  vi.stubGlobal('useRoute', () => ({
    path: '/settings',
    query: { tab: 'billing', checkout: 'success' }
  }));
  vi.stubGlobal('useRouter', () => ({ hasRoute: () => true }));
  respond(current);
});

describe('plan and billing tab', () => {
  it('keeps free access until the server confirms payment and shows the price', async () => {
    const wrapper = render();
    await flushPromises();
    expect(text()).toContain('Free plan');
    expect(text()).toContain('Payment confirmation is pending');
    expect(text()).toContain('$86.28');
    expect(text()).not.toContain('Paid plan');
    expect(button('Manage billing')).toBeUndefined();
    wrapper.unmount();
  });

  it('starts checkout with yearly billing and allows switching to monthly', async () => {
    const wrapper = render();
    await flushPromises();
    button('Upgrade')!.click();
    await flushPromises();
    expect(request).toHaveBeenCalledWith('/cloud/billing/checkout', {
      method: 'POST',
      body: { region: 'us', plan: 'yearly' }
    });
    (document.body.querySelector('#billing-period') as HTMLElement).click();
    await flushPromises();
    (
      [...document.body.querySelectorAll('[role="option"]')].find(
        (option) => option.textContent?.trim() === 'Monthly'
      ) as HTMLElement
    ).click();
    await flushPromises();
    expect(text()).toContain('$8.99');
    button('Upgrade')!.click();
    await flushPromises();
    expect(request).toHaveBeenCalledWith('/cloud/billing/checkout', {
      method: 'POST',
      body: { region: 'us', plan: 'monthly' }
    });
    expect(navigate).toHaveBeenCalledWith('https://example.invalid/pay', { external: true });
    wrapper.unmount();
  });

  it('shows only server-confirmed access and monthly usage', async () => {
    respond(
      {
        ...current,
        paid_access: true,
        has_billing_account: true,
        status: 'active',
        ai_usage: { ...current.ai_usage, used: 420, allowance: 100000 }
      },
      { trial_days: 3, regions: {} }
    );
    const wrapper = render();
    await flushPromises();
    expect(text()).toContain('Paid plan');
    expect(text()).toContain('420 / 100,000');
    expect(text()).toContain('Payment confirmed. Paid access is active.');
    expect(button('Manage billing')).toBeDefined();
    expect(text()).not.toContain('Payment confirmation is pending');
    wrapper.unmount();
  });

  it('hides bank management when the bank layer is absent', async () => {
    vi.stubGlobal('useRouter', () => ({ hasRoute: () => false }));
    respond({ ...current, paid_access: true, has_billing_account: true }, { regions: {} });
    const wrapper = render();
    await flushPromises();
    expect(button('Manage billing')).toBeDefined();
    expect(button('Manage banks')).toBeUndefined();
    wrapper.unmount();
  });

  it('clears the checkout result from the address when dismissed', async () => {
    const wrapper = render();
    await flushPromises();
    (document.body.querySelector('[aria-label="Dismiss"]') as HTMLElement).click();
    await flushPromises();
    expect(navigate).toHaveBeenCalledWith(
      { path: '/settings', query: { tab: 'billing' } },
      { replace: true }
    );
    expect(text()).not.toContain('Payment confirmation is pending');
    wrapper.unmount();
  });
});
