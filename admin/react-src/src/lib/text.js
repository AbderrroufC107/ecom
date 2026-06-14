export function cleanText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim()
}

function cp1252Byte(character) {
  const code = character.charCodeAt(0)
  const map = {
    0x20ac: 0x80,
    0x201a: 0x82,
    0x0192: 0x83,
    0x201e: 0x84,
    0x2026: 0x85,
    0x2020: 0x86,
    0x2021: 0x87,
    0x02c6: 0x88,
    0x2030: 0x89,
    0x0160: 0x8a,
    0x2039: 0x8b,
    0x0152: 0x8c,
    0x017d: 0x8e,
    0x2018: 0x91,
    0x2019: 0x92,
    0x201c: 0x93,
    0x201d: 0x94,
    0x2022: 0x95,
    0x2013: 0x96,
    0x2014: 0x97,
    0x02dc: 0x98,
    0x2122: 0x99,
    0x0161: 0x9a,
    0x203a: 0x9b,
    0x0153: 0x9c,
    0x017e: 0x9e,
    0x0178: 0x9f,
  }

  return map[code] || (code <= 255 ? code : 63)
}

function arabicScore(value) {
  const matches = String(value || '').match(/[\u0600-\u06ff]/g)
  return matches ? matches.length : 0
}

export function decodeText(value) {
  const text = String(value || '')
  if (!/[ÃÂØÙ]/.test(text) || typeof TextDecoder === 'undefined') {
    return cleanText(text)
  }

  let current = text
  let best = text

  try {
    for (let pass = 0; pass < 5; pass += 1) {
      if (!/[ÃÂØÙ]/.test(current)) break

      const bytes = new Uint8Array(current.length)
      for (let index = 0; index < current.length; index += 1) {
        bytes[index] = cp1252Byte(current.charAt(index))
      }

      const next = new TextDecoder('utf-8').decode(bytes)
      if (!next || next === current) break

      current = next
      if (arabicScore(current) >= arabicScore(best)) {
        best = current
      }
    }
  } catch {
    return cleanText(text)
  }

  return cleanText(best)
}

export function normalizeHref(href) {
  if (!href || href === '#') return '#'
  const raw = String(href).split('/').pop() || href
  return raw.split('?')[0] || raw
}

export function currentFile() {
  return normalizeHref(window.location.pathname.split('/').pop() || 'index.php')
}

export function parseNumeric(value) {
  const number = Number(String(value || '').replace(/[^\d.-]/g, ''))
  return Number.isFinite(number) ? number : 0
}

export function getInitials(name) {
  const cleaned = decodeText(name)
  const parts = cleaned.split(/\s+/).filter(Boolean)
  if (parts.length === 0) return 'AD'
  return parts.slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
}
