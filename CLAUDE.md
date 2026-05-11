# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Diablo Valley Card Show (DVCSHOW)** — A single-page marketing website for a TCG, sports cards, and collectibles convention held at Concord High School, Concord, CA. The site uses a retro/arcade-inspired dark theme with neon accents.

## Tech Stack

- Pure HTML/CSS/JS — single `index.html` file, no build step
- Google Fonts: Press Start 2P, Russo One, Orbitron, Chakra Petch
- Hosted on a GoDaddy VPS (static file serving)

## Structure

- `index.html` — The entire site (markup, styles, scripts all inline)
- `Images/` — Brand assets (Logo.png, background.jpg, Flyer.png, Collab.png)

## Design System

- **Palette:** Deep navy (`#0a0e27`) base, neon blue (`#00d4ff`), neon pink (`#ff2d95`), neon yellow (`#ffe135`), gold (`#d4a843`)
- **Fonts:** Orbitron (headings), Press Start 2P (pixel/retro accents), Chakra Petch (body), Russo One (hero headline)
- **Effects:** CRT scanline overlay, perspective grid floor, floating particles, neon glow text-shadows, scroll-reveal animations

## Event Details (keep current)

- **Dates:** June 6 & 7, 2026
- **Hours:** 10 AM – 4 PM
- **Venue:** Concord High School, 4200 Concord Blvd, Concord, CA 94521
- **Admission:** FREE (all ages)
- **Tables:** 120+
- **Focus:** TCG show (Pokemon, sports cards, trading cards, collectibles)
- **Past venue:** Concord Plaza Hotel, 45 John Glen Dr (April 2026 show — now shown in Past Events section)
- **Instagram:** @diablovalleycardshow

## Deployment

Static files — upload `index.html` and the `Images/` folder to the VPS web root. No build or install required.
