import telebot
import sqlite3
import os
import time
from datetime import datetime

# Resolve database path
DB_PATH = os.path.join(os.path.dirname(__file__), '..', 'database', 'attendance.db')

def get_settings():
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT telegram_bot_token, telegram_group_id FROM settings LIMIT 1")
        row = cursor.fetchone()
        conn.close()
        if row:
            return row[0], row[1]
    except Exception as e:
        print(f"Error: {e}")
    return None, None

def run_demo():
    token, chat_id = get_settings()
    if not token or not chat_id:
        print("Error: Bot Token or Chat ID not found in settings!")
        return

    # Initialize Bot with 60s timeout
    bot = telebot.TeleBot(token, parse_mode='HTML')
    telebot.apihelper.CONNECT_TIMEOUT = 30
    telebot.apihelper.READ_TIMEOUT = 60

    first_name = "Reymark"
    last_name = "Delin"
    today_full = datetime.now().strftime("%B %d, %Y")
    
    print(f"Starting Final 'Pure Data' UI Demo for: {first_name} {last_name}")
    
    retries = 3
    for attempt in range(retries):
        try:
            print(f"Attempt {attempt + 1}/{retries}: Sending scan animation...")
            # 1. Animated Progress Sweep
            sweep_msg = bot.send_message(chat_id, "🔍 <b>SYSTEM SWEEP:</b> Scanning database...\n<code>[░░░░░░░░░░] 0%</code>", parse_mode='HTML', timeout=60)
            
            for i in range(1, 6):
                time.sleep(0.4)
                filled = i * 2
                bar = "█" * filled + "░" * (10 - filled)
                bot.edit_message_text(
                    f"🔍 <b>SYSTEM SWEEP:</b> Scanning database...\n<code>[{bar}] {i*20}%</code>",
                    chat_id,
                    sweep_msg.message_id,
                    parse_mode='HTML',
                    timeout=60
                )
            
            time.sleep(0.3)
            bot.delete_message(chat_id, sweep_msg.message_id, timeout=60)

            # 2. Ultra-Minimalist 'Pure Data' UI (No Labels, Full Date)
            msg = (
                "<b>[ SYSTΞM :: BIRTHDΛY NOTICΞ ]</b>\n"
                "<b>───────────────────</b>\n\n"
                f"<b>{first_name} {last_name}</b>\n"
                f"<code>{today_full}</code>\n\n"
                "<i>Wishing you a great day!</i>\n\n"
                "<b>───────────────────</b>\n"
                "<i>Automated Message by System Admin</i>"
            )
            bot.send_message(chat_id, msg, parse_mode='HTML', timeout=60)
            print("Success: Final Pure Data UI Demo sent!")
            break # Exit retry loop on success
            
        except Exception as e:
            print(f"Attempt {attempt + 1} failed: {e}")
            if attempt < retries - 1:
                print("Retrying in 5 seconds...")
                time.sleep(5)
            else:
                print("All retry attempts failed. Check network or bot token.")

if __name__ == "__main__":
    run_demo()
