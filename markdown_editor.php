<?php
// markdown_editor.php - Realtime Markdown to PDF Editor
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Editor | Attendance System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@300;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    
    <!-- KaTeX for Math Support -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body);"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        :root {
            --editor-bg: #ffffff;
            --preview-bg: #ffffff;
            --border: #e2e8f0;
            --text-main: #000000;
            --text-muted: #64748b;
            --accent: var(--primary);
        }
        body { overflow: hidden; height: 100vh; display: flex; flex-direction: column; background: var(--preview-bg); color: var(--text-main); }
        
        .editor-container {
            display: flex; flex: 1; height: calc(100vh - 60px); width: 100%;
            border-top: 1px solid var(--border);
        }
        
        .pane { flex: 1; height: 100%; overflow-y: auto; padding: 3rem; position: relative; }
        
        #editor {
            width: 100%; height: 100%; border: none; outline: none;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 1rem; line-height: 1.8; resize: none;
            background: var(--editor-bg); color: #1e293b;
            padding: 1rem; border-radius: 8px;
        }
        
        #preview {
            background: #ffffff; border-left: 1px solid var(--border);
            color: var(--text-main); line-height: 1.7; padding: 4rem;
            font-family: 'Inter', -apple-system, sans-serif;
        }
        
        /* Premium Light Markdown Styling */
        #preview h1 { 
            font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.5rem;
            letter-spacing: -0.04em; margin-bottom: 2rem; color: #000000;
            border-bottom: 2px solid #000000; padding-bottom: 1rem;
        }
        #preview h2 { 
            font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.5rem;
            margin-top: 3rem; margin-bottom: 1rem; color: #000000;
            letter-spacing: -0.02em;
        }
        #preview h3 { font-weight: 700; font-size: 1.2rem; color: #000000; margin-top: 2rem; }
        
        #preview p { margin-bottom: 1.25rem; font-size: 1.05rem; color: #1e293b; }
        
        /* Accounting Style Tables */
        #preview table { width: 100%; border-collapse: collapse; margin: 2rem 0; font-size: 0.95rem; }
        #preview th { 
            text-align: left; padding: 1rem; border-bottom: 3px solid #000000; 
            font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem;
        }
        #preview td { padding: 1rem; border-bottom: 1px solid #e2e8f0; color: #1e293b; }
        #preview tr:last-child td { border-bottom: none; }
        #preview tr:hover { background: #f8fafc; }

        #preview blockquote {
            border-left: 5px solid #000000; background: #f1f5f9;
            margin: 2rem 0; padding: 1.5rem 2rem; border-radius: 0 4px 4px 0;
            font-style: italic; color: #334155; font-size: 1.1rem;
        }

        #preview pre {
            background: #1e293b; color: #f8fafc; padding: 1.5rem; border-radius: 8px;
            font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; overflow-x: auto;
            margin: 1.5rem 0;
        }
        #preview code { 
            font-family: 'JetBrains Mono', monospace; font-size: 0.85em;
            background: #f1f5f9; color: #e11d48; padding: 0.2rem 0.4rem; border-radius: 4px;
        }
        #preview pre code { background: transparent; color: inherit; padding: 0; }

        #preview ul, #preview ol { margin-bottom: 1.5rem; padding-left: 1.5rem; color: #1e293b; }
        #preview li { margin-bottom: 0.5rem; }

        .toolbar {
            height: 60px; display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem; background: #ffffff; z-index: 10;
            border-bottom: 1px solid var(--border);
        }
        
        .toolbar h3 { color: #000000; }
        .btn-ghost { color: #64748b; }
        .btn-ghost:hover { color: #000000; background: #f1f5f9; }

        .tab-label {
            position: absolute; top: 0.5rem; right: 1rem; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted);
            font-weight: 700; pointer-events: none;
        }

        @media (max-width: 768px) {
            .editor-container { flex-direction: column; }
            #preview { border-left: none; border-top: 1px solid var(--border); }
        }

        /* 100% Accuracy: High-Fidelity Print Engine */
        @media print {
            body { background: white !important; color: black !important; overflow: visible !important; height: auto !important; }
            .toolbar, .pane:first-child, .tab-label { display: none !important; }
            .editor-container { display: block !important; border: none !important; }
            #preview { 
                width: 100% !important; border: none !important; padding: 0 !important; 
                margin: 0 !important; overflow: visible !important; min-height: 100% !important;
            }
            #preview h1, #preview h2, #preview h3 { color: black !important; border-color: black !important; }
            #preview table { page-break-inside: auto; border: 1px solid #ddd !important; }
            #preview tr { page-break-inside: avoid; page-break-after: auto; }
            #preview pre { background: #f8fafc !important; color: black !important; border: 1px solid #e2e8f0 !important; border-radius: 4px; }
            #preview code { color: #e11d48 !important; background: #f1f5f9 !important; }
            
            @page { margin: 20mm; size: A4; }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="index.php" class="btn btn-ghost" style="padding: 0.5rem;"><i class="bi bi-arrow-left"></i></a>
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">Markdown <span class="text-gradient">Editor</span></h3>
            <span class="badge" style="background: #f1f5f9; color: #1e293b; border: 1px solid #e2e8f0; font-size: 0.65rem; padding: 0.3rem 0.6rem; border-radius: 4px;">
                <i class="bi bi-check-circle-fill text-success" style="margin-right: 0.3rem;"></i> Print Aligned
            </span>
        </div>
        
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <div class="dropdown" style="margin-right: 1rem;">
                <i class="bi bi-info-circle" style="color: #64748b; cursor: help;" title="High Fidelity (100% Accuracy) uses your browser engine for pixel-perfect math and layout. Export PDF (Quick) is a one-click server download."></i>
            </div>
            <button onclick="loadTemplate('dossier')" class="btn btn-ghost btn-sm">Dossier Template</button>
            <button onclick="window.print()" class="btn btn-ghost btn-sm">
                <i class="bi bi-printer"></i> Save (High Fidelity)
            </button>
            <button onclick="generatePDF()" class="btn btn-primary btn-sm">
                <i class="bi bi-file-pdf"></i> Quick Export
            </button>
        </div>
    </div>

    <div class="editor-container">
        <div class="pane">
            <span class="tab-label">Editor (Markdown)</span>
            <textarea id="editor" placeholder="Start typing markdown here..."></textarea>
        </div>
        <div class="pane" id="preview">
            <span class="tab-label">Live Preview (HTML)</span>
            <div id="render-target"></div>
        </div>
    </div>

    <form id="pdf-form" action="api/generate_custom_pdf.php" method="POST" style="display: none;">
        <input type="hidden" name="markdown" id="pdf-content">
    </form>

    <script>
        const editor = document.getElementById('editor');
        const renderTarget = document.getElementById('render-target');
        const pdfContent = document.getElementById('pdf-content');
        const pdfForm = document.getElementById('pdf-form');

        // Initial update
        updatePreview();

        editor.addEventListener('input', updatePreview);

        function updatePreview() {
            let rawValue = editor.value;
            
            // Protect backslashes for KaTeX delimiters before marked parses them
            // This ensures \( remains \( instead of becoming (
            const protectedValue = rawValue
                .replace(/\\\(/g, '\\\\(')
                .replace(/\\\)/g, '\\\\)')
                .replace(/\\\[/g, '\\\\[')
                .replace(/\\\]/g, '\\\\]');

            renderTarget.innerHTML = marked.parse(protectedValue);
            
            // Re-render math symbols
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(renderTarget, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false},
                        {left: '\\(', right: '\\)', display: false},
                        {left: '\\[', right: '\\]', display: true}
                    ],
                    throwOnError : false
                });
            }
        }

        function loadTemplate(type) {
            const templates = {
                dossier: `# ACADEMIC REPORT: MATHEMATICAL ANALYSIS\n\n## Student Profile\n- **Name:** John Doe | **ID:** 2023-0001\n\n## Mathematical Models\n- **Growth Rate:** $f(x) = 3x^2 + 5x - 2$\n- **Volume:** $V = \\frac{4}{3} pi r^3$\n- **Standard Deviation:** $s = sqrt{\\frac{\\sum(x-\\bar{x})^2}{n-1}}$\n\n## Attendance Summary\n| Status | Count |\n| :--- | :--- |\n| Present | 15 |\n| Late | 2 |\n| Absent | 0 |\n\n## Conclusion\nGiven that $\\lim_{x \\to \\infty} \\frac{1}{x} = 0$, we can conclude the student's efficiency is optimal.`
            };
            editor.value = templates[type] || '';
            updatePreview();
        }

        function generatePDF() {
            const content = editor.value;
            if (!content.trim()) {
                alert("Please enter some content first.");
                return;
            }
            pdfContent.value = content;
            pdfForm.submit();
        }

        // Default template
        loadTemplate('dossier');
    </script>
</body>
</html>
