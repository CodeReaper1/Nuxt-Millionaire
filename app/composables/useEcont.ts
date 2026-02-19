/**
 * Composable for Econt shipping integration.
 *
 * Uses raw $fetch to the GraphQL endpoint instead of .gql files
 * so that nuxt-graphql-client's build-time schema validation
 * doesn't break when the WordPress plugin isn't installed yet.
 */
export interface EcontOffice {
  id: string;
  code: string;
  name: string;
  nameEn?: string;
  address: string;
  city: string;
  postCode?: string;
  latitude?: number | null;
  longitude?: number | null;
  workingTimeFrom?: string | null;
  workingTimeTo?: string | null;
  workingTimeHalfFrom?: string | null;
  workingTimeHalfTo?: string | null;
}

export interface EcontCity {
  id: string;
  name: string;
  nameEn?: string;
  postCode?: string;
  regionName?: string;
}

// ── Inline GraphQL documents ────────────────────────────────────────

const OFFICES_QUERY = `
  query GetEcontOffices($city: String, $search: String) {
    econtOffices(city: $city, search: $search) {
      id code name nameEn address city postCode
      latitude longitude
      workingTimeFrom workingTimeTo
      workingTimeHalfFrom workingTimeHalfTo
    }
  }
`;

const CITIES_QUERY = `
  query GetEcontCities($search: String) {
    econtCities(search: $search) {
      id name nameEn postCode regionName
    }
  }
`;

const SAVE_OFFICE_MUTATION = `
  mutation SaveEcontOffice(
    $orderId: Int!
    $officeCode: String!
    $officeName: String
    $officeAddress: String
    $officeCity: String
  ) {
    saveEcontOfficeToOrder(input: {
      orderId: $orderId
      officeCode: $officeCode
      officeName: $officeName
      officeAddress: $officeAddress
      officeCity: $officeCity
    }) {
      success
      message
    }
  }
`;

// ── Helper: raw GraphQL fetch ───────────────────────────────────────

async function gqlFetch<T = any>(
  endpoint: string,
  query: string,
  variables: Record<string, any> = {},
): Promise<T> {
  const res = await $fetch<{ data: T; errors?: any[] }>(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: { query, variables },
  });

  if (res.errors?.length) {
    throw new Error(res.errors[0].message || 'GraphQL error');
  }

  return res.data;
}

// ── Composable ──────────────────────────────────────────────────────

export function useEcont() {
  const runtimeConfig = useRuntimeConfig();
  const gqlHost: string =
    (runtimeConfig.public as any)?.['graphql-client']?.clients?.default?.host
    || (runtimeConfig.public as any)?.GQL_HOST
    || '/graphql';

  // ── Reactive state ─────────────────────────────────────────────────
  const cities = useState<EcontCity[]>('econtCities', () => []);
  const offices = useState<EcontOffice[]>('econtOffices', () => []);
  const selectedOffice = useState<EcontOffice | null>('econtSelectedOffice', () => null);
  const selectedCity = useState<string>('econtSelectedCity', () => '');
  const loading = useState<boolean>('econtLoading', () => false);
  const error = useState<string | null>('econtError', () => null);

  // ── Fetch cities ───────────────────────────────────────────────────
  async function fetchCities(search?: string): Promise<EcontCity[]> {
    loading.value = true;
    error.value = null;

    try {
      const data = await gqlFetch<{ econtCities: EcontCity[] }>(
        gqlHost, CITIES_QUERY, { search: search || null },
      );
      cities.value = data.econtCities || [];
      return cities.value;
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch cities';
      console.error('[useEcont] fetchCities error:', e);
      return [];
    } finally {
      loading.value = false;
    }
  }

  // ── Fetch offices ──────────────────────────────────────────────────
  async function fetchOffices(city?: string, search?: string): Promise<EcontOffice[]> {
    loading.value = true;
    error.value = null;

    try {
      const data = await gqlFetch<{ econtOffices: EcontOffice[] }>(
        gqlHost, OFFICES_QUERY, { city: city || null, search: search || null },
      );
      offices.value = data.econtOffices || [];
      return offices.value;
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch offices';
      console.error('[useEcont] fetchOffices error:', e);
      return [];
    } finally {
      loading.value = false;
    }
  }

  // ── Select an office ───────────────────────────────────────────────
  function selectOffice(office: EcontOffice | null): void {
    selectedOffice.value = office;
  }

  // ── Clear selection ────────────────────────────────────────────────
  function clearSelection(): void {
    selectedOffice.value = null;
    selectedCity.value = '';
    offices.value = [];
  }

  // ── Build checkout metaData entries ────────────────────────────────
  function getCheckoutMetaData(): Array<{ key: string; value: string }> {
    if (!selectedOffice.value) return [];

    const office = selectedOffice.value;
    return [
      { key: '_econt_office_code', value: office.code || office.id },
      { key: '_econt_office_name', value: office.name || '' },
      { key: '_econt_office_address', value: office.address || '' },
      { key: '_econt_office_city', value: office.city || '' },
    ];
  }

  // ── Check if a shipping method ID is Econt ─────────────────────────
  function isEcontShippingMethod(methodId: string | undefined | null): boolean {
    if (!methodId) return false;
    const id = methodId.toLowerCase();
    return id.includes('econt') || id.includes('еконт');
  }

  // ── Save office to order (post-checkout fallback) ──────────────────
  async function saveOfficeToOrder(
    orderId: number,
    office?: EcontOffice | null,
  ): Promise<boolean> {
    const o = office || selectedOffice.value;
    if (!o) return false;

    try {
      const data = await gqlFetch<{ saveEcontOfficeToOrder: { success: boolean } }>(
        gqlHost, SAVE_OFFICE_MUTATION, {
          orderId,
          officeCode: o.code || o.id,
          officeName: o.name,
          officeAddress: o.address,
          officeCity: o.city,
        },
      );
      return data.saveEcontOfficeToOrder?.success ?? false;
    } catch (e: any) {
      console.error('[useEcont] saveOfficeToOrder error:', e);
      return false;
    }
  }

  return {
    cities,
    offices,
    selectedOffice,
    selectedCity,
    loading,
    error,

    fetchCities,
    fetchOffices,
    selectOffice,
    clearSelection,
    getCheckoutMetaData,
    isEcontShippingMethod,
    saveOfficeToOrder,
  };
}
