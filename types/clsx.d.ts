declare module 'clsx' {
  type ClassValue = string | number | boolean | null | undefined | ClassValue[] | { [key: string]: any }
  export function clsx(...inputs: ClassValue[]): string
  export default clsx
  export type { ClassValue }
}
