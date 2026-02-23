{{-- PBX Make Call modal for .pbx-make-call-btn and .pbx-call-btn. tel: links are NOT intercepted so they trigger the default handler (e.g. MicroSIP). --}}
@if($pbxCanCall ?? false)
<div class="modal fade" id="pbxMakeCallModal" tabindex="-1" aria-labelledby="pbxMakeCallModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pbxMakeCallModalLabel">
                    <i class="bi bi-telephone-outbound-fill me-2"></i>Make Call (PBX)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="pbxMakeCallCustomer"></p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" id="pbxCallNumber" class="form-control" placeholder="e.g. 0722000000 or 254722000000" autocomplete="tel">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Your Extension</label>
                    <input type="text" id="pbxCallExtension" class="form-control" placeholder="e.g. 2002 (for Dial PBX only)" value="{{ $pbxDefaultExtension ?? '' }}">
                </div>
                <div id="pbxMakeCallMessage" class="alert d-none mb-0"></div>
                <p class="small text-muted mb-0 mt-2"><i class="bi bi-lightbulb me-1"></i>Enter number and click <strong>Call</strong> to open MicroSIP.</p>
            </div>
            <div class="modal-footer d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-success" id="pbxCallSipPhone" title="Open MicroSIP with this number" role="button">
                    <i class="bi bi-telephone-fill me-1"></i>Call
                </a>
                <button type="button" class="btn btn-outline-primary" id="pbxMakeCallSubmit">
                    <span class="pbx-call-btn-text"><i class="bi bi-telephone me-1"></i>Dial (PBX)</span>
                    <span class="pbx-call-btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span>Calling...</span>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    const makeCallModal = document.getElementById('pbxMakeCallModal');
    const numberInput = document.getElementById('pbxCallNumber');
    const extensionInput = document.getElementById('pbxCallExtension');
    const customerInfo = document.getElementById('pbxMakeCallCustomer');
    const messageEl = document.getElementById('pbxMakeCallMessage');
    const submitBtn = document.getElementById('pbxMakeCallSubmit');
    const btnText = submitBtn?.querySelector('.pbx-call-btn-text');
    const btnLoading = submitBtn?.querySelector('.pbx-call-btn-loading');

    function showMessage(msg, isError, detail) {
        if (!messageEl) return;
        const safe = (s) => ('' + s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        if (detail && isError) {
            messageEl.innerHTML = msg + '<br><small class="opacity-75">' + safe(detail) + '</small>';
        } else {
            messageEl.textContent = msg;
        }
        messageEl.className = 'alert mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
        messageEl.classList.remove('d-none');
    }
    function hideMessage() {
        if (messageEl) messageEl.classList.add('d-none');
    }
    function setLoading(loading) {
        if (btnText) btnText.classList.toggle('d-none', loading);
        if (btnLoading) btnLoading.classList.toggle('d-none', !loading);
        if (submitBtn) submitBtn.disabled = loading;
    }

    function openMakeCallModal(number, customer) {
        const digits = (number || '').replace(/\D/g, '');
        if (numberInput) numberInput.value = digits || number || '';
        if (customerInfo) customerInfo.textContent = customer ? 'Calling: ' + customer : '';
        if (customerInfo && !customer) customerInfo.classList.add('d-none');
        else if (customerInfo) customerInfo.classList.remove('d-none');
        hideMessage();
        if (makeCallModal) {
            const modal = new bootstrap.Modal(makeCallModal);
            modal.show();
            setTimeout(() => numberInput?.focus(), 300);
        }
    }

    makeCallModal?.addEventListener('show.bs.modal', () => {
        setLoading(false);
        if (submitBtn) submitBtn.disabled = false;
    });

    document.querySelectorAll('.pbx-make-call-btn').forEach(btn => {
        btn.addEventListener('click', () => openMakeCallModal(btn.dataset.number || '', btn.dataset.customer || ''));
    });
    document.querySelectorAll('.pbx-call-btn').forEach(btn => {
        btn.addEventListener('click', () => openMakeCallModal(btn.dataset.number || '', btn.dataset.customer || ''));
    });

    function normalizeTel(num) {
        var d = String(num || '').replace(/\D/g, '');
        if (d.indexOf('254') === 0 && d.length === 12) d = d.slice(3);
        else if (d.indexOf('0') === 0 && d.length === 10) d = d.slice(1);
        else if (d.indexOf('00254') === 0 && d.length >= 14) d = d.slice(5);
        return d;
    }
    function updateCallLink() {
        var link = document.getElementById('pbxCallSipPhone');
        if (!link) return;
        var d = normalizeTel(numberInput?.value?.trim());
        link.href = (d && d.length >= 9) ? ('tel:' + d) : '#';
    }
    numberInput?.addEventListener('input', updateCallLink);
    numberInput?.addEventListener('change', updateCallLink);
    makeCallModal?.addEventListener('shown.bs.modal', updateCallLink);

    document.getElementById('pbxCallSipPhone')?.addEventListener('click', function(e) {
        var d = normalizeTel(numberInput?.value?.trim());
        if (!d || d.length < 9) {
            e.preventDefault();
            showMessage('Please enter a valid phone number (at least 9 digits).', true);
            return;
        }
        updateCallLink();
        if (this.href === '#' || this.href.endsWith('#')) {
            e.preventDefault();
        }
    });

    submitBtn?.addEventListener('click', async function() {
        const number = numberInput?.value?.trim();
        const extension = extensionInput?.value?.trim();
        if (!number || !extension) {
            showMessage('Please enter both phone number and your extension.', true);
            return;
        }
        setLoading(true);
        hideMessage();
        try {
            const res = await fetch('{{ route("tools.pbx-manager.make-call") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ number, extension }),
            });
            const data = await res.json();
            if (data.success) {
                let msg = data.message;
                if (data.debug) {
                    msg += ' Sent: ' + data.debug.number_sent + ' → ext ' + data.debug.extension_sent + ' (context: ' + data.debug.context + ', trunk: ' + data.debug.trunk + ').';
                    msg += ' ' + (data.debug.hint || '');
                }
                showMessage(msg, false);
                submitBtn.disabled = true;
                setTimeout(() => {
                    bootstrap.Modal.getInstance(makeCallModal)?.hide();
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show d-flex align-items-center mb-4';
                    alert.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    document.querySelector('.app-content')?.prepend(alert);
                }, 2000);
            } else {
                showMessage(data.message || 'Call failed.', true, data.detail);
            }
        } catch (e) {
            showMessage('Network error. Could not reach the server.', true);
        } finally {
            setLoading(false);
        }
    });
})();
</script>
@endif
