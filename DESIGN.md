---
name: Book Lending Shop
description: ร้านเช่าหนังสือออนไลน์ as a Thai book fair hall you can rent from.
colors:
  hall-blue-ink: "#12206e"
  hall-blue-ink-2: "#2c3a8c"
  banner-yellow: "#ffd21f"
  banner-yellow-ink: "#3a2c00"
  flag-red: "#d62a25"
  flag-red-dark: "#b01f1b"
  cool-hall-floor: "#f3f5fb"
  card-white: "#ffffff"
  night-ink-text: "#151b3b"
  muted-ink: "#5b6388"
  hairline: "#dde1f0"
  hairline-strong: "#c8cee6"
  blue-soft: "#e6eafb"
  ok: "#0f7a3d"
  ok-soft: "#e1f4e8"
  warn: "#9a5200"
  warn-soft: "#fff1d6"
  bad: "#b3261e"
  bad-soft: "#fde7e5"
  zone-1-fantasy-violet: "#6a3df0"
  zone-2-science-green: "#0f8a43"
  zone-3-history-orange: "#bd4f00"
  zone-4-literature-rose: "#d81e5b"
  zone-5-computing-cyan: "#0a7ea4"
  after-closing-floor: "#0a1030"
  after-closing-card: "#131b4a"
  after-closing-text: "#e8ecff"
  after-closing-muted: "#a3acd6"
  after-closing-ink: "#c9d2ff"
  after-closing-banner: "#1a2470"
typography:
  display:
    fontFamily: "Prompt, Sarabun, system-ui, sans-serif"
    fontSize: "clamp(2rem, 6.5vw, 3.7rem)"
    fontWeight: 800
    lineHeight: 1.08
    letterSpacing: "normal"
  headline:
    fontFamily: "Prompt, Sarabun, system-ui, sans-serif"
    fontSize: "clamp(1.6rem, 4vw, 2.2rem)"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Prompt, Sarabun, system-ui, sans-serif"
    fontSize: "1.4rem"
    fontWeight: 700
    lineHeight: 1.25
  body:
    fontFamily: "Sarabun, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.65
  label:
    fontFamily: "Prompt, Sarabun, system-ui, sans-serif"
    fontSize: "0.95rem"
    fontWeight: 600
    lineHeight: 1.2
rounded:
  xs: "4px"
  sm: "8px"
  field: "12px"
  md: "14px"
  pill: "999px"
spacing:
  xs: "0.3rem"
  sm: "0.6rem"
  md: "1rem"
  lg: "1.6rem"
  xl: "2.5rem"
components:
  button-primary:
    backgroundColor: "{colors.hall-blue-ink}"
    textColor: "{colors.card-white}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "0.6rem 1.2rem"
  button-primary-hover:
    backgroundColor: "{colors.hall-blue-ink-2}"
  button-accent:
    backgroundColor: "{colors.flag-red}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.pill}"
    padding: "0.6rem 1.2rem"
  button-accent-hover:
    backgroundColor: "{colors.flag-red-dark}"
  button-yellow:
    backgroundColor: "{colors.banner-yellow}"
    textColor: "{colors.banner-yellow-ink}"
    rounded: "{rounded.pill}"
    padding: "0.6rem 1.2rem"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.hall-blue-ink}"
    rounded: "{rounded.pill}"
    padding: "0.6rem 1.2rem"
  input-field:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.night-ink-text}"
    rounded: "{rounded.field}"
    padding: "0.65rem 0.85rem"
  card:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.night-ink-text}"
    rounded: "{rounded.md}"
    padding: "1.3rem"
  price-tag:
    backgroundColor: "{colors.banner-yellow}"
    textColor: "{colors.banner-yellow-ink}"
    rounded: "{rounded.xs}"
    padding: "0.38rem 0.7rem 0.38rem 1.5rem"
  badge:
    backgroundColor: "{colors.blue-soft}"
    textColor: "{colors.hall-blue-ink}"
    rounded: "{rounded.pill}"
    padding: "0.12rem 0.7rem"
  banner:
    backgroundColor: "{colors.hall-blue-ink}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.md}"
    padding: "2rem 2.6rem 2.2rem"
  zone-block:
    backgroundColor: "{colors.zone-1-fantasy-violet}"
    textColor: "{colors.card-white}"
    rounded: "10px"
    padding: "0.9rem 1rem"
