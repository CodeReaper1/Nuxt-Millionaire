<script setup lang="ts">
const { history, fetchHistory, isLoading } = useLoyalty();
const { formatDate } = useHelpers();

const currentPage = ref(0);
const pageSize = 10;

onMounted(() => {
  fetchHistory(pageSize, 0);
});

const loadPage = (page: number) => {
  currentPage.value = page;
  fetchHistory(pageSize, page * pageSize);
};

const typeIcons: Record<string, string> = {
  order: 'ion:bag-check-outline',
  registration: 'ion:person-add-outline',
  review: 'ion:chatbubble-outline',
  referral: 'ion:people-outline',
  redeem: 'ion:gift-outline',
  expire: 'ion:time-outline',
  admin: 'ion:settings-outline',
};

const typeColors: Record<string, string> = {
  order: 'text-green-600 bg-green-50 dark:text-green-400 dark:bg-green-900/20',
  registration: 'text-blue-600 bg-blue-50 dark:text-blue-400 dark:bg-blue-900/20',
  review: 'text-purple-600 bg-purple-50 dark:text-purple-400 dark:bg-purple-900/20',
  referral: 'text-indigo-600 bg-indigo-50 dark:text-indigo-400 dark:bg-indigo-900/20',
  redeem: 'text-orange-600 bg-orange-50 dark:text-orange-400 dark:bg-orange-900/20',
  expire: 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-900/20',
  admin: 'text-gray-600 bg-gray-50 dark:text-gray-400 dark:bg-gray-700/50',
};

const totalPages = computed(() => {
  if (!history.value) return 0;
  return Math.ceil(history.value.totalCount / pageSize);
});
</script>

<template>
  <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs">
    <div class="p-6 pb-0">
      <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('loyalty.transactionHistory') }}</h3>
    </div>

    <div v-if="isLoading && !history" class="flex items-center justify-center min-h-40 p-6">
      <LoadingIcon size="24" stroke="2" />
    </div>

    <div v-else-if="history?.nodes?.length" class="divide-y divide-gray-100 dark:divide-gray-700">
      <div
        v-for="tx in history.nodes"
        :key="tx.id"
        class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
        <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center" :class="typeColors[tx.type] || typeColors.admin">
          <Icon :name="typeIcons[tx.type] || typeIcons.admin" size="18" />
        </div>

        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ tx.description }}</p>
          <p class="text-xs text-gray-400 dark:text-gray-500">
            {{ formatDate(tx.createdAt) }}
            <span v-if="tx.expiresAt" class="ml-2 text-red-400">expires {{ formatDate(tx.expiresAt) }}</span>
          </p>
        </div>

        <div class="flex-shrink-0 text-right">
          <span
            class="text-sm font-bold"
            :class="tx.points > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
            {{ tx.points > 0 ? '+' : '' }}{{ tx.points.toLocaleString() }}
          </span>
        </div>
      </div>
    </div>

    <div v-else class="flex items-center justify-center min-h-40 p-6 text-gray-400 dark:text-gray-500">
      {{ $t('loyalty.noTransactions') }}
    </div>

    <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 p-4 border-t border-gray-100 dark:border-gray-700">
      <button
        v-for="page in totalPages"
        :key="page"
        class="w-8 h-8 rounded-lg text-sm font-medium transition-colors"
        :class="currentPage === page - 1
          ? 'bg-primary text-white'
          : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400'"
        @click="loadPage(page - 1)">
        {{ page }}
      </button>
    </div>
  </div>
</template>
