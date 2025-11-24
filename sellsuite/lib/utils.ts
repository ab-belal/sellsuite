import { clsx, type ClassValue } from 'clsx'

// Simplified helper: combine class names with clsx. Tailwind-specific merging removed.
export function cn(...inputs: ClassValue[]) {
  return clsx(inputs)
}
