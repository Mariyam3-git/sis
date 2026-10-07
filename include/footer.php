<?php
if (!function_exists('sisRenderFooter')) {
    function sisRenderFooter() {
        ?>
        <div class="modal fade" id="sisCenterAlertModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content sis-center-alert-modal">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i><span data-sis-alert-title>Notice</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" data-sis-alert-message></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary d-none" data-sis-alert-cancel data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-sis-alert-ok data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <footer class="sis-footer">
            <span class="sis-footer-brand"><img src="<?php echo e(appAsset('assets/images/logo.svg')); ?>" alt="Company logo"></span>
            <span>V0.07.26</span>
            <span>Customer Care Management System</span>
        </footer>

        <script>
        if (!window.bootstrap) { document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"><\/script>'); }
        </script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            // One token per rendered page, shared by its POST forms. A successful
            // action redirects to a fresh page/token, so this still blocks genuine
            // double-click/replay requests without growing the session per table row.
            const sisPagePostOnceToken = '<?php echo e(function_exists('sisPostOnceToken') ? sisPostOnceToken('page_form') : ''); ?>';
            const sisPageCsrfToken = '<?php echo e(function_exists('csrfToken') ? csrfToken() : ''); ?>';
            if (sisPagePostOnceToken) {
                document.querySelectorAll('form').forEach(function (form) {
                    const method = (form.getAttribute('method') || 'get').toLowerCase();
                    if (method !== 'post') return;
                    if (!form.querySelector('input[name="sis_post_once_token"]')) {
                        const onceInput = document.createElement('input');
                        onceInput.type = 'hidden';
                        onceInput.name = 'sis_post_once_token';
                        onceInput.value = sisPagePostOnceToken;
                        form.appendChild(onceInput);
                    }
                    if (sisPageCsrfToken && !form.querySelector('input[name="csrf_token"]')) {
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = sisPageCsrfToken;
                        form.appendChild(csrfInput);
                    }
                });
            }

            // Turn legacy delete/approve/restore links into CSRF-protected POST forms.
            // Their original GET URLs are never followed, preventing crawler/prefetch actions.
            const sisMutationKeys = ['delete','hard_delete','restore','deactivate','activate','approve','reject','fulfill','receive','toggle_paid'];
            // Submit a set of fields as a CSRF-protected POST to the given path (defaults to this page).
            window.sisSubmitPost = function (fields, path) {
                const form = document.createElement('form');
                form.method = 'post';
                form.action = path || window.location.pathname;
                const values = Object.assign({}, fields, { csrf_token: sisPageCsrfToken, sis_post_once_token: sisPagePostOnceToken });
                Object.keys(values).forEach(function (key) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = values[key];
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            };
            document.querySelectorAll('a[href*="?"]').forEach(function (link) {
                let url;
                try { url = new URL(link.getAttribute('href'), window.location.href); } catch (err) { return; }
                const mutationKey = sisMutationKeys.find(function (key) { return url.searchParams.has(key); });
                if (!mutationKey || url.origin !== window.location.origin) return;
                link.setAttribute('href', '#');
                link.addEventListener('click', function (event) {
                    // An inline onclick="return confirm(...)" that was cancelled has already prevented the default.
                    if (event.defaultPrevented) return;
                    event.preventDefault();
                    const fields = {};
                    [mutationKey, 'id', 'reason'].forEach(function (key) {
                        if (url.searchParams.has(key)) fields[key] = url.searchParams.get(key);
                    });
                    window.sisSubmitPost(fields, url.pathname);
                });
            });

            const sidebar = document.querySelector('[data-sis-sidebar]');
            const toggleButtons = document.querySelectorAll('[data-sis-sidebar-toggle]');
            toggleButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    if (sidebar) sidebar.classList.toggle('open');
                });
            });
            document.addEventListener('click', function (event) {
                if (!sidebar || window.innerWidth > 991 || !sidebar.classList.contains('open')) return;
                const clickedToggle = event.target.closest('[data-sis-sidebar-toggle]');
                if (!sidebar.contains(event.target) && !clickedToggle) sidebar.classList.remove('open');
            });
            document.addEventListener('hidden.bs.modal', function () {
                document.body.style.overflow = '';
            });
            document.addEventListener('click', function () {
                const anyOpenOverlay = document.querySelector('.modal.show, .es-overlay.open, .modal-overlay.show, .modal-overlay.open');
                if (!anyOpenOverlay) document.body.style.overflow = '';
            });

            // Prevent accidental double-click submits on protected POST forms.
            // Important: do not disable buttons before our custom confirmation modal is answered.
            // Earlier versions disabled Save/Role buttons while the confirmation popup was still open.
            document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function (form) {
                if (!form.querySelector('input[name="request_token"], input[name="sis_post_once_token"]')) return;
                if (form.dataset.sisDuplicateGuard === '1') return;
                form.dataset.sisDuplicateGuard = '1';
                form.addEventListener('submit', function () {
                    const needsConfirm = !!(form.dataset.confirmMessage || (form.getAttribute('onsubmit') || '').indexOf('confirm(') !== -1);
                    if (needsConfirm && form.dataset.sisConfirmed !== '1') return;
                    setTimeout(function () {
                        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (btn) {
                            btn.disabled = true;
                            if (btn.classList && (btn.classList.contains('btn-primary') || btn.classList.contains('btn-login') || btn.classList.contains('btn-success') || btn.classList.contains('btn-warning'))) {
                                btn.dataset.originalHtml = btn.dataset.originalHtml || btn.innerHTML;
                                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
                            }
                        });
                    }, 0);
                });
            });

            // Centered alert/confirm system. This replaces browser default alert popups with a branded modal.
            (function () {
                const modalEl = document.getElementById('sisCenterAlertModal');
                if (!modalEl) return;
                const titleEl = modalEl.querySelector('[data-sis-alert-title]');
                const messageEl = modalEl.querySelector('[data-sis-alert-message]');
                const okBtn = modalEl.querySelector('[data-sis-alert-ok]');
                const cancelBtn = modalEl.querySelector('[data-sis-alert-cancel]');
                let fallbackBackdrop = null;
                function forceModalHide() {
                    try {
                        if (window.bootstrap && bootstrap.Modal) {
                            const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
                            instance.hide();
                        }
                    } catch (err) {}
                    modalEl.classList.remove('show');
                    modalEl.style.display = 'none';
                    modalEl.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    document.querySelectorAll('.modal-backdrop.sis-fallback-backdrop').forEach(function (b) { b.remove(); });
                    if (fallbackBackdrop && fallbackBackdrop.parentNode) fallbackBackdrop.parentNode.removeChild(fallbackBackdrop);
                    fallbackBackdrop = null;
                }
                function forceModalShow() {
                    if (window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl, {backdrop: true, keyboard: true}).show();
                        return;
                    }
                    forceModalHide();
                    fallbackBackdrop = document.createElement('div');
                    fallbackBackdrop.className = 'modal-backdrop fade show sis-fallback-backdrop';
                    document.body.appendChild(fallbackBackdrop);
                    modalEl.style.display = 'block';
                    modalEl.removeAttribute('aria-hidden');
                    modalEl.setAttribute('aria-modal', 'true');
                    modalEl.classList.add('show');
                    document.body.classList.add('modal-open');
                    document.body.style.overflow = 'hidden';
                    setTimeout(function () { try { okBtn.focus(); } catch (err) {} }, 30);
                }
                function show(message, opts) {
                    opts = opts || {};
                    return new Promise(function (resolve) {
                        titleEl.textContent = opts.title || 'Notice';
                        messageEl.innerHTML = String(message || '').replace(/\n/g, '<br>');
                        okBtn.textContent = opts.okText || 'OK';
                        cancelBtn.textContent = opts.cancelText || 'Cancel';
                        cancelBtn.classList.toggle('d-none', !opts.confirm);
                        okBtn.disabled = false;
                        cancelBtn.disabled = false;
                        let resolved = false;
                        function done(value) {
                            if (resolved) return;
                            resolved = true;
                            cleanup();
                            forceModalHide();
                            resolve(value);
                        }
                        function cleanup() {
                            okBtn.removeEventListener('click', okHandler);
                            cancelBtn.removeEventListener('click', cancelHandler);
                            modalEl.removeEventListener('hidden.bs.modal', hiddenHandler);
                            document.removeEventListener('keydown', keyHandler);
                        }
                        function okHandler(event){ if (event) event.preventDefault(); done(true); }
                        function cancelHandler(event){ if (event) event.preventDefault(); done(false); }
                        function hiddenHandler(){ done(false); }
                        function keyHandler(event){ if (event.key === 'Escape') done(false); }
                        okBtn.addEventListener('click', okHandler);
                        cancelBtn.addEventListener('click', cancelHandler);
                        modalEl.addEventListener('hidden.bs.modal', hiddenHandler);
                        document.addEventListener('keydown', keyHandler);
                        forceModalShow();
                    });
                }
                window.sisCenterAlert = function (message, type, title) { return show(message, {title: title || 'Notice'}); };
                window.sisCenterConfirm = function (message, title) { return show(message, {confirm: true, title: title || 'Confirm Action', okText: 'Yes, continue'}); };
                // Native window.alert/window.confirm are not overridden.
                // Page alerts stay inline. Only explicit confirmations use the center modal.

                document.querySelectorAll('[data-confirm-logout]').forEach(function (link) {
                    if (link.dataset.boundLogoutConfirm === '1') return;
                    link.dataset.boundLogoutConfirm = '1';
                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        const href = link.getAttribute('href');
                        window.sisCenterConfirm('Are you sure you want to logout?', 'Confirm Logout').then(function (ok) {
                            if (ok && href) window.location.href = href;
                        });
                    });
                });

                // Do not automatically replace every page confirm()/alert with a popup.
                // This avoids unnecessary modal interruptions and keeps older forms working normally.
                document.querySelectorAll('[data-sis-confirm-message]').forEach(function (el) {
                    if (el.dataset.sisConfirmBound === '1') return;
                    el.dataset.sisConfirmBound = '1';
                    el.addEventListener('click', function (event) {
                        event.preventDefault();
                        const href = el.getAttribute('href');
                        window.sisCenterConfirm(el.dataset.sisConfirmMessage || 'Continue with this action?').then(function (ok) {
                            if (ok && href) window.location.href = href;
                        });
                    });
                });
            })();

            // Bootstrap modal fallback for pages/actions that use data-bs-toggle="modal".
            // This keeps Admin role/password controls working even if Bootstrap JS fails to load.
            (function () {
                function ensureBackdrop() {
                    let backdrop = document.querySelector('.modal-backdrop.sis-modal-fallback-backdrop');
                    if (!backdrop) {
                        backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show sis-modal-fallback-backdrop';
                        document.body.appendChild(backdrop);
                    }
                    return backdrop;
                }
                function closeModal(modal) {
                    if (!modal) return;
                    if (window.bootstrap && bootstrap.Modal) {
                        try { bootstrap.Modal.getOrCreateInstance(modal).hide(); return; } catch (err) {}
                    }
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    modal.removeAttribute('aria-modal');
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    document.querySelectorAll('.modal-backdrop.sis-modal-fallback-backdrop').forEach(function (b) { b.remove(); });
                }
                function openModal(modal) {
                    if (!modal) return;
                    if (window.bootstrap && bootstrap.Modal) {
                        try { bootstrap.Modal.getOrCreateInstance(modal).show(); return; } catch (err) {}
                    }
                    document.querySelectorAll('.modal.show').forEach(function (open) { if (open !== modal) closeModal(open); });
                    ensureBackdrop();
                    modal.style.display = 'block';
                    modal.removeAttribute('aria-hidden');
                    modal.setAttribute('aria-modal', 'true');
                    modal.classList.add('show');
                    document.body.classList.add('modal-open');
                    document.body.style.overflow = 'hidden';
                    setTimeout(function () {
                        const focusable = modal.querySelector('input, select, textarea, button, a[href]');
                        if (focusable) { try { focusable.focus(); } catch (err) {} }
                    }, 30);
                }
                document.addEventListener('click', function (event) {
                    const trigger = event.target.closest('[data-bs-toggle="modal"]');
                    if (trigger) {
                        const selector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
                        const modal = selector ? document.querySelector(selector) : null;
                        if (modal && !(window.bootstrap && bootstrap.Modal)) {
                            event.preventDefault();
                            openModal(modal);
                        }
                        return;
                    }
                    const closer = event.target.closest('[data-bs-dismiss="modal"]');
                    if (closer) {
                        const modal = closer.closest('.modal');
                        if (modal && !(window.bootstrap && bootstrap.Modal)) {
                            event.preventDefault();
                            closeModal(modal);
                        }
                    }
                    if (event.target.classList && event.target.classList.contains('modal') && event.target.classList.contains('show')) {
                        closeModal(event.target);
                    }
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        const modal = document.querySelector('.modal.show');
                        if (modal && !(window.bootstrap && bootstrap.Modal)) closeModal(modal);
                    }
                });
            })();

            // Bootstrap dropdown fallback for notification and announcement menus.
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    const holder = button.closest('.dropdown');
                    const menu = holder ? holder.querySelector('.dropdown-menu') : null;
                    if (!menu) return;
                    const wasOpen = menu.classList.contains('show');
                    function openFallback() {
                        document.querySelectorAll('.dropdown-menu.show').forEach(function (open) { if (open !== menu) open.classList.remove('show'); });
                        document.querySelectorAll('.dropdown.sis-dropdown-open').forEach(function (openHolder) { if (openHolder !== holder) openHolder.classList.remove('sis-dropdown-open'); });
                        menu.classList.add('show');
                        if (holder) holder.classList.add('sis-dropdown-open');
                        button.setAttribute('aria-expanded', 'true');
                    }
                    if (window.bootstrap && bootstrap.Dropdown) {
                        setTimeout(function () { if (!wasOpen && !menu.classList.contains('show')) openFallback(); }, 90);
                        return;
                    }
                    event.preventDefault();
                    if (menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        if (holder) holder.classList.remove('sis-dropdown-open');
                        button.setAttribute('aria-expanded', 'false');
                    } else {
                        openFallback();
                    }
                });
            });
            document.addEventListener('click', function (event) {
                if (event.target.closest('.dropdown')) return;
                document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) { menu.classList.remove('show'); });
                document.querySelectorAll('.dropdown.sis-dropdown-open').forEach(function (holder) { holder.classList.remove('sis-dropdown-open'); });
            });

            // Inactivity timeout is enforced on the server in config.php.
            // The old client-side redirect was removed because it could interrupt forms and leave stale UI state.



            // Dashboard announcement showcase: show one announcement at a time.
            document.querySelectorAll('[data-sis-announcement-hero-slider]').forEach(function (slider) {
                const slides = Array.prototype.slice.call(slider.querySelectorAll('[data-announcement-slide]'));
                const dots = Array.prototype.slice.call(slider.querySelectorAll('[data-announcement-dot]'));
                const prev = slider.querySelector('[data-announcement-prev]');
                const next = slider.querySelector('[data-announcement-next]');
                if (slides.length <= 1) return;
                let index = slides.findIndex(function (slide) { return slide.classList.contains('active'); });
                if (index < 0) index = 0;
                let timer = null;
                function show(nextIndex) {
                    index = (nextIndex + slides.length) % slides.length;
                    slides.forEach(function (slide, i) {
                        const active = i === index;
                        slide.classList.toggle('active', active);
                        slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                    });
                    dots.forEach(function (dot, i) { dot.classList.toggle('active', i === index); });
                }
                function restart() {
                    clearInterval(timer);
                    timer = setInterval(function () { show(index + 1); }, 9000);
                }
                if (prev) prev.addEventListener('click', function () { show(index - 1); restart(); });
                if (next) next.addEventListener('click', function () { show(index + 1); restart(); });
                dots.forEach(function (dot) {
                    dot.addEventListener('click', function () {
                        show(parseInt(dot.getAttribute('data-announcement-dot') || '0', 10));
                        restart();
                    });
                });
                slider.addEventListener('mouseenter', function () { clearInterval(timer); });
                slider.addEventListener('mouseleave', restart);
                show(index);
                restart();
            });

            // Auto-refresh announcements and allow users to mark them read without page reload.
            (function () {
                const root = document.querySelector('[data-sis-announcements]');
                if (!root) return;
                const apiUrl = root.getAttribute('data-api-url');
                const countEl = root.querySelector('[data-announcement-count]');
                const listEl = root.querySelector('[data-announcement-list]');
                const markAll = root.querySelector('[data-mark-all-announcements-read]');
                const toastWrap = document.querySelector('[data-notification-toast-wrap]');
                let lastCount = parseInt((countEl && countEl.textContent) || '0', 10) || 0;

                function esc(value) {
                    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (ch) {
                        return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];
                    });
                }
                function post(action, data) {
                    const fd = new FormData();
                    fd.append('action', action);
                    fd.append('csrf_token', '<?php echo e(csrfToken()); ?>');
                    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
                    return fetch(apiUrl, {method:'POST', body:fd, credentials:'same-origin'}).then(function (r) { if (r.status === 401) { window.location.href = '<?php echo appPath('logout.php?timeout=1&force=1'); ?>'; return Promise.reject(new Error('Session expired')); } return r.json(); });
                }
                function render(payload) {
                    if (!payload || !payload.ok) return;
                    const count = parseInt(payload.count || 0, 10);
                    if (countEl) {
                        countEl.textContent = count;
                        countEl.style.display = count > 0 ? '' : 'none';
                    }
                    if (listEl) {
                        if (!payload.items || payload.items.length === 0) {
                            listEl.innerHTML = '<div class="sis-notification-empty"><i class="bi bi-check2-circle"></i> No active announcements</div>';
                        } else {
                            listEl.innerHTML = payload.items.map(function (a) {
                                const dotClass = a.priority_class ? ' bg-' + esc(a.priority_class) : '';
                                const pin = parseInt(a.is_pinned || 0, 10) ? '<span class="sis-mini-badge">Pinned</span> ' : '';
                                const ack = parseInt(a.requires_ack || 0, 10) ? '<span class="sis-mini-badge">Read</span> ' : '';
                                return '<a class="dropdown-item sis-notification-item sis-announcement-item ' + (parseInt(a.is_read,10) ? '' : 'unread') + '" href="' + esc(a.url) + '" data-announcement-id="' + esc(a.id) + '">' +
                                    '<span class="sis-notification-dot' + dotClass + '"></span><span><strong>' + pin + esc(a.title) + '</strong><small>' + esc(a.message) + '</small><em>' + ack + esc(a.created_at) + '</em></span></a>';
                            }).join('');
                        }
                    }
                    if (count > lastCount && toastWrap) {
                        const toast = document.createElement('div');
                        toast.className = 'toast show sis-live-toast';
                        toast.innerHTML = '<div class="toast-header"><i class="bi bi-megaphone-fill text-primary me-2"></i><strong class="me-auto">New announcement</strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div><div class="toast-body">A new management announcement is available.</div>';
                        toastWrap.appendChild(toast);
                        setTimeout(function(){ toast.remove(); }, 9000);
                    }
                    lastCount = count;
                }
                function refresh() { post('list', {}).then(render).catch(function () {}); }
                root.addEventListener('click', function (event) {
                    const item = event.target.closest('[data-announcement-id]');
                    if (item) {
                        const id = item.getAttribute('data-announcement-id');
                        post('mark_read', {id:id}).then(render).catch(function () {});
                        return;
                    }
                });
                document.addEventListener('click', function (event) {
                    const btn = event.target.closest('[data-inline-announcement-read]');
                    if (!btn) return;
                    event.preventDefault();
                    const id = btn.getAttribute('data-inline-announcement-read');
                    btn.disabled = true;
                    post('mark_read', {id:id}).then(function (payload) {
                        const card = btn.closest('.sis-announcement-card');
                        if (card) card.classList.remove('unread');
                        btn.remove();
                        render(payload);
                    }).catch(function () { btn.disabled = false; });
                });
                if (markAll) {
                    markAll.addEventListener('click', function (event) {
                        event.preventDefault();
                        post('mark_all_read', {}).then(render).catch(function () {});
                    });
                }
                refresh();
                setInterval(refresh, 30000);
            })();

            // Auto-refresh notifications and allow users to mark them read without page reload.
            (function () {
                const root = document.querySelector('[data-sis-notifications]');
                if (!root) return;
                const apiUrl = root.getAttribute('data-api-url');
                const countEl = root.querySelector('[data-notification-count]');
                const listEl = root.querySelector('[data-notification-list]');
                const markAll = root.querySelector('[data-mark-all-read]');
                const soundToggle = root.querySelector('[data-notification-sound-toggle]');
                const toastWrap = document.querySelector('[data-notification-toast-wrap]');
                let lastCount = parseInt((countEl && countEl.textContent) || '0', 10) || 0;
                let soundEnabled = localStorage.getItem('sis_notification_sound') !== 'off';
                let audioContext = null;

                function updateSoundButton() {
                    if (!soundToggle) return;
                    soundToggle.innerHTML = soundEnabled ? '<i class="bi bi-volume-up"></i>' : '<i class="bi bi-volume-mute"></i>';
                    soundToggle.title = soundEnabled ? 'Notification sound is on' : 'Notification sound is off';
                }
                function unlockAudio() {
                    if (!soundEnabled || audioContext) return;
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    try { audioContext = new AudioCtx(); } catch (err) {}
                }
                function playNotificationSound() {
                    if (!soundEnabled) return;
                    unlockAudio();
                    if (!audioContext) return;
                    try {
                        if (audioContext.state === 'suspended') audioContext.resume();
                        const now = audioContext.currentTime;
                        [880, 1175].forEach(function (frequency, index) {
                            const oscillator = audioContext.createOscillator();
                            const gain = audioContext.createGain();
                            oscillator.type = 'sine';
                            oscillator.frequency.value = frequency;
                            gain.gain.setValueAtTime(0.0001, now + index * 0.16);
                            gain.gain.exponentialRampToValueAtTime(0.16, now + index * 0.16 + 0.02);
                            gain.gain.exponentialRampToValueAtTime(0.0001, now + index * 0.16 + 0.14);
                            oscillator.connect(gain);
                            gain.connect(audioContext.destination);
                            oscillator.start(now + index * 0.16);
                            oscillator.stop(now + index * 0.16 + 0.15);
                        });
                    } catch (err) {}
                }
                document.addEventListener('pointerdown', unlockAudio, {once:true});
                document.addEventListener('keydown', unlockAudio, {once:true});
                updateSoundButton();

                function esc(value) {
                    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (ch) {
                        return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];
                    });
                }
                function post(action, data) {
                    const fd = new FormData();
                    fd.append('action', action);
                    fd.append('csrf_token', '<?php echo e(csrfToken()); ?>');
                    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
                    return fetch(apiUrl, {method:'POST', body:fd, credentials:'same-origin'}).then(function (r) { if (r.status === 401) { window.location.href = '<?php echo appPath('logout.php?timeout=1&force=1'); ?>'; return Promise.reject(new Error('Session expired')); } return r.json(); });
                }
                function render(payload) {
                    if (!payload || !payload.ok) return;
                    const count = parseInt(payload.count || 0, 10);
                    if (countEl) {
                        countEl.textContent = count;
                        countEl.style.display = count > 0 ? '' : 'none';
                    }
                    if (listEl) {
                        if (!payload.items || payload.items.length === 0) {
                            listEl.innerHTML = '<div class="sis-notification-empty"><i class="bi bi-check2-circle"></i> No notifications</div>';
                        } else {
                            listEl.innerHTML = payload.items.map(function (n) {
                                return '<a class="dropdown-item sis-notification-item ' + (parseInt(n.is_read,10) ? '' : 'unread') + '" href="' + esc(n.url) + '" data-notification-id="' + esc(n.id) + '">' +
                                    '<span class="sis-notification-dot"></span><span><strong>' + esc(n.title) + '</strong><small>' + esc(n.message) + '</small><em>' + esc(n.created_at) + '</em></span></a>';
                            }).join('');
                        }
                    }
                    if (count > lastCount && toastWrap) {
                        playNotificationSound();
                        const toast = document.createElement('div');
                        toast.className = 'toast show sis-live-toast';
                        toast.innerHTML = '<div class="toast-header"><i class="bi bi-bell-fill text-danger me-2"></i><strong class="me-auto">New notification</strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div><div class="toast-body">You have a new notification. Open the bell to view it.</div>';
                        toastWrap.appendChild(toast);
                        setTimeout(function(){ toast.remove(); }, 8000);
                    }
                    lastCount = count;
                }
                function refresh() { post('list', {}).then(render).catch(function () {}); }
                root.addEventListener('click', function (event) {
                    const item = event.target.closest('[data-notification-id]');
                    if (item) {
                        const id = item.getAttribute('data-notification-id');
                        post('mark_read', {id:id}).then(render).catch(function () {});
                        return;
                    }
                });
                if (markAll) {
                    markAll.addEventListener('click', function (event) {
                        event.preventDefault();
                        post('mark_all_read', {}).then(render).catch(function () {});
                    });
                }
                if (soundToggle) {
                    soundToggle.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        soundEnabled = !soundEnabled;
                        localStorage.setItem('sis_notification_sound', soundEnabled ? 'on' : 'off');
                        updateSoundButton();
                        if (soundEnabled) {
                            unlockAudio();
                            playNotificationSound();
                        }
                    });
                }
                refresh();
                setInterval(refresh, 20000);
            })();
        });
        </script>

        <script>
        // Load the global theme normalizer on pages that use the shared layout but forgot to include it.
        (function(){
            if (window.SISTheme) return;
            var existing = document.querySelector('script[src*="assets/js/sis-theme.js"]');
            if (existing) return;
            var s = document.createElement('script');
            s.src = '<?php echo appAsset('assets/js/sis-theme.js'); ?>';
            s.defer = true;
            document.body.appendChild(s);
        })();
        </script>
        <?php
    }
}

if (!function_exists('sisRenderLayoutEnd')) {
    function sisRenderLayoutEnd() {
        echo "\n            </section>\n        </main>\n    </div>\n";
        sisRenderFooter();
    }
}
