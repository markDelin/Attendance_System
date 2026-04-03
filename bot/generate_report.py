import sqlite3
import os
import sys
from datetime import datetime
import markdown2
from fpdf import FPDF, HTMLMixin
import re
import warnings

# Silence deprecation warnings from fpdf2 and other libraries
warnings.filterwarnings("ignore", category=DeprecationWarning)

# Configuration
DB_PATH = os.path.join(os.path.dirname(__file__), 'database', 'attendance.db')

class PDF(FPDF, HTMLMixin):
    def header(self):
        # Header removed as per user request to remove 'watermarks'
        pass

    def footer(self):
        # Footer removed as per user request to remove 'watermarks'
        pass

def get_student_data(qr_code):
    """Fetches data and formats it as HTML for PDF conversion."""
    if not os.path.exists(DB_PATH):
        return None
        
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # 1. User Info
    cursor.execute("SELECT name, course, student_type, year_level FROM users WHERE qr_code = ? LIMIT 1", (qr_code,))
    user = cursor.fetchone()
    if not user:
        conn.close()
        return None
    name, course, stype, year = user
    
    # 2. General Stats
    cursor.execute("SELECT SUM(status='present'), SUM(status='late'), SUM(status='absent') FROM attendance WHERE qr_code=?", (qr_code,))
    stats = cursor.fetchone()
    p, l, a = (stats[0] or 0, stats[1] or 0, stats[2] or 0)
    
    # 3. Subject Stats
    cursor.execute("""
        SELECT s.name, SUM(sa.status='present'), SUM(sa.status='late'), SUM(sa.status='absent')
        FROM subject_attendance sa JOIN subjects s ON sa.subject_id = s.id
        WHERE sa.qr_code = ? GROUP BY s.id
    """, (qr_code,))
    subjects = cursor.fetchall()
    
    conn.close()
    
    # Build HTML with Premium Formatting
    html = f"<h1 style='font-family: helvetica; color: #0f172a;'>{name}</h1>"
    html += f"<p><b>ID:</b> <code>{qr_code}</code> &nbsp; | &nbsp; <b>Course:</b> {course or 'N/A'}<br>"
    html += f"<b>Type:</b> {stype or 'Regular'} &nbsp; | &nbsp; <b>Year:</b> {year or 'N/A'}</p>"
    
    html += "<h2 style='border-bottom: 0.5px solid #cbd5e1; padding-bottom: 5px;'>Attendance Summary</h2>"
    html += f"<ul><li>Present: <b>{p}</b></li><li>Late: <b>{l}</b></li><li>Absent: <b>{a}</b></li></ul>"
    
    if subjects:
        html += "<h2>Subject Breakdown</h2>"
        html += "<table border='1' width='100%' cellpadding='5'>"
        html += "<thead><tr bgcolor='#f1f5f9'><th width='70%'><b>Subject Name</b></th><th width='10%'><b>P</b></th><th width='10%'><b>L</b></th><th width='10%'><b>A</b></th></tr></thead>"
        html += "<tbody>"
        for s in subjects:
            html += f"<tr><td>{s[0]}</td><td align='center'>{s[1]}</td><td align='center'>{s[2]}</td><td align='center'>{s[3]}</td></tr>"
        html += "</tbody></table>"
        
    return html

import re

