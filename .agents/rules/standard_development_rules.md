---
trigger: always_on
description: Standard Development & Documentation Rules for PT Susanti Megah Backend
---

# Standar Rule Pengembangan & Dokumentasi (Mandatory AI Agent Guidelines)

1. **Swagger Update Mandatory:**
   - Setiap kali membuat/mengubah route endpoint, request payload, response structure, atau parameter query/path, WAJIB langsung memperbarui `resources/docs/openapi.yaml`.

2. **Markdown Documentation Mandatory:**
   - Setiap kali membuat fitur baru atau mengubah alur bisnis (flow):
     - Jika file dokumentasi `.md` terkait sudah ada, WAJIB meng-update file `.md` tersebut.
     - Jika file dokumentasi `.md` belum ada, WAJIB membuat file `.md` baru di root repository (contoh: `<feature_name>_guide.md` atau `<feature_name>_flow.md`) yang berisi penjelasan alur, struktur database, diagram mermaid, dan spesifikasi API endpoint.

3. **Frontend Isolation:**
   - Jangan melakukan modifikasi pada workspace `fe-distributor-channel` kecuali diminta secara eksplisit oleh user.

4. **Code Quality:**
   - Selalu periksa sintaks PHP (`php -l`) pada setiap file yang dimodifikasi.
