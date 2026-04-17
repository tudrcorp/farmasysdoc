const baseUrl = process.env.EXPO_PUBLIC_API_URL?.replace(/\/$/, '') ?? '';

export type DeliveryUser = {
  id: number;
  name: string;
  email: string;
};

export type LoginResponse = {
  token: string;
  token_type: string;
  user: DeliveryUser;
};

export type PendingOrderPartner = {
  code: string | null;
  name: string | null;
};

export type PendingOrderClient = {
  name: string | null;
  phone: string | null;
};

export type PendingOrderDelivery = {
  recipient_name: string | null;
  phone: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  notes: string | null;
  latitude?: number | null;
  longitude?: number | null;
};

export type PendingOrderLine = {
  product_name: string;
  quantity: string;
};

export type PendingJobKind = 'order' | 'transfer';

/** Etapa en el flujo del repartidor (pool pendientes vs. tareas ya tomadas). */
export type DeliveryJobStage = 'pendiente' | 'en_proceso';

export type PendingTransferBranch = {
  name: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
};

/** Fila unificada: pedido (cliente o aliado) o traslado de inventario. */
export type PendingJob = {
  kind: PendingJobKind;
  /** Viene del API; la UI lo usa para colores y texto de estado. */
  job_stage: DeliveryJobStage;
  id: number;
  sort_at: string | null;
  order_number: string | null;
  status: string;
  status_label: string;
  source: string;
  source_label: string;
  items_count: number;
  created_at: string | null;
  lines: PendingOrderLine[];
  partner: PendingOrderPartner | null;
  client: PendingOrderClient | null;
  delivery: PendingOrderDelivery;
  from_branch?: PendingTransferBranch | null;
  to_branch?: PendingTransferBranch | null;
};

/** @deprecated Usa PendingJob; se mantiene el nombre para imports existentes. */
export type PendingOrder = PendingJob;

export function deliveryJobKey(job: PendingJob): string {
  return `${job.kind}-${job.id}`;
}

function parseDeliveryJobRow(row: unknown): PendingJob {
  const r = row as PendingJob & { lines?: unknown; job_stage?: string };
  const rawLines = r.lines;
  const lines: PendingOrderLine[] = Array.isArray(rawLines)
    ? rawLines
        .filter(
          (cell): cell is PendingOrderLine =>
            cell !== null &&
            typeof cell === 'object' &&
            typeof (cell as PendingOrderLine).product_name === 'string' &&
            typeof (cell as PendingOrderLine).quantity === 'string',
        )
        .map((cell) => ({
          product_name: cell.product_name,
          quantity: cell.quantity,
        }))
    : [];

  const kind: PendingJobKind = r.kind === 'transfer' ? 'transfer' : 'order';
  const job_stage: DeliveryJobStage =
    r.job_stage === 'en_proceso' ? 'en_proceso' : 'pendiente';

  return {
    kind,
    job_stage,
    id: r.id,
    sort_at: r.sort_at ?? r.created_at ?? null,
    order_number: r.order_number,
    status: r.status,
    status_label: r.status_label,
    source: r.source ?? (kind === 'transfer' ? 'traslado' : 'cliente'),
    source_label: r.source_label ?? (kind === 'transfer' ? 'Traslado' : 'Pedido'),
    items_count: r.items_count,
    created_at: r.created_at,
    lines,
    partner: r.partner,
    client: r.client,
    delivery: r.delivery ?? {
      recipient_name: null,
      phone: null,
      address: null,
      city: null,
      state: null,
      notes: null,
    },
    from_branch: r.from_branch,
    to_branch: r.to_branch,
  };
}

export type OrderDetailItem = {
  id: number;
  product_name: string;
  sku: string | null;
  quantity: string;
};

export type OrderDetailPartner = {
  code: string | null;
  name: string | null;
  phone: string | null;
};

export type OrderDetailClient = {
  name: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
};

export type OrderDetailDelivery = {
  recipient_name: string | null;
  phone: string | null;
  recipient_document: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  notes: string | null;
  latitude?: number | null;
  longitude?: number | null;
};

export type OrderDetail = {
  id: number;
  order_number: string | null;
  status: string;
  status_label: string;
  notes: string | null;
  created_at: string | null;
  scheduled_delivery_at: string | null;
  partner: OrderDetailPartner | null;
  client: OrderDetailClient | null;
  delivery: OrderDetailDelivery;
  items: OrderDetailItem[];
};