---

# Design System: Book Lending Shop

## Overview

**Creative North Star: "The Thai Book Fair Hall"**

The shop is a book fair floor in full swing that happens to rent. Hall-blue ink sits on a cool white floor, a banner-yellow price flag and a flag-red call to action carry the energy, and five zone colours do the sorting that a template storefront would do with a sidebar. The covers are the shop's real colour; the interface frames them with zone-tinted backing boards, soft shadows and fair objects: a bunting strip of flags across the top edge, a booth-fascia header, a price tag with a punched hole, a starburst sticker for new and sold-out, a hall floor map as category navigation, and a ticket-stub order timeline.

Tone is lively but never childish: Thai-first copy, Prompt at 700 to 800 for banners, prices and labels, Sarabun for reading, generous Thai line height (1.65). Density is relaxed on the catalogue (Persuade) and tighter on the staff desk (Operate), but both speak the same tokens. Light is the fair by day; dark is the hall after closing, with a deep navy floor, the same five zones lifted for contrast, and yellow staying yellow.

**Key Characteristics:**
- Hall-blue ink and banner yellow carry identity; flag red marks the one action that matters.
- Five zone colours (A to E) tint category chips, cover backing boards, headings and charts.
- Fully pill-shaped controls over softly rounded (14px) cards.
- Soft blue-tinted ambient shadows; depth comes from lift on hover, not from hard edges.
- Dashed rules and dotted connectors echo tickets and floor plans.
- Light and dark themes are first-class, toggled by `data-theme` and falling back to `prefers-color-scheme`.

## Colors

A cool, blue-leaning neutral field with one saturated ink, one yellow and one red, plus five zone hues used only as category identity.

