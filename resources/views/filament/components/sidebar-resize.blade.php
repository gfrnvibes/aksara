<script>
    (function () {
        const WIDTH_KEY = 'aksara_sidebar_width';
        const COLLAPSED_KEY = 'aksara_sidebar_collapsed';
        const MIN_WIDTH = 190;
        const MAX_WIDTH = 420;
        const COLLAPSE_THRESHOLD = 135;

        function sidebarStore() {
            return window.Alpine && typeof window.Alpine.store === 'function'
                ? window.Alpine.store('sidebar')
                : null;
        }

        function applySavedState() {
            const store = sidebarStore();
            const isCollapsed = localStorage.getItem(COLLAPSED_KEY) === '1';

            if (store) {
                if (isCollapsed && store.isOpen) {
                    store.close();
                } else if (!isCollapsed && !store.isOpen) {
                    store.open();
                }
            }

            if (isCollapsed) {
                document.documentElement.style.removeProperty('--sidebar-width');
                return;
            }

            const saved = localStorage.getItem(WIDTH_KEY);
            if (saved) {
                const px = parseInt(saved, 10);
                if (px >= MIN_WIDTH && px <= MAX_WIDTH) {
                    document.documentElement.style.setProperty('--sidebar-width', px + 'px');
                }
            }
        }

        function attachHandle() {
            const sidebar = document.querySelector('.fi-main-sidebar');
            if (!sidebar) return;

            document.querySelectorAll('.fi-sidebar-close-collapse-sidebar-btn').forEach((btn) => {
                if (btn.dataset.aksaraBound) return;
                btn.dataset.aksaraBound = '1';
                btn.addEventListener('click', () => {
                    document.documentElement.style.removeProperty('--sidebar-width');
                    localStorage.setItem(COLLAPSED_KEY, '1');
                });
            });

            document.querySelectorAll('.fi-sidebar-open-collapse-sidebar-btn').forEach((btn) => {
                if (btn.dataset.aksaraBound) return;
                btn.dataset.aksaraBound = '1';
                btn.addEventListener('click', () => {
                    localStorage.setItem(COLLAPSED_KEY, '0');
                    const saved = localStorage.getItem(WIDTH_KEY);
                    if (saved) {
                        const px = parseInt(saved, 10);
                        if (px >= MIN_WIDTH && px <= MAX_WIDTH) {
                            document.documentElement.style.setProperty('--sidebar-width', px + 'px');
                        }
                    }
                });
            });

            if (sidebar.querySelector('.fi-sidebar-resize-handle')) return;

            const handle = document.createElement('div');
            handle.className = 'fi-sidebar-resize-handle hidden lg:block';
            handle.title = 'Geser ke kiri penuh untuk mengecilkan sidebar, atau geser ke kanan untuk melebarkan';
            sidebar.appendChild(handle);

            let dragging = false;

            handle.addEventListener('mousedown', (e) => {
                if (window.innerWidth < 1024) return;
                dragging = true;
                handle.classList.add('is-resizing');
                document.body.style.userSelect = 'none';
                document.body.style.cursor = 'col-resize';
                e.preventDefault();
            });

            window.addEventListener('mousemove', (e) => {
                if (!dragging) return;
                const store = sidebarStore();

                if (e.clientX < COLLAPSE_THRESHOLD) {
                    if (store && store.isOpen) {
                        store.close();
                    }
                    document.documentElement.style.removeProperty('--sidebar-width');
                    localStorage.setItem(COLLAPSED_KEY, '1');
                    return;
                }

                if (store && !store.isOpen) {
                    store.open();
                }
                localStorage.setItem(COLLAPSED_KEY, '0');

                const width = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, e.clientX));
                document.documentElement.style.setProperty('--sidebar-width', width + 'px');
                localStorage.setItem(WIDTH_KEY, String(width));
            });

            window.addEventListener('mouseup', () => {
                if (!dragging) return;
                dragging = false;
                handle.classList.remove('is-resizing');
                document.body.style.userSelect = '';
                document.body.style.cursor = '';
            });
        }

        applySavedState();
        document.addEventListener('alpine:init', applySavedState);
        document.addEventListener('DOMContentLoaded', () => {
            applySavedState();
            attachHandle();
        });
        document.addEventListener('livewire:navigated', () => {
            applySavedState();
            attachHandle();
        });
    })();
</script>
