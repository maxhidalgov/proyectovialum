import { Canvg } from 'canvg'

/**
 * Convierte un SVG data-URI a JPEG data-URI para incrustar en el PDF.
 *
 * Estrategia (en orden de calidad):
 * 1. Renderizado nativo del browser vía blob URL + <img> + canvas
 *    → calidad perfecta (idéntica a lo que se ve en pantalla)
 *    → puede fallar si el canvas queda "tainted"
 * 2. canvg + extracción manual de texturas JPEG desde los <pattern>
 *    → calidad muy buena: ventana correcta + textura de vidrio real
 *    → funciona porque los JPEG se cargan como data: URI (same-origin)
 * 3. canvg con vidrio celeste sólido (fallback final)
 */
export async function svgDataUriToPng(svgDataUri, targetWidth = 320) {
  if (!svgDataUri) return null

  // ── Intento 1: renderizado nativo via blob URL ────────────────────────
  try {
    return await _svgViaBlobImg(svgDataUri, targetWidth)
  } catch (e) {
    console.debug('[useSvgToPng] nativo falló (' + e.message + ') → canvg+patterns')
  }

  // ── Intento 2: canvg + texturas JPEG de los <pattern> ────────────────
  try {
    return await _svgViaCanvgWithPatterns(svgDataUri, targetWidth)
  } catch (e) {
    console.debug('[useSvgToPng] canvg+patterns falló (' + e.message + ') → canvg básico')
  }

  // ── Intento 3: canvg con vidrio sólido (fallback final) ──────────────
  return _svgViaCanvgBasic(svgDataUri, targetWidth)
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers de decodificación compartidos
// ─────────────────────────────────────────────────────────────────────────────

function _decodeSvgText(svgDataUri) {
  const b64 = svgDataUri.replace(/^data:image\/svg\+xml;base64,\s*/, '').trim()
  const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0))
  let text = new TextDecoder('utf-8').decode(bytes)
  text = text.replace(/^﻿/, '')              // quitar BOM
  text = text.replace(/\s+width="[\d.]+mm"/gi, '') // quitar dimensiones físicas
  text = text.replace(/\s+height="[\d.]+mm"/gi, '')
  return text
}

function _getViewBoxAspect(svgDoc) {
  try {
    const vb = svgDoc.documentElement.getAttribute('viewBox')
    if (vb) {
      const parts = vb.trim().split(/[\s,]+/).map(Number)
      const [, , vbW, vbH] = parts
      if (vbW > 0 && vbH > 0) return { w: vbW, h: vbH, aspect: vbH / vbW }
    }
  } catch (_) {}
  return { w: 600, h: 900, aspect: 1.5 }
}

function _makeCanvas(width, height) {
  const canvas = document.createElement('canvas')
  canvas.width  = width
  canvas.height = height
  const ctx = canvas.getContext('2d')
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, width, height)
  return { canvas, ctx }
}

// ─────────────────────────────────────────────────────────────────────────────
// Opción 1: blob URL → <img> → canvas (calidad perfecta si no hay taint)
// Blob URLs son same-origin → mayor probabilidad de evitar taint que data URIs.
// ─────────────────────────────────────────────────────────────────────────────
function _svgViaBlobImg(svgDataUri, targetWidth) {
  return new Promise((resolve, reject) => {
    let blobUrl = null
    try {
      const svgText = _decodeSvgText(svgDataUri)
      const blob = new Blob([svgText], { type: 'image/svg+xml;charset=utf-8' })
      blobUrl = URL.createObjectURL(blob)
    } catch (e) {
      return reject(new Error('blob create error: ' + e.message))
    }

    const img = new Image()
    const cleanup = () => { if (blobUrl) { URL.revokeObjectURL(blobUrl); blobUrl = null } }

    img.onload = () => {
      let w = img.naturalWidth
      let h = img.naturalHeight
      if (!w || !h) { w = targetWidth; h = Math.round(targetWidth * 1.5) }

      const { canvas, ctx } = _makeCanvas(targetWidth, Math.round(targetWidth * h / w))
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
      cleanup()

      try {
        const result = canvas.toDataURL('image/jpeg', 0.90)
        console.debug('[useSvgToPng] nativo OK →', canvas.width + '×' + canvas.height)
        resolve(result)
      } catch (e) {
        reject(e) // SecurityError → canvas tainted
      }
    }

    img.onerror = () => { cleanup(); reject(new Error('img load error')) }
    img.src = blobUrl
  })
}

// ─────────────────────────────────────────────────────────────────────────────
// Opción 2: canvg + extracción de texturas JPEG desde <pattern>
//
// Flujo:
//  a) canvg dibuja la estructura (marco, flechas, texto) con vidrio = color marcador
//  b) Cargamos los JPEG de cada <pattern> como Image(src=dataURI) → no tainta canvas
//  c) Pixel-scan: reemplazamos los píxeles marcadores con píxeles del JPEG
//
// Resultado: textura de vidrio real dentro de la estructura correcta de la ventana.
// ─────────────────────────────────────────────────────────────────────────────
const GLASS_MARKER = { r: 197, g: 223, b: 240 }   // #c5dff0 — marcador canvg

