import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import WorkspaceBilling from '../components/WorkspaceBilling.vue';

const request = vi.fn();
const navigate = vi.fn();
let wrapper: VueWrapper | undefined;
const workspace = {
  id: 1,
  slug: 'our-space',
  name: 'Our space',
  description: null,
  kind: 'group' as const,
  role: 'owner' as const,
  read_only: true,
  can_manage: true,
  member_count: 1
};
const status = {
  active: false,
  status: 'inactive',
  member_count: 1,
  capacity: 0,
  quote: null,
  can_manage_billing: true,
  has_billing_account: false,
  has_subscription: false,
  pending_checkout: false
};
const quote = {
  quote_id: 'current-server-quote',
  currency: 'usd',
  interval: 'month',
  members: 2,
  included_members: 2,
  max_members: 100,
  base_amount_cents: 1200,
  additional_member_amount_cents: 500,
  amount_cents: 1200
};
const button = (label: string) =>
  [...document.body.querySelectorAll('button')].find((item) => item.textContent?.trim() === label)!;

beforeEach(() => {
  request.mockReset();
  navigate.mockReset();
  vi.stubGlobal('useApi', () => request);
  vi.stubGlobal('navigateTo', navigate);
  vi.stubGlobal('useRoute', () => ({ query: {} }));
  request.mockImplementation((path: string, options?: { method?: string }) =>
    Promise.resolve({
      data:
        options?.method === 'POST'
          ? { url: 'https://example.invalid/checkout' }
          : path === '/cloud/workspace-pricing'
            ? quote
            : status
    })
  );
});
afterEach(() => {
  wrapper?.unmount();
  document.body.innerHTML = '';
});

describe('group subscription', () => {
  it('confirms a cancelled checkout without granting access', async () => {
    vi.stubGlobal('useRoute', () => ({ query: { checkout: 'cancelled' } }));
    wrapper = mount(WorkspaceBilling, { props: { workspace }, attachTo: document.body });
    await flushPromises();
    expect(wrapper.text()).toContain('Checkout cancelled. Nothing has changed.');
    expect(wrapper.text()).not.toContain('Paid access is active.');
  });

  it('shows the server price and requires confirmation before checkout', async () => {
    wrapper = mount(WorkspaceBilling, {
      props: { workspace },
      attachTo: document.body
    });
    await flushPromises();
    expect(wrapper.text()).toContain('$12.00 per month for 2 people');
    expect(wrapper.text()).toContain('Each additional person is $5.00');
    button('Continue to checkout').click();
    await flushPromises();
    expect(request.mock.calls.filter(([, options]) => options?.method === 'POST')).toHaveLength(0);
    expect(document.body.textContent).toContain('Confirm group subscription');
    const confirm = [...document.body.querySelectorAll('button')]
      .filter((item) => item.textContent?.trim() === 'Continue to checkout')
      .at(-1)!;
    confirm.click();
    await flushPromises();
    expect(request).toHaveBeenCalledWith('/cloud/workspaces/our-space/billing/checkout', {
      method: 'POST',
      body: { members: 2, quote_id: 'current-server-quote', confirmed: true }
    });
    expect(navigate).toHaveBeenCalledWith('https://example.invalid/checkout', {
      external: true
    });
  });

  it('keeps billing controls with the owner', async () => {
    request.mockImplementation((path: string) =>
      Promise.resolve({
        data: path === '/cloud/workspace-pricing' ? quote : { ...status, can_manage_billing: false }
      })
    );
    wrapper = mount(WorkspaceBilling, {
      props: { workspace },
      attachTo: document.body
    });
    await flushPromises();
    expect(wrapper.find('input').exists()).toBe(false);
    expect(button('Continue to checkout')).toBeUndefined();
  });
});
