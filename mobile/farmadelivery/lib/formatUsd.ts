export function formatUsd(value: string | number): string {
  const n = typeof value === 'string' ? Number(value) : value;
  if (Number.isNaN(n)) {
    return String(value);
  }
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
}

export function formatQuantity(value: string): string {
  const n = Number(value);
  if (Number.isNaN(n)) {
    return value;
  }
  return new Intl.NumberFormat('es-VE', {
    maximumFractionDigits: 3,
  }).format(n);
}