const DEVICE_NETWORK_HINT =
  'Con Expo Go en un iPhone o Android físico: la Mac y el teléfono en la misma Wi‑Fi. ' +
  'No uses localhost ni dominios *.test (solo existen en tu Mac). ' +
  'En la raíz del Laravel ejecuta: php artisan serve --host=0.0.0.0 --port=8000. ' +
  'En mobile/farmadelivery/.env pon EXPO_PUBLIC_API_URL=http://IP_DE_TU_MAC:8000 (sin barra final; HTTP en desarrollo). ' +
  'La IP de la Mac: Ajustes del Sistema → Red. Reinicia Metro: npx expo start -c. ' +
  'En Android, adb reverse (solo USB): adb reverse tcp:8000 tcp:8000 y entonces puedes usar http://127.0.0.1:8000. ' +
  'Para HTTPS público: túnel (ngrok, Cloudflare Tunnel) apuntando a Laravel.';

function isLikelyNetworkFailure(error: unknown): boolean {
  const msg =
    error instanceof Error
      ? error.message
      : typeof error === 'string'
        ? error
        : '';
  const m = msg.toLowerCase();
  return (
    m.includes('network request failed') ||
    m.includes('failed to fetch') ||
    m.includes('networkerror') ||
    m.includes('could not connect') ||
    m.includes('connection refused') ||
    (m.includes('ssl') && m.includes('fail')) ||
    m.includes('certificate')
  );
}

/**
 * fetch con mensaje útil cuando el dispositivo no alcanza el backend (URL / red).
 */
async function apiFetch(url: string, init?: RequestInit): Promise<Response> {
  try {
    return await fetch(url, init);
  } catch (error) {
    if (isLikelyNetworkFailure(error)) {
      throw new Error(`Sin conexión al servidor.\n\n${DEVICE_NETWORK_HINT}`);
    }
    throw error;
  }
}

function errorMessageFromBody(data: unknown, fallback: string): string {
  if (data && typeof data === 'object') {
    const o = data as Record<string, unknown>;
    if (typeof o.message === 'string') {
      return o.message;
    }
    const errors = o.errors;
    if (errors && typeof errors === 'object') {
      const first = Object.values(errors as Record<string, string[]>).flat()[0];
      if (typeof first === 'string') {
        return first;
      }
    }
  }
  return fallback;
}

export function getApiBaseUrl(): string {
  if (!baseUrl) {
    throw new Error(
      'Configura EXPO_PUBLIC_API_URL en .env (copia desde .env.example).',
    );
  }
  return baseUrl;
}

export async function loginRequest(
  email: string,
  password: string,
): Promise<LoginResponse> {
  const base = getApiBaseUrl();
  const res = await apiFetch(`${base}/api/v1/delivery/auth/login`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email: email.trim(), password }),
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudo iniciar sesión. Intenta de nuevo.'),
    );
  }
  return data as LoginResponse;
}

export async function fetchPendingOrders(token: string): Promise<PendingJob[]> {
  const base = getApiBaseUrl();
  const res = await apiFetch(`${base}/api/v1/delivery/orders/pending`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(data, 'Sesión expirada o sin permiso. Vuelve a iniciar sesión.'),
    );
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudieron cargar los pedidos.'),
    );
  }
  const body = data as { data?: unknown[] };
  if (!Array.isArray(body.data)) {
    return [];
  }
  return body.data.map(parseDeliveryJobRow);
}

export async function fetchActiveJobs(token: string): Promise<PendingJob[]> {
  const base = getApiBaseUrl();
  const res = await apiFetch(`${base}/api/v1/delivery/jobs/active`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(data, 'Sesión expirada o sin permiso. Vuelve a iniciar sesión.'),
    );
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudieron cargar las tareas en gestión.'),
    );
  }
  const body = data as { data?: unknown[] };
  if (!Array.isArray(body.data)) {
    return [];
  }
  return body.data.map(parseDeliveryJobRow);
}

export type TakeOrderResponse = {
  message: string;
  data: {
    id: number;
    order_number: string | null;
    status: string;
    status_label: string;
  };
};

export type DeliveryNavigationDestination = {
  lat: number;
  lng: number;
  formatted_address: string;
};

/** Ruta por calles calculada en el servidor (Directions + clave de Laravel). */
export type DeliveryNavigationRoute = {
  encoded_polyline: string;
  distance_meters: number;
  duration_seconds: number;
};

export type DeliveryNavigationData = {
  order_id?: number;
  /** Presente solo en respuesta de traslados; si falta, es navegación de pedido. */
  transfer_id?: number | null;
  order_number: string | null;
  address_label: string;
  destination: DeliveryNavigationDestination | null;
  geocoding_configured: boolean;
  destination_from_coordinates?: boolean;
  route?: DeliveryNavigationRoute | null;
};

