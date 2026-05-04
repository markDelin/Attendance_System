# 📱 Mobile Ingress Guide: Connecting External Scanners

This guide explains how to connect mobile devices to your local attendance server. Because modern mobile browsers require **HTTPS** for camera access, follow one of these verified methods to enable remote scanning.

---

## ⚡ Method 1: The "Mobile Node" App (Recommended)
Use the dedicated **AttendanceMobile** application included in this repository.

1.  **Build/Run** the app located in `/AttendanceMobile`.
2.  **Input IP**: Enter your PC's Local IP (e.g., `192.168.1.15`) into the app.
3.  **Bridge**: The app uses a native bridge to bypass browser security, granting instant camera access.

---

## 🌐 Method 2: Public Tunneling (No App Required)
If you want to use a standard mobile browser (Chrome/Safari) without installing an app, create a secure tunnel.

1.  Open **PowerShell/Terminal** on your PC.
2.  Run:
    ```bash
    npx localtunnel --port 8000
    ```
3.  **Access**: Open the generated `https://...` link on your phone.
4.  **Security**: The camera will work immediately because the connection is served over HTTPS.

---

## 🔧 Method 3: Chrome Android Bypass
For developers who prefer a direct LAN connection without tunneling:

1.  On your Android phone, open Chrome and navigate to:
    `chrome://flags/#unsafely-treat-insecure-origin-as-secure`
2.  **Whitelist**: Enter your server address: `http://192.168.x.x:8000`.
3.  **Enable**: Set the flag to **Enabled** and relaunch Chrome.
4.  **Result**: Chrome will now treat your local IP as a "Secure Context".

---

## 🚩 Essential Checklist
- [ ] **Network Identity**: Both devices MUST be on the same Wi-Fi SSID.
- [ ] **Windows Firewall**: Ensure **Port 8000** is open for inbound traffic on your PC.
- [ ] **IP Consistency**: If your router uses DHCP, your local IP might change. Check the dashboard footer for the current address.

---
*Technical Documentation | QR Tools Ecosystem*