### Primary
- **Hall Blue Ink** (#12206e): Header bunting backing, banner, footer, primary buttons, current-page nav pill, selected tabs and pager. In dark mode the token flips to a pale periwinkle (#c9d2ff) and filled surfaces carry dark text (#0a1030).
- **Hall Blue Ink 2** (#2c3a8c): Links, primary-button hover. Dark: #aab6ff.

### Secondary
- **Banner Yellow** (#ffd21f): Banner headline, price tag, focus ring, selection, current step, staff-bar current item. Always paired with **Banner Yellow Ink** (#3a2c00) when it is a fill. In dark mode yellow also becomes the price and bar-chart colour.

### Tertiary
- **Flag Red** (#d62a25): The accent button (sign-up, buy), cart count, price numbers, pennant markers on row headings, the new/sale starburst. Hover is **Flag Red Dark** (#b01f1b). Dark: #ff6b63 with near-black text (#1a0605).

### Neutral
- **Cool Hall Floor** (#f3f5fb): Page background. Dark: #0a1030.
- **Card White** (#ffffff): Cards, fields, topbar. Dark: #131b4a.
- **Night Ink Text** (#151b3b): Body text. Dark: #e8ecff.
- **Muted Ink** (#5b6388): Secondary text. Dark: #a3acd6.
- **Hairline** (#dde1f0) and **Hairline Strong** (#c8cee6): Card borders and field/dashed rules. Dark: #26306d and #36418a.
- **Blue Soft** (#e6eafb): Hover wash, neutral badge, info alert. Dark: #1c2660.
- **Status**: ok #0f7a3d on #e1f4e8, warn #9a5200 on #fff1d6, bad #b3261e on #fde7e5; each lifts to a pastel on a deep tint in dark.

### Zones
- **Fantasy Violet** (#6a3df0), **Science Green** (#0f8a43), **History Orange** (#bd4f00), **Literature Rose** (#d81e5b), **Computing Cyan** (#0a7ea4). Set with `.z1` to `.z5` as `--zc` on any element; dark mode lifts them to #9b7bff, #38c777, #ff9a4d, #ff5c8f, #3ec3ee with dark text on the fill.

### Measured contrast (WCAG, computed from the token pairs)
Light: body text on floor 15.39, on card 16.78; muted on floor 5.37, on card 5.86, on blue-soft 4.89; link ink-2 on floor 9.21; white on ink 14.41; white on flag red 4.98; yellow-ink on yellow 9.41; banner yellow on ink 9.94; pale text (#dfe4ff) on ink 11.44; ok/warn/bad on their soft tints 4.73 / 5.25 / 5.52; white on zones 1, 3, 4, 5 = 5.93, 4.90, 4.93, 4.63.
Dark: text on floor 15.82, on card 13.93; muted on floor 8.35, on card 7.35; ink on card 11.02; dark text on ink fill 12.52; dark text on red 7.03; red text on card 5.87; yellow on card 11.29; banner text on #1a2470 10.90; ok/warn/bad on tints 7.30 / 8.48 / 7.11; dark text on zones 5.91 to 9.07; zone text on card 5.20 to 7.98.

### Named Rules
**The One Red Rule.** Flag red marks the single action or price on a screen (accent button, price number, cart count). Everything else clickable is ink.
**The Zone Is Identity Rule.** A zone colour appears only on things that belong to that zone (chip, backing board, heading mark, chart bar). Never use a zone hue as generic decoration or status.
**The Flip Rule.** In dark mode, filled ink/red/zone surfaces take dark text (#0a1030 or #1a0605), never white.

## Typography

**Display Font:** Prompt (with Sarabun, system-ui)
**Body Font:** Sarabun (with system-ui, -apple-system, Segoe UI)
**Label/Mono Font:** Prompt for labels; `ui-monospace, Cascadia Mono, Consolas` only for coupon and tracking codes.

**Character:** Prompt is geometric and a little loud, right for banners, prices and buttons; Sarabun is open and sharp for Thai paragraphs. Both are loaded from Google Fonts (Prompt 400 to 800, Sarabun 400/600/700).

### Hierarchy
- **Display** (800, clamp(2rem, 6.5vw, 3.7rem), 1.08): Banner headline only, in banner yellow, max 18 to 20ch.
- **Headline** (700, clamp(1.6rem, 4vw, 2.2rem), 1.25, -0.01em): Page h1.
- **Title** (700, 1.4rem h2 / 1.1rem h3, 1.25): Section and card headings; row headings carry a red pennant mark.
- **Body** (400, 1.0625rem, 1.65): Sarabun paragraphs, descriptions capped at 64ch.
- **Label** (600, 0.95rem, Prompt): Buttons, nav, field labels, tabs, badges (0.8rem), table heads (0.82rem).
- **Numerals** (Prompt 800, tabular-nums): Prices, KPIs, totals, via `.num`.

### Named Rules
**The Price Is Display Rule.** Money is always Prompt, tabular, heavier than its neighbours.

## Layout

Single column page shell, max width 1200px, 1rem side padding on phones, sticky topbar. Spacing runs on a loose rem rhythm (0.3, 0.6, 1, 1.6, 2.5rem). Home is banner, then the hall floor map (a 4-column grid with the first zone spanning two rows and columns, collapsing to a 2-column grid under 760px), then horizontally snapping rails and a `repeat(auto-fill, minmax(188px, 1fr))` catalogue grid (two columns under 520px). Book page is three columns (260px cover, flexible text, 360px sticky buy box); cart and checkout are a main column plus a 350px summary; catalogue filters are a 260px sticky sidebar. Breakpoints in use: 520, 560, 600, 720, 760, 860, 900px. Everything collapses to one column by 900px; the nav becomes a toggle at 860px. Staff screens use the same shell with a second ink strip (staff bar) and denser tables, KPI tiles and SVG charts.

## Elevation & Depth

Hybrid: tonal layering for rest, soft blue-tinted ambient shadow for cards, larger lift for hover and overlays.

### Shadow Vocabulary
- **Card** (`0 1px 2px rgba(18,32,110,.08), 0 4px 14px rgba(18,32,110,.08)`): cards, KPIs, notifications. Dark: black at .4 and .35.
- **Lift** (`0 16px 38px rgba(18,32,110,.2)`): banner, buy box, hovered book card, suggest list. Dark: `0 18px 42px rgba(0,0,0,.55)`.
- **Cover drop** (`filter: drop-shadow(0 8px 9px rgba(18,32,110,.35))`): covers sitting on their zone board.
- **Price flag** (`0 6px 12px -6px rgba(0,0,0,.5)`): the tilted price tag.

### Named Rules
**The Soft Ink Shadow Rule.** Shadows are blurred and tinted with hall blue; no hard offset shadows.
**The Focus Pair Rule.** Focus is a 3px yellow outline plus a 5px ink ring, so it reads on both yellow-adjacent and dark surfaces.

## Shapes

Pills for everything you press (buttons, nav items, tabs, pager, chips, search); 14px cards; 12px fields and alerts; 8 to 10px for logo mark, zone blocks and zone stickers; 4 to 6px for covers. Zone stickers are rounded squares carrying the zone letter. Signature silhouettes: the price tag (4px left, 12px right, tilted -3deg, punched hole), the starburst (clip-path polygon, tilted 8deg), the red pennant before row headings, dashed floor-plan border around the floor map, dashed ticket borders for steps and the pay box, dotted stepper connectors.

## Components

### Buttons
- **Shape:** Pill (999px), 2px border, Prompt 600 1rem, padding 0.6rem 1.2rem; small is 0.32rem 0.85rem.
- **Primary:** Ink fill, white text. Hover goes to ink-2.
- **Accent:** Flag red fill with a red ambient glow; hover lifts 1px and darkens.
- **Yellow:** Banner yellow with dark yellow ink; used on dark surfaces.
- **Outline / Danger:** Transparent with strong hairline border; danger uses a 40% bad border and bad-soft hover.
- **Press:** `translateY(1px) scale(.99)`. Disabled is 50% opacity.

### Inputs / Fields
- **Style:** 12px radius, 2px strong-hairline border, card fill, red caret; labels in Prompt 600.
- **Focus:** Border to ink plus 3px yellow outline.
- **Search:** Pill field with leading icon joined to an ink "search" button; the banner version is a taller white "information desk" with a type-ahead list.

### Cards / Containers
- 14px radius, 1px hairline, Card shadow, 1.3rem padding.
- **Book card:** Cover on a board tinted 14% with its zone colour, zone-letter sticker top left, heart button top right, tilted yellow price tag bottom left, optional starburst, two-line clamped title, rating, small add-to-cart. Hover lifts 4px.

### Navigation
- Topbar on card white with the ink logo mark (yellow glyph; reversed in dark), pill nav items in Prompt 500, current page as a filled ink pill, red cart count. Below 860px a bordered "menu" button reveals the nav. A staff bar in ink with a yellow label and a yellow current-item pill appears for staff and admin.

### Hall Floor Map (signature)
Five zone blocks in a dashed-border grid; each shows the zone letter large, name and book count, and acts as a category button. Hover lifts 3px with a faint tilt; the active zone gets a 4px yellow outline.

### Bunting (signature)
A 30px ink strip of repeating triangular flags in yellow, red, green and cyan along the very top edge.

### Order Timeline
Ticket-stub stepper: numbered rounded squares joined by dotted lines; done turns green with a check, now is yellow with a soft halo, with a short yellow tab on the card edge. Becomes a vertical list under 600px.

### Badges, Alerts, Tables
Pill badges in ok, warn, bad, info tints. Alerts are 12px bordered tints with a leading icon. Tables use hairline rows and a blue-soft row hover; money right-aligned and tabular.

### Icons
One inline SVG sprite (`assets/icons.svg`), 2px round-capped stroke on a 24px grid, `currentColor`: search, cart, heart, bell, user, star, truck, tag, shield, calendar, book, check, x, moon, sun, menu, pin, clock, chevron, plus, info, gift, receipt, chart, qr, flag, refresh, alert, ticket, edit, trash.

## Do's and Don'ts

### Do:
- **Do** show rent and refundable deposit together before any commitment, using the price tag and the Prompt tabular total.
- **Do** set a zone with `.z1` to `.z5` and read `--zc`; tint backing boards at 14% over the card colour.
- **Do** give filled ink, red and zone surfaces dark text in dark mode.
- **Do** keep the yellow-plus-ink focus pair on every interactive element.
- **Do** honour `prefers-reduced-motion`; the rise-in and lift effects are decoration only.
- **Do** use real covers; where none exists use the zone-coloured placeholder with the title.

### Don't:
- **Don't** return to a clean white grid of identical cards under a hero; the floor map, rails and tags are the structure.
- **Don't** use red for anything but the single main action or price on a screen.
- **Don't** use a zone hue for status or decoration; status is ok, warn, bad.
- **Don't** add photographs of a shop, testimonials or sales figures; none exist.
- **Don't** add hard offset shadows or new radii outside the shape set above.
- **Don't** make the interface childish; keep copy short and Thai-first.
