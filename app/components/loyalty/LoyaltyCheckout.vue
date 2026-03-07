<script setup lang="ts">
const { viewer } = useAuth();
const { cart } = useCart();
const { pointsBalance, settings, calculateDiscount, maxRedeemablePoints, redeemPoints, fetchBalance, isLoading, pointsForOrder } = useLoyalty();

const message = ref('');
const messageType = ref<'success' | 'error' | ''>('');
const hasFetched = ref(false);

const orderTotal = computed(() => {
  if (!cart.value?.rawTotal) return 0;
  return parseFloat(cart.value.rawTotal);
});

const maxPoints = computed(() => {
  if (orderTotal.value <= 0) return 0;
  return maxRedeemablePoints(orderTotal.value);
});

const estimatedEarnings = computed(() => {
  if (orderTotal.value <= 0) return 0;
  return pointsForOrder(orderTotal.value);
});

watch(viewer, async (v) => {
  if (v && !hasFetched.value) {
    hasFetched.value = true;
    await fetchBalance();
  }
}, { immediate: true });

const handleQuickRedeem = async () => {
  if (maxPoints.value <= 0) return;

  const result = await redeemPoints(maxPoints.value);
  message.value = result.message;
  messageType.value = result.success ? 'success' : 'error';
};
</script>

<template>
  <div v-if="viewer && pointsBalance > 0" class="rounded-lg border border-primary/20 bg-primary/5 dark:bg-primary/10 p-4 mb-4">
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2">
        <Icon name="ion:star" size="18" class="text-primary" />
        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $t('loyalty.loyaltyPoints') }}</span>
      </div>
      <span class="text-sm font-bold text-primary">{{ pointsBalance.toLocaleString() }} pts</span>
    </div>

    <div v-if="maxPoints > 0" class="flex items-center justify-between">
      <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ $t('loyalty.useUpTo') }} {{ maxPoints.toLocaleString() }} pts
        (<strong>{{ calculateDiscount(maxPoints).toFixed(2) }}</strong> {{ $t('loyalty.discount') }})
      </p>
      <button
        type="button"
        class="text-xs font-semibold text-primary hover:underline disabled:opacity-50"
        :disabled="isLoading"
        @click="handleQuickRedeem">
        {{ isLoading ? '...' : $t('loyalty.applyDiscount') }}
      </button>
    </div>

    <div
      v-if="message"
      class="mt-2 px-3 py-2 rounded text-xs"
      :class="messageType === 'success'
        ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
        : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'">
      {{ message }}
    </div>

    <div v-if="estimatedEarnings > 0" class="mt-2 text-xs text-gray-400 dark:text-gray-500">
      <Icon name="ion:trending-up" size="12" class="inline" />
      {{ $t('loyalty.earnFromOrder', { points: estimatedEarnings }) }}
    </div>
  </div>

  <div v-else-if="viewer && estimatedEarnings > 0" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/30 p-3 mb-4">
    <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
      <Icon name="ion:star" size="14" class="text-primary" />
      {{ $t('loyalty.earnFromOrder', { points: estimatedEarnings }) }}
    </p>
  </div>
</template>
