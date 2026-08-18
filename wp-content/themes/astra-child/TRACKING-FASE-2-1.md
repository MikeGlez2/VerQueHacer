# Tracking Fase 2.1 (nomenclatura estable)

Este documento define la convención de eventos para medir la botonera de compartir en eventos.

## Evento principal

- Nombre de evento: `vqh_share_click`
- Dónde se envía:
  - `dataLayer` (siempre)
  - `gtag` (si existe)
  - `fbq trackCustom` (si existe)

## Parámetros del evento

- `share_channel`: `whatsapp|facebook|twitter|email`
- `share_position`: `top|bottom`
- `content_type`: normalmente `listado`
- `item_id`: ID del evento
- `item_title`: título del evento
- `page_location`: URL completa de la página
- `page_path`: path de la URL
- `city_slug`: ciudad detectada en la URL
- `primary_category`: primer término de `ecategory`
- `share_target_url`: URL de destino del botón
- `timestamp`: ISO datetime

## GA4 (recomendación)

1. Crear dimensiones personalizadas de evento para:
   - `share_channel`
   - `share_position`
   - `city_slug`
   - `primary_category`
   - `content_type`

2. Verificar en Tiempo real:
   - Evento `vqh_share_click`
   - Evento estándar `share` (con `method`)

## GTM (si aplica)

- Trigger: evento personalizado `vqh_share_click`
- Variables desde dataLayer:
  - `share_channel`
  - `share_position`
  - `item_id`
  - `city_slug`
  - `primary_category`

## Meta Pixel (si aplica)

- Evento custom: `VQHShareClick`
- Parámetros:
  - `share_channel`
  - `share_position`
  - `item_id`
  - `item_title`
  - `city_slug`
  - `primary_category`
