<template>
  <v-switch
    v-model="hoja1Adelante"
    label="Hoja 1 adelante"
  />

 <v-stage ref="stageRef" :config="stageConfig">
    <v-layer>
      <!-- Marco con mitras -->
      <v-line v-bind="topMitra" />
      <v-line v-bind="rightMitra" />
      <v-line v-bind="bottomMitra" />
      <v-line v-bind="leftMitra" />

      <!-- Hojas, mitras y números según orden -->
      <template v-if="hoja1Adelante">
        <!-- Hoja 2 detrás -->
        <v-rect v-bind="vidrio2" />
        
        <v-line
          v-for="(mitra, i) in hoja2Mitras"
          :key="'mitra-h2-' + i"
          v-bind="getMitraProps(mitra.points)"
        />
        <v-text
          :x="hoja2X + hojaAncho / 2 - 10"
          :y="hoja2Y + hojaAlto / 2 - 20"
          text="2"
          fontSize="30"
          fontStyle="bold"
          fill="black"
        />

        <!-- Hoja 1 adelante -->
        <v-rect v-bind="vidrio1" />
        <v-line
          v-for="(mitra, i) in hoja1Mitras"
          :key="'mitra-h1-' + i"
          v-bind="getMitraProps(mitra.points)"
        />
        <v-text
          :x="hoja1X + hojaAncho / 2 - 10"
          :y="hoja1Y + hojaAlto / 2 - 20"
          text="1"
          fontSize="30"
          fontStyle="bold"
          fill="black"
        />
      </template>

      <template v-else>
        <!-- Hoja 1 detrás -->
        <v-rect v-bind="vidrio1" />
        <v-line
          v-for="(mitra, i) in hoja1Mitras"
          :key="'mitra-h1-' + i"
          v-bind="getMitraProps(mitra.points)"
        />
        <v-text
          :x="hoja1X + hojaAncho / 2 - 10"
          :y="hoja1Y + hojaAlto / 2 - 20"
          text="2"
          fontSize="30"
          fontStyle="bold"
          fill="black"
        />

        <!-- Hoja 2 adelante -->
        <v-rect v-bind="vidrio2" />
        <v-line
          v-for="(mitra, i) in hoja2Mitras"
          :key="'mitra-h2-' + i"
          v-bind="getMitraProps(mitra.points)"
        />
        <v-text
          :x="hoja2X + hojaAncho / 2 - 10"
          :y="hoja2Y + hojaAlto / 2 - 20"
          text="1"
          fontSize="30"
          fontStyle="bold"
          fill="black"
        />
       
      </template>

      <!-- Etiquetas -->
      <v-text v-bind="widthLabel" />
      <v-text v-bind="heightLabel" />
      <v-text v-bind="indicador1" />
      <v-text v-bind="indicador2" />
      <Manilla
        v-if="mostrarManilla1"
        :x="hoja1X + hojaMarcoAncho - escala*35"  
        :y="hoja1Y + hojaAlto / 2"
        :escalaManilla="escala*4"
      />

      <Manilla
        v-if="mostrarManilla2"
        :x="hoja2X + hojaAncho - hojaMarcoAncho + escala*35"
        :y="hoja2Y + hojaAlto / 2"
        :escalaManilla="escala*4"
      />
      
    </v-layer>
  </v-stage>
</template>




<script setup>
import { computed, onMounted } from 'vue'
import { watchEffect } from 'vue'
import { ref, watch } from 'vue'
import Manilla from '@/components/Manilla.vue'
import robleUrl from '@/assets/images/roble.png'
import nogalUrl from '@/assets/images/nogal.png'


const stageRef = ref(null)

const exportarImagen = () => {
  if (stageRef.value) {
    const dataURL = stageRef.value.getStage().toDataURL({ pixelRatio: 1,quality: 0.7, })
    return dataURL
  }
  return null
}

defineExpose({ exportarImagen }) // esto permite usar el método desde el padree


const texturas = {
  roble: null,
  nogal: null,
}

