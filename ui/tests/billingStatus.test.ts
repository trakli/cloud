import { describe, expect, it } from 'vitest';
import messages from '../i18n/locales/en.json';
import { defaultRegion, planStatus, regionOptions, usageShare } from '../utils/billing';

const plans = {
  trial_days: 3,
  regions: {
    eu: { name: 'Europe', currency: 'EUR', prices: {} },
    uk: { name: 'United Kingdom', currency: 'GBP', prices: {} }
  }
};

describe('billing logic', () => {
  it.each([
    ['free', 'default'],
    ['active', 'success'],
    ['comped', 'success'],
    ['trialing', 'primary'],
    ['past_due', 'warning'],
    ['canceled', 'default']
  ])('shows %s with a %s chip and a translated label', (status, tone) => {
    const result = planStatus(status);
    expect(result.tone).toBe(tone);
    expect(messages).toHaveProperty([result.label]);
  });

  it('lists each priced region with its currency', () => {
    expect(regionOptions(plans)).toEqual([
      { value: 'eu', label: 'Europe (EUR)' },
      { value: 'uk', label: 'United Kingdom (GBP)' }
    ]);
  });

  it('keeps the preferred region only when it is priced', () => {
    expect(defaultRegion(plans, 'uk')).toBe('uk');
    expect(defaultRegion(plans, 'us')).toBe('eu');
  });

  it('caps the usage meter at a full bar', () => {
    expect(usageShare(420, 100000)).toBe(0);
    expect(usageShare(50000, 100000)).toBe(50);
    expect(usageShare(120000, 100000)).toBe(100);
    expect(usageShare(10, 0)).toBe(0);
  });
});
