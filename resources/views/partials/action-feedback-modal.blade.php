@php
    $actionFeedback = session('flash');
    $status = session('status');
    $statusMessage = match ($status) {
        'profile-updated' => 'Profile information updated successfully.',
        'password-updated' => 'Password updated successfully.',
        'verification-link-sent' => 'A new verification link has been sent.',
        default => $status,
    };

    if (!is_array($actionFeedback) || empty($actionFeedback['message'])) {
        $actionFeedback = session('login_success')
            ? ['type' => 'success', 'title' => 'Login Successful', 'message' => session('login_success')]
            : (session('success')
                ? ['type' => 'success', 'message' => session('success')]
                : ($statusMessage ? ['type' => 'success', 'message' => $statusMessage] : null));
    }
@endphp

<style>
    .feedback-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        visibility: hidden;
        opacity: 0;
        background: rgba(28, 52, 79, .42);
        backdrop-filter: blur(9px) saturate(125%);
        -webkit-backdrop-filter: blur(9px) saturate(125%);
        transition: opacity .22s ease, visibility .22s ease;
    }
    .feedback-modal-overlay.show { visibility: visible; opacity: 1; }
    .feedback-modal {
        position: relative;
        isolation: isolate;
        width: min(430px, 100%);
        overflow: hidden;
        color: #172033;
        border: 1px solid rgba(255, 255, 255, .94);
        border-radius: 24px;
        background: linear-gradient(135deg, rgba(255, 255, 255, .96), rgba(226, 243, 255, .86));
        box-shadow: 0 35px 90px rgba(15, 45, 75, .3), 0 10px 30px rgba(52, 126, 177, .16), inset 0 1px 1px #fff, inset 0 -1px 0 rgba(112, 165, 204, .2);
        backdrop-filter: blur(32px) saturate(170%);
        -webkit-backdrop-filter: blur(32px) saturate(170%);
        transform: translateY(14px) scale(.97);
        transition: transform .24s cubic-bezier(.22, 1, .36, 1);
    }
    .feedback-modal-overlay.show .feedback-modal { transform: none; }
    .feedback-modal::before {
        content: "";
        position: absolute;
        z-index: -1;
        inset: -50% -22% auto;
        height: 275px;
        background: radial-gradient(circle at 25% 55%, rgba(255, 255, 255, .98), transparent 34%), radial-gradient(circle at 72% 35%, rgba(93, 199, 255, .38), transparent 39%), radial-gradient(circle at 52% 82%, rgba(164, 126, 255, .2), transparent 43%);
        filter: blur(8px);
        pointer-events: none;
    }
    .feedback-modal::after {
        content: "";
        position: absolute;
        z-index: -1;
        inset: 1px;
        border-radius: 23px;
        background: linear-gradient(115deg, rgba(255, 255, 255, .5), transparent 28%, transparent 68%, rgba(124, 202, 255, .15));
        pointer-events: none;
    }
    .feedback-modal-body { position: relative; padding: 32px 28px 19px; text-align: center; }
    .feedback-modal-close {
        position: absolute;
        top: 17px;
        right: 18px;
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        padding: 0;
        border: 0;
        outline: 0;
        border-radius: 9px;
        color: #64748b;
        background: transparent;
        font-size: 1.1rem;
        cursor: pointer;
    }
    .feedback-modal-close:hover { color: #1e293b; background: rgba(71, 103, 135, .1); }
    .feedback-modal-icon {
        display: grid;
        place-items: center;
        width: 68px;
        height: 68px;
        margin: 0 auto 18px;
        border: 3px solid #27a963;
        border-radius: 50%;
        color: #16854a;
        background: rgba(255, 255, 255, .78);
        box-shadow: 0 9px 28px rgba(24, 143, 78, .2), inset 0 0 0 6px rgba(39, 174, 96, .08);
        font-size: 2rem;
    }
    .feedback-modal.warning .feedback-modal-icon { border-color: #e29a09; color: #b86f00; box-shadow: 0 9px 28px rgba(207, 132, 8, .2), inset 0 0 0 6px rgba(251, 191, 36, .1); }
    .feedback-modal.danger .feedback-modal-icon { border-color: #e54655; color: #d92232; box-shadow: 0 9px 28px rgba(213, 35, 52, .2), inset 0 0 0 6px rgba(255, 77, 79, .08); }
    .feedback-modal h3 { margin: 0 34px 9px; color: #101828; font: 800 1.42rem/1.25 Mulish, sans-serif; text-shadow: 0 1px 0 rgba(255, 255, 255, .92); }
    .feedback-modal p { margin: 0; color: #3f5065; font-size: .96rem; font-weight: 600; line-height: 1.6; white-space: pre-line; overflow-wrap: anywhere; }
    .feedback-modal-actions { display: flex; gap: 11px; margin: 0 20px 20px; padding-top: 18px; border-top: 1px solid rgba(71, 103, 135, .22); }
    .feedback-modal-button {
        flex: 1;
        min-height: 47px;
        border: 0;
        outline: 0;
        border-radius: 13px;
        color: #fff;
        background: linear-gradient(135deg, rgba(67, 211, 137, .97), rgba(18, 145, 78, .92));
        box-shadow: inset 0 1px 1px rgba(255, 255, 255, .38), 0 9px 23px rgba(22, 138, 72, .23);
        font-weight: 800;
        cursor: pointer;
        transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
    }
    .feedback-modal.warning .feedback-modal-button { background: linear-gradient(135deg, rgba(255, 190, 53, .97), rgba(213, 116, 8, .92)); box-shadow: inset 0 1px 1px rgba(255, 255, 255, .38), 0 9px 23px rgba(190, 107, 8, .22); }
    .feedback-modal.danger .feedback-modal-button { background: linear-gradient(135deg, rgba(255, 83, 93, .96), rgba(204, 24, 45, .92)); box-shadow: inset 0 1px 1px rgba(255, 255, 255, .35), 0 9px 23px rgba(207, 38, 55, .24); }
    .feedback-modal-button:hover { transform: translateY(-1px); filter: brightness(1.04); }
    .feedback-modal-button.cancel { display: none; color: #31455d; background: rgba(255, 255, 255, .76); box-shadow: inset 0 1px 1px #fff, 0 7px 18px rgba(42, 74, 105, .13); }
    .feedback-modal-button:focus-visible, .feedback-modal-close:focus-visible { box-shadow: 0 0 0 4px rgba(59, 130, 246, .22); }
    @media (prefers-reduced-motion: reduce) { .feedback-modal-overlay, .feedback-modal { transition: none; } }
</style>

<div class="feedback-modal-overlay" id="feedbackModalOverlay" aria-hidden="true">
    <section class="feedback-modal success" id="feedbackModal" role="alertdialog" aria-modal="true" aria-labelledby="feedbackModalTitle" aria-describedby="feedbackModalMessage" data-sweet-alert>
        <div class="feedback-modal-body">
            <button class="feedback-modal-close" id="feedbackModalClose" type="button" aria-label="Close message"><i class="bi bi-x-lg"></i></button>
            <div class="feedback-modal-icon" id="feedbackModalIcon"><i class="bi bi-check-lg"></i></div>
            <h3 id="feedbackModalTitle">Success!</h3>
            <p id="feedbackModalMessage">Your action was completed successfully.</p>
        </div>
        <div class="feedback-modal-actions">
            <button class="feedback-modal-button cancel" id="feedbackModalCancel" type="button"><i class="bi bi-x-lg me-2"></i>Cancel</button>
            <button class="feedback-modal-button" id="feedbackModalConfirm" type="button"><i class="bi bi-check2 me-2"></i>Okay, Got it!</button>
        </div>
    </section>
</div>

<script>
(() => {
    const overlay = document.getElementById('feedbackModalOverlay');
    const modal = document.getElementById('feedbackModal');
    const title = document.getElementById('feedbackModalTitle');
    const message = document.getElementById('feedbackModalMessage');
    const icon = document.getElementById('feedbackModalIcon');
    const confirmButton = document.getElementById('feedbackModalConfirm');
    const cancelButton = document.getElementById('feedbackModalCancel');
    const closeButton = document.getElementById('feedbackModalClose');
    let resolver = null;
    let previousOverflow = '';

    const normalizeTone = tone => ['warning', 'danger', 'error'].includes(String(tone).toLowerCase())
        ? (String(tone).toLowerCase() === 'error' ? 'danger' : String(tone).toLowerCase())
        : 'success';

    const close = (result = false) => {
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousOverflow;
        if (resolver) {
            const resolve = resolver;
            resolver = null;
            window.setTimeout(() => resolve(result), 180);
        }
    };

    window.showFeedbackModal = (options = {}) => {
        const settings = typeof options === 'string' ? { message: options } : options;
        const tone = normalizeTone(settings.tone || settings.type || 'success');
        const defaultTitle = tone === 'success' ? 'Success!' : (tone === 'warning' ? 'Attention' : 'Action Failed');

        modal.className = `feedback-modal ${tone}`;
        title.textContent = settings.title || defaultTitle;
        message.textContent = settings.message || 'Your action was completed successfully.';
        icon.innerHTML = `<i class="bi ${tone === 'success' ? 'bi-check-lg' : 'bi-exclamation-lg'}"></i>`;
        confirmButton.innerHTML = `<i class="bi ${tone === 'success' ? 'bi-check2' : 'bi-hand-thumbs-up'} me-2"></i>${settings.confirmText || 'Okay, Got it!'}`;
        cancelButton.style.display = settings.confirmation ? 'block' : 'none';
        cancelButton.innerHTML = `<i class="bi bi-x-lg me-2"></i>${settings.cancelText || 'Cancel'}`;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => confirmButton.focus(), 0);

        return new Promise(resolve => { resolver = resolve; });
    };

    const sweetAlertFire = (options = {}) => {
        const settings = typeof options === 'string' ? { text: options } : options;
        return window.showFeedbackModal({
            title: settings.title,
            message: settings.text || settings.message,
            tone: settings.icon || settings.tone || 'success',
            confirmText: settings.confirmButtonText,
            cancelText: settings.cancelButtonText,
            confirmation: Boolean(settings.showCancelButton),
        }).then(isConfirmed => ({ isConfirmed, isDismissed: !isConfirmed }));
    };

    if (!window.Swal || typeof window.Swal.fire !== 'function') {
        window.Swal = { ...(window.Swal || {}), fire: sweetAlertFire };
    }

    window.showSuccessModal = (messageText, titleText = 'Success!') => window.Swal.fire({
        title: titleText,
        text: messageText,
        icon: 'success',
    });

    window.showConfirmationModal = (options = {}) => window.showFeedbackModal({
        ...options,
        confirmation: true,
    });

    confirmButton.addEventListener('click', () => close(true));
    cancelButton.addEventListener('click', () => close(false));
    closeButton.addEventListener('click', () => close(false));
    overlay.addEventListener('click', event => { if (event.target === overlay) close(false); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && overlay.classList.contains('show')) close();
    });

    @if($actionFeedback)
    document.addEventListener('DOMContentLoaded', () => window.showFeedbackModal({
        type: @json($actionFeedback['type'] ?? 'success'),
        message: @json($actionFeedback['message']),
        title: @json($actionFeedback['title'] ?? null),
    }));
    @endif
})();
</script>
