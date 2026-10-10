/**
 * The `deviceLabel` every auth endpoint requires (max 100 chars), shown to the person in their
 * sessions list later. "Chrome on Android", "Safari on iPhone", "Firefox on Windows".
 */
export function deviceLabel(userAgent = navigator.userAgent): string {
  const browser =
    /SamsungBrowser/.test(userAgent) ? 'Samsung Internet'
    : /Edg\//.test(userAgent) ? 'Edge'
    : /Firefox\//.test(userAgent) ? 'Firefox'
    : /Chrome\//.test(userAgent) ? 'Chrome'
    : /Safari\//.test(userAgent) ? 'Safari'
    : 'Browser'

  const system =
    /Android/.test(userAgent) ? 'Android'
    : /iPhone|iPad/.test(userAgent) ? 'iPhone'
    : /Windows/.test(userAgent) ? 'Windows'
    : /Mac OS X/.test(userAgent) ? 'Mac'
    : /Linux/.test(userAgent) ? 'Linux'
    : 'the web'

  return `${browser} on ${system}`
}