function coerceDestination(raw: unknown): DeliveryNavigationDestination | null {
  if (raw === null || raw === undefined || typeof raw !== 'object') {
    return null;
  }
  const o = raw as Record<string, unknown>;
  const lat = Number(o.lat);
  const lng = Number(o.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }
  const formatted =
    typeof o.formatted_address === 'string' ? o.formatted_address : '';

  return { lat, lng, formatted_address: formatted };
}

function parseNavigationData(raw: unknown): DeliveryNavigationData {
  const d = raw as DeliveryNavigationData & {
    route?: unknown;
    destination?: unknown;
  };
  const destination = coerceDestination(d.destination);

  let route: DeliveryNavigationRoute | null | undefined = undefined;
  if (d.route !== undefined && d.route !== null && typeof d.route === 'object') {
    const r = d.route as Record<string, unknown>;
    const enc = r.encoded_polyline;
    if (typeof enc === 'string' && enc.length > 0) {
      const dm = Number(r.distance_meters);
      const ds = Number(r.duration_seconds);
      route = {
        encoded_polyline: enc,
        distance_meters: Number.isFinite(dm) ? dm : 0,
        duration_seconds: Number.isFinite(ds) ? ds : 0,
      };
    } else {
      route = null;
    }
  } else if (d.route === null) {
    route = null;
  }

  return {
    ...d,
    destination,
    route,
  };
}

export async function takeDeliveryOrder(
  token: string,
  orderId: number,
): Promise<TakeOrderResponse> {
  const base = getApiBaseUrl();
  const res = await apiFetch(`${base}/api/v1/delivery/orders/${orderId}/take`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(data, 'Sesión expirada o sin permiso. Vuelve a iniciar sesión.'),
    );
  }
  if (res.status === 404) {
    throw new Error(
      errorMessageFromBody(data, 'Este pedido ya no está disponible o no existe.'),
    );
  }
  if (res.status === 409) {
    throw new Error(errorMessageFromBody(data, 'Conflicto al tomar el pedido.'));
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudo tomar el pedido. Intenta de nuevo.'),
    );
  }
  const body = data as TakeOrderResponse;
  if (!body.data || typeof body.data !== 'object') {
    throw new Error('Respuesta inválida del servidor.');
  }
  return body;
}

export type CompleteDeliveryOrderResponse = {
  message: string;
  data: {
    id: number;
    order_number: string | null;
    status: string;
    status_label: string;
  };
};

/**
 * Sube evidencia fotográfica y marca el pedido como finalizado en servidor.
 */
export async function completeDeliveryOrder(
  token: string,
  orderId: number,
  imageUri: string,
  mimeType?: string | null,
): Promise<CompleteDeliveryOrderResponse> {
  const base = getApiBaseUrl();
  const type =
    mimeType && mimeType.length > 0 ? mimeType : 'image/jpeg';
  const ext = type.includes('png')
    ? 'png'
    : type.includes('webp')
      ? 'webp'
      : 'jpg';

  const form = new FormData();
  form.append(
    'evidence',
    {
      uri: imageUri,
      name: `entrega-${orderId}.${ext}`,
      type,
    } as unknown as Blob,
  );

  const res = await apiFetch(`${base}/api/v1/delivery/orders/${orderId}/complete`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: form,
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(data, 'Sesión expirada o sin permiso. Vuelve a iniciar sesión.'),
    );
  }
  if (res.status === 404) {
    throw new Error(
      errorMessageFromBody(
        data,
        'Este pedido ya no está en ruta contigo o no existe.',
      ),
    );
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(
        data,
        'No se pudo registrar la entrega. Revisa la foto e intenta de nuevo.',
      ),
    );
  }
  const body = data as CompleteDeliveryOrderResponse;
  if (!body.data || typeof body.data !== 'object') {
    throw new Error('Respuesta inválida del servidor.');
  }
  return body;
}

export type TakeTransferResponse = {
  message: string;
  data: {
    id: number;
    code: string;
    status: string;
    status_label: string;
  };
};

export async function takeDeliveryTransfer(
  token: string,
  transferId: number,
): Promise<TakeTransferResponse> {
  const base = getApiBaseUrl();
  const res = await apiFetch(
    `${base}/api/v1/delivery/transfers/${transferId}/take`,
    {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
    },
  );
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(data, 'Sesión expirada o sin permiso. Vuelve a iniciar sesión.'),
    );
  }
  if (res.status === 404) {
    throw new Error(
      errorMessageFromBody(data, 'Este traslado ya no está disponible.'),
    );
  }
  if (res.status === 409) {
    throw new Error(errorMessageFromBody(data, 'Conflicto al tomar el traslado.'));
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudo tomar el traslado. Intenta de nuevo.'),
    );
  }
  const body = data as TakeTransferResponse;
  if (!body.data || typeof body.data !== 'object') {
    throw new Error('Respuesta inválida del servidor.');
  }
  return body;
}

