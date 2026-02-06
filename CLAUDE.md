# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Diablo Valley Card Show (DVCSHOW)** — A single-page marketing website for a card and collectibles convention held at Concord Plaza Hotel, Concord, CA. The site uses a retro/arcade-inspired dark theme with neon accents.

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

- **Dates:** February 28 & March 1, 2026
- **Hours:** 10 AM – 4 PM
- **Venue:** Concord Plaza Hotel, 45 John Glen Dr, Concord, CA
- **Admission:** $10 (kids 10 & under free)
- **Tables:** 120+
- **Trade Night:** DVY Collectibles, 1150 Arnold Drive Suite D, Martinez, CA — Feb 28th evening
- **Instagram:** @dvcshow

## Deployment

Static files — upload `index.html` and the `Images/` folder to the VPS web root. No build or install required.
