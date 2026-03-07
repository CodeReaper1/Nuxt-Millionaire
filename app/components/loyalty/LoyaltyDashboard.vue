<script setup lang="ts">
const { viewer } = useAuth();
const { fetchBalance, isLoading, balance } = useLoyalty();

onMounted(async () => {
  if (viewer.value) {
    await fetchBalance();
  }
});
</script>

<template>
  <div class="space-y-6">
    <div v-if="isLoading && !balance" class="flex items-center justify-center min-h-62.5">
      <LoadingIcon size="24" stroke="2" />
    </div>

    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <PointsBalance />
        <TierProgress />
      </div>

      <RedeemPoints />
      <ReferralCard />
      <PointsHistory />
    </template>
  </div>
</template>
