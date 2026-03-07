<script setup lang="ts">
const { pointsBalance, tierName, tier, tierMultiplier, settings, calculateDiscount } = useLoyalty();

const tierColors: Record<string, string> = {
  bronze: 'from-amber-600 to-amber-800',
  silver: 'from-gray-400 to-gray-600',
  gold: 'from-yellow-400 to-yellow-600',
};

const tierBg = computed(() => tierColors[tier.value] || tierColors.bronze);

const discountValue = computed(() => {
  if (!settings.value) return '0.00';
  return calculateDiscount(pointsBalance.value).toFixed(2);
});
</script>

<template>
  <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs">
    <div class="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 rounded-full opacity-10 bg-linear-to-br" :class="tierBg" />

    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('loyalty.availablePoints') }}</span>
        <span
          class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold text-white rounded-full bg-linear-to-r"
          :class="tierBg">
          <Icon name="ion:star" size="12" />
          {{ tierName }}
        </span>
      </div>

      <div class="text-4xl font-bold text-gray-900 dark:text-white mb-1">
        {{ pointsBalance.toLocaleString() }}
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ $t('loyalty.worthDiscount', { amount: discountValue }) }}
      </p>

      <div v-if="tierMultiplier > 1" class="mt-4 flex items-center gap-2 text-sm text-primary">
        <Icon name="ion:trending-up" size="16" />
        <span>{{ tierMultiplier }}x {{ $t('loyalty.earningMultiplier') }}</span>
      </div>
    </div>
  </div>
</template>