export async function fetchDeliveryNavigation(
  token: string,
  orderId: number,
  origin?: { lat: number; lng: number },
): Promise<DeliveryNavigationData> {
  const base = getApiBaseUrl();
  let url = `${base}/api/v1/delivery/orders/${orderId}/navigation`;
  if (origin) {
    url += `?${new URLSearchParams({
      origin_lat: String(origin.lat),
      origin_lng: String(origin.lng),
    }).toString()}`;
  }
  const res = await apiFetch(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(
        data,
        'Sesión expirada o sin permiso. Vuelve a iniciar sesión.',
      ),
    );
  }
  if (res.status === 404) {
    throw new Error(
      errorMessageFromBody(
        data,
        'Pedido no encontrado o ya no está asignado a ti.',
      ),
    );
  }
  if (res.status === 422) {
    throw new Error(
      errorMessageFromBody(data, 'No hay dirección de entrega para este pedido.'),
    );
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudo cargar la ruta del pedido.'),
    );
  }
  const body = data as { data?: unknown };
  if (!body.data || typeof body.data !== 'object') {
    throw new Error('Respuesta inválida del servidor.');
  }
  return parseNavigationData(body.data);
}

export async function fetchTransferNavigation(
  token: string,
  transferId: number,
  origin?: { lat: number; lng: number },
): Promise<DeliveryNavigationData> {
  const base = getApiBaseUrl();
  let url = `${base}/api/v1/delivery/transfers/${transferId}/navigation`;
  if (origin) {
    url += `?${new URLSearchParams({
      origin_lat: String(origin.lat),
      origin_lng: String(origin.lng),
    }).toString()}`;
  }
  const res = await apiFetch(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(
        data,
        'Sesión expirada o sin permiso. Vuelve a iniciar sesión.',
      ),
    );
  }
  if (res.status === 404) {
    throw new Error(
      errorMessageFromBody(
        data,
        'Traslado no encontrado o no está asignado a ti.',
      ),
    );
  }
  if (res.status === 422) {
    throw new Error(
      errorMessageFromBody(
        data,
        'No hay dirección de destino para este traslado.',
      ),
    );
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudo cargar la ruta del traslado.'),
    );
  }
  const body = data as { data?: unknown };
  if (!body.data || typeof body.data !== 'object') {
    throw new Error('Respuesta inválida del servidor.');
  }
  return parseNavigationData(body.data);
}

export type TransferDetail = {
  id: number;
  code: string;
  status: string;
  status_label: string;
  created_at: string | null;
  from_branch: {
    name: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    phone: string | null;
  } | null;
  to_branch: {
    name: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    phone: string | null;
  } | null;
  items: { id: number; product_name: string; quantity: string }[];
};

export async function fetchTransferDetail(
  token: string,
  transferId: number,
): Promise<TransferDetail> {
  const base = getApiBaseUrl();
  const res = await apiFetch(`${base}/api/v1/delivery/transfers/${transferId}`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(data, 'Sesión expirada o sin permiso. Vuelve a iniciar sesión.'),
    );
  }
  if (res.status === 404) {
    throw new Error(
      errorMessageFromBody(data, 'Traslado no encontrado.'),
    );
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudo cargar el traslado.'),
    );
  }
  const body = data as { data?: TransferDetail };
  if (!body.data || typeof body.data !== 'object') {
    throw new Error('Respuesta inválida del servidor.');
  }
  return body.data;
}

export async function fetchOrderDetail(
  token: string,
  orderId: number,
): Promise<OrderDetail> {
  const base = getApiBaseUrl();
  const res = await apiFetch(`${base}/api/v1/delivery/orders/${orderId}`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data: unknown = await res.json().catch(() => ({}));
  if (res.status === 401 || res.status === 403) {
    throw new Error(
      errorMessageFromBody(data, 'Sesión expirada o sin permiso. Vuelve a iniciar sesión.'),
    );
  }
  if (res.status === 404) {
    throw new Error(
      errorMessageFromBody(data, 'Este pedido ya no está disponible o no existe.'),
    );
  }
  if (!res.ok) {
    throw new Error(
      errorMessageFromBody(data, 'No se pudo cargar el pedido.'),
    );
  }
  const body = data as { data?: OrderDetail };
  if (!body.data || typeof body.data !== 'object') {
    throw new Error('Respuesta inválida del servidor.');
  }
  return body.data;
}

export async function logoutRequest(token: string): Promise<void> {
  const base = getApiBaseUrl();
  const res = await apiFetch(`${base}/api/v1/delivery/auth/logout`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  if (!res.ok) {
    const data: unknown = await res.json().catch(() => ({}));
    throw new Error(errorMessageFromBody(data, 'No se pudo cerrar sesión.'));
  }
}
