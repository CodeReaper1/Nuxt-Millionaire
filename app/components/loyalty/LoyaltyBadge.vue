<script setup lang="ts">
const { viewer } = useAuth();
const { pointsBalance, fetchBalance } = useLoyalty();

const hasFetched = ref(false);

watch(viewer, async (v) => {
  if (v && !hasFetched.value) {
    hasFetched.value = true;
    await fetchBalance();
  }
}, { immediate: true });
</script>

<template>
  <NuxtLink
    v-if="viewer && pointsBalance > 0"
    to="/my-account?tab=loyalty"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full bg-primary/10 text-primary hover:bg-primary/20 transition-colors"
    :title="$t('loyalty.viewLoyalty')">
    <Icon name="ion:star" size="14" />
    <span>{{ pointsBalance.toLocaleString() }}</span>
  </NuxtLink>
</template>