async function _svgViaCanvgWithPatterns(svgDataUri, targetWidth) {
  const svgText = _decodeSvgText(svgDataUri)

  const parser   = new DOMParser()
  const svgDoc   = parser.parseFromString(svgText, 'image/svg+xml')
  const { w: svgW, h: svgH, aspect } = _getViewBoxAspect(svgDoc)
  const canvasH  = Math.round(targetWidth * aspect)
  const scale    = targetWidth / svgW

  // ── a) Extraer patrones con imágenes JPEG ──
  const patternDefs = []   // { id, patW, patH, href }
  svgDoc.querySelectorAll('pattern').forEach(pat => {
    const imgEl = pat.querySelector('image')
    if (!imgEl) return
    const href  = imgEl.getAttribute('href') || imgEl.getAttribute('xlink:href') || ''
    if (!href.startsWith('data:image/')) return
    const patW  = parseFloat(pat.getAttribute('width'))  || svgW
    const patH  = parseFloat(pat.getAttribute('height')) || svgH
    patternDefs.push({ id: pat.id, patW, patH, href })
  })

  // ── b) Renderizar con canvg (vidrio = marcador #c5dff0) ──
  let modSvg = svgText
  modSvg = modSvg.replace(/fill="url\(#[^"]+\)"/gi,   'fill="#c5dff0"')
  modSvg = modSvg.replace(/stroke="url\(#[^"]+\)"/gi, 'stroke="#c5dff0"')

  const { canvas, ctx } = _makeCanvas(targetWidth, canvasH)
  const v = Canvg.fromString(ctx, modSvg)
  await v.render()

  // Si no hay patrones con JPEG, devolver tal cual (vidrio sólido)
  if (patternDefs.length === 0) {
    console.debug('[useSvgToPng] canvg sin patrones →', canvas.width + '×' + canvas.height)
    return canvas.toDataURL('image/jpeg', 0.85)
  }

  // ── c) Cargar imágenes JPEG de patrones (data: URI → no tainta canvas) ──
  //    Construimos un map: markerColor → tileCanvas
  //    (si hay varios patrones usamos el mismo marcador #c5dff0 para todos;
  //     en Winperfil los patrones son variaciones de la misma textura de vidrio)
  const tileCanvas = await _buildGlassTileCanvas(patternDefs[0], scale, targetWidth, canvasH)

  if (!tileCanvas) {
    // no se pudo cargar la imagen → devolver vidrio sólido
    console.debug('[useSvgToPng] canvg sin tile →', canvas.width + '×' + canvas.height)
    return canvas.toDataURL('image/jpeg', 0.85)
  }

  // ── d) Pixel-swap: reemplazar píxeles marcadores con píxeles de la textura ──
  const srcData  = ctx.getImageData(0, 0, canvas.width, canvas.height)
  const tileCtx  = tileCanvas.getContext('2d')
  const tileData = tileCtx.getImageData(0, 0, tileCanvas.width, tileCanvas.height)
  const d = srcData.data
  const t = tileData.data
  const tw = tileCanvas.width
  const th = tileCanvas.height

  const { r: mr, g: mg, b: mb } = GLASS_MARKER
  const TOLERANCE = 8   // tolerancia de color para evitar falsos positivos

  for (let i = 0; i < d.length; i += 4) {
    if (
      Math.abs(d[i]   - mr) <= TOLERANCE &&
      Math.abs(d[i+1] - mg) <= TOLERANCE &&
      Math.abs(d[i+2] - mb) <= TOLERANCE
    ) {
      // Calcular posición en el tile (repetición)
      const px = (i / 4) % canvas.width
      const py = Math.floor((i / 4) / canvas.width)
      const tx = px % tw
      const ty = py % th
      const ti = (ty * tw + tx) * 4
      d[i]   = t[ti]
      d[i+1] = t[ti+1]
      d[i+2] = t[ti+2]
      // d[i+3] = 255  // alpha ya es 255
    }
  }

  ctx.putImageData(srcData, 0, 0)

  console.debug('[useSvgToPng] canvg+patterns OK →', canvas.width + '×' + canvas.height)
  return canvas.toDataURL('image/jpeg', 0.88)
}

/**
 * Crea un canvas con la textura del patrón, escalada al tamaño canvas.
 * Carga el JPEG desde data: URI — no tainta el canvas.
 */
async function _buildGlassTileCanvas(patDef, scale, canvasW, canvasH) {
  return new Promise(resolve => {
    const img = new Image()
    img.onload = () => {
      // El tile cubre el área completa del canvas (equivale a un patrón que llena la ventana)
      const tileW = Math.max(1, Math.round(patDef.patW * scale))
      const tileH = Math.max(1, Math.round(patDef.patH * scale))

      const tc  = document.createElement('canvas')
      tc.width  = tileW
      tc.height = tileH
      tc.getContext('2d').drawImage(img, 0, 0, tileW, tileH)
      resolve(tc)
    }
    img.onerror = () => resolve(null)
    img.src = patDef.href   // data:image/jpeg;base64,... → same-origin, no taint
  })
}

// ─────────────────────────────────────────────────────────────────────────────
// Opción 3: canvg con vidrio sólido celeste (fallback final)
// ─────────────────────────────────────────────────────────────────────────────
async function _svgViaCanvgBasic(svgDataUri, targetWidth) {
  const svgText = _decodeSvgText(svgDataUri)

  const parser   = new DOMParser()
  const svgDoc   = parser.parseFromString(svgText, 'image/svg+xml')
  const { aspect } = _getViewBoxAspect(svgDoc)

  let modSvg = svgText
  modSvg = modSvg.replace(/fill="url\(#[^"]+\)"/gi,   'fill="#c5dff0"')
  modSvg = modSvg.replace(/stroke="url\(#[^"]+\)"/gi, 'stroke="#c5dff0"')

  const { canvas, ctx } = _makeCanvas(targetWidth, Math.round(targetWidth * aspect))

  const v = Canvg.fromString(ctx, modSvg)
  await v.render()

  console.debug('[useSvgToPng] canvg básico OK →', canvas.width + '×' + canvas.height)
  return canvas.toDataURL('image/jpeg', 0.85)
}
