<?php
/* ------------- PHP: build recording list --------------- */
$dir   = __DIR__ . '/data';
$files = [];
if (is_dir($dir)) {
    foreach (glob("$dir/*.json") ?: [] as $f) {
        $files[basename($f)] = filemtime($f);
    }
    arsort($files);          // newest first
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>3-D Cube Playback</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
 body{font-family:system-ui,Arial,sans-serif;margin:1rem}
 #ui{display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem;align-items:center}
 select,button{padding:.45rem .9rem;font-size:.9rem}
 .slider-control{display:flex;align-items:center;gap:.3rem}
 input[type=range]{vertical-align:middle}
 .slider-label{font-size:.9rem}
 .slider-value{font-weight:600;width:3ch;text-align:right}
 canvas{border:1px solid #ddd;border-radius:6px;max-width:100%;height:60vh}
 #status{font-weight:600}
</style>
</head>
<body>
<h1>Accelerometer → Moving Cube</h1>

<div id="ui">
  <label for="fileSel">File:</label>
  <select id="fileSel">
    <?php if ($files): foreach($files as $name=>$ts): ?>
      <option value="<?=htmlspecialchars($name)?>"><?=htmlspecialchars($name)?> (<?=date('Y-m-d H:i:s',$ts)?>)</option>
    <?php endforeach; else: ?>
      <option disabled selected>(no recordings found)</option>
    <?php endif; ?>
  </select>

  <button id="playBtn" <?= $files?'':'disabled'?>>Play</button>
  <button id="stopBtn" disabled>Stop</button>
  <span id="status">Idle</span>

  <!-- Sliders for tuning parameters -->
  <div class="slider-control">
    <span class="slider-label">Accel Scale</span>
    <input type="range" id="accelScale" min="0.1" max="5.0" step="0.1" value="1.0">
    <span class="slider-value" id="accelScaleVal">1.0</span>
  </div>
  <div class="slider-control">
    <span class="slider-label">Damping</span>
    <input type="range" id="damping" min="0.9" max="1.0" step="0.0001" value="0.9995">
    <span class="slider-value" id="dampingVal">0.9995</span>
  </div>
  <div class="slider-control">
    <span class="slider-label">HP Alpha</span>
    <input type="range" id="hpAlpha" min="0.5" max="0.99" step="0.01" value="0.9">
    <span class="slider-value" id="hpAlphaVal">0.90</span>
  </div>
  <div class="slider-control">
    <span class="slider-label">Arrow Scale</span>
    <input type="range" id="arrowScale" min="1" max="50" step="1" value="20">
    <span class="slider-value" id="arrowScaleVal">20</span>
  </div>
  <div class="slider-control">
    <span class="slider-label">Arrow Max</span>
    <input type="range" id="arrowMax" min="1" max="10" step="0.5" value="4">
    <span class="slider-value" id="arrowMaxVal">4.0</span>
  </div>
</div>

<canvas id="scene"></canvas>

<script type="importmap">
{
  "imports": {
    "three": "https://unpkg.com/three@0.161.0/build/three.module.js"
  }
}
</script>

<script type="module">
import * as THREE from 'https://unpkg.com/three@0.161.0/build/three.module.js';
import { OrbitControls } from 'https://unpkg.com/three@0.161.0/examples/jsm/controls/OrbitControls.js';

const fileSel        = document.getElementById('fileSel');
const playBtn        = document.getElementById('playBtn');
const stopBtn        = document.getElementById('stopBtn');
const status         = document.getElementById('status');
const canvas         = document.getElementById('scene');

// Sliders
const accelScaleSlider = document.getElementById('accelScale');
const dampingSlider    = document.getElementById('damping');
const hpAlphaSlider    = document.getElementById('hpAlpha');
const arrowScaleSlider = document.getElementById('arrowScale');
const arrowMaxSlider   = document.getElementById('arrowMax');

// Display elements
const accelScaleVal = document.getElementById('accelScaleVal');
const dampingVal    = document.getElementById('dampingVal');
const hpAlphaVal    = document.getElementById('hpAlphaVal');
const arrowScaleVal = document.getElementById('arrowScaleVal');
const arrowMaxVal   = document.getElementById('arrowMaxVal');

// Tuning parameters
let ACCEL_SCALE = parseFloat(accelScaleSlider.value);
let DAMPING     = parseFloat(dampingSlider.value);
let HP_ALPHA    = parseFloat(hpAlphaSlider.value);
let ARROW_SCALE = parseFloat(arrowScaleSlider.value);
let ARROW_MAX   = parseFloat(arrowMaxSlider.value);

// Update on slider input
accelScaleSlider.addEventListener('input', () => {
  ACCEL_SCALE = parseFloat(accelScaleSlider.value);
  accelScaleVal.textContent = ACCEL_SCALE.toFixed(1);
});
dampingSlider.addEventListener('input', () => {
  DAMPING = parseFloat(dampingSlider.value);
  dampingVal.textContent = DAMPING.toFixed(4);
});
hpAlphaSlider.addEventListener('input', () => {
  HP_ALPHA = parseFloat(hpAlphaSlider.value);
  hpAlphaVal.textContent = HP_ALPHA.toFixed(2);
});
arrowScaleSlider.addEventListener('input', () => {
  ARROW_SCALE = parseFloat(arrowScaleSlider.value);
  arrowScaleVal.textContent = ARROW_SCALE;
});
arrowMaxSlider.addEventListener('input', () => {
  ARROW_MAX = parseFloat(arrowMaxSlider.value);
  arrowMaxVal.textContent = ARROW_MAX.toFixed(1);
});

// Data & state
let samples   = [], idx = 0, timerID = null;
let metadata  = null;
let playbackSpeed = 1.0;
let currentQuaternion = new THREE.Quaternion();

// Integration state
let position    = new THREE.Vector3();
let velocity    = new THREE.Vector3();
let accelLP     = new THREE.Vector3();
let lastA       = new THREE.Vector3();
let lastT       = 0;

// three.js setup
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
const scene    = new THREE.Scene(); scene.background = new THREE.Color(0xf7f7f7);
const camera   = new THREE.PerspectiveCamera(60, 1, 0.1, 100);
camera.position.set(3,3,6); camera.lookAt(0,0,0);
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true; controls.dampingFactor = 0.1;
scene.add(new THREE.GridHelper(6,12,0xcccccc,0xeeeeee));
scene.add(new THREE.AxesHelper(2));

// Cube, arrow, trail
const cube = new THREE.Mesh(
  new THREE.BoxGeometry(0.05,0.05,0.05),
  new THREE.MeshStandardMaterial({ color: 0x0066ff })
); scene.add(cube);
const accelArrow = new THREE.ArrowHelper(
  new THREE.Vector3(1,0,0), new THREE.Vector3(0,0,0), 0.5, 0xff0000
); cube.add(accelArrow);
const trailMat  = new THREE.LineBasicMaterial({ color: 0x333333 });
const trailGeom = new THREE.BufferGeometry().setFromPoints([]);
const trailLine = new THREE.Line(trailGeom, trailMat);
scene.add(trailLine); let trailPoints = [];

renderer.setAnimationLoop(() => { controls.update(); renderer.render(scene, camera); });
window.addEventListener('resize', resize); resize();
playBtn.addEventListener('click', play);
stopBtn.addEventListener('click', stop);

function resetSimulation() {
  position.set(0,0,0);
  velocity.set(0,0,0);
  accelLP.set(0,0,0);
  lastA.set(0,0,0);
  lastT = 0;
  trailPoints = [];
  trailGeom.setFromPoints([]);
  cube.position.set(0,0,0);
  cube.quaternion.set(0,0,0,1);
  currentQuaternion.set(0, 0, 0, 1);

  idx = 0;
}

let accelInitialized = false;

function integrateMotion(sample, dt) {
  // 1) grab & orient the raw acceleration
  const raw = new THREE.Vector3(
    sample.accelerationCorrected.x,
    sample.accelerationCorrected.y,
    sample.accelerationCorrected.z
  )
    .multiplyScalar(ACCEL_SCALE)
    .applyQuaternion(currentQuaternion);

  // 2) seed the LP filter on first run
  if (!accelInitialized) {
    accelLP.copy(raw);
    accelInitialized = true;
  }

  // 3) high-pass filter the accel
  //    LP_new = α * LP_old + (1 - α) * raw
  accelLP.multiplyScalar(HP_ALPHA)
         .add(raw.clone().multiplyScalar(1 - HP_ALPHA));
  //    HP = raw - LP_new
  const accelHP = raw.clone().sub(accelLP);

  // 4) integrate velocity (trapezoid)
  const avgA = lastA.clone().add(accelHP).multiplyScalar(0.5);
  velocity.add(avgA.multiplyScalar(dt));
  velocity.multiplyScalar(DAMPING);

  // 5) integrate position directly
  position.add( velocity.clone().multiplyScalar(dt) );

  // 6) stash for next frame
  lastA.copy(accelHP);

  return {
    position: position.clone(),
    acceleration: accelHP.clone()
  };
}


function updateOrientation(sample, dt) {
  if (!sample.rotationRate) return;
  const rotRate = new THREE.Vector3(
    sample.rotationRate.beta * Math.PI/180,
    sample.rotationRate.gamma * Math.PI/180,
    sample.rotationRate.alpha * Math.PI/180
  );
  const mag = rotRate.length();
  if (mag > 0.001) {
    const axis = rotRate.normalize();
    const deltaQ = new THREE.Quaternion().setFromAxisAngle(axis, mag * dt);
    currentQuaternion.multiply(deltaQ).normalize();
  }
}

function processSample(sample) {
  if (!sample || sample.type==='metadata') return;
  const interval    = sample.interval / 1000;
  const currentTime = sample.relativeTime / 1000;
  const dt          = lastT>0 ? (currentTime-lastT) : interval;
  if (dt<=0 || dt>0.1) { lastT = currentTime; return; }
  updateOrientation(sample, dt);
  const motion = integrateMotion(sample, dt);
  // update cube
  cube.position.copy(motion.position);
  cube.quaternion.copy(currentQuaternion);
  // arrow
  const m = motion.acceleration.length();
  if (m>0.001) {
    accelArrow.setDirection(motion.acceleration.clone().normalize());
    accelArrow.setLength(Math.min(m * ARROW_SCALE, ARROW_MAX));
    accelArrow.visible = true;
  } else accelArrow.visible = false;
  // trail
  trailPoints.push(motion.position.clone());
  if (trailPoints.length>500) trailPoints.shift();
  trailGeom.setFromPoints(trailPoints);
  // status
  const rate = idx>0 ? (idx/(currentTime||1)).toFixed(1) : 0;
  status.textContent = `Playing… ${idx}/${samples.length} | Pos: (${motion.position.x.toFixed(2)}, ${motion.position.y.toFixed(2)}, ${motion.position.z.toFixed(2)}) | Rate: ${rate} fps`;
  lastT = currentTime;
}

function playbackStep() {
  if (!samples || idx>=samples.length) {
    stop(); status.textContent = `Playback complete - ${samples.length} samples processed`;
    return;
  }
  processSample(samples[idx]);
  idx++;
  const next = idx<samples.length && samples[idx].relativeTime ? samples[idx].relativeTime - samples[idx-1].relativeTime : 16;
  timerID = setTimeout(playbackStep, Math.max(1, next/playbackSpeed));
}

async function play() {
  stop();
  if (fileSel.value) {
    try { samples = await fetch(`/data/${fileSel.value}`).then(r=>r.json()); }
    catch { status.textContent = 'Error loading file'; return; }
  } else { status.textContent='No data to play'; return; }
  if (!samples.length) { status.textContent='No samples found'; return; }
  if (samples[0].type==='metadata') {
    metadata = samples[0]; samples=samples.slice(1);
    status.textContent = `Loaded ${samples.length} samples (${metadata.recordingDuration/1000}s)`;
  }
  resetSimulation();
  playBtn.disabled = true; stopBtn.disabled=false;
  status.textContent='Starting playback…';
  timerID = setTimeout(playbackStep, 100);
}

function stop() {
  if (timerID) clearTimeout(timerID);
  timerID=null; playBtn.disabled=false; stopBtn.disabled=true;
  if (status.textContent.includes('Playing'))
    status.textContent = `Stopped at sample ${idx}/${samples.length}`;
}

function resize() {
  const w=canvas.clientWidth, h=canvas.clientHeight;
  renderer.setSize(w,h,false);
  camera.aspect = w/h; camera.updateProjectionMatrix();
}
</script>
</body>
</html>
