<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=3">
    <title>{{ $buku->judul }} · PDF Viewer</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            padding-bottom: 72px;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 14px;
            background: #064e3b;
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }
        .btn-icon {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.14);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 10px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-icon:disabled {
            opacity: 0.4;
            cursor: default;
        }
        .title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .page-badge {
            flex-shrink: 0;
            font-size: 12px;
            font-weight: 600;
            color: #fde68a;
            background: rgba(0,0,0,0.25);
            padding: 4px 10px;
            border-radius: 999px;
        }
        #status {
            padding: 40px 16px;
            text-align: center;
            font-size: 14px;
            color: #cbd5e1;
        }
        #viewer {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 8px 16px;
        }
        canvas {
            max-width: 100%;
            height: auto !important;
            background: #ffffff;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.35);
        }
        .bottombar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 14px;
            background: rgba(15, 23, 42, 0.95);
            border-top: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
        }
        .page-jump {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #e2e8f0;
        }
        .page-input {
            width: 56px;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.1);
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
        }
        .drawer-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 40;
            background: rgba(0,0,0,0.55);
        }
        .drawer-backdrop.open { display: block; }
        .drawer {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 50;
            width: min(320px, 85vw);
            background: #1e293b;
            border-right: 1px solid rgba(255,255,255,0.12);
            transform: translateX(-100%);
            transition: transform 0.2s ease;
            display: flex;
            flex-direction: column;
        }
        .drawer.open { transform: translateX(0); }
        .drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: #064e3b;
            font-size: 14px;
            font-weight: 700;
        }
        .drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        .toc-item {
            display: block;
            width: 100%;
            text-align: left;
            background: transparent;
            border: 0;
            color: #e2e8f0;
            padding: 9px 10px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.35;
            cursor: pointer;
        }
        .toc-item:hover { background: rgba(255,255,255,0.08); }
        .toc-empty {
            padding: 20px 12px;
            font-size: 13px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="btn-icon" id="toc-btn" title="Daftar Isi">
                ☰ <span id="toc-btn-label">Daftar Isi</span>
            </button>
            <div class="title">{{ $buku->judul }}</div>
        </div>
        <div class="page-badge" id="page-badge">Memuat...</div>
    </header>

    <div id="status">Memuat dokumen PDF...</div>
    <main id="viewer">
        <canvas id="pdf-canvas" style="display:none;"></canvas>
    </main>

    <nav class="bottombar" aria-label="Navigasi halaman PDF">
        <button type="button" class="btn-icon" id="prev-btn" disabled>◀ Sebelumnya</button>
        <form class="page-jump" id="jump-form">
            <span>Hal</span>
            <input type="number" min="1" value="1" class="page-input" id="page-input" aria-label="Nomor halaman">
            <span id="total-pages">/ -</span>
        </form>
        <button type="button" class="btn-icon" id="next-btn" disabled>Berikutnya ▶</button>
    </nav>

    <div class="drawer-backdrop" id="drawer-backdrop"></div>
    <aside class="drawer" id="toc-drawer" aria-label="Daftar Isi / Heading">
        <div class="drawer-head">
            <span>Daftar Isi / Heading</span>
            <button type="button" class="btn-icon" id="close-drawer">✕</button>
        </div>
        <div class="drawer-body" id="toc-list">
            <div class="toc-empty">Memuat daftar isi...</div>
        </div>
    </aside>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        (() => {
            const pdfUrl = @json($pdfUrl);
            const statusEl = document.getElementById('status');
            const canvas = document.getElementById('pdf-canvas');
            const ctx = canvas.getContext('2d');
            const pageBadge = document.getElementById('page-badge');
            const pageInput = document.getElementById('page-input');
            const totalPagesEl = document.getElementById('total-pages');
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const jumpForm = document.getElementById('jump-form');
            const tocBtn = document.getElementById('toc-btn');
            const tocDrawer = document.getElementById('toc-drawer');
            const drawerBackdrop = document.getElementById('drawer-backdrop');
            const closeDrawer = document.getElementById('close-drawer');
            const tocList = document.getElementById('toc-list');

            let pdfDoc = null;
            let currentPage = 1;
            let totalPages = 0;
            let renderTask = null;

            function toggleDrawer(open) {
                tocDrawer.classList.toggle('open', open);
                drawerBackdrop.classList.toggle('open', open);
            }

            tocBtn.addEventListener('click', () => toggleDrawer(true));
            closeDrawer.addEventListener('click', () => toggleDrawer(false));
            drawerBackdrop.addEventListener('click', () => toggleDrawer(false));

            async function renderPage(num) {
                if (!pdfDoc) return;
                currentPage = Math.max(1, Math.min(num, totalPages));
                pageInput.value = String(currentPage);
                pageBadge.textContent = `${currentPage} / ${totalPages}`;
                prevBtn.disabled = currentPage <= 1;
                nextBtn.disabled = currentPage >= totalPages;

                if (renderTask) {
                    try { renderTask.cancel(); } catch (_) {}
                }

                const page = await pdfDoc.getPage(currentPage);
                const containerWidth = Math.min(window.innerWidth - 16, 900);
                const baseViewport = page.getViewport({ scale: 1 });
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                const scale = Math.max(containerWidth / baseViewport.width, 1) * dpr;
                const viewport = page.getViewport({ scale });

                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.style.display = 'block';
                statusEl.style.display = 'none';

                renderTask = page.render({ canvasContext: ctx, viewport });
                try {
                    await renderTask.promise;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } catch (e) {
                    if (e && e.name !== 'RenderingCancelledException') {
                        throw e;
                    }
                }
            }

            async function resolveDestPage(dest) {
                try {
                    const resolved = typeof dest === 'string' ? await pdfDoc.getDestination(dest) : dest;
                    if (Array.isArray(resolved) && resolved[0]) {
                        const idx = await pdfDoc.getPageIndex(resolved[0]);
                        return idx + 1;
                    }
                } catch (_) {}
                return null;
            }

            async function buildOutline(items, level = 0) {
                for (const item of items) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'toc-item';
                    btn.style.paddingLeft = `${10 + level * 14}px`;
                    btn.textContent = item.title || '(Tanpa judul)';

                    btn.addEventListener('click', async () => {
                        const pageNum = await resolveDestPage(item.dest);
                        if (pageNum) {
                            toggleDrawer(false);
                            renderPage(pageNum);
                        }
                    });

                    tocList.appendChild(btn);

                    if (Array.isArray(item.items) && item.items.length > 0) {
                        await buildOutline(item.items, level + 1);
                    }
                }
            }

            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) renderPage(currentPage - 1);
            });
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) renderPage(currentPage + 1);
            });
            jumpForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const target = parseInt(pageInput.value, 10);
                if (!Number.isNaN(target)) renderPage(target);
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft' && currentPage > 1) renderPage(currentPage - 1);
                if (e.key === 'ArrowRight' && currentPage < totalPages) renderPage(currentPage + 1);
            });

            (async () => {
                try {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    pdfDoc = await pdfjsLib.getDocument({
                        url: pdfUrl,
                        rangeChunkSize: 65536,
                        disableAutoFetch: true,
                    }).promise;

                    totalPages = pdfDoc.numPages;
                    pageInput.max = String(totalPages);
                    totalPagesEl.textContent = `/ ${totalPages}`;

                    await renderPage(1);

                    const outline = await pdfDoc.getOutline();
                    tocList.innerHTML = '';
                    if (Array.isArray(outline) && outline.length > 0) {
                        await buildOutline(outline, 0);
                    } else {
                        tocList.innerHTML = '<div class="toc-empty">Dokumen ini belum memiliki daftar isi (heading/bookmark). Gunakan navigasi nomor halaman di bawah.</div>';
                    }
                } catch (err) {
                    pageBadge.textContent = 'Gagal';
                    statusEl.textContent = 'Gagal memuat PDF. Pastikan koneksi internet aktif.';
                }
            })();
        })();
    </script>
</body>
</html>
