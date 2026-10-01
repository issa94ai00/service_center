<template>
    <!-- Mounted once in the layout; opened from any print button's menu or the
         sidebar, so a clerk can set up the counter printer without the
         settings permission. -->
    <BaseModal
        :open="dialogOpen"
        title="تفضيلات الطباعة لهذا الجهاز"
        subtitle="حجم الورق وطريقة الطباعة على هذا المتصفح"
        max-width="max-w-xl"
        @close="dialogOpen = false"
    >
        <DevicePrintPreferences />
    </BaseModal>
</template>

<script setup>
import BaseModal from '../ui/BaseModal.vue';
import DevicePrintPreferences from './DevicePrintPreferences.vue';
import { usePrintPreferences } from '../../composables/usePrintPreferences';

const { dialogOpen } = usePrintPreferences();
</script>
