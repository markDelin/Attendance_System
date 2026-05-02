try:
    import psutil
except ImportError:
    print("[ERROR]: 'psutil' is not installed.")
    print("Please run: pip install psutil")
    exit(1)
import os

print("Checking for running python processes...")
found = False
for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
    try:
        cmd = proc.info.get('cmdline')
        if cmd and any('attendance_bot.py' in part for part in cmd):
            print(f"Bot found! PID: {proc.info['pid']}, Command: {' '.join(cmd)}")
            found = True
    except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
        pass

if not found:
    print("No instance of attendance_bot.py is running.")
