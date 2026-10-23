export interface BillingStatus {
  paid_access: boolean;
  has_billing_account: boolean;
  status: string;
  paid_through: string | null;
  limits: {
    wallets: number | null;
    categories: number | null;
    bank_connections: number | null;
  };
  ai_usage: {
    used: number;
    allowance: number;
    remaining: number;
    period_start: string;
    period_end: string;
  };
}
export interface PremiumResponse<T> {
  success: boolean;
  message: string;
  data: T;
}
export interface CloudPlans {
  trial_days: number;
  regions: Record<
    string,
    {
      name: string;
      currency: string;
      prices: Record<string, { price: number; price_formatted: string }>;
    }
  >;
}
