<script setup lang="ts">
const { referralCode, settings, applyReferral, isLoading } = useLoyalty();

const inputCode = ref('');
const message = ref('');
const messageType = ref<'success' | 'error' | ''>('');
const copied = ref(false);

const referralUrl = computed(() => {
  if (!referralCode.value) return '';
  const base = typeof window !== 'undefined' ? window.location.origin : '';
  return `${base}/?ref=${referralCode.value}`;
});

const copyCode = async () => {
  if (!referralCode.value) return;
  try {
    await navigator.clipboard.writeText(referralCode.value);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
  } catch {
    // Fallback handled by the UI
  }
};

const handleApply = async () => {
  if (!inputCode.value.trim()) return;

  const result = await applyReferral(inputCode.value.trim());
  message.value = result.message;
  messageType.value = result.success ? 'success' : 'error';

  if (result.success) {
    inputCode.value = '';
  }
};
</script>

<template>
  <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-xs p-6">
    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">{{ $t('loyalty.referralProgram') }}</h3>

    <div v-if="referralCode" class="mb-6">
      <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
        {{ $t('loyalty.shareCode') }}
        <span v-if="settings" class="font-semibold text-primary">+{{ settings.referralBonusReferrer }}</span>
        {{ $t('loyalty.pointsPerReferral') }}
      </p>

      <div class="flex items-center gap-2">
        <div class="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-700 rounded-lg font-mono text-lg font-bold text-gray-900 dark:text-white text-center tracking-wider">
          {{ referralCode }}
        </div>
        <button
          type="button"
          class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium rounded-lg transition-colors"
          :class="copied
            ? 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400'
            : 'bg-primary/10 text-primary hover:bg-primary/20'"
          @click="copyCode">
          <Icon :name="copied ? 'ion:checkmark' : 'ion:copy-outline'" size="16" />
          {{ copied ? $t('loyalty.copied') : $t('loyalty.copy') }}
        </button>
      </div>
    </div>

    <div class="border-t border-gray-100 dark:border-gray-700 pt-5">
      <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">{{ $t('loyalty.haveReferralCode') }}</p>

      <div class="flex items-center gap-2">
        <input
          v-model="inputCode"
          type="text"
          :placeholder="$t('loyalty.enterCode')"
          class="flex-1 px-4 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white uppercase tracking-wider"
          maxlength="8" />
        <Button
          size="sm"
          :disabled="!inputCode.trim() || isLoading"
          :loading="isLoading"
          @click="handleApply">
          {{ $t('loyalty.apply') }}
        </Button>
      </div>

      <div
        v-if="message"
        class="mt-3 px-4 py-3 rounded-lg text-sm"
        :class="messageType === 'success'
          ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
          : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'">
        {{ message }}
      </div>
    </div>
  </div>
</template>
