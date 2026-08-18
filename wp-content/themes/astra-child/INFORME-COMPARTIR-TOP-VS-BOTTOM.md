# Informe comparativo de compartir: Top vs Bottom y por canal

Objetivo: medir qué botonera (arriba o abajo) y qué canal (WhatsApp, Facebook, X, Email) genera más interacción en eventos.

## 1) Verificación rápida en GA4 (Tiempo real)

1. Entra en Informes > Tiempo real.
2. Filtra por nombre de evento: vqh_share_click.
3. Haz 2 clics de prueba en un evento:

- un botón de la botonera superior
- un botón de la botonera inferior

4. Comprueba que llegan parámetros:

- share_position
- share_channel
- item_id
- page_path

Si esto se ve en Tiempo real, la base de medición está correcta.

## 2) Crear definiciones personalizadas en GA4

En Administrar > Definiciones personalizadas > Crear dimensión personalizada (alcance: Evento), crea estas dimensiones:

1. share_position
2. share_channel
3. content_type
4. city_slug
5. primary_category
6. item_id
7. item_title
8. page_path

Nota: pueden tardar unas horas en poblarse en informes estándar.

## 3) Informe principal (Exploraciones en GA4)

Tipo: Exploración libre
Nombre recomendado: Comparativa compartir Top vs Bottom

Dimensiones a importar:

1. share_position
2. share_channel
3. page_path
4. city_slug
5. primary_category

Métricas a importar:

1. Recuento de eventos
2. Usuarios activos

Filtros:

1. Nombre del evento coincide exactamente con vqh_share_click
2. content_type coincide exactamente con listado

Configuración de tabla pivote:

1. Filas: share_channel
2. Columnas: share_position
3. Valores: Recuento de eventos

Resultado esperado:

- Verás por canal cuántos clics vienen de top y cuántos de bottom.

## 4) Informe secundario (ranking de canal)

Tipo: Exploración libre
Nombre recomendado: Ranking canales de compartir

Configuración:

1. Filas: share_channel
2. Valores: Recuento de eventos, Usuarios activos
3. Filtro: Nombre del evento = vqh_share_click

Resultado esperado:

- Ranking de canales con mejor rendimiento.

## 5) Informe por páginas de evento

Tipo: Exploración libre
Nombre recomendado: Rendimiento por evento (share)

Configuración:

1. Filas: page_path
2. Columnas: share_position
3. Valores: Recuento de eventos
4. Filtro: Nombre del evento = vqh_share_click

Resultado esperado:

- Qué eventos disparan más shares y en qué posición.

## 6) KPI recomendados para decidir diseño

1. Clics top: suma de vqh_share_click con share_position = top
2. Clics bottom: suma de vqh_share_click con share_position = bottom
3. Peso top: clics top / (clics top + clics bottom)
4. Peso por canal: clics del canal / clics totales

## 7) Criterio de decisión (práctico)

1. Si top aporta al menos 60% de clics totales, mantener doble botonera y priorizar visibilidad superior.
2. Si bottom supera 45%, revisar longitud de contenido y llamada final.
3. Si un canal supera 50%, considerar destacar ese canal visualmente.

## 8) Opcional: panel en Looker Studio

Fuente: propiedad GA4

Bloques mínimos:

1. Tarjetas KPI:

- Clics share totales
- Clics top
- Clics bottom

2. Tabla pivote:

- Filas: share_channel
- Columnas: share_position
- Métrica: Recuento de eventos

3. Serie temporal:

- Dimensión: Fecha
- Métrica: Recuento de eventos
- Desglose: share_position

4. Tabla por evento:

- Dimensión: page_path
- Métricas: Recuento de eventos

## 9) Frecuencia de lectura

1. Diario: validación técnica y anomalías.
2. Semanal: decisiones de UX (posición/canal).
3. Mensual: comparativa por ciudad y categoría.

## 10) Comprobaciones si no ves datos

1. Revisar en Tiempo real que exista el evento vqh_share_click.
2. Confirmar que las dimensiones personalizadas estén creadas exactamente con esos nombres.
3. Esperar propagación en informes estándar de GA4.
4. Verificar que no haya bloqueadores de analítica en el navegador de prueba.
