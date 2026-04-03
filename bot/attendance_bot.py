import telebot
from telebot.types import InlineKeyboardMarkup, InlineKeyboardButton, ReplyKeyboardMarkup, KeyboardButton
import sqlite3
import os
import time
import threading
import shutil
from datetime import datetime
import markdown2
from fpdf import FPDF, HTMLMixin

class PDF(FPDF, HTMLMixin):
    def header(self):
        self.set_font('helvetica', 'B', 15)
        self.cell(0, 10, 'STUDENT ATTENDANCE DOSSIER', 0, 1, 'C')
        self.ln(5)

    def footer(self):
        self.set_y(-15)
        self.set_font('helvetica', 'I', 8)
        self.cell(0, 10, f'Page {self.page_no()} | Generated on {datetime.now().strftime("%Y-%m-%d %H:%M")}', 0, 0, 'C')

# Resolve database path (Updated for bot/ subdirectory)
DB_PATH = os.path.join(os.path.dirname(__file__), '..', 'database', 'attendance.db')

# State management for announcements
user_states = {} # {user_id: {'state': 'idle', 'msg_id': None}}

def get_settings():
    if not os.path.exists(DB_PATH):
        return None, None, None
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT telegram_bot_token, telegram_group_id, admin_telegram_id FROM settings LIMIT 1")
        row = cursor.fetchone()
        conn.close()
        if row:
            return row[0], row[1], row[2]
    except Exception as e:
        print(f"Error reading settings: {e}")
    return None, None, None

def find_students(query):
    """Finds students matching name or QR code and returns a list."""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    # Search for matches
    cursor.execute("SELECT qr_code, name, course FROM users WHERE qr_code = ? OR name LIKE ? LIMIT 6", (query, f"%{query}%"))
    results = cursor.fetchall()
    conn.close()
    return results

def get_student_stats(qr_code):
    """Fetches full attendance statistics for a specific student."""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Base user info
    cursor.execute("SELECT qr_code, name, course FROM users WHERE qr_code = ? LIMIT 1", (qr_code,))
    user = cursor.fetchone()
    if not user:
        conn.close()
        return None
        
    qr_code, name, course = user
    course_text = f" ({course})" if course else ""
    
    # Get General Attendance
    cursor.execute("""
        SELECT 
            SUM(status = 'present') as present,
            SUM(status = 'late') as late,
            SUM(status = 'absent') as absent
        FROM attendance WHERE qr_code = ?
    """, (qr_code,))
    stats = cursor.fetchone()
    p, l, a = (stats[0] or 0, stats[1] or 0, stats[2] or 0)
    
    # Get Per Subject/Event Attendance
    cursor.execute("""
        SELECT s.name, 
            SUM(sa.status = 'present') as sp,
            SUM(sa.status = 'late') as sl,
            SUM(sa.status = 'absent') as sa,
            s.category
        FROM subject_attendance sa
        JOIN subjects s ON sa.subject_id = s.id
        WHERE sa.qr_code = ?
        GROUP BY s.id
    """, (qr_code,))
    contexts = cursor.fetchall()
    conn.close()
    
    subjects = [c for c in contexts if (c[4] or 'subject') == 'subject']
    events = [c for c in contexts if c[4] == 'event']
    
    msg = f"<b>STUDENT DOSSIER</b>\n\n"
    msg += f"<b>Name:</b> {name}\n"
    msg += f"<b>Info:</b> {course_text.strip()}\n"
    msg += f"<b>QR  :</b> <code>{qr_code}</code>\n\n"
    
    # Add Progress Bar
    def get_bar(p, l, a):
        total = p + l + a
        if total > 0:
            filled = int((p / total) * 10)
            bar = "█" * filled + "░" * (10 - filled)
            percent = int((p / total) * 100)
            return f"<code>[{bar}]</code> {percent}%"
        return "<code>[░░░░░░░░░░]</code> 0%"

    msg += f"<b>Overall:</b> {get_bar(p, l, a)}\n\n"

    msg += "<b>ATTENDANCE SUMMARY</b>\n"
    msg += f"Present: <b>{p}</b>\n"
    msg += f"Late   : <b>{l}</b>\n"
    msg += f"Absent : <b>{a}</b>\n\n"
    
    if subjects:
        msg += "<b>SUBJECT RECORDS</b>\n"
        for sub in subjects:
            msg += f"• <b>{sub[0]}</b>\n"
            msg += f"  {get_bar(sub[1], sub[2], sub[3])}\n"
        msg += "\n"

    if events:
        msg += "<b>EVENT RECORDS</b>\n"
        for ev in events:
            msg += f"• <b>{ev[0]}</b>\n"
            msg += f"  {get_bar(ev[1], ev[2], ev[3])}\n"
    
    if not subjects and not events:
        msg += "<i>(No specific subject/event data)</i>\n"
    
    return msg