onMounted(() => {
  const cargarTextura = (url, key) => {
    const img = new Image()
    img.src = url
    img.onload = () => {
      texturas[key] = img
    }
  }

  cargarTextura(robleUrl, 'roble')
  cargarTextura(nogalUrl, 'nogal')
})



const props = defineProps({
  ancho: Number,
  alto: Number,
  colorMarco: { type: String, default: 'blanco' },
  hojasMoviles: { type: Number, default: '2' },     // cuántas hojas se mueven
  hojaMovilSeleccionada: { type: Number, default: 1 }, // cuál hoja se mueve (1 o 2)
  ordenHoja1AlFrente: { type: Boolean, default: true },
  color: Object, // o puede ser 'color: { type: Object, required: true }'
})

const colorNombre = computed(() => props.colorMarco.toLowerCase())


function getMitraProps(points) {
  const color = colorNombre.value.toLowerCase()

  // Si hay textura para ese color
  if (['roble', 'nogal'].includes(color) && texturas[color]) {
    return {
      points,
      closed: true,
      fillPatternImage: texturas[color],
      fillPatternRepeat: 'repeat',
      fillPatternScale: { x: 0.2, y: 0.2 },
      stroke: 'black',
    }
  }

  // Si es color plano normal
  return {
    points,
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
  }
}



const offset = 40
const marcoAnchoOriginal = 44
const hojaMarcoAnchoOriginal = 66
const traslape = 20 // cuánto traslapan las hojas
const hoja1Adelante = ref(props.ordenHoja1AlFrente)
const mostrarManilla1 = computed(() => hoja1EsMovil.value)
const mostrarManilla2 = computed(() => hoja2EsMovil.value)

watch(() => props.ordenHoja1AlFrente, (val) => {
  hoja1Adelante.value = val
})


// Computadas para saber si cada hoja es móvil
const hoja1EsMovil = computed(() =>
  props.hojasMoviles === 2 || props.hojaMovilSeleccionada === 1
)
const hoja2EsMovil = computed(() =>
  props.hojasMoviles === 2 || props.hojaMovilSeleccionada === 2
)

const colorHexMap = {
  blanco: '#ffffff',
  negro: '#0a0a0a',
  gris: '#808080',
  grafito: '#2f2f2f',
  nogal: '#8b5a2b',
}

const colorMarcoHex = computed(() => {
  const c = props.colorMarco?.toLowerCase?.()
  return colorHexMap[c] || '#ffffff'
})

const maxCanvasSize = 300
const escala = computed(() => {
  const escalaAncho = maxCanvasSize / props.ancho
  const escalaAlto = maxCanvasSize / props.alto
  return Math.min(escalaAncho, escalaAlto, 1) * 0.9
})

const marcoAncho = computed(() => marcoAnchoOriginal * escala.value)
const hojaMarcoAncho = computed(() => hojaMarcoAnchoOriginal * escala.value)

const screenWidth = computed(() => props.ancho * escala.value)
const screenHeight = computed(() => props.alto * escala.value)

const stageConfig = {
  width: 400,
  height: 400,
}

const traslapeEscalado = computed(() => traslape * escala.value)

function getMitraMarco(points) {
  const nombre = props.colorMarco.toLowerCase()

  if (['roble', 'nogal'].includes(nombre) && texturas[nombre]) {
    return {
      points,
      closed: true,
      fillPatternImage: texturas[nombre],
      fillPatternRepeat: 'repeat',
      fillPatternScale: { x: 0.2, y: 0.2 },
      stroke: 'black',
    }
  }

  return {
    points,
    closed: true,
    fill: colorMarcoHex.value,
    stroke: 'black',
  }
}



