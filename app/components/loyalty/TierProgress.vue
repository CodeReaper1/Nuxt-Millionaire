<script setup lang="ts">
const { tierName, tier, lifetimePoints, nextTier, nextTierPoints, tierProgress, settings } = useLoyalty();

const tierColors: Record<string, { bar: string; text: string }> = {
  bronze: { bar: 'bg-amber-600', text: 'text-amber-600' },
  silver: { bar: 'bg-gray-500', text: 'text-gray-500' },
  gold: { bar: 'bg-yellow-500', text: 'text-yellow-500' },
};

const currentColors = computed(() => tierColors[tier.value] || tierColors.bronze);

const allTiers = computed(() => {
  if (!settings.value?.tiers) return [];
  return [...settings.value.tiers].sort((a, b) => a.minPoints - b.minPoints);
});
</script>

<template>
  <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs p-6">
    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">{{ $t('loyalty.tierProgress') }}</h3>

    <div class="flex items-center justify-between mb-2">
      <span class="text-sm font-semibold" :class="currentColors.text">{{ tierName }}</span>
      <span v-if="nextTier" class="text-sm text-gray-400 dark:text-gray-500">{{ nextTier.name }}</span>
      <span v-else class="text-sm text-yellow-500 font-semibold">{{ $t('loyalty.maxTier') }}</span>
    </div>

    <div class="w-full h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
      <div
        class="h-full rounded-full transition-all duration-700 ease-out"
        :class="currentColors.bar"
        :style="{ width: `${tierProgress}%` }" />
    </div>

    <div class="flex justify-between mt-2 text-xs text-gray-400 dark:text-gray-500">
      <span>{{ lifetimePoints.toLocaleString() }} pts</span>
      <span v-if="nextTier">{{ nextTierPoints.toLocaleString() }} {{ $t('loyalty.pointsToGo') }}</span>
    </div>

    <div v-if="allTiers.length" class="mt-5 flex gap-2">
      <div
        v-for="t in allTiers"
        :key="t.slug"
        class="flex-1 text-center text-xs py-2 px-1 rounded-lg border transition-colors"
        :class="lifetimePoints >= t.minPoints
          ? 'bg-primary/5 border-primary/20 text-primary font-semibold dark:bg-primary/10'
          : 'bg-gray-50 dark:bg-gray-700/50 border-gray-100 dark:border-gray-600 text-gray-400 dark:text-gray-500'">
        <div class="font-medium">{{ t.name }}</div>
        <div class="mt-0.5">{{ t.multiplier }}x</div>
      </div>
    </div>
  </div>
</template>
