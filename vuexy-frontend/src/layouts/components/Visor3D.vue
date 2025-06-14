<template>
  <div>
    <div ref="container" class="three-container" />
    <v-btn class="mt-2" @click="exportarImagen" color="primary">Descargar PNG</v-btn>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import * as THREE from 'three'
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js'
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js'

const props = defineProps({
  ancho: { type: Number, default: 2000 },
  alto: { type: Number, default: 2000 },
  modelo: { type: String, required: true },
  baseX: { type: Number, default: 2000 },
  baseY: { type: Number, default: 2000 },
})

const container = ref(null)
let scene, camera, renderer, model, controls

const inicializar = () => {
  scene = new THREE.Scene()
  scene.background = new THREE.Color(0xf0f0f0)

  // Luces
  const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 1.2)
  hemiLight.position.set(0, 1, 0)
  scene.add(hemiLight)

  const ambientLight = new THREE.AmbientLight(0xffffff, 2.5)
  scene.add(ambientLight)

  const directionalLight = new THREE.DirectionalLight(0xffffff, 1.2)
  directionalLight.position.set(5, 5, 5)
  scene.add(directionalLight)

  const backLight = new THREE.DirectionalLight(0xffffff, 0.8)
  backLight.position.set(-5, -5, -5)
  scene.add(backLight)

  // Cámara
  const width = container.value.clientWidth
  const height = container.value.clientHeight
  const aspect = width / height

  camera = new THREE.PerspectiveCamera(45, aspect, 0.1, 1000)
  camera.position.set(0, 0, 5)
  camera.lookAt(0, 0, 0)

  // Render
  renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true })
  renderer.setSize(width, height)
  container.value.innerHTML = ''
  container.value.appendChild(renderer.domElement)

  // Controles
  controls = new OrbitControls(camera, renderer.domElement)
  controls.enableDamping = true
  controls.dampingFactor = 0.05
  controls.target.set(0, 0, 0)
  controls.update()
}

const cargarModelo = () => {
  const loader = new GLTFLoader()
  loader.load(props.modelo, gltf => {
    model = gltf.scene

    const box = new THREE.Box3().setFromObject(model)
    const center = new THREE.Vector3()
    box.getCenter(center)
    model.position.sub(center)

    const size = box.getSize(new THREE.Vector3()).length()
    const distancia = size * 1.2 // puedes ajustar 1.2 → 1.0 para acercarlo más

    // Ajustar la posición Z de la cámara para que encuadre bien el modelo
    camera.position.set(0, 0, distancia)
    controls.target.set(0, 0, 0)
    controls.update()

    const escalaX = props.ancho / props.baseX
    const escalaY = props.alto / props.baseY
    model.scale.set(escalaX, escalaY, escalaX)

    scene.add(model)
    animate()
  }, undefined, error => {
    console.error('Error cargando modelo:', error)
  })
}

const animate = () => {
  requestAnimationFrame(animate)
  controls.update()
  renderer.render(scene, camera)
}

const exportarImagen = () => {
  controls.update()
  renderer.render(scene, camera)

  requestAnimationFrame(() => {
    try {
      const dataURL = renderer.domElement.toDataURL('image/png')
      const link = document.createElement('a')
      link.href = dataURL
      link.download = 'modelo-3d.png'
      link.click()
    } catch (e) {
      console.error('No se pudo exportar la imagen:', e)
    }
  })
}

onMounted(() => {
  inicializar()
  cargarModelo()
})

watch(() => [props.ancho, props.alto, props.modelo], () => {
  inicializar()
  cargarModelo()
})
</script>

<style scoped>
.three-container {
  width: 100%;
  max-width: 500px;
  aspect-ratio: 1.6;
  margin: auto;
  border: 1px solid #ccc;
  background-color: #fff;
}
</style>