// === MARCO MITRAS ===
const topMitra = computed(() =>
  getMitraMarco([
    offset, offset,
    offset + screenWidth.value, offset,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + marcoAncho.value,
  ])
)
const rightMitra = computed(() =>
  getMitraMarco([
    offset + screenWidth.value, offset,
    offset + screenWidth.value, offset + screenHeight.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + marcoAncho.value,
  ])
)
const bottomMitra = computed(() =>
  getMitraMarco([
    offset + screenWidth.value, offset + screenHeight.value,
    offset, offset + screenHeight.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
    offset + screenWidth.value - marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ])
)
const leftMitra = computed(() =>
  getMitraMarco([
    offset, offset + screenHeight.value,
    offset, offset,
    offset + marcoAncho.value, offset + marcoAncho.value,
    offset + marcoAncho.value, offset + screenHeight.value - marcoAncho.value,
  ])
)

// === VIDRIOS (HOJAS)
const hojaAncho = computed(() => (screenWidth.value - marcoAncho.value * 2 + traslape) / 2)
const hojaAlto = computed(() => screenHeight.value - marcoAncho.value * 2)

// H1
const hoja1X = computed(() => offset + marcoAncho.value)
const hoja1Y = computed(() => offset + marcoAncho.value)

// H2 (traslapada a izquierda)
const hoja2X = computed(() => {
  return offset + screenWidth.value - marcoAncho.value - hojaAncho.value + traslapeEscalado.value - hojaMarcoAncho.value * 0.22
})

const hoja2Y = hoja1Y



// === MITRAS HOJAS DINÁMICAS SEGÚN ADELANTE ===

const hoja1Mitras = computed(() => {
  if (hoja1Adelante.value) {
    // Hoja 1 adelante => se dibujan sus 4 mitras completas
    return [
      {
        points: [
          hoja1X.value, hoja1Y.value,
          hoja1X.value + hojaAncho.value, hoja1Y.value,
          hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
          hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
        ],
          closed: true,
          fill: colorMarcoHex.value,
          stroke: 'black',
      },
      {
        points: [
          hoja1X.value + hojaAncho.value, hoja1Y.value,
          hoja1X.value + hojaAncho.value, hoja1Y.value + hojaAlto.value,
          hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
          hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
        ],
          closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
      },
      {
        points: [
          hoja1X.value + hojaAncho.value, hoja1Y.value + hojaAlto.value,
          hoja1X.value, hoja1Y.value + hojaAlto.value,
          hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
          hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
        ],
          closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
      },
      {
        points: [
          hoja1X.value, hoja1Y.value + hojaAlto.value,
          hoja1X.value, hoja1Y.value,
          hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
          hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
        ],
          closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
      },
    ]
} else {
  // Hoja 1 atrás => 4 mitras, pero derecha y superior camufladas
  return [
    // 🔵 Mitra inferior (visible)
    {
      points: [
        hoja1X.value, hoja1Y.value + hojaAlto.value,
        hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
        hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
        hoja1X.value + hojaAncho.value, hoja1Y.value + hojaAlto.value,
      ],
      closed: true,
      fill: colorMarcoHex.value,
      stroke: 'black',
    },

    // 🔵 Mitra izquierda (visible)
    {
      points: [
        hoja1X.value, hoja1Y.value + hojaAlto.value,
        hoja1X.value, hoja1Y.value,
        hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
        hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
      ],
      closed: true,
      fill: colorMarcoHex.value,
      stroke: 'black',
    },

    // ⚫ Mitra superior (oculta visualmente)
    {
      points: [
        hoja1X.value, hoja1Y.value,
        hoja1X.value + hojaAncho.value, hoja1Y.value,
        hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
        hoja1X.value + hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
      ],
      closed: true,
      fill: colorMarcoHex.value,
      stroke: 'black', 
    },

    // ⚫ Mitra derecha (oculta visualmente)
    {
      points: [
        hoja1X.value + hojaAncho.value, hoja1Y.value,
        hoja1X.value + hojaAncho.value, hoja1Y.value + hojaAlto.value,
        hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaAlto.value - hojaMarcoAncho.value,
        hoja1X.value + hojaAncho.value - hojaMarcoAncho.value, hoja1Y.value + hojaMarcoAncho.value,
      ],
      closed: true,
      fill: colorMarcoHex.value,
      stroke: 'black', 
    },
  ]
}

})

