import './bootstrap';

// x-mary-signature memanggil `new SignaturePad(...)` dari scope global, bukan
// lewat import — jadi tanpa baris ini kanvas tanda tangan hanya diam tanpa
// pesan error apa pun, dan formulir tampak rusak tanpa petunjuk penyebabnya.
import SignaturePad from 'signature_pad';

window.SignaturePad = SignaturePad;
