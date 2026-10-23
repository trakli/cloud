<template>
  <div />
</template>

<script setup lang="ts">
definePageMeta({
  redirect: (to) => ({ path: '/settings', query: { ...to.query, tab: 'billing' } })
});
</script>