def preprocess_math_to_html(text):
    """
    Converts LaTeX patterns and common Unicode characters into 
    standard ASCII/HTML tags that FPDF's standard fonts can render.
    """
    # 1. Strip \( and \) delimiters
    text = re.sub(r'\\\((.*?)\\\)', r'\1', text)
    text = re.sub(r'\\\[(.*?)\\\]', r'\1', text)
    
    # 2. Convert superscripts: x^{y} or x^y -> x<sup>y</sup>
    text = re.sub(r'(\w+)\^\{(.*?)\}', r'\1<sup>\2</sup>', text)
    text = re.sub(r'(\w+)\^(\w)', r'\1<sup>\2</sup>', text)
    
    # 3. Convert subscripts: x_{y} or x_y -> x<sub>y</sub>
    text = re.sub(r'(\w+)_\{(.*?)\}', r'\1<sub>\2</sub>', text)
    text = re.sub(r'(\w+)_(\w)', r'\1<sub>\2</sub>', text)
    
    # 4. Math symbols and Typography (Mapping to PDF-safe equivalents)
    replacements = {
        r'\times': 'x',
        r'\div': '/',
        r'\pm': '+/-',
        r'\pi': 'pi',
        r'\infty': 'inf',
        r'\rightarrow': '->',
        r'\approx': '~',
        r'\cdot': '*',
        r'\sqrt': 'sqrt',
        # Common Unicode characters that crash Helvetica
        '\u2013': '-', # en dash
        '\u2014': '--', # em dash
        '\u2018': "'", # smart single quote
        '\u2019': "'", # smart single quote
        '\u201c': '"', # smart double quote
        '\u201d': '"', # smart double quote
        '\u2026': '...', # ellipsis
        '\u00a0': ' ', # non-breaking space
    }
    for tex, sym in replacements.items():
        text = text.replace(tex, sym)
        
    return text

def main():
    if len(sys.argv) < 2:
        print("Usage: python generate_report.py [QR_CODE] or python generate_report.py --raw [MARKDOWN]")
        sys.exit(1)
        
    is_raw = sys.argv[1] == "--raw"
    
    if is_raw:
        if len(sys.argv) < 3:
            print("Error: No markdown file or text provided.")
            sys.exit(1)
        md_arg = sys.argv[2]
        
        # Check if the argument is a file path (to handle large content)
        if os.path.isfile(md_arg):
            with open(md_arg, 'r', encoding='utf-8') as f:
                md_text = f.read()
        else:
            md_text = md_arg
        qr_code = "custom_" + datetime.now().strftime("%H%M%S")
    else:
        qr_code = sys.argv[1]
        md_text = get_student_data(qr_code)
    
    if not md_text:
        print(f"Error: No content found for {qr_code}")
        sys.exit(1)
        
    # Pre-process math (LaTeX to HTML tags)
    md_text = preprocess_math_to_html(md_text)
    
    # Convert MD to HTML
    html_raw = markdown2.markdown(md_text, extras=["superscript", "subscript", "tables"])
    
    # Inject "Swiss Style" Inline CSS for alignment
    # FPDF HTMLMixin supports basic inline styles
    html_content = html_raw.replace('<h1>', '<h1 style="color: #000000; font-family: helvetica;">')
    html_content = html_content.replace('<h2>', '<h2 style="color: #000000; font-family: helvetica; border-bottom: 0.5px solid #000000;">')
    html_content = html_content.replace('<table>', '<table width="100%" cellpadding="5" border="0">')
    html_content = html_content.replace('<thead>', '<thead bgcolor="#000000" color="#ffffff">')
    html_content = html_content.replace('<th>', '<th style="color: #ffffff; font-weight: bold;">')
    
    pdf = PDF()
    try:
        # Align Margins with Web (20mm ~ 4rem)
        pdf.set_margins(20, 20, 20)
        pdf.add_page()
        pdf.set_font("helvetica", size=11) # Slightly smaller, more professional font size
        pdf.write_html(html_content)
    except Exception as e:
        # If write_html fails, ensure we have a page for the error message
        if not pdf.page:
            pdf.add_page()
        pdf.set_font("helvetica", size=10)
        # Clean the error message to prevent secondary Unicode crashes
        error_msg = str(e).encode('latin-1', 'replace').decode('latin-1')
        pdf.cell(0, 10, f"Error rendering content: {error_msg}", ln=True)
    
    filename = f"dossier_{qr_code}.pdf"
    pdf.output(filename)
    print(filename)

if __name__ == "__main__":
    main()
