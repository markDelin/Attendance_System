# How to Connect Mobile Devices (Scanner)

Modern mobile browsers (Chrome, Safari, etc.) only allow camera access on **Secure Contexts** (HTTPS). If you access the system via http://192.168.x.x, the camera will be blocked.

## Option 1: Public HTTPS (Easiest & Best)
Use a "tunnel" to get a secure public address.

1. Open a **Command Prompt** on your computer.
2. Run this command: 
   ```bash
   npx localtunnel --port 8000
   ```
3. You will get a link like `https://warm-potato-scans.loca.lt`.
4. Open that link on your phone. The camera will work perfectly because it is HTTPS.

---

## Option 2: Chrome Android Bypass
If you don't want to use a tunnel and are on Android:

1. On your phone's Chrome, go to: `chrome://flags/#unsafely-treat-insecure-origin-as-secure`
2. Enter your computer's IP address in the text box (e.g., `http://192.168.1.15:8000`).
3. Set the status to **Enabled**.
4. Relaunch Chrome.

---

## Tips for Success
- **Same Wi-Fi**: Ensure your phone and computer are on the same network.
- **Firewall**: If the phone can't even load the page, ensure your Windows Firewall allows "Port 8000".