const hoja2Mitras = computed(() => {
  if (hoja1Adelante.value) {
    // Hoja 2 atrás => omitimos mitras del lado traslapado (izq y parte superior)
    return [
      // 🔵 Mitra superior (normal)
      {
        points: [
          hoja2X.value, hoja2Y.value,
          hoja2X.value + hojaAncho.value, hoja2Y.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
        ],
        closed: true,
        fill: colorMarcoHex.value,
        stroke: 'black',
      },

      // 🔵 Mitra derecha (normal)
      {
        points: [
          hoja2X.value + hojaAncho.value, hoja2Y.value,
          hoja2X.value + hojaAncho.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
        ],
        closed: true,
        fill: colorMarcoHex.value,
        stroke: 'black',
      },

      // 🔵 Mitra inferior (normal)
      {
        points: [
          hoja2X.value + hojaAncho.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
        ],
        closed: true,
        fill: colorMarcoHex.value,
        stroke: 'black',
      },

      // ⚫ Mitra izquierda (camuflada para cerrar figura)
      {
        points: [
          hoja2X.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value, hoja2Y.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
        ],
        closed: true,
        fill: colorMarcoHex.value,
        stroke: 'black', // para que no se vea
      },
    ]
  } else {
    // Hoja 2 adelante => se dibujan sus 4 mitras completas
    return [
      {
        points: [
          hoja2X.value, hoja2Y.value,
          hoja2X.value + hojaAncho.value, hoja2Y.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
        ],
          closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
      },
      {
        points: [
          hoja2X.value + hojaAncho.value, hoja2Y.value,
          hoja2X.value + hojaAncho.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
        ],
          closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
      },
      {
        points: [
          hoja2X.value + hojaAncho.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
          hoja2X.value + hojaAncho.value - hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
        ],
          closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
      },
      {
        points: [
          hoja2X.value, hoja2Y.value + hojaAlto.value,
          hoja2X.value, hoja2Y.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaMarcoAncho.value,
          hoja2X.value + hojaMarcoAncho.value, hoja2Y.value + hojaAlto.value - hojaMarcoAncho.value,
        ],
          closed: true,
  fill: colorMarcoHex.value,
  stroke: 'black',
      },
    ]
  }
})


const vidrio1 = computed(() => {
  if (hoja1Adelante.value) {
    return {
      x: hoja1X.value + hojaMarcoAncho.value,
      y: hoja1Y.value + hojaMarcoAncho.value,
      width: hojaAncho.value - hojaMarcoAncho.value * 2,
      height: hojaAlto.value - hojaMarcoAncho.value * 2,
      fill: 'lightblue',
      stroke: 'black',
    }
  }

  // Solo esta parte se ajusta: cuando hoja 2 va adelante
const ajusteX1 = hojaMarcoAncho.value * 0.35 + escala.value * 50
const ajusteWidth1 = hojaMarcoAncho.value * 1.3 + escala.value * 1
  return {
    x: hoja1X.value + ajusteX1,
    y: hoja1Y.value + hojaMarcoAncho.value,
    width: hojaAncho.value - ajusteWidth1-15,
    height: hojaAlto.value - hojaMarcoAncho.value * 2,
    fill: 'lightblue',
    stroke: 'black',
  }
})

