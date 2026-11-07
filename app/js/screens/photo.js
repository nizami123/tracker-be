// Komponen foto bersama (kamera belakang untuk kendaraan). Cadangan: kamera bawaan iOS via input file.
import { Camera, imageFileToJpeg } from '../camera.js';
import { icon, esc, $ } from '../util.js';

export function photoBox(host, { facing = 'environment', hint = '', onChange = () => {} } = {}) {
  host.innerHTML = `
    <div class="cam wide">
      <video playsinline muted autoplay></video>
      <img class="shot hidden" alt="Foto">
      <div class="hint">${esc(hint)}</div>
      <div class="msg hidden"></div>
    </div>
    <div class="row gap mt12">
      <button type="button" class="btn outline grow" data-a="cap" disabled>${icon('camera', 20)} Ambil Foto</button>
      <button type="button" class="btn ghost hidden" data-a="re">Ulangi</button>
    </div>
    <input type="file" accept="image/*" capture="${facing === 'user' ? 'user' : 'environment'}" class="hidden">`;

  const video = $('video', host), shot = $('img.shot', host), msg = $('.msg', host), hintEl = $('.hint', host);
  const cap = $('[data-a="cap"]', host), re = $('[data-a="re"]', host), file = $('input[type=file]', host);
  const cam = new Camera(video, facing);
  let photo = null, failed = false;

  const set = (p) => {
    photo = p;
    if (p) {
      shot.src = p.dataUrl; shot.classList.remove('hidden'); video.classList.add('hidden'); hintEl.classList.add('hidden'); msg.classList.add('hidden');
      cap.classList.add('hidden'); re.classList.remove('hidden');
    } else {
      shot.classList.add('hidden'); re.classList.add('hidden'); cap.classList.remove('hidden');
      if (failed) msg.classList.remove('hidden'); else { video.classList.remove('hidden'); hintEl.classList.remove('hidden'); }
    }
    onChange(photo);
  };

  async function start() {
    try { await cam.start(); failed = false; msg.classList.add('hidden'); }
    catch (e) {
      failed = true;
      msg.innerHTML = `<div>${esc(e.message)}<br><br>Gunakan tombol “Ambil Foto” untuk membuka kamera iPhone.</div>`;
      msg.classList.remove('hidden'); hintEl.classList.add('hidden');
    }
    cap.disabled = false;
  }
  cap.addEventListener('click', () => {
    if (cam.stream) { try { set(cam.capture()); } catch { file.click(); } } else file.click();
  });
  re.addEventListener('click', () => set(null));
  file.addEventListener('change', async (e) => {
    const f = e.target.files && e.target.files[0];
    if (f) { try { set(await imageFileToJpeg(f)); } catch { /* */ } }
    e.target.value = '';
  });

  start();
  return { get photo() { return photo; }, stop: () => cam.stop(), reset: () => set(null) };
}
