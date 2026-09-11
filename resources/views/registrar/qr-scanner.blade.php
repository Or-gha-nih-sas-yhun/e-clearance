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

@push('styles')<link href="{{ asset('css/clearance_document_viewer.css') }}" rel="stylesheet">@endpush

@section('content')
    <div class="card card-stat mx-auto" style="max-width:720px;">
        <div class="card-body p-4 text-center">
            <i class="bi bi-qr-code-scan fs-1 text-primary"></i>
            <h4 class="mt-2">Scan Student Clearance QR Code</h4>
            <p class="text-secondary">Allow camera access, then hold the student clearance QR code inside the frame.</p>
            <video id="scannerVideo" class="w-100 rounded border" style="max-height:420px;object-fit:cover;" autoplay muted playsinline></video>
            <div class="mt-3">
                <label class="btn btn-outline-primary mb-0" for="qrImageInput"><i class="bi bi-image me-1"></i> Upload QR Code Image</label>
                <input id="qrImageInput" type="file" accept="image/*" class="d-none">
            </div>
            <p id="scannerStatus" class="small text-secondary mt-3 mb-0">Starting camera…</p>
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
<script>
let qrReader;
let scannerControls;

function openScannedClearance(value) {
    let url;
    try {
        url = new URL(value, window.location.origin);
    } catch (error) {
        document.getElementById('scannerStatus').textContent = 'This is not a valid ClearanceMS verification QR code.';
        return;
    }
    const isVerificationPath = /^\/clearance\/verify\/[A-Za-z0-9]{64}$/.test(url.pathname);
    if (url.origin !== window.location.origin || !isVerificationPath) {
        document.getElementById('scannerStatus').textContent = 'This is not a valid ClearanceMS verification QR code.';
        return;
    }
    // Shown in place rather than navigated to: leaving the portal to read one
    // result meant coming back and restarting the camera for the next student.
    scannerControls?.stop();
    url.searchParams.set('embed', '1');

    const trigger = document.getElementById('scanResultTrigger');
    trigger.dataset.clearanceFormSrc = url.href;
    trigger.click();

    document.getElementById('scannerStatus').textContent = 'Record shown. Close it to scan another QR code.';
}

async function scanQrImage(file) {
    const status = document.getElementById('scannerStatus');
    if (!file) return;
    try {
        status.textContent = 'Reading the uploaded QR code image…';
        const imageUrl = URL.createObjectURL(file);
        const result = await qrReader.decodeFromImageUrl(imageUrl);
        URL.revokeObjectURL(imageUrl);
        openScannedClearance(result.getText());
    } catch (error) {
        status.textContent = 'Unable to read that image. Please use a clear QR code image.';
    }
}

async function startScanner() {
    const status = document.getElementById('scannerStatus');
    if (!window.ZXingBrowser || !navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'Camera scanning is not supported by this device. You can still upload a QR code image.';
        return;
    }
    try {
        qrReader = new ZXingBrowser.BrowserQRCodeReader();
        scannerControls = await qrReader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            document.getElementById('scannerVideo'),
            (result) => { if (result) openScannedClearance(result.getText()); }
        );
        status.textContent = 'Camera ready. Point it at a clearance QR code.';
    } catch (error) {
        status.textContent = 'Camera access was not available. Please allow camera permission and try again.';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.ZXingBrowser) qrReader = new ZXingBrowser.BrowserQRCodeReader();
    startScanner();
    document.getElementById('qrImageInput').addEventListener('change', event => scanQrImage(event.target.files[0]));

    // Closing the result hands the camera back, ready for the next student.
    document.getElementById('scanResultViewer')?.addEventListener('click', event => {
        if (event.target.closest('[data-clearance-form-close]')) {
            window.setTimeout(startScanner, 150);
        }
    });
});
window.addEventListener('beforeunload', () => scannerControls?.stop());
</script>
@endpush
