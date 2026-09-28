@extends('layouts.portal')

@section('title', 'QR Code Scanner')
@section('portal-name', 'Registrar Portal')
@section('portal-subtitle', 'Clearance Verification')
@section('page-title', 'QR Code Scanner')
@section('user-label', $registrar->full_name ?? $registrar->email)
@section('user-role', 'Registrar')

@section('nav')
    <a class="nav-link" href="{{ route('registrar.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('registrar.student-clearance') }}"><i class="bi bi-bar-chart-line me-2"></i> Student Clearance</a>
    <a class="nav-link active" href="{{ route('registrar.qr-scanner') }}"><i class="bi bi-qr-code-scan me-2"></i> QR Code Scanner</a>
    <a class="nav-link" href="{{ route('registrar.chat') }}"><i class="bi bi-chat-square-text me-2"></i> Messages</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('registrar.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')
<link href="{{ asset('css/clearance_document_viewer.css') }}" rel="stylesheet">
<style>
    .qr-scanner-card { max-width:760px; }
    .qr-camera-preview { position:relative; width:100%; overflow:hidden; aspect-ratio:4/3; border:1px solid rgba(161,196,222,.72); border-radius:16px; background:linear-gradient(145deg,#12263b,#08131f); box-shadow:inset 0 0 0 1px rgba(255,255,255,.08); }
    .qr-camera-preview video { display:block; width:100%; height:100%; object-fit:cover; background:#08131f; }
    .qr-camera-guide { position:absolute; inset:50% auto auto 50%; width:min(58%,290px); aspect-ratio:1; transform:translate(-50%,-50%); border:2px solid rgba(255,255,255,.85); border-radius:18px; box-shadow:0 0 0 999px rgba(4,17,29,.2); pointer-events:none; }
    .qr-camera-guide::before,.qr-camera-guide::after { content:""; position:absolute; left:10%; right:10%; height:2px; background:linear-gradient(90deg,transparent,#2aa8ff,transparent); }
    .qr-camera-guide::before { top:33%; }
    .qr-camera-guide::after { bottom:33%; }
    .qr-camera-actions { display:flex; flex-wrap:wrap; justify-content:center; gap:9px; margin-top:16px; }
    .qr-camera-status { display:flex; min-height:42px; align-items:center; justify-content:center; gap:8px; margin:14px 0 0; padding:10px 12px; border-radius:11px; color:#62788e; background:rgba(234,244,251,.7); font-size:.78rem; font-weight:700; line-height:1.4; }
    .qr-camera-status[data-tone="success"] { color:#17734d; background:rgba(218,249,234,.76); }
    .qr-camera-status[data-tone="warning"] { color:#98620a; background:rgba(255,242,205,.78); }
    .qr-camera-status[data-tone="danger"] { color:#a23a45; background:rgba(255,226,230,.78); }
    @media (max-width:575px) { .qr-scanner-card .card-body { padding:18px !important; } .qr-camera-actions .btn { flex:1 1 calc(50% - 9px); } }
</style>
@endpush

@section('content')
    <div class="card card-stat qr-scanner-card mx-auto">
        <div class="card-body p-4 text-center">
            <i class="bi bi-qr-code-scan fs-1 text-primary"></i>
            <h4 class="mt-2">Scan Student Clearance QR Code</h4>
            <p class="text-secondary">Allow camera access, then hold the student clearance QR code inside the frame.</p>
            <div class="qr-camera-preview">
                <video id="scannerVideo" autoplay muted playsinline aria-label="Camera preview"></video>
                <div class="qr-camera-guide" aria-hidden="true"></div>
            </div>
            <div class="qr-camera-actions">
                <button id="startCameraButton" type="button" class="btn btn-primary"><i class="bi bi-camera-video me-1"></i> Start camera</button>
                <button id="switchCameraButton" type="button" class="btn btn-outline-primary d-none"><i class="bi bi-arrow-repeat me-1"></i> Switch camera</button>
                <button id="stopCameraButton" type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-camera-video-off me-1"></i> Stop camera</button>
                <label class="btn btn-outline-primary mb-0" for="qrImageInput"><i class="bi bi-image me-1"></i> Upload QR Code Image</label>
                <input id="qrImageInput" type="file" accept="image/png,image/jpeg,image/webp" class="d-none">
            </div>
            <p id="scannerStatus" class="qr-camera-status" data-tone="muted" role="status" aria-live="polite"><i class="bi bi-camera"></i><span>Preparing camera…</span></p>
        </div>
    </div>

    {{-- The scanned record opens in the same viewer the Student Clearance table
         uses, so the registrar stays on the scanner and can read the result,
         close it, and scan the next student without leaving the page. --}}
    <button type="button" class="d-none" id="scanResultTrigger"
            data-clearance-form-open
            data-clearance-form-src=""
            data-clearance-form-title="Clearance verification"
            data-clearance-form-subtitle="Scanned QR code"></button>

    <x-portal.document-viewer id="scanResultViewer" title="Clearance verification" subtitle="Scanned QR code" />
@endsection

@push('scripts')
<script src="{{ asset('js/zxing-browser.min.js') }}"></script>
@php($verificationExamplePath = route('clearance.verify', ['token' => str_repeat('A', 64)], false))
<script>
(() => {
    const verificationExamplePath = @json($verificationExamplePath);
    const verificationPathPrefix = verificationExamplePath.slice(0, -64);
    const video = document.getElementById('scannerVideo');
    const status = document.getElementById('scannerStatus');
    const startButton = document.getElementById('startCameraButton');
    const switchButton = document.getElementById('switchCameraButton');
    const stopButton = document.getElementById('stopCameraButton');
    const imageInput = document.getElementById('qrImageInput');
    const resultViewer = document.getElementById('scanResultViewer');
    let qrReader = null;
    let scannerControls = null;
    let cameras = [];
    let activeDeviceId = null;
    let operationId = 0;
    let resultOpen = false;

    const setStatus = (message, tone = 'muted', icon = 'bi-camera') => {
        status.dataset.tone = tone;
        status.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span></span>`;
        status.querySelector('span').textContent = message;
    };

    const updateButtons = (running, busy = false) => {
        startButton.disabled = running || busy;
        startButton.innerHTML = busy
            ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Starting…'
            : '<i class="bi bi-camera-video me-1"></i> Start camera';
        stopButton.disabled = !running || busy;
        switchButton.disabled = !running || busy;
    };

    const releaseCamera = async () => {
        const controls = scannerControls;
        scannerControls = null;
        if (controls) {
            try { await Promise.resolve(controls.stop()); } catch (error) {}
        }
        const stream = video.srcObject;
        if (stream instanceof MediaStream) stream.getTracks().forEach(track => track.stop());
        video.srcObject = null;
        updateButtons(false);
    };

    const stopScanner = async (message = null) => {
        operationId += 1;
        await releaseCamera();
        if (message) setStatus(message, 'muted', 'bi-camera-video-off');
    };

    const refreshCameras = async () => {
        try {
            cameras = await ZXingBrowser.BrowserCodeReader.listVideoInputDevices();
            activeDeviceId = video.srcObject?.getVideoTracks?.()[0]?.getSettings?.().deviceId || activeDeviceId;
            switchButton.classList.toggle('d-none', cameras.length < 2);
        } catch (error) {
            cameras = [];
            switchButton.classList.add('d-none');
        }
    };

    const cameraErrorMessage = error => {
        if (!window.isSecureContext) return 'Camera access requires HTTPS. Open the scanner using the secure HTTPS address or localhost.';
        if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') return 'Camera permission is blocked. Allow camera access in the browser settings, then press Start camera.';
        if (error?.name === 'NotFoundError' || error?.name === 'DevicesNotFoundError') return 'No camera was found on this device. You can upload a QR code image instead.';
        if (error?.name === 'NotReadableError' || error?.name === 'TrackStartError' || error?.name === 'AbortError') return 'The camera is being used by another application. Close it there, then try again.';
        if (error?.name === 'OverconstrainedError') return 'The selected camera is no longer available. Press Start camera to use another camera.';
        return 'The camera could not start. Check browser permission and try again, or upload a QR code image.';
    };

    const isValidVerificationUrl = url => {
        if (url.origin !== window.location.origin || !url.pathname.startsWith(verificationPathPrefix)) return false;
        const token = url.pathname.slice(verificationPathPrefix.length).replace(/\/$/, '');
        return /^[A-Za-z0-9]{64}$/.test(token)
            && url.pathname.replace(/\/$/, '') === `${verificationPathPrefix}${token}`.replace(/\/$/, '');
    };

    const openScannedClearance = async value => {
        let url;
        try {
            url = new URL(value, window.location.origin);
        } catch (error) {
            setStatus('This is not a valid ClearanceMS verification QR code.', 'warning', 'bi-exclamation-triangle');
            return;
        }
        if (!isValidVerificationUrl(url)) {
            setStatus('This is not a valid ClearanceMS verification QR code.', 'warning', 'bi-exclamation-triangle');
            return;
        }

        resultOpen = true;
        await stopScanner();
        url.searchParams.set('embed', '1');
        const trigger = document.getElementById('scanResultTrigger');
        trigger.dataset.clearanceFormSrc = url.href;
        trigger.click();
        setStatus('Record shown. Close it to scan another QR code.', 'success', 'bi-check-circle');
    };

    const scanQrImage = async file => {
        if (!file) return;
        let imageUrl;
        try {
            setStatus('Reading the uploaded QR code image…', 'muted', 'bi-image');
            imageUrl = URL.createObjectURL(file);
            const imageReader = new ZXingBrowser.BrowserQRCodeReader();
            const result = await imageReader.decodeFromImageUrl(imageUrl);
            await openScannedClearance(result.getText());
        } catch (error) {
            setStatus('Unable to read that image. Please use a clear QR code image.', 'danger', 'bi-x-circle');
        } finally {
            if (imageUrl) URL.revokeObjectURL(imageUrl);
            imageInput.value = '';
        }
    };

    const startScanner = async (deviceId = null) => {
        if (!window.isSecureContext) {
            setStatus(cameraErrorMessage(), 'danger', 'bi-shield-lock');
            updateButtons(false);
            return;
        }
        if (!window.ZXingBrowser || !navigator.mediaDevices?.getUserMedia) {
            setStatus('Camera scanning is not supported by this browser. You can upload a QR code image instead.', 'danger', 'bi-camera-video-off');
            updateButtons(false);
            return;
        }

        const currentOperation = ++operationId;
        await releaseCamera();
        updateButtons(false, true);
        setStatus('Requesting camera access…', 'muted', 'bi-camera');

        try {
            qrReader = qrReader || new ZXingBrowser.BrowserQRCodeReader();
            const onResult = result => {
                if (result && !resultOpen) openScannedClearance(result.getText());
            };
            const controls = deviceId
                ? await qrReader.decodeFromVideoDevice(deviceId, video, onResult)
                : await qrReader.decodeFromConstraints(
                    { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
                    video,
                    onResult
                );

            if (currentOperation !== operationId) {
                await Promise.resolve(controls.stop());
                return;
            }
            scannerControls = controls;
            activeDeviceId = video.srcObject?.getVideoTracks?.()[0]?.getSettings?.().deviceId || deviceId;
            await refreshCameras();
            updateButtons(true);
            setStatus('Camera ready. Point it at a clearance QR code.', 'success', 'bi-camera-video');
        } catch (error) {
            if (currentOperation !== operationId) return;
            await releaseCamera();
            setStatus(cameraErrorMessage(error), 'danger', 'bi-exclamation-circle');
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        startButton.addEventListener('click', () => startScanner());
        stopButton.addEventListener('click', () => stopScanner('Camera stopped. Press Start camera when you are ready.'));
        switchButton.addEventListener('click', async () => {
            await refreshCameras();
            if (cameras.length < 2) return;
            const currentIndex = cameras.findIndex(camera => camera.deviceId === activeDeviceId);
            const nextCamera = cameras[(currentIndex + 1 + cameras.length) % cameras.length];
            startScanner(nextCamera.deviceId);
        });
        imageInput.addEventListener('change', event => scanQrImage(event.target.files[0]));
        resultViewer?.addEventListener('clearance-document:closed', () => {
            resultOpen = false;
            window.setTimeout(() => startScanner(activeDeviceId), 150);
        });
        startScanner();
    });

    window.addEventListener('pagehide', () => stopScanner());
})();
</script>
@endpush