def get_today_stats():
    """Generates a summary of today's attendance."""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    today = datetime.now().strftime("%Y-%m-%d")
    
    # General Attendance
    cursor.execute("SELECT status, COUNT(*) FROM attendance WHERE date = ? GROUP BY status", (today,))
    gen_stats = dict(cursor.fetchall())
    gp, gl, ga = gen_stats.get('present', 0), gen_stats.get('late', 0), gen_stats.get('absent', 0)
    
    # Subject Attendance
    cursor.execute("""
        SELECT s.name, 
            SUM(sa.status = 'present'), 
            SUM(sa.status = 'late'), 
            SUM(sa.status = 'absent')
        FROM subject_attendance sa
        JOIN subjects s ON sa.subject_id = s.id
        WHERE sa.date = ?
        GROUP BY s.id
    """, (today,))
    sub_stats = cursor.fetchall()
    conn.close()
    
    def get_bar(p, l, a):
        total = (p or 0) + (l or 0) + (a or 0)
        if total > 0:
            filled = int((p / total) * 10)
            bar = "█" * filled + "░" * (10 - filled)
            percent = int((p / total) * 100)
            return f"<code>[{bar}]</code> {percent}%"
        return "<code>[░░░░░░░░░░]</code> 0%"

    msg = f"<b>📊 TODAY'S SUMMARY</b> ({today})\n"
    msg += "━━━━━━━━━━━━━━━━━━━━\n"
    msg += f"<b>General Traffic:</b>\n"
    msg += f"• {get_bar(gp, gl, ga)}\n\n"
    
    if sub_stats:
        msg += "<b>Subject Sessions:</b>\n"
        for name, p, l, a in sub_stats:
            msg += f"• <b>{name}</b>\n"
            msg += f"  {get_bar(p, l, a)}\n"
    else:
        msg += "<i>No subject sessions recorded today.</i>\n"
        
    return msg

