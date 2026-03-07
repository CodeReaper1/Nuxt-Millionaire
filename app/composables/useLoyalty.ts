interface LoyaltyTier {
  slug: string;
  name: string;
  minPoints: number;
  multiplier: number;
}

interface LoyaltyBalance {
  points: number;
  tier: string;
  tierName: string;
  tierMultiplier: number;
  lifetimePoints: number;
  nextTier: LoyaltyTier | null;
  nextTierPoints: number;
  referralCode: string;
}

interface LoyaltyTransaction {
  id: string;
  points: number;
  type: string;
  description: string;
  referenceId: number | null;
  createdAt: string;
  expiresAt: string | null;
}

interface LoyaltySettings {
  pointsPerCurrency: number;
  redemptionRate: number;
  maxDiscountPercentage: number;
  registrationBonus: number;
  reviewBonus: number;
  referralBonusReferrer: number;
  referralBonusReferee: number;
  expirationDays: number;
  tiers: LoyaltyTier[];
}

interface LoyaltyHistory {
  nodes: LoyaltyTransaction[];
  totalCount: number;
  hasMore: boolean;
}

export function useLoyalty() {
  const { applyCoupon } = useCart();
  const { getErrorMessage } = useHelpers();

  const balance = useState<LoyaltyBalance | null>('loyaltyBalance', () => null);
  const history = useState<LoyaltyHistory | null>('loyaltyHistory', () => null);
  const settings = useState<LoyaltySettings | null>('loyaltySettings', () => null);
  const isLoading = useState<boolean>('loyaltyLoading', () => false);
  const error = useState<string | null>('loyaltyError', () => null);

  const pointsBalance = computed(() => balance.value?.points ?? 0);
  const tier = computed(() => balance.value?.tier ?? 'bronze');
  const tierName = computed(() => balance.value?.tierName ?? 'Bronze');
  const tierMultiplier = computed(() => balance.value?.tierMultiplier ?? 1.0);
  const lifetimePoints = computed(() => balance.value?.lifetimePoints ?? 0);
  const nextTier = computed(() => balance.value?.nextTier ?? null);
  const nextTierPoints = computed(() => balance.value?.nextTierPoints ?? 0);
  const referralCode = computed(() => balance.value?.referralCode ?? '');

  const tierProgress = computed(() => {
    if (!balance.value?.nextTier) return 100;
    const currentTierMin = getCurrentTierMin();
    const nextMin = balance.value.nextTier.minPoints;
    const range = nextMin - currentTierMin;
    if (range <= 0) return 100;
    const progress = balance.value.lifetimePoints - currentTierMin;
    return Math.min(100, Math.round((progress / range) * 100));
  });

  function getCurrentTierMin(): number {
    if (!settings.value?.tiers || !balance.value) return 0;
    const sorted = [...settings.value.tiers].sort((a, b) => b.minPoints - a.minPoints);
    for (const t of sorted) {
      if (balance.value.lifetimePoints >= t.minPoints) return t.minPoints;
    }
    return 0;
  }

  async function fetchBalance(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const data = await GqlGetLoyaltyBalance();
      if (data.loyaltyBalance) {
        balance.value = data.loyaltyBalance as LoyaltyBalance;
      }
      if (data.loyaltySettings) {
        settings.value = data.loyaltySettings as LoyaltySettings;
      }
    } catch (e: any) {
      error.value = getErrorMessage(e);
      console.error('Error fetching loyalty balance:', error.value);
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchHistory(first = 20, offset = 0): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const data = await GqlGetLoyaltyHistory({ first, offset });
      if (data.loyaltyHistory) {
        history.value = data.loyaltyHistory as LoyaltyHistory;
      }
    } catch (e: any) {
      error.value = getErrorMessage(e);
      console.error('Error fetching loyalty history:', error.value);
    } finally {
      isLoading.value = false;
    }
  }

  async function redeemPoints(points: number): Promise<{ success: boolean; message: string }> {
    isLoading.value = true;
    error.value = null;

    try {
      const data = await GqlRedeemLoyaltyPoints({ points });
      const result = data.redeemLoyaltyPoints;

      if (result?.success && result.couponCode) {
        const couponResult = await applyCoupon(result.couponCode);
        if (!couponResult.success) {
          return { success: false, message: couponResult.error || 'Failed to apply discount coupon.' };
        }
        await fetchBalance();
        return { success: true, message: result.message || 'Points redeemed successfully!' };
      }

      return { success: false, message: result?.message || 'Redemption failed.' };
    } catch (e: any) {
      const msg = getErrorMessage(e);
      error.value = msg;
      return { success: false, message: msg || 'An error occurred.' };
    } finally {
      isLoading.value = false;
    }
  }

  async function applyReferral(code: string): Promise<{ success: boolean; message: string }> {
    isLoading.value = true;
    error.value = null;

    try {
      const data = await GqlApplyReferralCode({ code });
      const result = data.applyReferralCode;

      if (result?.success) {
        await fetchBalance();
      }

      return {
        success: result?.success ?? false,
        message: result?.message || 'Unknown error.',
      };
    } catch (e: any) {
      const msg = getErrorMessage(e);
      error.value = msg;
      return { success: false, message: msg || 'An error occurred.' };
    } finally {
      isLoading.value = false;
    }
  }

  function calculateDiscount(points: number): number {
    const rate = settings.value?.redemptionRate ?? 100;
    return Math.round((points / rate) * 100) / 100;
  }

  function pointsForOrder(orderTotal: number): number {
    const rate = settings.value?.pointsPerCurrency ?? 1;
    const mult = tierMultiplier.value;
    return Math.floor(orderTotal * rate * mult);
  }

  function maxRedeemablePoints(orderTotal: number): number {
    const maxPct = settings.value?.maxDiscountPercentage ?? 50;
    const maxDiscount = orderTotal * (maxPct / 100);
    const rate = settings.value?.redemptionRate ?? 100;
    const maxFromOrder = Math.floor(maxDiscount * rate);
    return Math.min(pointsBalance.value, maxFromOrder);
  }

  return {
    balance,
    history,
    settings,
    isLoading,
    error,
    pointsBalance,
    tier,
    tierName,
    tierMultiplier,
    lifetimePoints,
    nextTier,
    nextTierPoints,
    referralCode,
    tierProgress,
    fetchBalance,
    fetchHistory,
    redeemPoints,
    applyReferral,
    calculateDiscount,
    pointsForOrder,
    maxRedeemablePoints,
  };
}