const vidrio2 = computed(() => {
  if (hoja1Adelante.value) {
    // Si la hoja 1 va al frente, el vidrio 2 se ajusta (más chico)
    const ajusteX = hojaMarcoAncho.value * 0.4 + escala.value * 10
    const ajusteWidth = hojaMarcoAncho.value * 1.4 + escala.value * 10

    return {
      x: hoja2X.value + ajusteX + 10,
      y: hoja2Y.value + hojaMarcoAncho.value,
      width: hojaAncho.value - ajusteWidth - 10,
      height: hojaAlto.value - hojaMarcoAncho.value * 2,
      fill: 'lightblue',
      stroke: 'black',
    }
  }

  // Si la hoja 2 va adelante, su vidrio se muestra completo
  return {
    x: hoja2X.value + hojaMarcoAncho.value,
    y: hoja2Y.value + hojaMarcoAncho.value,
    width: hojaAncho.value - hojaMarcoAncho.value * 2,
    height: hojaAlto.value - hojaMarcoAncho.value * 2,
    fill: 'lightblue',
    stroke: 'black',
  }
})


//  watchEffect(() => {
//    console.log('VIDRIO 2 x:', vidrio2.value.x.toFixed(2))
//    console.log('HOJA 2 X:', hoja2X.value.toFixed(2))
//   console.log('Marco hoja ancho:', hojaMarcoAncho.value.toFixed(2))
//    console.log('Ancho hoja:', hojaAncho.value.toFixed(2))
//    console.log('Vidrio2 width:', vidrio2.value.width.toFixed(2))
//    console.log('Escala:', escala.value.toFixed(2))
//    console.log('Traslape escalado:', traslapeEscalado.value.toFixed(2))
//    console.log('Ajuste X:', hojaMarcoAncho.value * 0.4 + escala.value * 10)
//    console.log('Ajuste Width:', hojaMarcoAncho.value * 1.4 + escala.value * 10)
//    console.log('Vidrio 2:', vidrio2.value)
//     console.log('Vidrio 1:', vidrio1.value)
//   console.log('Hoja 1 Mitras:', hoja1Mitras.value)
//   console.log('Hoja 2 Mitras:', hoja2Mitras.value)
//   console.log('Hoja 1 X:', hoja1X.value.toFixed(2))
//   console.log('Hoja 2 X:', hoja2X.value.toFixed(2))
//   console.log('Hoja 1 Y:', hoja1Y.value.toFixed(2))
//   console.log('Hoja 2 Y:', hoja2Y.value.toFixed(2))
//   console.log('Hoja 1 Ancho:', hojaAncho.value.toFixed(2))
//   console.log('Hoja 1 Alto:', hojaAlto.value.toFixed(2))
//   console.log('Escala:', escala.value.toFixed(2))

// })

  const textoIndicador1 = computed(() => {
    if (props.hojasMoviles === 2) return '→'
    if (props.hojasMoviles === 1 && props.hojaMovilSeleccionada === 1) return '→'
    return '+'
  })

  const textoIndicador2 = computed(() => {
    if (props.hojasMoviles === 2) return '←'
    if (props.hojasMoviles === 1 && props.hojaMovilSeleccionada === 2) return '←'
    return '+'
  })

  const indicador1 = computed(() => ({
    x: hoja1X.value + hojaAncho.value / 2 - 10,
    y: hoja1Y.value + hojaAlto.value / 2 +10,
    text: textoIndicador1.value,
    fontSize: 30,
    fill: 'black',
  }))

  const indicador2 = computed(() => ({
    x: hoja2X.value + hojaAncho.value / 2 - 10,
    y: hoja2Y.value + hojaAlto.value / 2 +10 ,
    text: textoIndicador2.value,
    fontSize: 30,
    fill: 'black',
}))



// === ETIQUETAS
const fontSize = computed(() => {
  const base = Math.max(props.ancho, props.alto)
  return Math.min(Math.max((base / 100), 20), 40)
})

const widthLabel = computed(() => ({
  x: offset + screenWidth.value / 2 - 30,
  y: offset + screenHeight.value + 10,
  text: `${props.ancho}mm`,
  fontSize: fontSize.value,
  fill: 'black',
}))
const heightLabel = computed(() => ({
  x: offset - 25,
  y: offset + screenHeight.value / 2,
  text: `${props.alto}mm`,
  fontSize: fontSize.value,
  fill: 'black',
  rotation: -90,
  offsetX: fontSize.value / 2,
}))
</script>
