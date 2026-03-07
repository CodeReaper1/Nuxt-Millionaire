<script setup lang="ts">
const { pointsBalance, settings, calculateDiscount, maxRedeemablePoints, redeemPoints, isLoading } = useLoyalty();
const { cart } = useCart();

const redeemAmount = ref(0);
const message = ref('');
const messageType = ref<'success' | 'error' | ''>('');

const orderTotal = computed(() => {
  if (!cart.value?.rawTotal) return 0;
  return parseFloat(cart.value.rawTotal);
});

const maxPoints = computed(() => {
  if (orderTotal.value <= 0) return pointsBalance.value;
  return maxRedeemablePoints(orderTotal.value);
});

const discountPreview = computed(() => calculateDiscount(redeemAmount.value).toFixed(2));

const canRedeem = computed(() => {
  return redeemAmount.value > 0 && redeemAmount.value <= maxPoints.value && !isLoading.value;
});

watch(maxPoints, (val) => {
  if (redeemAmount.value > val) {
    redeemAmount.value = val;
  }
});

const handleRedeem = async () => {
  if (!canRedeem.value) return;

  const result = await redeemPoints(redeemAmount.value);
  message.value = result.message;
  messageType.value = result.success ? 'success' : 'error';

  if (result.success) {
    redeemAmount.value = 0;
  }
};

const setMax = () => {
  redeemAmount.value = maxPoints.value;
};
</script>

<template>
  <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs p-6">
    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">{{ $t('loyalty.redeemPoints') }}</h3>

    <div v-if="pointsBalance <= 0" class="text-center py-6 text-gray-400 dark:text-gray-500">
      {{ $t('loyalty.noPointsToRedeem') }}
    </div>

    <template v-else>
      <div class="space-y-4">
        <div>
          <div class="flex items-center justify-between mb-2">
            <label for="redeem-input" class="text-sm text-gray-600 dark:text-gray-300">{{ $t('loyalty.pointsToRedeem') }}</label>
            <button
              type="button"
              class="text-xs text-primary hover:underline"
              @click="setMax">
              {{ $t('loyalty.useMax') }} ({{ maxPoints.toLocaleString() }})
            </button>
          </div>

          <input
            id="redeem-input"
            v-model.number="redeemAmount"
            type="range"
            :min="0"
            :max="maxPoints"
            :step="settings?.redemptionRate ?? 10"
            class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-primary" />

          <div class="flex items-center justify-between mt-2">
            <input
              v-model.number="redeemAmount"
              type="number"
              :min="0"
              :max="maxPoints"
              class="w-24 px-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
            <span class="text-sm text-gray-500 dark:text-gray-400">
              = <strong class="text-gray-900 dark:text-white">{{ discountPreview }}</strong> {{ $t('loyalty.discount') }}
            </span>
          </div>
        </div>

        <div
          v-if="message"
          class="px-4 py-3 rounded-lg text-sm"
          :class="messageType === 'success'
            ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
            : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'">
          {{ message }}
        </div>

        <Button
          class="w-full"
          :disabled="!canRedeem"
          :loading="isLoading"
          @click="handleRedeem">
          {{ $t('loyalty.redeemNow') }}
        </Button>
      </div>
    </template>
  </div>
</template>
