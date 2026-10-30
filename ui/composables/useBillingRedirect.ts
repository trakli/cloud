import { ref } from 'vue';
import { useApi } from '~/composables/useApi';
import { extractApiErrors } from '~/utils/apiErrors';

export function useBillingRedirect<Key extends string>() {
  const api = useApi();
  const busy = ref<Key | null>(null);
  const error = ref<string | null>(null);

  async function go<T extends { url?: string }>(key: Key, path: string, body?: object) {
    busy.value = key;
    error.value = null;
    try {
      const result = await api<{ data: T }>(path, { method: 'POST', body });
      if (result.data.url) await navigateTo(result.data.url, { external: true });
      return result.data;
    } catch (cause) {
      error.value = extractApiErrors(cause);
      return null;
    } finally {
      busy.value = null;
    }
  }

  return { busy, error, go };
}
