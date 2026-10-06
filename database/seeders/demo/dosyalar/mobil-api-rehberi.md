# Mobil uygulama için API — kısa rehber

1. **Oturum aç:** `POST /api/v1/oturum` — `kullanici`, `parola`, `cihaz`
   alanları. Yanıtta `token` (Bearer anahtarı) ve son geçerlilik tarihi gelir.
2. **İstek gönder:** her istekte `Authorization: Bearer <token>` başlığı.
3. **Kim olduğunu öğren:** `GET /api/v1/ben`
4. **Oturumlar:** `GET /api/v1/oturumlar` (bu cihaz `bu_cihaz: true`),
   `DELETE /api/v1/oturumlar/{id}` başka bir cihazı kapatır.
5. **Çıkış:** `DELETE /api/v1/oturum` — bu cihazın anahtarını iptal eder.
6. **Dosyalar:** `GET /api/v1/dosyalar`, `GET /api/v1/dosyalar/{ad}`

Parola değişince, hesap pasife alınınca ya da "Diğer cihazlardan çıkış"ta
bütün mobil oturumlar kapanır.
