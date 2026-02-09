/**
 * Composable for Econt shipping integration.
 *
 * Provides reactive state and methods to:
 *  - Fetch Econt cities & offices via GraphQL
 *  - Track selected office
 *  - Build metaData entries for the checkout mutation
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

export function useEcont() {
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
      const { econtCities } = await GqlGetEcontCities({ search: search || null });
      cities.value = (econtCities as EcontCity[]) || [];
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
      const { econtOffices } = await GqlGetEcontOffices({
        city: city || null,
        search: search || null,
      });
      offices.value = (econtOffices as EcontOffice[]) || [];
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
  /**
   * Returns an array of { key, value } objects that should be merged
   * into the checkout mutation's `metaData` array.
   *
   * Returns an empty array when no office is selected.
   */
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
      const { saveEcontOfficeToOrder } = await GqlSaveEcontOffice({
        orderId,
        officeCode: o.code || o.id,
        officeName: o.name,
        officeAddress: o.address,
        officeCity: o.city,
      });
      return saveEcontOfficeToOrder?.success ?? false;
    } catch (e: any) {
      console.error('[useEcont] saveOfficeToOrder error:', e);
      return false;
    }
  }

  return {
    // State
    cities,
    offices,
    selectedOffice,
    selectedCity,
    loading,
    error,

    // Methods
    fetchCities,
    fetchOffices,
    selectOffice,
    clearSelection,
    getCheckoutMetaData,
    isEcontShippingMethod,
    saveOfficeToOrder,
  };
}
