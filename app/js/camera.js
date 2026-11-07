// Kamera: getUserMedia (pratinjau langsung) + cadangan input file (kamera bawaan iOS).

export const cameraSupported = () => !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

export class Camera {
  constructor(video, facing = 'user') { this.video = video; this.facing = facing; this.stream = null; }

  async start() {
    if (!cameraSupported()) throw new Error('Kamera tidak didukung di browser ini');
    this.stop();
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: this.facing }, width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false,
      });
    } catch (e) {
      const denied = e && (e.name === 'NotAllowedError' || e.name === 'SecurityError');
      throw new Error(denied
        ? 'Izin kamera ditolak. Aktifkan di Pengaturan iPhone › Safari › Kamera, lalu buka ulang aplikasi.'
        : 'Kamera tidak dapat dibuka');
    }
    const v = this.video;
    v.setAttribute('playsinline', ''); v.setAttribute('webkit-playsinline', '');
    v.muted = true; v.srcObject = this.stream;
    v.classList.toggle('mirror', this.facing === 'user');
    await v.play().catch(() => {});
  }

  /** Ambil frame -> { dataUrl, base64 } (JPEG, tidak dicerminkan, sisi terpanjang <= maxSide) */
  capture(maxSide = 1280, quality = 0.7) {
    const v = this.video;
    if (!v.videoWidth) throw new Error('Kamera belum siap');
    const scale = Math.min(1, maxSide / Math.max(v.videoWidth, v.videoHeight));
    const c = document.createElement('canvas');
    c.width = Math.round(v.videoWidth * scale); c.height = Math.round(v.videoHeight * scale);
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
    const dataUrl = c.toDataURL('image/jpeg', quality);
    return { dataUrl, base64: dataUrl.split(',')[1] };
  }

  stop() {
    if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
    this.stream = null;
    if (this.video) this.video.srcObject = null;
  }
}

/** File gambar -> JPEG ter-resize. { dataUrl, base64 } */
export async function imageFileToJpeg(file, maxSide = 1280, quality = 0.7) {
  let bmp;
  try { bmp = await createImageBitmap(file, { imageOrientation: 'from-image' }); }
  catch {
    bmp = await new Promise((res, rej) => {
      const img = new Image(); img.onload = () => res(img); img.onerror = rej; img.src = URL.createObjectURL(file);
    });
  }
  const w = bmp.width || bmp.naturalWidth, h = bmp.height || bmp.naturalHeight;
  const scale = Math.min(1, maxSide / Math.max(w, h));
  const c = document.createElement('canvas');
  c.width = Math.round(w * scale); c.height = Math.round(h * scale);
  c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
  const dataUrl = c.toDataURL('image/jpeg', quality);
  return { dataUrl, base64: dataUrl.split(',')[1] };
}

export function fileToBase64(file) {
  return new Promise((res, rej) => {
    const r = new FileReader();
    r.onload = () => res(String(r.result).split(',')[1]);
    r.onerror = () => rej(new Error('Gagal membaca file'));
    r.readAsDataURL(file);
  });
}