def export_attendance_csv():
    """Exports all attendance data to a CSV file."""
    import csv
    output_path = "attendance_export.csv"
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Join everything for a master export
    cursor.execute("""
        SELECT u.name, u.qr_code, u.course, a.date, a.time, a.status, 'General' as context
        FROM attendance a
        JOIN users u ON a.qr_code = u.qr_code
        UNION ALL
        SELECT u.name, u.qr_code, u.course, sa.date, sa.time, sa.status, s.name as context
        FROM subject_attendance sa
        JOIN users u ON sa.qr_code = u.qr_code
        JOIN subjects s ON sa.subject_id = s.id
        ORDER BY date DESC, time DESC
    """)
    rows = cursor.fetchall()
    headers = ["Name", "QR Code", "Course", "Date", "Time", "Status", "Context"]
    
    with open(output_path, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerow(headers)
        writer.writerows(rows)
    
    conn.close()
    return output_path

def get_student_markdown(qr_code):
    """Generates a clean HTML/Markdown string for PDF conversion."""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("SELECT name, course FROM users WHERE qr_code = ? LIMIT 1", (qr_code,))
    user = cursor.fetchone()
    if not user:
        conn.close()
        return None
        
    name, course = user
    # Simplified HTML for FPDF HTMLMixin compatibility
    html = f"<h1>{name}</h1>"
    html += f"<p><b>QR Code:</b> {qr_code}<br>"
    html += f"<b>Course:</b> {course or 'N/A'}</p>"
    
    # Stats
    cursor.execute("SELECT SUM(status='present'), SUM(status='late'), SUM(status='absent') FROM attendance WHERE qr_code=?", (qr_code,))
    stats = cursor.fetchone()
    p, l, a = (stats[0] or 0, stats[1] or 0, stats[2] or 0)
    
    html += "<h2>Attendance Summary</h2>"
    html += f"<ul><li>Present: {p}</li><li>Late: {l}</li><li>Absent: {a}</li></ul>"
    
    # Subjects
    cursor.execute("""
        SELECT s.name, SUM(sa.status='present'), SUM(sa.status='late'), SUM(sa.status='absent')
        FROM subject_attendance sa JOIN subjects s ON sa.subject_id = s.id
        WHERE sa.qr_code = ? GROUP BY s.id
    """, (qr_code,))
    subjects = cursor.fetchall()
    
    if subjects:
        html += "<h2>Subject Records</h2>"
        html += "<table border='1' width='100%'>"
        html += "<thead><tr><th width='70%'>Subject</th><th width='10%'>P</th><th width='10%'>L</th><th width='10%'>A</th></tr></thead>"
        html += "<tbody>"
        for s in subjects:
            html += f"<tr><td>{s[0]}</td><td>{s[1]}</td><td>{s[2]}</td><td>{s[3]}</td></tr>"
        html += "</tbody></table>"
    
    conn.close()
    return html

def generate_pdf_dossier(qr_code):
    """Converts student data to a PDF file and returns the path."""
    html_content = get_student_markdown(qr_code)
    if not html_content:
        return None
        
    pdf = PDF()
    pdf.add_page()
    pdf.set_font("helvetica", size=12)
    
    try:
        # Use HTMLMixin's write_html
        pdf.write_html(html_content)
    except Exception as e:
        print(f"PDF HTML Error: {e}")
        # Fallback to simple text if HTML fails
        pdf.cell(0, 10, f"Error generating rich PDF: {e}", ln=True)
        
    output_path = f"dossier_{qr_code}.pdf"
    pdf.output(output_path)
    return output_path

def queue_worker(bot):
    """Background thread to process pending messages from telegram_queue with anti-spam lock"""
    while True:
        try:
            _, chat_id, _ = get_settings()
            if not chat_id:
                time.sleep(10)
                continue
                
            conn = sqlite3.connect(DB_PATH)
            conn.row_factory = sqlite3.Row
            cursor = conn.cursor()
            
            # Fetch pending messages
            cursor.execute("SELECT id, message FROM telegram_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5")
            rows = cursor.fetchall()
            
            for row in rows:
                row_id = row['id']
                message = row['message']
                try:
                    # Update to 'processing' before sending
                    cursor.execute("UPDATE telegram_queue SET status = 'processing' WHERE id = ?", (row_id,))
                    conn.commit()
                    
                    # Send message (Telebot uses global timeouts)
                    bot.send_message(chat_id, message, parse_mode='HTML')
                    
                    # Update status on success
                    cursor.execute("UPDATE telegram_queue SET status = 'sent' WHERE id = ?", (row_id,))
                    conn.commit()
                    time.sleep(1) # Small gap between sends
                except Exception as e:
                    print(f"Failed to send queued message (ID: {row_id}): {e}")
                    # Revert to pending on failure to retry later
                    cursor.execute("UPDATE telegram_queue SET status = 'pending' WHERE id = ?", (row_id,))
                    conn.commit()
                    time.sleep(5)
            
            conn.close()
            
        except Exception as e:
            print(f"Queue worker error: {e}")
            
        time.sleep(10) # Check queue every 10 seconds

def birthday_check_worker(bot):
    """Daily worker to send birthday greetings to the group chat"""
    print("Birthday Greeting Worker initialized.")
    while True:
        try:
            _, chat_id, _ = get_settings()
            if not chat_id:
                time.sleep(60)
                continue
                
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            # Ensure table exists (in case PHP hasn't run yet)
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS birthday_greetings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    qr_code TEXT NOT NULL,
                    year TEXT NOT NULL,
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(qr_code, year)
                )
            """)
            conn.commit()
            
            # Use STRFTIME to find matching month/day
            today_md = datetime.now().strftime("%m-%d")
            current_year = datetime.now().strftime("%Y")
            
            # Find students with birthday today who haven't been greeted yet this year
            cursor.execute("""
                SELECT u.qr_code, u.name 
                FROM users u 
                LEFT JOIN birthday_greetings bg ON u.qr_code = bg.qr_code AND bg.year = ?
                WHERE strftime('%m-%d', u.birthday) = ? 
                  AND u.deleted_at IS NULL
                  AND bg.id IS NULL
            """, (current_year, today_md))
            
            birthdays = cursor.fetchall()
            
            for qr_code, name in birthdays:
                msg = f"<b>SYSTEM NOTICE</b>\nBirthday — {name}"
                
                try:
                    bot.send_message(chat_id, msg, parse_mode='HTML')
                    # Mark as sent
                    cursor.execute("INSERT INTO birthday_greetings (qr_code, year) VALUES (?, ?)", (qr_code, current_year))
                    conn.commit()
                    print(f"Sent birthday greeting to {name}")
                except Exception as e:
                    print(f"Failed to send birthday message: {e}")
            
            conn.close()
            
        except Exception as e:
            print(f"Birthday worker error: {e}")
            
        # Check every 4 hours to avoid heavy DB load but catch up if bot was down
        time.sleep(14400) 

# Main Loop
print("Starting up Attendance Web-Bot connector...")
while True:
    TOKEN, _, _ = get_settings()
    if not TOKEN:
        print("Waiting for Telegram Bot Token to be set in Settings...")
        time.sleep(10)
        continue
    
    print("Initialize Bot (with 90s timeout for stability)...")
    bot = telebot.TeleBot(TOKEN, parse_mode='HTML', threaded=True)
    # Global timeouts for all requests
    telebot.apihelper.CONNECT_TIMEOUT = 30
    telebot.apihelper.READ_TIMEOUT = 90

    def get_admin_main_keyboard():
        markup = ReplyKeyboardMarkup(resize_keyboard=True)
        markup.row(KeyboardButton("🔍 Search Student"), KeyboardButton("📊 Today's Stats"))
        markup.row(KeyboardButton("📣 Announce"), KeyboardButton("📄 Export CSV"))
        markup.row(KeyboardButton("📂 Get Database"))
        return markup

    @bot.message_handler(commands=['start', 'help'])
    def send_welcome(message):
        _, _, ADMIN_ID = get_settings()
        is_admin = str(message.from_user.id) == str(ADMIN_ID).strip()
        
        welcome_text = (
            "<b>ATTENDANCE INTERFACE</b>\n\n"
            "Welcome. Available commands:\n"
            "• <code>/search [Name/ID]</code>\n"
            "• <code>/pdf [ID]</code> (Export PDF)\n"
        )
        
        if is_admin:
            welcome_text += (
                "• <code>/today</code> (Daily Stats)\n"
                "• <code>/export</code> (Export CSV)\n"
                "• <code>/getdb</code> (Admin DB Access)\n\n"
                "<i>Use the menu below for quick actions.</i>"
            )
        else:
            welcome_text += "\n<i>Please contact an administrator for full access.</i>"
            
        bot.reply_to(message, welcome_text, reply_markup=get_admin_main_keyboard() if is_admin else None)

    @bot.message_handler(commands=['announce', 'cancel'])
    def handle_announce_command(message):
        _, _, ADMIN_ID = get_settings()
        if str(message.from_user.id) != str(ADMIN_ID).strip():
            return
            
        if message.text.startswith('/cancel'):
            user_states[message.from_user.id] = {'state': 'idle'}
            bot.reply_to(message, "❌ <b>Announcement cancelled.</b> Returning to normal mode.", reply_markup=get_admin_main_keyboard())
            return
            
        user_states[message.from_user.id] = {'state': 'announcing'}
        bot.reply_to(message, "📣 <b>Announcement Mode</b>\n━━━━━━━━━━━━━━━━━━━━\nReady! Send the text, photo, video, or file you want to broadcast to the Group Chat.\n\n<i>Type /cancel to abort.</i>", reply_markup=telebot.types.ReplyKeyboardRemove())

    @bot.message_handler(commands=['today'])
    def handle_today(message):
        _, _, ADMIN_ID = get_settings()
        if str(message.from_user.id) != str(ADMIN_ID).strip():
            return
        bot.send_chat_action(message.chat.id, 'typing')
        bot.reply_to(message, get_today_stats())

    @bot.message_handler(commands=['export'])
    def handle_export(message):
        _, _, ADMIN_ID = get_settings()
        if str(message.from_user.id) != str(ADMIN_ID).strip():
            return
        bot.send_chat_action(message.chat.id, 'upload_document')
        try:
            path = export_attendance_csv()
            with open(path, 'rb') as f:
                bot.send_document(message.chat.id, f, caption="📊 Full Attendance Export (CSV)")
            os.remove(path)
        except Exception as e:
            bot.reply_to(message, f"❌ Export failed: {e}")

    @bot.message_handler(commands=['pdf'])
    def handle_pdf_export(message):
        args = message.text.split(maxsplit=1)
        if len(args) < 2:
            bot.reply_to(message, "<b>📄 PDF Export</b>\n━━━━━━━━━━━━━━━━━━━━\nPlease provide a student ID.\n\nExample: <code>/pdf 12345</code>")
            return
            
        qr_code = args[1].strip()
        bot.send_chat_action(message.chat.id, 'upload_document')
        
        try:
            pdf_path = generate_pdf_dossier(qr_code)
            if pdf_path and os.path.exists(pdf_path):
                with open(pdf_path, 'rb') as f:
                    bot.send_document(message.chat.id, f, caption=f"📄 Attendance Dossier: {qr_code}")
                os.remove(pdf_path) # Cleanup
            else:
                bot.reply_to(message, "❌ Failed to generate PDF. Ensure ID is correct.")
        except Exception as e:
            bot.reply_to(message, f"❌ Error: {e}")


    @bot.message_handler(commands=['search'])
    def handle_search(message):
        args = message.text.split(maxsplit=1)
        if len(args) < 2:
            bot.reply_to(message, "<b>🔍 Search Required</b>\n━━━━━━━━━━━━━━━━━━━━\nPlease provide a name or ID.\n\nExample: <code>/search John</code>")
            return
        
        query = args[1].strip()
        bot.send_chat_action(message.chat.id, 'typing')
        results = find_students(query)
        
        if not results:
            bot.reply_to(message, f"<b>No Match Found</b>\nNo records found for '<b>{query}</b>'.")
        elif len(results) == 1:
            # Single match
            bot.reply_to(message, get_student_stats(results[0][0]))
        else:
            # Multiple matches
            markup = InlineKeyboardMarkup(row_width=1)
            for qr_code, name, course in results[:5]:
                course_tag = f" ({course})" if course else ""
                markup.add(InlineKeyboardButton(f"{name}{course_tag}", callback_data=f"view_{qr_code}"))
            
            if len(results) > 5:
                bot.reply_to(message, f"<b>Multiple Results</b>\nFound several matches for '<b>{query}</b>'.\n\n<i>Select a profile:</i>", reply_markup=markup)
            else:
                bot.reply_to(message, f"<b>Multiple Results</b>\nFound {len(results)} matches.\n\n<i>Select a profile:</i>", reply_markup=markup)

    @bot.callback_query_handler(func=lambda call: call.data.startswith('view_'))
    def handle_selection(call):
        qr_code = call.data.split('_', 1)[1]
        stats = get_student_stats(qr_code)
        if stats:
            # Edit the message with stats
            markup = InlineKeyboardMarkup()
            markup.add(InlineKeyboardButton("🔄 New Search", switch_inline_query_current_chat=""))
            bot.edit_message_text(stats, chat_id=call.message.chat.id, message_id=call.message.message_id, parse_mode='HTML', reply_markup=markup)
        else:
            bot.answer_callback_query(call.id, "Student not found.")

    @bot.callback_query_handler(func=lambda call: call.data.startswith('ann_'))
    def handle_announcement_confirm(call):
        _, chat_id, ADMIN_ID = get_settings()
        if str(call.from_user.id) != str(ADMIN_ID).strip():
            bot.answer_callback_query(call.id, "Unauthorized.")
            return
            
        action = call.data.split('_')[1]
        
        if action == 'confirm':
            state = user_states.get(call.from_user.id)
            if state and state.get('msg_id'):
                try:
                    bot.copy_message(chat_id, call.message.chat.id, state['msg_id'])
                    bot.edit_message_text("✅ <b>Announcement Broadcasat Successful!</b>", chat_id=call.message.chat.id, message_id=call.message.message_id, parse_mode='HTML')
                    bot.send_message(call.message.chat.id, "Returning to main menu...", reply_markup=get_admin_main_keyboard())
                except Exception as e:
                    bot.edit_message_text(f"❌ <b>Broadcast Failed:</b> {e}", chat_id=call.message.chat.id, message_id=call.message.message_id, parse_mode='HTML')
            else:
                bot.answer_callback_query(call.id, "Error: Message source not found.")
        else:
            bot.edit_message_text("❌ <b>Announcement Discarded.</b>", chat_id=call.message.chat.id, message_id=call.message.message_id, parse_mode='HTML')
            bot.send_message(call.message.chat.id, "Returning to main menu...", reply_markup=get_admin_main_keyboard())
            
        user_states[call.from_user.id] = {'state': 'idle'}
        bot.answer_callback_query(call.id)

    @bot.message_handler(func=lambda message: not message.text.startswith('/'))
    def handle_text_interactions(message):
        """Routes text from buttons or search queries."""
        _, _, ADMIN_ID = get_settings()
        is_admin = str(message.from_user.id) == str(ADMIN_ID).strip()
        state = user_states.get(message.from_user.id, {}).get('state', 'idle')
        
        text = message.text
        if is_admin and state == 'announcing':
            # Handle text announcement with confirmation
            user_states[message.from_user.id]['msg_id'] = message.message_id
            markup = InlineKeyboardMarkup()
            markup.row(InlineKeyboardButton("✅ Confirm Broadcast", callback_data="ann_confirm"), 
                      InlineKeyboardButton("❌ Cancel", callback_data="ann_cancel"))
            bot.reply_to(message, "💬 <b>Verify Text Announcement</b>\n━━━━━━━━━━━━━━━━━━━━\nReady to send the above text to the group chat?", parse_mode='HTML', reply_markup=markup)
            return

        if text == "🔍 Search Student":
            bot.reply_to(message, "🔍 <b>Search</b>\nType the student name or ID to search.")
        elif text == "📊 Today's Stats":
            handle_today(message)
        elif text == "📣 Announce":
            handle_announce_command(message)
        elif text == "📄 Export CSV":
            handle_export(message)
        elif text == "📂 Get Database":
            handle_getdb(message)
        else:
            # Fallback to auto-search
            handle_search(message)

    @bot.message_handler(content_types=['photo', 'video', 'voice', 'audio'])
    def handle_media_announcement(message):
        _, _, ADMIN_ID = get_settings()
        if str(message.from_user.id) != str(ADMIN_ID).strip():
            return
            
        state = user_states.get(message.from_user.id, {}).get('state', 'idle')
        if state == 'announcing':
            user_states[message.from_user.id]['msg_id'] = message.message_id
            markup = InlineKeyboardMarkup()
            markup.row(InlineKeyboardButton("✅ Confirm Broadcast", callback_data="ann_confirm"), 
                      InlineKeyboardButton("❌ Cancel", callback_data="ann_cancel"))
            bot.reply_to(message, f"📸 <b>Verify {message.content_type.capitalize()} Announcement</b>\n━━━━━━━━━━━━━━━━━━━━\nReady to send this media to the group chat?", parse_mode='HTML', reply_markup=markup)

    @bot.message_handler(commands=['getdb'])

    def handle_getdb(message):
        _, _, ADMIN_ID = get_settings()
        if not ADMIN_ID or str(message.from_user.id) != str(ADMIN_ID).strip():
            bot.reply_to(message, "❌ Unauthorized.")
            return
        
        if os.path.exists(DB_PATH):
            with open(DB_PATH, 'rb') as db_file:
                bot.send_document(message.chat.id, db_file, caption="📁 Here is the current attendance database.")
        else:
            bot.reply_to(message, "❌ Database file not found.")

    @bot.message_handler(content_types=['document'])
    def handle_document(message):
        _, _, ADMIN_ID = get_settings()
        if not ADMIN_ID or str(message.from_user.id) != str(ADMIN_ID).strip():
            return
            
        state = user_states.get(message.from_user.id, {}).get('state', 'idle')
        
        # Priority: Announcement mode
        if state == 'announcing':
            user_states[message.from_user.id]['msg_id'] = message.message_id
            markup = InlineKeyboardMarkup()
            markup.row(InlineKeyboardButton("✅ Confirm Broadcast", callback_data="ann_confirm"), 
                      InlineKeyboardButton("❌ Cancel", callback_data="ann_cancel"))
            bot.reply_to(message, "📂 <b>Verify Document Announcement</b>\n━━━━━━━━━━━━━━━━━━━━\nReady to send this file to the group chat?", parse_mode='HTML', reply_markup=markup)
            return

        # Fallback: Database Sync
        if message.document.file_name.endswith('.db') or message.document.file_name.endswith('.sqlite'):
            try:
                msg = bot.reply_to(message, "⏳ Downloading and syncing database...")
                file_info = bot.get_file(message.document.file_id)
                downloaded_file = bot.download_file(file_info.file_path)
                
                # Create backup
                backup_dir = os.path.dirname(DB_PATH).replace('database', 'backups')
                if not os.path.exists(backup_dir):
                    os.makedirs(backup_dir)
                    
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                backup_path = os.path.join(backup_dir, f"attendance_backup_{timestamp}.db")
                
                if os.path.exists(DB_PATH):
                    shutil.copy2(DB_PATH, backup_path)
                    
                # Save new DB
                with open(DB_PATH, 'wb') as new_file:
                    new_file.write(downloaded_file)
                    
                bot.edit_message_text(f"✅ Database synchronized successfully!\nPrevious database backed up as: `{os.path.basename(backup_path)}`", chat_id=msg.chat.id, message_id=msg.message_id, parse_mode='Markdown')
            except Exception as e:
                bot.reply_to(message, f"❌ Failed to sync: {e}")

    # Start Background Thread for Queue
    queue_thread = threading.Thread(target=queue_worker, args=(bot,), daemon=True)
    queue_thread.start()
    
    # Start Background Thread for Birthdays
    birthday_thread = threading.Thread(target=birthday_check_worker, args=(bot,), daemon=True)
    birthday_thread.start()
    
    try:
        print(f"Bot fully operational and polling (https://t.me/{bot.get_me().username})")
        bot.polling(none_stop=True, timeout=60)
    except Exception as e:
        print(f"Bot crashed or connection lost: {e}")
        time.sleep(5)
