import sqlite3
import os
from datetime import datetime

# Resolve database path
DB_PATH = os.path.join(os.path.dirname(__file__), '..', 'database', 'attendance.db')

def test_birthday_logic():
    print("--- [ BIRTHDAY LOGIC TEST ] ---")
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT first_name, last_name, birthday FROM users WHERE birthday IS NOT NULL AND deleted_at IS NULL")
        rows = cursor.fetchall()
        conn.close()
        
        if not rows:
            print("No student records found.")
            return
            
        today = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
        upcoming = []
        
        for f_name, l_name, b_day_str in rows:
            try:
                # Parse birthday (assumes YYYY-MM-DD)
                bday_dt = datetime.strptime(b_day_str, '%Y-%m-%d')
                # Find next occurrence
                this_year_bday = bday_dt.replace(year=today.year)
                if this_year_bday < today:
                    next_bday = this_year_bday.replace(year=today.year + 1)
                else:
                    next_bday = this_year_bday
                    
                days_left = (next_bday - today).days
                upcoming.append({
                    'name': f"{f_name} {l_name}",
                    'date': next_bday.strftime("%B %d"),
                    'days': days_left
                })
            except Exception as e:
                print(f"Error parsing {f_name}: {e}")
                continue 
            
        # Sort by nearest
        upcoming.sort(key=lambda x: x['days'])
        top_5 = upcoming[:5]
        
        print(f"Today: {today.strftime('%B %d, %Y')}")
        print("───────────────────")
        for i, stu in enumerate(top_5, 1):
            print(f"{i}. {stu['name']}")
            print(f"   {stu['date']} · {stu['days']} Days Left")
        print("───────────────────")
        
    except Exception as e:
        print(f"Test Error: {e}")

if __name__ == "__main__":
    test_birthday_logic()
