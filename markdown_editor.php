<?php
// markdown_editor.php - Premium Markdown Editor for Reports
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Markdown Report Maker | Advanced Systems</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .editor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 1.5rem;
            height: calc(100vh - 250px);
            min-height: 500px;
        }

        .editor-pane, .preview-pane {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-neu-out);
        }

        .pane-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }

        .pane-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        textarea#markdown-input {
            width: 100%;
            height: 100%;
            border: none;
            background: var(--bg-main);
            color: var(--text-main);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-neu-in);
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.95rem;
            line-height: 1.6;
            resize: none;
            outline: none;
            transition: all 0.3s var(--ease-out-expo);
        }

        textarea#markdown-input:focus {
            box-shadow: var(--shadow-neu-in-sm), 0 0 0 2px var(--primary);
        }

        #preview-content {
            width: 100%;
            height: 100%;
            overflow-y: auto;
            background: #ffffff;
            color: #1a1a1a;
            padding: 2.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-neu-in);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            line-height: 1.7;
        }

        html.dark #preview-content {
            background: #ffffff;
            color: #1a1a1a;
        }

        /* Markdown Preview Styling - Professional Document Look */
        #preview-content h1 { font-family: 'Outfit'; font-size: 2.25rem; font-weight: 800; border-bottom: 2px solid #000; padding-bottom: 0.5rem; margin-top: 0; margin-bottom: 1.5rem; color: #000; }
        #preview-content h2 { font-family: 'Outfit'; font-size: 1.5rem; font-weight: 700; border-bottom: 1px solid #ddd; padding-bottom: 0.3rem; margin-top: 1.5rem; margin-bottom: 1rem; color: #333; }
        #preview-content h3 { font-size: 1.25rem; font-weight: 700; margin-top: 1.25rem; }
        #preview-content p { margin-bottom: 1rem; }
        #preview-content ul, #preview-content ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        #preview-content li { margin-bottom: 0.5rem; }
        #preview-content code { background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
        #preview-content pre { background: #f8fafc; padding: 1.25rem; border-radius: 8px; overflow-x: auto; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; }
        #preview-content table { border-collapse: collapse; margin-bottom: 1.5rem; width: 100%; font-size: 0.9rem; }
        #preview-content th, #preview-content td { border: 1px solid #cbd5e1; padding: 0.75rem; text-align: left; }
        #preview-content th { background: #f1f5f9; font-weight: 700; color: #0f172a; }
        #preview-content blockquote { border-left: 4px solid #cbd5e1; padding-left: 1rem; margin-left: 0; font-style: italic; color: #64748b; }

        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .tool-btn {
            background: var(--bg-card);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.2s;
        }

        .tool-btn:hover { color: var(--primary); transform: translateY(-2px); }
        .tool-btn:active { box-shadow: var(--shadow-neu-in-sm); transform: translateY(0); }

        .action-bar {
            position: fixed;
            bottom: 40px;
            right: 40px;
            z-index: 500;
        }

        @media (max-width: 992px) {
            .editor-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .editor-pane, .preview-pane {
                height: 500px;
            }
            .action-bar {
                bottom: 100px;
                right: 20px;
            }
        }

        /* --- NATIVE PRINT STYLES --- */
        @media print {
            @page { margin: 0; } /* Hides browser-generated headers/footers */
            body { 
                background: white !important; 
                padding: 1.5cm !important; /* Re-apply margin via padding for professional spacing */
                margin: 0 !important;
            }
            .navbar, .bottom-nav, .toolbar, .editor-pane, .pane-header, .action-bar, footer, .ip-access, .section-title, .header-text {
                display: none !important;
            }
            main.container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .editor-container {
                display: block !important;
                margin: 0 !important;
                height: auto !important;
            }
            .preview-pane {
                background: white !important;
                box-shadow: none !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                border-radius: 0 !important;
            }
            #preview-content {
                background: white !important;
                box-shadow: none !important;
                height: auto !important;
                padding: 0 !important;
                overflow: visible !important;
                border-radius: 0 !important;
            }
            /* Ensure page breaks don't chop middle of images/tables if possible */
            h1, h2, h3 { page-break-after: avoid; }
            table, pre { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-top: 2.5rem; display: flex; justify-content: space-between; align-items: center;">
            <div class="header-text">
            <h1 style="margin: 0; font-size: 2.5rem; font-weight: 800; letter-spacing: -0.05em;">Markdown to Pdf</h1>
                <p style="color: var(--text-muted); font-size: 1.1rem; font-weight: 500;">Draft or academic reports with professional print-ready layouts.</p>
            </div>
            <div class="toolbar">
                <button onclick="insertAtCursor('# ')" class="tool-btn" title="H1"><i class="bi bi-type-h1"></i></button>
                <button onclick="insertAtCursor('## ')" class="tool-btn" title="H2"><i class="bi bi-type-h2"></i></button>
                <button onclick="insertAtCursor('**', '**')" class="tool-btn" title="Bold"><i class="bi bi-type-bold"></i></button>
                <button onclick="insertAtCursor('*', '*')" class="tool-btn" title="Italic"><i class="bi bi-type-italic"></i></button>
                <button onclick="insertAtCursor('| Header | Header |\n| --- | --- |\n| Content | Content |')" class="tool-btn" title="Table"><i class="bi bi-table"></i></button>
            </div>
        </div>

        <div class="editor-container">
            <div class="editor-pane animate-fade-up">
                <div class="pane-header">
                    <span class="pane-title"><i class="bi bi-pencil-square"></i> Markdown Editor</span>
                    <span id="char-count" style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700;">0 Characters</span>
                </div>
                <textarea id="markdown-input" placeholder="Type your report here..."></textarea>
            </div>

            <div class="preview-pane animate-fade-up" style="animation-delay: 0.1s;">
                <div class="pane-header">
                    <span class="pane-title"><i class="bi bi-eye"></i> Professional Preview</span>
                    <button onclick="clearEditor()" class="btn btn-sm btn-ghost" style="font-size: 0.6rem; padding: 4px 12px; box-shadow: var(--shadow-neu-out-sm);">Clear All</button>
                </div>
                <div id="preview-content"></div>
            </div>
        </div>
    </main>

    <div class="action-bar animate-fade-up" style="animation-delay: 0.3s;">
        <button id="print-btn" onclick="printReport()" class="btn btn-primary" style="padding: 1.25rem 2.5rem; border-radius: 50px; font-size: 1rem; box-shadow: 0 15px 35px rgba(92, 107, 192, 0.3);">
            <i class="bi bi-printer"></i> Print / Export Report
        </button>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        const editor = document.getElementById('markdown-input');
        const preview = document.getElementById('preview-content');
        const charCount = document.getElementById('char-count');

        // Initial content if empty
        if (!editor.value) {
            editor.value = "# ACADEMIC / MEDICAL REPORT\n\n**Subject:** Student Performance Evaluation\n**Date:** " + new Date().toLocaleDateString() + "\n\n---\n\n## 1. EXECUTIVE SUMMARY\nThe student has demonstrated exceptional participation in recent laboratory sessions. Overall attendance remains stable at 95%.\n\n## 2. DETAILED OBSERVATIONS\n| Attribute | Rating | Notes |\n| :--- | :--- | :--- |\n| Technical Skill | Advanced | Excellent use of QR tools |\n| Punctuality | Consistent | Always on time |\n| Collaboration | High | Leads study groups |\n\n## 3. RECOMMENDATIONS\n- Continue existing study plan.\n- Consider advanced certification in system management.";
        }

        function updatePreview() {
            const text = editor.value;
            preview.innerHTML = marked.parse(text);
            charCount.innerText = text.length + " Characters";
            // Store draft
            localStorage.setItem('markdown_report_draft', text);
        }

        editor.addEventListener('input', updatePreview);

        // Load draft if exists
        window.addEventListener('DOMContentLoaded', () => {
            const draft = localStorage.getItem('markdown_report_draft');
            if (draft) {
                editor.value = draft;
            }
            updatePreview();
        });

        function insertAtCursor(before, after = "") {
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            const text = editor.value;
            const selection = text.substring(start, end);
            const replacement = before + selection + after;
            editor.value = text.substring(0, start) + replacement + text.substring(end);
            editor.focus();
            editor.setSelectionRange(start + before.length, start + before.length + selection.length);
            updatePreview();
        }

        function clearEditor() {
            Swal.fire({
                title: 'Clear everything?',
                text: "Your current draft will be wiped.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Yes, clear it'
            }).then((result) => {
                if (result.isConfirmed) {
                    editor.value = "";
                    updatePreview();
                }
            })
        }

        function printReport() {
            if (!editor.value.trim()) {
                Swal.fire('Empty Content', 'Please write something before printing.', 'error');
                return;
            }
            
            // Standard Print Dialog
            window.print();
        }
    </script>
</body>
</html>
